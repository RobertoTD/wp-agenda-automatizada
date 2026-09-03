<?php
/**
 * Canonical Mutation Persistence Failed — Fallo confirmado de persistencia en mutación.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalMutationPersistenceFailed extends \RuntimeException {

    public function __construct(string $message = 'Canonical mutation persistence failed.') {
        parent::__construct('[mutation_persistence_failed] ' . $message);
    }
}
