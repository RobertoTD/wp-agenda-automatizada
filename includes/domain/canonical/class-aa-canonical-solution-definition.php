<?php
/**
 * Canonical Solution Definition — contrato declarativo e inmutable de una solution.
 *
 * Dominio puro: sin WordPress, persistencia, callbacks ni SQL.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Solution_Definition {

    public const APPLICATION_SCOPE_CONTAINER = 'container';

    public const ACTIVATION_EXPLICIT = 'explicit';
    public const DEACTIVATION_PRESERVE_RESOURCES = 'preserve_resources';
    public const LIFECYCLE_LAZY_CREATE_PRESERVE = 'lazy_create_preserve_on_deactivate';

    /** @var string */
    private $key;

    /** @var int */
    private $version;

    /** @var string */
    private $label;

    /** @var bool */
    private $is_ready;

    /** @var string */
    private $application_scope;

    /** @var list<string> */
    private $applicable_family_keys;

    /** @var list<string> */
    private $required_enabled_family_keys;

    /** @var list<string> */
    private $capability_keys;

    /** @var string */
    private $activation_policy;

    /** @var string */
    private $deactivation_policy;

    /** @var string */
    private $lifecycle_policy;

    /**
     * @param list<string> $applicable_family_keys
     * @param list<string> $required_enabled_family_keys
     * @param list<string> $capability_keys
     */
    public function __construct(
        string $key,
        int $version,
        string $label,
        bool $is_ready,
        string $application_scope,
        array $applicable_family_keys,
        array $required_enabled_family_keys,
        array $capability_keys,
        string $activation_policy,
        string $deactivation_policy,
        string $lifecycle_policy
    ) {
        $this->key = AA_Canonical_Key::assert_valid($key, 'solution_key');
        if ($version < 1) {
            throw new \InvalidArgumentException('[invalid_solution_version] Solution version must be positive.');
        }
        $this->version = $version;

        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('[invalid_solution_label] Solution label cannot be empty.');
        }
        $this->label = $label;
        $this->is_ready = $is_ready;

        if ($application_scope !== self::APPLICATION_SCOPE_CONTAINER) {
            throw new \InvalidArgumentException('[invalid_solution_application_scope] Unsupported application scope.');
        }
        $this->application_scope = $application_scope;
        $this->applicable_family_keys = self::normalize_keys(
            $applicable_family_keys,
            'applicable_family_key',
            false
        );
        $this->required_enabled_family_keys = self::normalize_keys(
            $required_enabled_family_keys,
            'required_family_key',
            true
        );
        $this->capability_keys = self::normalize_keys($capability_keys, 'capability_key', true);
        $this->activation_policy = self::require_policy($activation_policy, 'activation_policy');
        $this->deactivation_policy = self::require_policy($deactivation_policy, 'deactivation_policy');
        $this->lifecycle_policy = self::require_policy($lifecycle_policy, 'lifecycle_policy');
    }

    public function key(): string { return $this->key; }
    public function version(): int { return $this->version; }
    public function label(): string { return $this->label; }
    public function is_ready(): bool { return $this->is_ready; }
    public function application_scope(): string { return $this->application_scope; }

    /** @return list<string> */
    public function applicable_family_keys(): array { return $this->applicable_family_keys; }

    /** @return list<string> */
    public function required_enabled_family_keys(): array { return $this->required_enabled_family_keys; }

    /** @return list<string> */
    public function capability_keys(): array { return $this->capability_keys; }

    public function activation_policy(): string { return $this->activation_policy; }
    public function deactivation_policy(): string { return $this->deactivation_policy; }
    public function lifecycle_policy(): string { return $this->lifecycle_policy; }

    public function applies_to_family(string $family_key): bool {
        return in_array($family_key, $this->applicable_family_keys, true);
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    private static function normalize_keys(array $keys, string $field, bool $allow_empty): array {
        if (!$allow_empty && $keys === []) {
            throw new \InvalidArgumentException('[invalid_solution_context] Applicable families cannot be empty.');
        }

        $out = [];
        $seen = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('[invalid_solution_key_list] Solution key lists must contain strings.');
            }
            $normalized = AA_Canonical_Key::assert_valid($key, $field);
            if (isset($seen[$normalized])) {
                throw new \InvalidArgumentException('[duplicate_solution_key_list_item] Duplicate key in solution definition.');
            }
            $seen[$normalized] = true;
            $out[] = $normalized;
        }

        return $out;
    }

    private static function require_policy(string $policy, string $field): string {
        $policy = trim($policy);
        if ($policy === '') {
            throw new \InvalidArgumentException('[invalid_solution_policy] ' . $field . ' cannot be empty.');
        }

        return AA_Canonical_Key::assert_valid($policy, $field);
    }
}
