<?php
/**
 * Canonical Capability Definition — Definición inmutable de una capacidad canónica.
 *
 * Dominio puro: sin WordPress ni persistencia.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Definition {

    public const SCOPE_RECORD = 'record';
    public const SCOPE_CONTAINER = 'container';
    public const SCOPE_BOTH = 'both';

    /** @var string */
    private $key;

    /** @var string */
    private $scope;

    /** @var bool */
    private $is_ready;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $key, string $scope, bool $is_ready) {
        $this->key = AA_Canonical_Key::assert_valid($key, 'capability_key');

        if ($scope !== self::SCOPE_RECORD
            && $scope !== self::SCOPE_CONTAINER
            && $scope !== self::SCOPE_BOTH
        ) {
            throw new \InvalidArgumentException('[invalid_capability_scope] Unsupported capability scope.');
        }

        $this->scope = $scope;
        $this->is_ready = $is_ready;
    }

    public function key(): string {
        return $this->key;
    }

    public function scope(): string {
        return $this->scope;
    }

    public function is_ready(): bool {
        return $this->is_ready;
    }
}
