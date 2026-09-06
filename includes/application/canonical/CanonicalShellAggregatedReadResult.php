<?php
/**
 * Canonical Shell Aggregated Read Result — Resultado tipado del listado «Todas las listas».
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalAggregatedContainersPage')) {
    require_once __DIR__ . '/CanonicalAggregatedContainersPage.php';
}

final class CanonicalShellAggregatedReadResult {

    public const STATE_RESOLVED_PAGE = 'resolved_page';
    public const STATE_EMPTY = 'empty';
    public const STATE_CONTRACT_ERROR = 'contract_error';

    private const ALLOWED_STATES = [
        self::STATE_RESOLVED_PAGE,
        self::STATE_EMPTY,
        self::STATE_CONTRACT_ERROR,
    ];

    /** @var string */
    private $state;

    /** @var CanonicalAggregatedContainersPage|null */
    private $page;

    private function __construct(string $state, ?CanonicalAggregatedContainersPage $page) {
        if (!in_array($state, self::ALLOWED_STATES, true)) {
            throw new \InvalidArgumentException('[invalid_read_state] Unsupported aggregated read state.');
        }
        if (
            ($state === self::STATE_RESOLVED_PAGE || $state === self::STATE_EMPTY)
            && !$page instanceof CanonicalAggregatedContainersPage
        ) {
            throw new \InvalidArgumentException('[invalid_read_result] Page required for this state.');
        }
        if ($state === self::STATE_CONTRACT_ERROR && $page !== null) {
            throw new \InvalidArgumentException('[invalid_read_result] Page must be null for contract_error.');
        }

        $this->state = $state;
        $this->page = $page;
    }

    public static function resolved_page(CanonicalAggregatedContainersPage $page): self {
        return new self(self::STATE_RESOLVED_PAGE, $page);
    }

    public static function empty_page(CanonicalAggregatedContainersPage $page): self {
        return new self(self::STATE_EMPTY, $page);
    }

    public static function contract_error(): self {
        return new self(self::STATE_CONTRACT_ERROR, null);
    }

    public function state(): string {
        return $this->state;
    }

    public function page(): ?CanonicalAggregatedContainersPage {
        return $this->page;
    }
}
