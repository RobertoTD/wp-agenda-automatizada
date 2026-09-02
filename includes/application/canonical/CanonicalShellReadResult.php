<?php
/**
 * Canonical Shell Read Result — Resultado tipado de lectura de contenedores del shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalPage')) {
    require_once __DIR__ . '/CanonicalPage.php';
}

final class CanonicalShellReadResult {

    public const STATE_RESOLVED_PAGE = 'resolved_page';
    public const STATE_EMPTY = 'empty';
    public const STATE_READ_ADAPTER_PENDING = 'read_adapter_pending';
    public const STATE_CONTRACT_ERROR = 'contract_error';

    private const ALLOWED_STATES = [
        self::STATE_RESOLVED_PAGE,
        self::STATE_EMPTY,
        self::STATE_READ_ADAPTER_PENDING,
        self::STATE_CONTRACT_ERROR,
    ];

    /** @var CanonicalShellManifest */
    private $manifest;

    /** @var string */
    private $state;

    /** @var CanonicalPage|null */
    private $page;

    private function __construct(
        CanonicalShellManifest $manifest,
        string $state,
        ?CanonicalPage $page
    ) {
        if (!in_array($state, self::ALLOWED_STATES, true)) {
            throw new \InvalidArgumentException('[invalid_read_state] Unsupported shell read state.');
        }
        if (
            ($state === self::STATE_RESOLVED_PAGE || $state === self::STATE_EMPTY)
            && !$page instanceof CanonicalPage
        ) {
            throw new \InvalidArgumentException('[invalid_read_result] Page required for this state.');
        }
        if (
            ($state === self::STATE_READ_ADAPTER_PENDING || $state === self::STATE_CONTRACT_ERROR)
            && $page !== null
        ) {
            throw new \InvalidArgumentException('[invalid_read_result] Page must be null for this state.');
        }

        $this->manifest = $manifest;
        $this->state = $state;
        $this->page = $page;
    }

    public static function resolved_page(
        CanonicalShellManifest $manifest,
        CanonicalPage $page
    ): self {
        return new self($manifest, self::STATE_RESOLVED_PAGE, $page);
    }

    public static function empty_page(
        CanonicalShellManifest $manifest,
        CanonicalPage $page
    ): self {
        return new self($manifest, self::STATE_EMPTY, $page);
    }

    public static function read_adapter_pending(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_READ_ADAPTER_PENDING, null);
    }

    public static function contract_error(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_CONTRACT_ERROR, null);
    }

    public function manifest(): CanonicalShellManifest {
        return $this->manifest;
    }

    public function state(): string {
        return $this->state;
    }

    public function page(): ?CanonicalPage {
        return $this->page;
    }
}
