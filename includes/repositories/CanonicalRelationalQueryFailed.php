<?php
/**
 * Canonical Relational Query Failed — Fallo SQL confirmado del repositorio universal.
 *
 * Si había transacción abierta, el rollback se completó correctamente.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Repositories
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRelationalQueryFailed extends \RuntimeException {

    public const CODE_SQL = 'sql';
    public const CODE_CONTAINER_NOT_FOUND = 'container_not_found';
    public const CODE_RECORD_NOT_FOUND = 'record_not_found';
    public const CODE_FAMILY_NOT_PROVISIONED = 'family_not_provisioned';

    /** @var string */
    private $code_key;

    public function __construct(string $message, string $code_key = self::CODE_SQL) {
        $this->code_key = $code_key;
        parent::__construct('[canonical_relational_query_failed] ' . $code_key . ': ' . $message);
    }

    public function code_key(): string {
        return $this->code_key;
    }
}
