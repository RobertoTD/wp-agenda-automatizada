<?php
/**
 * Canonical Write Adapter — Puerto de mutación canónica de contenedores y registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!class_exists('CanonicalMutationReceipt')) {
    require_once __DIR__ . '/CanonicalMutationReceipt.php';
}
if (!class_exists('CanonicalCreateContainerCommand')) {
    require_once __DIR__ . '/CanonicalCreateContainerCommand.php';
}
if (!class_exists('CanonicalUpdateContainerCommand')) {
    require_once __DIR__ . '/CanonicalUpdateContainerCommand.php';
}
if (!class_exists('CanonicalDeleteContainerCommand')) {
    require_once __DIR__ . '/CanonicalDeleteContainerCommand.php';
}
if (!class_exists('CanonicalCreateRecordCommand')) {
    require_once __DIR__ . '/CanonicalCreateRecordCommand.php';
}
if (!class_exists('CanonicalUpdateRecordCommand')) {
    require_once __DIR__ . '/CanonicalUpdateRecordCommand.php';
}
if (!class_exists('CanonicalDeleteRecordCommand')) {
    require_once __DIR__ . '/CanonicalDeleteRecordCommand.php';
}

interface CanonicalWriteAdapter {

    /**
     * @param list<CanonicalContainerMutationEffect> $effects
     * @throws CanonicalMutationPersistenceFailed
     */
    public function create_container(
        CanonicalReadIdentity $identity,
        CanonicalCreateContainerCommand $command,
        array $effects = []
    ): CanonicalMutationReceipt;

    /**
     * @param list<CanonicalContainerMutationEffect> $effects
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function update_container(
        CanonicalReadIdentity $identity,
        CanonicalUpdateContainerCommand $command,
        array $effects = []
    ): CanonicalMutationReceipt;

    /**
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function delete_container(
        CanonicalReadIdentity $identity,
        CanonicalDeleteContainerCommand $command
    ): CanonicalMutationReceipt;

    /**
     * @param list<CanonicalRecordCapabilityEffect> $effects
     * @throws CanonicalContainerNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function create_record(
        CanonicalReadIdentity $identity,
        CanonicalCreateRecordCommand $command,
        array $effects = []
    ): CanonicalMutationReceipt;

    /**
     * @param list<CanonicalRecordCapabilityEffect> $effects
     * @throws CanonicalContainerNotFound
     * @throws CanonicalRecordNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function update_record(
        CanonicalReadIdentity $identity,
        CanonicalUpdateRecordCommand $command,
        array $effects = []
    ): CanonicalMutationReceipt;

    /**
     * @throws CanonicalContainerNotFound
     * @throws CanonicalRecordNotFound
     * @throws CanonicalMutationPersistenceFailed
     */
    public function delete_record(
        CanonicalReadIdentity $identity,
        CanonicalDeleteRecordCommand $command
    ): CanonicalMutationReceipt;
}
