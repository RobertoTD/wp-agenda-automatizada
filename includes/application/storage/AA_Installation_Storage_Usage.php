<?php
/**
 * Consumo de almacenamiento de la instalación (blog actual).
 *
 * Agrega lecturas SQL de repositorios; no muta operaciones ni Storage.
 *
 * Conceptos:
 * - confirmed_bytes: originales confirmados legacy + canónicos.
 * - reserved_bytes: ops admitted vigentes (ver CanonicalImageUploadOperationsRepository).
 * - admission_used_bytes: confirmed + reserved, excluyendo solo la reserva propia
 *   cuando Application pasa un upload_operation_id ya validado como admisión
 *   reanudable (nunca un flag del navegador).
 *
 * Reloj: una sola referencia `$now_ms` por cálculo; `now_utc` = gmdate derivado
 * del mismo instante (segundos). Resume/commit futuros deben usar el mismo
 * ancla `backend_intent_exp_ms` / `expires_at` (no liberar reserva mientras
 * la op aún pueda confirmarse).
 *
 * Dependencia temporal (retirable al retirar adjuntos legacy):
 *   ExpedienteAdjuntosRepository::sum_byte_size_total()
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Storage
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Installation_Storage_Usage_Failed')) {
    require_once __DIR__ . '/AA_Installation_Storage_Usage_Failed.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    // TEMPORARY — legacy adjuntos; retirar con el legado de imágenes.
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalImageUploadOperationsRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalImageUploadOperationsRepository.php';
}

final class AA_Installation_Storage_Usage {

    /** @var self|null Override solo para acceptance tests. */
    private static $default_for_tests = null;

    /** @var callable():?int|null TEMPORARY legacy sum */
    private $legacy_sum;

    /** @var CanonicalRecordImagesRepository|object */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository|object */
    private $operations;

    /** @var callable():int */
    private $clock_ms;

    /**
     * @internal Acceptance tests only.
     */
    public static function set_default_for_tests(?self $usage): void {
        self::$default_for_tests = $usage;
    }

    public static function create_default(): self {
        return self::$default_for_tests instanceof self
            ? self::$default_for_tests
            : new self();
    }

    /**
     * @param callable():?int|null $legacy_sum TEMPORARY — ExpedienteAdjuntosRepository::sum_byte_size_total
     * @param CanonicalRecordImagesRepository|object|null $images
     * @param CanonicalImageUploadOperationsRepository|object|null $operations
     * @param callable():int|null $clock_ms
     */
    public function __construct(
        $legacy_sum = null,
        $images = null,
        $operations = null,
        $clock_ms = null
    ) {
        $this->legacy_sum = is_callable($legacy_sum)
            ? $legacy_sum
            : static function (): ?int {
                return ExpedienteAdjuntosRepository::sum_byte_size_total();
            };
        $this->images = $images instanceof CanonicalRecordImagesRepository || is_object($images)
            ? $images
            : new CanonicalRecordImagesRepository();
        $this->operations = $operations instanceof CanonicalImageUploadOperationsRepository || is_object($operations)
            ? $operations
            : new CanonicalImageUploadOperationsRepository();
        $this->clock_ms = is_callable($clock_ms)
            ? $clock_ms
            : static function (): int {
                return (int) floor(microtime(true) * 1000);
            };
    }

    /**
     * UTC datetime (Y-m-d H:i:s) derivado del mismo instante ms (floor a segundos).
     */
    public static function utc_datetime_from_ms(int $ms): string {
        return gmdate('Y-m-d H:i:s', intdiv($ms, 1000));
    }

    /**
     * expires_at de admisión: representación UTC del backend_intent_exp_ms.
     */
    public static function expires_at_from_intent_exp_ms(int $backend_intent_exp_ms): string {
        return self::utc_datetime_from_ms($backend_intent_exp_ms);
    }

    /**
     * @throws AA_Installation_Storage_Usage_Failed
     */
    public function confirmed_bytes(): int {
        $legacy = $this->read_legacy_confirmed();
        $canonical = $this->read_canonical_confirmed();
        return $this->safe_add($legacy, $canonical);
    }

    /**
     * @throws AA_Installation_Storage_Usage_Failed
     */
    public function reserved_bytes(): int {
        $now_ms = $this->now_ms();
        $now_utc = self::utc_datetime_from_ms($now_ms);

        try {
            $reserved = $this->operations->sum_reserved_byte_size($now_ms, $now_utc, null);
        } catch (\Throwable $e) {
            throw new AA_Installation_Storage_Usage_Failed(
                'Reserved storage usage unavailable.',
                0,
                $e
            );
        }

        if (!is_int($reserved) || $reserved < 0) {
            throw new AA_Installation_Storage_Usage_Failed('Reserved storage usage unavailable.');
        }

        return $reserved;
    }

    /**
     * @param string|null $exclude_operation_id Solo Application; ID de admisión propia ya validada.
     *
     * @throws AA_Installation_Storage_Usage_Failed
     */
    public function admission_used_bytes(?string $exclude_operation_id = null): int {
        $confirmed = $this->confirmed_bytes();
        $now_ms = $this->now_ms();
        $now_utc = self::utc_datetime_from_ms($now_ms);

        $exclude = is_string($exclude_operation_id) ? trim($exclude_operation_id) : '';
        if ($exclude === '') {
            $exclude = null;
        }

        try {
            $reserved = $this->operations->sum_reserved_byte_size($now_ms, $now_utc, $exclude);
        } catch (\Throwable $e) {
            throw new AA_Installation_Storage_Usage_Failed(
                'Admission storage usage unavailable.',
                0,
                $e
            );
        }

        if (!is_int($reserved) || $reserved < 0) {
            throw new AA_Installation_Storage_Usage_Failed('Admission storage usage unavailable.');
        }

        return $this->safe_add($confirmed, $reserved);
    }

    /**
     * @throws AA_Installation_Storage_Usage_Failed
     */
    private function read_legacy_confirmed(): int {
        try {
            $sum = ($this->legacy_sum)();
        } catch (\Throwable $e) {
            throw new AA_Installation_Storage_Usage_Failed(
                'Legacy storage usage unavailable.',
                0,
                $e
            );
        }

        // null = fallo SQL del repo legacy (no “cero filas”).
        if ($sum === null) {
            throw new AA_Installation_Storage_Usage_Failed('Legacy storage usage unavailable.');
        }
        if (!is_int($sum) || $sum < 0) {
            throw new AA_Installation_Storage_Usage_Failed('Legacy storage usage unavailable.');
        }

        return $sum;
    }

    /**
     * @throws AA_Installation_Storage_Usage_Failed
     */
    private function read_canonical_confirmed(): int {
        try {
            $sum = $this->images->sum_byte_size_total();
        } catch (\Throwable $e) {
            throw new AA_Installation_Storage_Usage_Failed(
                'Canonical images storage usage unavailable.',
                0,
                $e
            );
        }

        if (!is_int($sum) || $sum < 0) {
            throw new AA_Installation_Storage_Usage_Failed('Canonical images storage usage unavailable.');
        }

        return $sum;
    }

    /**
     * @throws AA_Installation_Storage_Usage_Failed
     */
    private function safe_add(int $a, int $b): int {
        $sum = $a + $b;
        if ($sum < 0 || $sum < $a || $sum < $b) {
            throw new AA_Installation_Storage_Usage_Failed('Storage usage overflow.');
        }
        return $sum;
    }

    private function now_ms(): int {
        $ms = (int) ($this->clock_ms)();
        if ($ms < 0) {
            return 0;
        }
        return $ms;
    }
}
