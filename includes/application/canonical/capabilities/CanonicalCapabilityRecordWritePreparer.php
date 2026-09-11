<?php
/**
 * Prepara efectos de registro a partir de una WriteBag.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordWritePreparer {

    /** @var CanonicalCapabilityWriteHandlerRegistry */
    private $handlers;

    public function __construct(CanonicalCapabilityWriteHandlerRegistry $handlers) {
        $this->handlers = $handlers;
    }

    /**
     * @return list<CanonicalRecordCapabilityEffect>
     *
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalCapabilityInactive
     * @throws CanonicalCapabilityWriteRejected
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalContainerNotFound
     */
    public function prepare(
        string $family_key,
        int $container_id,
        CanonicalCapabilityWriteBag $bag,
        bool $is_update
    ): array {
        $effects = [];
        foreach ($bag->keys() as $key) {
            $handler = $this->handlers->require($key);
            $effect = $handler->prepare_record_write(
                $family_key,
                $container_id,
                $bag->raw($key),
                $is_update
            );
            if ($effect !== null) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }
}
