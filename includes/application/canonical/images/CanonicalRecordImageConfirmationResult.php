<?php
/**
 * Resultado tipado de confirmación SQL de imagen canónica (IMG-3b).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordImageConfirmationResult {

    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_UNCERTAIN = 'uncertain';

    /** @var string */
    private $outcome;

    /** @var int|null */
    private $image_id;

    /** @var array{id:int,width:int,height:int,byte_size:int,created_at:string}|null */
    private $dto;

    /** @var string|null */
    private $failure_code;

    /** @var string|null */
    private $upload_operation_id;

    /**
     * @param array{id:int,width:int,height:int,byte_size:int,created_at:string}|null $dto
     */
    private function __construct(
        string $outcome,
        ?int $image_id,
        ?array $dto,
        ?string $failure_code,
        ?string $upload_operation_id
    ) {
        $this->outcome = $outcome;
        $this->image_id = $image_id;
        $this->dto = $dto;
        $this->failure_code = $failure_code;
        $this->upload_operation_id = $upload_operation_id;
    }

    /**
     * @param array{id:int,width:int,height:int,byte_size:int,created_at:string} $dto
     */
    public static function confirmed(int $image_id, array $dto): self {
        return new self(self::OUTCOME_CONFIRMED, $image_id, $dto, null, null);
    }

    public static function failed(string $code): self {
        return new self(self::OUTCOME_FAILED, null, null, $code, null);
    }

    public static function uncertain(string $upload_operation_id): self {
        return new self(self::OUTCOME_UNCERTAIN, null, null, null, $upload_operation_id);
    }

    public function outcome(): string {
        return $this->outcome;
    }

    public function image_id(): ?int {
        return $this->image_id;
    }

    /**
     * @return array{id:int,width:int,height:int,byte_size:int,created_at:string}|null
     */
    public function dto(): ?array {
        return $this->dto;
    }

    public function failure_code(): ?string {
        return $this->failure_code;
    }

    public function upload_operation_id(): ?string {
        return $this->upload_operation_id;
    }
}
