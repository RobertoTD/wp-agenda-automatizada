<?php
/**
 * Canonical Read Persistence Failed — Fallo de persistencia en lectura canónica.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalReadPersistenceFailed extends \RuntimeException {

    public const REASON_FAMILY_NOT_PROVISIONED = 'family_not_provisioned';
    public const REASON_SQL = 'sql';

    /** @var string */
    private $reason;

    public function __construct(string $reason, string $detail = '') {
        $this->reason = $reason;
        $suffix = $detail !== '' ? ': ' . $detail : '';
        parent::__construct('[read_persistence_failed] ' . $reason . $suffix);
    }

    public function reason(): string {
        return $this->reason;
    }
}
