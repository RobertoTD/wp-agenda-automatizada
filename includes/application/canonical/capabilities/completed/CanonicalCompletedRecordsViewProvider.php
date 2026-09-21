<?php
defined('ABSPATH') or die('No direct access');
final class CanonicalCompletedRecordsViewProvider implements CanonicalCapabilityRecordsViewProvider {
    public const KEY = 'completed';
    private $config; private $registry;
    public function __construct($config, AA_Canonical_Capability_Registry $registry) { $this->config = $config; $this->registry = $registry; }
    public function default_filter(string $family_key, int $container_id): ?CanonicalRecordsFilter { return $this->is_active($family_key, $container_id) ? new CanonicalCompletedRecordsFilter(false) : null; }
    public function filter_for_view(string $family_key, int $container_id, string $view_key): ?CanonicalRecordsFilter { return $view_key === self::KEY && $this->is_active($family_key, $container_id) ? new CanonicalCompletedRecordsFilter(true) : null; }
    public function owns_view_key(string $view_key): bool { return $view_key === self::KEY; }
    public function available_view(string $family_key, int $container_id, string $view_key): ?array { return $view_key === self::KEY && $this->is_active($family_key, $container_id) ? ['key' => self::KEY, 'label' => 'Completadas'] : null; }
    public function available_views(string $family_key, int $container_id): array { $view = $this->available_view($family_key, $container_id, self::KEY); return $view === null ? [] : [$view]; }
    private function is_active(string $family_key, int $container_id): bool {
        if ($family_key !== 'action' || $container_id < 1) { return false; }
        try { $definition = $this->registry->get(self::KEY); $row = $this->config->find_container_capability($container_id, self::KEY); return $definition->is_ready() && $row !== null && !empty($row['is_active']); } catch (\Throwable $e) { return false; }
    }
}
