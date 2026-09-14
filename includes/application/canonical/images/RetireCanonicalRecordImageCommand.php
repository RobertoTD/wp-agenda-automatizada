<?php
/**
 * Comando de retiro de una imagen canónica (IMG-5 inc. 5).
 *
 * Cliente envía image_id; el servidor resuelve registro/contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class RetireCanonicalRecordImageCommand {

    public const INTENT_RETIRE = 'retire';
    public const INTENT_CANCEL = 'cancel';

    /** @var string */
    private $family_key;

    /** @var int */
    private $image_id;

    /** @var string */
    private $intent;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $family_key,
        int $image_id,
        string $intent = self::INTENT_RETIRE
    ) {
        $family = trim($family_key);
        if ($family === '') {
            throw new \InvalidArgumentException('[invalid_family_key] family_key required.');
        }
        if ($image_id < 1) {
            throw new \InvalidArgumentException('[invalid_image_id] image_id must be positive.');
        }
        if ($intent !== self::INTENT_RETIRE && $intent !== self::INTENT_CANCEL) {
            throw new \InvalidArgumentException('[invalid_retire_intent] intent must be retire or cancel.');
        }

        $this->family_key = $family;
        $this->image_id = $image_id;
        $this->intent = $intent;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function image_id(): int {
        return $this->image_id;
    }

    public function intent(): string {
        return $this->intent;
    }

    public function is_cancel(): bool {
        return $this->intent === self::INTENT_CANCEL;
    }
}
