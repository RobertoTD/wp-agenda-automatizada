<?php
defined('ABSPATH') or die('No direct access');

final class SetContactDossierApplicationCommand {
    /** @var int */ private $contact_container_id;
    /** @var bool */ private $active;

    public function __construct(int $contact_container_id, bool $active) {
        if ($contact_container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] Contact container id must be positive.');
        }
        $this->contact_container_id = $contact_container_id;
        $this->active = $active;
    }

    public function contact_container_id(): int { return $this->contact_container_id; }
    public function active(): bool { return $this->active; }
}
