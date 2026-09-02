<?php
/**
 * Canonical Shell Records Read Result — Resultado tipado de lectura de registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once __DIR__ . '/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalRecordsPage')) {
    require_once __DIR__ . '/CanonicalRecordsPage.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-container.php';
}

final class CanonicalShellRecordsReadResult {

    public const STATE_RESOLVED_PAGE = 'resolved_page';
    public const STATE_EMPTY = 'empty';
    public const STATE_CONTAINER_NOT_FOUND = 'container_not_found';
    public const STATE_READ_ADAPTER_PENDING = 'read_adapter_pending';
    public const STATE_CONTRACT_ERROR = 'contract_error';

    private const ALLOWED_STATES = [
        self::STATE_RESOLVED_PAGE,
        self::STATE_EMPTY,
        self::STATE_CONTAINER_NOT_FOUND,
        self::STATE_READ_ADAPTER_PENDING,
        self::STATE_CONTRACT_ERROR,
    ];

    /** @var CanonicalShellManifest */
    private $manifest;

    /** @var string */
    private $state;

    /** @var AA_Canonical_Container|null */
    private $container;

    /** @var CanonicalRecordsPage|null */
    private $page;

    private function __construct(
        CanonicalShellManifest $manifest,
        string $state,
        ?AA_Canonical_Container $container,
        ?CanonicalRecordsPage $page
    ) {
        if (!in_array($state, self::ALLOWED_STATES, true)) {
            throw new \InvalidArgumentException('[invalid_read_state] Unsupported shell records read state.');
        }

        if (
            ($state === self::STATE_RESOLVED_PAGE || $state === self::STATE_EMPTY)
            && (!$container instanceof AA_Canonical_Container || !$page instanceof CanonicalRecordsPage)
        ) {
            throw new \InvalidArgumentException('[invalid_read_result] Container and page required for this state.');
        }

        if (
            (
                $state === self::STATE_CONTAINER_NOT_FOUND
                || $state === self::STATE_READ_ADAPTER_PENDING
                || $state === self::STATE_CONTRACT_ERROR
            )
            && ($container !== null || $page !== null)
        ) {
            throw new \InvalidArgumentException('[invalid_read_result] Container and page must be null for this state.');
        }

        $this->manifest = $manifest;
        $this->state = $state;
        $this->container = $container;
        $this->page = $page;
    }

    public static function resolved_page(
        CanonicalShellManifest $manifest,
        AA_Canonical_Container $container,
        CanonicalRecordsPage $page
    ): self {
        return new self($manifest, self::STATE_RESOLVED_PAGE, $container, $page);
    }

    public static function empty_page(
        CanonicalShellManifest $manifest,
        AA_Canonical_Container $container,
        CanonicalRecordsPage $page
    ): self {
        return new self($manifest, self::STATE_EMPTY, $container, $page);
    }

    public static function container_not_found(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_CONTAINER_NOT_FOUND, null, null);
    }

    public static function read_adapter_pending(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_READ_ADAPTER_PENDING, null, null);
    }

    public static function contract_error(CanonicalShellManifest $manifest): self {
        return new self($manifest, self::STATE_CONTRACT_ERROR, null, null);
    }

    public function manifest(): CanonicalShellManifest {
        return $this->manifest;
    }

    public function state(): string {
        return $this->state;
    }

    public function container(): ?AA_Canonical_Container {
        return $this->container;
    }

    public function page(): ?CanonicalRecordsPage {
        return $this->page;
    }
}
