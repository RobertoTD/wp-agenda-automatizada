<?php
/**
 * Resultado tipado de abrir o crear el expediente canónico de un contacto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Dossier
 */

defined('ABSPATH') or die('No direct access');

final class OpenOrCreateCanonicalContactDossierResult {

    public const STATE_OPENED = 'opened';
    public const STATE_CREATED = 'created';
    public const STATE_CONTAINER_NOT_FOUND = 'container_not_found';
    public const STATE_RECORD_NOT_FOUND = 'record_not_found';
    public const STATE_CAPABILITY_INACTIVE = 'capability_inactive';
    public const STATE_ORIGIN_RETIRING = 'origin_retiring';
    public const STATE_DOSSIER_RETIRING = 'dossier_retiring';
    public const STATE_TARGET_INVALID = 'dossier_target_invalid';
    public const STATE_ARCHIVE_DISABLED = 'archive_disabled';
    public const STATE_RESOURCE_BUSY = 'resource_busy';
    public const STATE_UNCERTAIN = 'uncertain';
    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';
    public const STATE_FORBIDDEN = 'forbidden';

    /** @var string */
    private $state;

    /** @var string|null */
    private $redirect_url;

    /** @var int|null */
    private $archive_container_id;

    private function __construct(string $state, ?string $redirect_url = null, ?int $archive_container_id = null) {
        $this->state = $state;
        $this->redirect_url = $redirect_url;
        $this->archive_container_id = $archive_container_id;
    }

    public static function opened(string $redirect_url, int $archive_container_id): self {
        return new self(self::STATE_OPENED, $redirect_url, $archive_container_id);
    }

    public static function created(string $redirect_url, int $archive_container_id): self {
        return new self(self::STATE_CREATED, $redirect_url, $archive_container_id);
    }

    public static function container_not_found(): self {
        return new self(self::STATE_CONTAINER_NOT_FOUND);
    }

    public static function record_not_found(): self {
        return new self(self::STATE_RECORD_NOT_FOUND);
    }

    public static function capability_inactive(): self {
        return new self(self::STATE_CAPABILITY_INACTIVE);
    }

    public static function origin_retiring(): self {
        return new self(self::STATE_ORIGIN_RETIRING);
    }

    public static function dossier_retiring(): self {
        return new self(self::STATE_DOSSIER_RETIRING);
    }

    public static function target_invalid(): self {
        return new self(self::STATE_TARGET_INVALID);
    }

    public static function archive_disabled(): self {
        return new self(self::STATE_ARCHIVE_DISABLED);
    }

    public static function resource_busy(): self {
        return new self(self::STATE_RESOURCE_BUSY);
    }

    public static function uncertain(): self {
        return new self(self::STATE_UNCERTAIN);
    }

    public static function persistence_failed(): self {
        return new self(self::STATE_PERSISTENCE_FAILED);
    }

    public static function forbidden(): self {
        return new self(self::STATE_FORBIDDEN);
    }

    public function state(): string {
        return $this->state;
    }

    public function redirect_url(): ?string {
        return $this->redirect_url;
    }

    public function archive_container_id(): ?int {
        return $this->archive_container_id;
    }
}
