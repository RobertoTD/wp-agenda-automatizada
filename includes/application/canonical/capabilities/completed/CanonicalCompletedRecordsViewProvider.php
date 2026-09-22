<?php
defined('ABSPATH') or die('No direct access');
require_once __DIR__ . '/CanonicalCompletedCriterion.php';

final class CanonicalCompletedRecordsViewProvider implements CanonicalCapabilityRecordsViewProvider {
    public const KEY = 'completed';
    private $config;
    private $registry;
    public function __construct($config, AA_Canonical_Capability_Registry $registry) {
        $this->config = $config;
        $this->registry = $registry;
    }
    public function capability_key(): string { return self::KEY; }
    public function legacy_aliases(): array { return [self::KEY => self::KEY]; }
    public function contributions(string $family_key, int $container_id): ?array {
        if ($family_key !== 'action' || $container_id < 1) { return null; }
        $definition = $this->registry->get(self::KEY);
        if (!$definition->is_ready()) { return null; }
        // A failed read is not deactivation: propagate to the canonical error state.
        $row = $this->config->find_container_capability($container_id, self::KEY);
        if ($row === null || empty($row['is_active'])) { return null; }
        return [
            'natural' => new CanonicalCompletedCriterion(false),
            'views' => [self::KEY => new CanonicalCapabilityRecordsViewDefinition(
                self::KEY,
                'Completadas',
                new CanonicalCompletedCriterion(true),
                false
            )],
        ];
    }
}
