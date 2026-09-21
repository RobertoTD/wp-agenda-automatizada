<?php
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordsViewRegistry {
    /** @var list<CanonicalCapabilityRecordsViewProvider> */
    private $providers = [];
    public function register(CanonicalCapabilityRecordsViewProvider $provider): void { $this->providers[] = $provider; }
    public function default_filter(string $family_key, int $container_id): ?CanonicalRecordsFilter {
        foreach ($this->providers as $provider) { $filter = $provider->default_filter($family_key, $container_id); if ($filter !== null) { return $filter; } }
        return null;
    }
    /** @return array{recognized:bool,filter:?CanonicalRecordsFilter,view:?array{key:string,label:string}} */
    public function resolve(string $family_key, int $container_id, string $view_key): array {
        foreach ($this->providers as $provider) {
            if (!$provider->owns_view_key($view_key)) { continue; }
            $view = $provider->available_view($family_key, $container_id, $view_key);
            return ['recognized' => true, 'filter' => $view === null ? null : $provider->filter_for_view($family_key, $container_id, $view_key), 'view' => $view];
        }
        return ['recognized' => false, 'filter' => null, 'view' => null];
    }
    /** @return list<array{key:string,label:string}> */
    public function available_views(string $family_key, int $container_id): array {
        $views = []; foreach ($this->providers as $provider) { foreach ($provider->available_views($family_key, $container_id) as $view) { $views[] = $view; } }
        return $views;
    }
}
