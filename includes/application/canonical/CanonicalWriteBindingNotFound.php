<?php
/**
 * Canonical Write Binding Not Found — Binding de escritura ausente.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalWriteBindingNotFound extends \RuntimeException {

    /** @var string */
    private $qualified_key;

    public function __construct(string $qualified_key) {
        $this->qualified_key = $qualified_key;
        parent::__construct(
            sprintf('[binding_not_found] No write adapter bound for "%s".', $qualified_key)
        );
    }

    public function qualified_key(): string {
        return $this->qualified_key;
    }
}
