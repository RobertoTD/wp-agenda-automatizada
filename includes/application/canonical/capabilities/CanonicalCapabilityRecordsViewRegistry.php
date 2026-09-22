<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/CanonicalCapabilityRecordsViewProvider.php';
require_once dirname(__DIR__) . '/CanonicalRecordsQuerySpec.php';

final class CanonicalCapabilityRecordsViewRegistry {
    private $providers = [];
    private $aliases = [];
    private $frozen = false;

    public function register(CanonicalCapabilityRecordsViewProvider $provider): self {
        $owner = $provider->capability_key();
        if ($this->frozen || isset($this->providers[$owner]) || !AA_Canonical_Key::is_valid($owner)) {
            throw new LogicException('Duplicate, invalid or frozen view provider.');
        }
        foreach ($provider->legacy_aliases() as $alias => $view) {
            if (!AA_Canonical_Key::is_valid($alias) || !AA_Canonical_Key::is_valid($view) || $alias === 'simple' || isset($this->aliases[$alias])) {
                throw new LogicException('Invalid or duplicate view alias.');
            }
        }
        foreach ($provider->legacy_aliases() as $alias => $view) { $this->aliases[$alias] = [$owner, $view]; }
        $this->providers[$owner] = $provider;
        ksort($this->providers);
        return $this;
    }

    public function freeze(): self { $this->frozen = true; return $this; }

    /** Syntax only; registry and activation are resolved separately. */
    public static function validate_selections($selections): array {
        if (!is_array($selections) || count($selections) > 32) { throw new InvalidArgumentException('Invalid capability_views.'); }
        foreach ($selections as $owner => $view) {
            if (!is_string($owner) || !AA_Canonical_Key::is_valid($owner) || !is_string($view) || !AA_Canonical_Key::is_valid($view)) {
                throw new InvalidArgumentException('Invalid capability view selection.');
            }
        }
        ksort($selections);
        return $selections;
    }

    /**
     * Resolve once per request. A provider read failure must propagate.
     * @return array{spec:CanonicalRecordsQuerySpec,selections:array,available:array,current:?array,redirect:bool,criteria_changed:bool}
     */
    public function resolve_query(string $family_key, int $container_id, array $selections = [], ?string $legacy_view = null): array {
        $selections = self::validate_selections($selections);
        $redirect = false;
        if ($legacy_view !== null && $legacy_view !== 'simple') {
            if (!isset($this->aliases[$legacy_view])) { throw new InvalidArgumentException('Unknown records view.'); }
            [$owner, $view] = $this->aliases[$legacy_view];
            if (isset($selections[$owner]) && $selections[$owner] !== $view) { throw new InvalidArgumentException('Conflicting view selections.'); }
            $selections[$owner] = $view;
            $redirect = true;
        }
        $requested = $selections;
        $criteria = [];
        $available = [];
        $current = [];
        foreach ($this->providers as $owner => $provider) {
            $contributions = $provider->contributions($family_key, $container_id);
            if ($contributions === null) { unset($selections[$owner]); continue; }
            if (!array_key_exists('natural', $contributions) || !is_array($contributions['views'] ?? null)) {
                throw new LogicException('Invalid view contributions.');
            }
            $criterion = $contributions['natural'];
            foreach ($contributions['views'] as $key => $definition) {
                if (!is_string($key) || !AA_Canonical_Key::is_valid($key) || !is_string($definition['label'] ?? null) || !array_key_exists('criterion', $definition)) {
                    throw new LogicException('Invalid view definition.');
                }
                if ($definition['label'] === '' || ($definition['criterion'] !== null && !$definition['criterion'] instanceof CanonicalRecordCriterion)) {
                    throw new LogicException('Invalid view criterion or label.');
                }
                $available[] = ['owner' => $owner, 'key' => $key, 'label' => $definition['label']];
            }
            if (isset($selections[$owner])) {
                $key = $selections[$owner];
                if (!isset($contributions['views'][$key])) { throw new InvalidArgumentException('Unknown active capability view.'); }
                $definition = $contributions['views'][$key];
                $criterion = $definition['criterion'];
                $current[] = $definition['label'];
            }
            if ($criterion !== null) { $criteria[$owner] = $criterion; }
        }
        $selections = array_intersect_key($selections, $this->providers);
        ksort($selections);
        ksort($requested);
        $changed = $requested !== $selections;
        return [
            'spec' => new CanonicalRecordsQuerySpec($container_id, $criteria),
            'selections' => $selections,
            'available' => $available,
            'current' => $current === [] ? null : ['label' => implode(' · ', $current)],
            'redirect' => $redirect || $changed,
            'criteria_changed' => $changed,
        ];
    }
}
