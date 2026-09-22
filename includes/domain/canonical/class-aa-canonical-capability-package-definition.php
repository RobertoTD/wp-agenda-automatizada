<?php
/**
 * Capability Package v0 — manifiesto declarativo e inmutable.
 *
 * No contiene callbacks, SQL, HTML, paths ejecutables ni factories. Describe el
 * contrato de producto de una capability registrada en código.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Package_Definition {

    public const PERSISTENCE_MANAGED_SCHEMA = 'managed_schema';
    public const DEACTIVATION_PRESERVE = 'preserve';
    public const UNINSTALL_PRESERVE = 'preserve';
    public const PURGE_EXPLICIT = 'explicit';

    public const CONTRACT_RECORD_READ = 'record_read';
    public const CONTRACT_RECORD_WRITE = 'record_write';
    public const WRITE_PERMISSION_CANONICAL_RECORD = 'canonical_record_write';
    public const WRITE_PERMISSION_NONE = 'none';

    public const PRESENTATION_CARD_ACTION = 'card_action';
    public const PRESENTATION_CLIENT_MODULE = 'client_module';
    public const PRESENTATION_CARD_METADATA = 'card_metadata';
    public const PRESENTATION_RECORD_VIEWS = 'record_views';

    /** @var AA_Canonical_Capability_Definition */
    private $capability;
    /** @var int */
    private $version;
    /** @var string */
    private $label;
    /** @var array<string,bool> */
    private $family_defaults;
    /** @var string */
    private $persistence_resource_key;
    /** @var string */
    private $persistence_schema_policy;
    /** @var string */
    private $deactivation_policy;
    /** @var string */
    private $uninstall_policy;
    /** @var string */
    private $purge_policy;
    /** @var list<string> */
    private $read_contracts;
    /** @var list<string> */
    private $write_contracts;
    /** @var string */
    private $write_permission_policy;
    /** @var bool */
    private $has_natural_criterion;
    /** @var list<string> */
    private $record_view_keys;
    /** @var list<string> */
    private $presentation_contributions;
    /** @var list<string> */
    private $required_capability_keys;
    /** @var list<string> */
    private $incompatible_capability_keys;

    /**
     * @param array<string,bool> $family_defaults
     * @param list<string> $read_contracts
     * @param list<string> $write_contracts
     * @param list<string> $record_view_keys
     * @param list<string> $presentation_contributions
     * @param list<string> $required_capability_keys
     * @param list<string> $incompatible_capability_keys
     */
    public function __construct(
        AA_Canonical_Capability_Definition $capability,
        int $version,
        string $label,
        array $family_defaults,
        string $persistence_resource_key,
        string $persistence_schema_policy,
        string $deactivation_policy,
        string $uninstall_policy,
        string $purge_policy,
        array $read_contracts,
        array $write_contracts,
        string $write_permission_policy,
        bool $has_natural_criterion,
        array $record_view_keys,
        array $presentation_contributions,
        array $required_capability_keys = [],
        array $incompatible_capability_keys = []
    ) {
        if ($version < 1) {
            throw new \InvalidArgumentException('[invalid_capability_package_version] Package version must be positive.');
        }
        $label = trim($label);
        if ($label === '' || strlen($label) > 100) {
            throw new \InvalidArgumentException('[invalid_capability_package_label] Package label must be between 1 and 100 characters.');
        }

        $this->capability = $capability;
        $this->version = $version;
        $this->label = $label;
        $this->family_defaults = self::normalize_family_defaults($family_defaults);
        $this->persistence_resource_key = AA_Canonical_Key::assert_valid($persistence_resource_key, 'persistence_resource_key');
        if ($persistence_schema_policy !== self::PERSISTENCE_MANAGED_SCHEMA
            || $deactivation_policy !== self::DEACTIVATION_PRESERVE
            || $uninstall_policy !== self::UNINSTALL_PRESERVE
            || $purge_policy !== self::PURGE_EXPLICIT
        ) {
            throw new \InvalidArgumentException('[invalid_capability_package_lifecycle] Unsupported package lifecycle declaration.');
        }
        $this->persistence_schema_policy = $persistence_schema_policy;
        $this->deactivation_policy = $deactivation_policy;
        $this->uninstall_policy = $uninstall_policy;
        $this->purge_policy = $purge_policy;
        $this->read_contracts = self::normalize_enum_list($read_contracts, [self::CONTRACT_RECORD_READ], 'read_contract');
        $this->write_contracts = self::normalize_enum_list($write_contracts, [self::CONTRACT_RECORD_WRITE], 'write_contract');
        if ($write_permission_policy !== self::WRITE_PERMISSION_CANONICAL_RECORD
            && $write_permission_policy !== self::WRITE_PERMISSION_NONE
        ) {
            throw new \InvalidArgumentException('[invalid_capability_package_write_permission] Unsupported package write permission policy.');
        }
        if (($this->write_contracts === []) !== ($write_permission_policy === self::WRITE_PERMISSION_NONE)) {
            throw new \InvalidArgumentException('[invalid_capability_package_write_permission] Write contracts and permission policy must agree.');
        }
        $this->write_permission_policy = $write_permission_policy;
        $this->has_natural_criterion = $has_natural_criterion;
        $this->record_view_keys = self::normalize_keys($record_view_keys, 'record_view_key', true);
        $this->presentation_contributions = self::normalize_enum_list(
            $presentation_contributions,
            [self::PRESENTATION_CARD_ACTION, self::PRESENTATION_CLIENT_MODULE, self::PRESENTATION_CARD_METADATA, self::PRESENTATION_RECORD_VIEWS],
            'presentation_contribution'
        );
        $this->required_capability_keys = self::normalize_keys($required_capability_keys, 'required_capability_key', true);
        $this->incompatible_capability_keys = self::normalize_keys($incompatible_capability_keys, 'incompatible_capability_key', true);
        $this->validate_combinations();

        if ($this->record_view_keys !== [] && !in_array(self::PRESENTATION_RECORD_VIEWS, $this->presentation_contributions, true)) {
            throw new \InvalidArgumentException('[invalid_capability_package_views] Record views require the record_views contribution.');
        }
    }

    public function capability(): AA_Canonical_Capability_Definition { return $this->capability; }
    public function key(): string { return $this->capability->key(); }
    public function version(): int { return $this->version; }
    public function label(): string { return $this->label; }
    /** @return array<string,bool> */
    public function family_defaults(): array { return $this->family_defaults; }
    public function persistence_resource_key(): string { return $this->persistence_resource_key; }
    public function persistence_schema_policy(): string { return $this->persistence_schema_policy; }
    public function deactivation_policy(): string { return $this->deactivation_policy; }
    public function uninstall_policy(): string { return $this->uninstall_policy; }
    public function purge_policy(): string { return $this->purge_policy; }
    /** @return list<string> */
    public function read_contracts(): array { return $this->read_contracts; }
    /** @return list<string> */
    public function write_contracts(): array { return $this->write_contracts; }
    public function write_permission_policy(): string { return $this->write_permission_policy; }
    public function has_natural_criterion(): bool { return $this->has_natural_criterion; }
    /** @return list<string> */
    public function record_view_keys(): array { return $this->record_view_keys; }
    /** @return list<string> */
    public function presentation_contributions(): array { return $this->presentation_contributions; }
    /** @return list<string> */
    public function required_capability_keys(): array { return $this->required_capability_keys; }
    /** @return list<string> */
    public function incompatible_capability_keys(): array { return $this->incompatible_capability_keys; }

    /** @param array<string,bool> $family_defaults @return array<string,bool> */
    private static function normalize_family_defaults(array $family_defaults): array {
        if ($family_defaults === []) {
            throw new \InvalidArgumentException('[invalid_capability_package_families] At least one compatible family is required.');
        }
        $out = [];
        foreach ($family_defaults as $family_key => $is_default) {
            if (!is_string($family_key) || !is_bool($is_default)) {
                throw new \InvalidArgumentException('[invalid_capability_package_family_default] Family defaults must map canonical keys to booleans.');
            }
            $out[AA_Canonical_Key::assert_valid($family_key, 'compatible_family_key')] = $is_default;
        }
        ksort($out);
        return $out;
    }

    /** @param list<string> $values @param list<string> $allowed @return list<string> */
    private static function normalize_enum_list(array $values, array $allowed, string $field): array {
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || !in_array($value, $allowed, true) || isset($out[$value])) {
                throw new \InvalidArgumentException('[invalid_capability_package_' . $field . '] Invalid or duplicate package declaration.');
            }
            $out[$value] = $value;
        }
        return array_values($out);
    }

    /** @param list<string> $keys @return list<string> */
    private static function normalize_keys(array $keys, string $field, bool $allow_empty): array {
        if (!$allow_empty && $keys === []) {
            throw new \InvalidArgumentException('[invalid_capability_package_' . $field . '] Package declaration cannot be empty.');
        }
        $out = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('[invalid_capability_package_' . $field . '] Package keys must be strings.');
            }
            $key = AA_Canonical_Key::assert_valid($key, $field);
            if (isset($out[$key])) {
                throw new \InvalidArgumentException('[duplicate_capability_package_' . $field . '] Duplicate package key.');
            }
            $out[$key] = $key;
        }
        return array_values($out);
    }

    private function validate_combinations(): void {
        $key = $this->key();
        if (in_array($key, $this->required_capability_keys, true) || in_array($key, $this->incompatible_capability_keys, true)) {
            throw new \InvalidArgumentException('[invalid_capability_package_combination] A package cannot require or conflict with itself.');
        }
        if (array_intersect($this->required_capability_keys, $this->incompatible_capability_keys) !== []) {
            throw new \InvalidArgumentException('[invalid_capability_package_combination] A package cannot both require and conflict with another capability.');
        }
    }
}
