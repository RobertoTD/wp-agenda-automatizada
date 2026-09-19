<?php
/**
 * Comando tipado: abrir o crear el expediente Archivo de un contacto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Dossier
 */

defined('ABSPATH') or die('No direct access');

final class OpenOrCreateCanonicalContactDossierCommand {

    /** @var int */
    private $contact_container_id;

    /** @var int */
    private $contact_record_id;

    /** @var string|null */
    private $lists_scope;

    /** @var int|null */
    private $page;

    /** @var int|null */
    private $containers_page;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        int $contact_container_id,
        int $contact_record_id,
        ?string $lists_scope = null,
        ?int $page = null,
        ?int $containers_page = null
    ) {
        if ($contact_container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] Contact container id is invalid.');
        }
        if ($contact_record_id < 1) {
            throw new \InvalidArgumentException('[invalid_record_id] Contact record id is invalid.');
        }
        if ($lists_scope !== null && $lists_scope !== AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL) {
            throw new \InvalidArgumentException('[invalid_lists_scope] Unsupported lists_scope.');
        }
        if ($page !== null && $page < 1) {
            throw new \InvalidArgumentException('[invalid_page] Page must be positive.');
        }
        if ($containers_page !== null && $containers_page < 1) {
            throw new \InvalidArgumentException('[invalid_containers_page] Containers page must be positive.');
        }

        $this->contact_container_id = $contact_container_id;
        $this->contact_record_id = $contact_record_id;
        $this->lists_scope = $lists_scope;
        $this->page = $page;
        $this->containers_page = $containers_page;
    }

    public function contact_container_id(): int {
        return $this->contact_container_id;
    }

    public function contact_record_id(): int {
        return $this->contact_record_id;
    }

    public function lists_scope(): ?string {
        return $this->lists_scope;
    }

    public function page(): ?int {
        return $this->page;
    }

    public function containers_page(): ?int {
        return $this->containers_page;
    }
}
