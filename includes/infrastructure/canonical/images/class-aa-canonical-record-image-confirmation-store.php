<?php
/**
 * Store TX de confirmación de imagen canónica (IMG-3b).
 *
 * Misma conexión: revalida admisión con reloj fresco → INSERT image → DELETE ops
 * → touch contenedor → COMMIT. Storage fuera de la TX.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalRecordImageConfirmationPort')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/images/CanonicalRecordImageConfirmationPort.php';
}
if (!class_exists('CanonicalRecordImageConfirmationResult')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/images/CanonicalRecordImageConfirmationResult.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalImageUploadOperationsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalImageUploadOperationsRepository.php';
}
if (!class_exists('AA_Canonical_Schema')) {
    require_once dirname(__DIR__, 2) . '/wp/CanonicalSchema.php';
}
if (!class_exists('AA_Installation_Storage_Usage')) {
    require_once dirname(__DIR__, 3) . '/application/storage/AA_Installation_Storage_Usage.php';
}
if (!class_exists('CanonicalRecordImagePublicDto')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/images/CanonicalRecordImagePublicDto.php';
}
if (!class_exists('CanonicalPurgeRunsRepository')) {
    require_once dirname(__DIR__, 3) . '/repositories/CanonicalPurgeRunsRepository.php';
}
if (!class_exists('CanonicalImageUploadSchemaNotReady')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadSchemaNotReady.php';
}
if (!class_exists('CanonicalImageUploadPersistenceFailed')) {
    require_once dirname(__DIR__, 3) . '/application/storage/CanonicalImageUploadPersistenceFailed.php';
}

final class AA_Canonical_Record_Image_Confirmation_Store implements CanonicalRecordImageConfirmationPort {

    /** @var CanonicalRecordImagesRepository */
    private $images;

    /** @var CanonicalImageUploadOperationsRepository */
    private $operations;

    /** @var object */
    private $wpdb;

    /** @var callable():int */
    private $clock_ms;

    /** @var CanonicalPurgeRunsRepository */
    private $purge_runs;

    /**
     * @param callable():int|null $clock_ms
     */
    public function __construct(
        ?CanonicalRecordImagesRepository $images = null,
        ?CanonicalImageUploadOperationsRepository $operations = null,
        $wpdb = null,
        ?callable $clock_ms = null,
        ?CanonicalPurgeRunsRepository $purge_runs = null
    ) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }

        $this->images = $images ?: new CanonicalRecordImagesRepository($this->wpdb);
        $this->operations = $operations ?: new CanonicalImageUploadOperationsRepository($this->wpdb);
        $this->clock_ms = $clock_ms ?: static function (): int {
            return (int) floor(microtime(true) * 1000);
        };
        $this->purge_runs = $purge_runs ?: new CanonicalPurgeRunsRepository($this->wpdb);
    }

    public function confirm_after_remote_finalize(array $payload): CanonicalRecordImageConfirmationResult {
        $op = strtolower(trim((string) ($payload['upload_operation_id'] ?? '')));
        $record_id = (int) ($payload['record_id'] ?? 0);
        $container_id = (int) ($payload['container_id'] ?? 0);
        $family_id = (int) ($payload['family_id'] ?? 0);

        if ($op === '' || $record_id < 1 || $container_id < 1 || $family_id < 1) {
            return CanonicalRecordImageConfirmationResult::failed('invalid_confirmation_payload');
        }

        $existing = $this->images->find_by_upload_operation_id($op);
        if ($existing !== null) {
            if (!$this->image_matches_payload($existing, $payload)) {
                return CanonicalRecordImageConfirmationResult::failed('image_identity_conflict');
            }

            return CanonicalRecordImageConfirmationResult::confirmed(
                (int) $existing['id'],
                $this->public_dto($existing)
            );
        }

        $now_ms = (int) call_user_func($this->clock_ms);
        $admission = $this->operations->find_by_operation_id($op);
        if ($admission === null) {
            return CanonicalRecordImageConfirmationResult::failed('admission_missing');
        }

        if ((int) ($admission['record_id'] ?? 0) !== $record_id) {
            return CanonicalRecordImageConfirmationResult::failed('operation_identity_conflict');
        }

        if ((string) ($admission['status'] ?? '') !== AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED) {
            return CanonicalRecordImageConfirmationResult::failed('admission_not_confirmable');
        }

        $intent = isset($admission['upload_intent']) ? trim((string) $admission['upload_intent']) : '';
        $objects = isset($admission['upload_objects_json']) ? trim((string) $admission['upload_objects_json']) : '';
        if ($intent === '' || $objects === '') {
            return CanonicalRecordImageConfirmationResult::failed('admission_not_confirmable');
        }

        if (!isset($admission['backend_intent_exp_ms']) || $admission['backend_intent_exp_ms'] === null || $admission['backend_intent_exp_ms'] === '') {
            return CanonicalRecordImageConfirmationResult::failed('admission_not_confirmable');
        }

        if ((int) $admission['backend_intent_exp_ms'] <= $now_ms) {
            return CanonicalRecordImageConfirmationResult::failed('admission_expired');
        }

        if (!$this->admission_matches_payload($admission, $payload)) {
            return CanonicalRecordImageConfirmationResult::failed('image_identity_conflict');
        }

        $blocked = $this->blocking_purge_failure($record_id, $container_id);
        if ($blocked !== null) {
            return $blocked;
        }

        $created_at = AA_Installation_Storage_Usage::utc_datetime_from_ms($now_ms);
        $image_id = null;
        $mutation_possible = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                return CanonicalRecordImageConfirmationResult::failed('persistence_failed');
            }

            $blocked = $this->blocking_purge_failure($record_id, $container_id);
            if ($blocked !== null) {
                $this->rollback_confirmed();
                return $blocked;
            }

            $image_id = $this->images->insert_confirmed([
                'record_id' => $record_id,
                'upload_operation_id' => $op,
                'storage_path' => (string) $payload['storage_path'],
                'content_sha256' => (string) $payload['content_sha256'],
                'mime_type' => (string) $payload['mime_type'],
                'byte_size' => (int) $payload['byte_size'],
                'width' => (int) $payload['width'],
                'height' => (int) $payload['height'],
                'created_at' => $created_at,
            ]);
            $mutation_possible = true;

            $deleted = $this->operations->delete_by_operation_id($op);
            if ($deleted !== 1) {
                $this->rollback_confirmed();
                return CanonicalRecordImageConfirmationResult::failed('admission_missing');
            }

            $this->touch_container($family_id, $container_id, $created_at);

            $this->wpdb->last_error = '';
            if ($this->wpdb->query('COMMIT') === false) {
                $this->best_effort_rollback();
                return CanonicalRecordImageConfirmationResult::uncertain($op);
            }

            $row = $this->images->find_by_id($image_id);
            if ($row === null) {
                return CanonicalRecordImageConfirmationResult::uncertain($op);
            }

            return CanonicalRecordImageConfirmationResult::confirmed($image_id, $this->public_dto($row));
        } catch (\Throwable $e) {
            if ($mutation_possible) {
                if (!$this->best_effort_rollback()) {
                    return CanonicalRecordImageConfirmationResult::uncertain($op);
                }

                $reconcile = $this->images->find_by_upload_operation_id($op);
                if ($reconcile !== null && $this->image_matches_payload($reconcile, $payload)) {
                    return CanonicalRecordImageConfirmationResult::confirmed(
                        (int) $reconcile['id'],
                        $this->public_dto($reconcile)
                    );
                }

                return CanonicalRecordImageConfirmationResult::uncertain($op);
            }

            $this->best_effort_rollback();
            return CanonicalRecordImageConfirmationResult::failed('persistence_failed');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $row
     */
    private function image_matches_payload(array $row, array $payload): bool {
        return (int) ($row['record_id'] ?? 0) === (int) ($payload['record_id'] ?? 0)
            && strtolower((string) ($row['content_sha256'] ?? '')) === strtolower((string) ($payload['content_sha256'] ?? ''))
            && (string) ($row['mime_type'] ?? '') === (string) ($payload['mime_type'] ?? '')
            && (int) ($row['byte_size'] ?? 0) === (int) ($payload['byte_size'] ?? 0)
            && (int) ($row['width'] ?? 0) === (int) ($payload['width'] ?? 0)
            && (int) ($row['height'] ?? 0) === (int) ($payload['height'] ?? 0)
            && (string) ($row['storage_path'] ?? '') === (string) ($payload['storage_path'] ?? '');
    }

    /**
     * @param array<string, mixed> $admission
     * @param array<string, mixed> $payload
     */
    private function admission_matches_payload(array $admission, array $payload): bool {
        return strtolower((string) ($admission['content_sha256'] ?? '')) === strtolower((string) ($payload['content_sha256'] ?? ''))
            && (string) ($admission['mime_type'] ?? '') === (string) ($payload['mime_type'] ?? '')
            && (int) ($admission['byte_size'] ?? 0) === (int) ($payload['byte_size'] ?? 0)
            && (int) ($admission['width'] ?? 0) === (int) ($payload['width'] ?? 0)
            && (int) ($admission['height'] ?? 0) === (int) ($payload['height'] ?? 0)
            && (string) ($admission['storage_path'] ?? '') === (string) ($payload['storage_path'] ?? '');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,width:int,height:int,byte_size:int,created_at:string}
     */
    private function public_dto(array $row): array {
        return CanonicalRecordImagePublicDto::from_row($row);
    }

    private function blocking_purge_failure(int $record_id, int $container_id): ?CanonicalRecordImageConfirmationResult {
        try {
            if ($this->purge_runs->has_blocking_purge($record_id, $container_id)) {
                return CanonicalRecordImageConfirmationResult::failed('purge_in_progress');
            }
        } catch (CanonicalImageUploadSchemaNotReady $e) {
            return CanonicalRecordImageConfirmationResult::failed('schema_not_ready');
        } catch (CanonicalImageUploadPersistenceFailed $e) {
            return CanonicalRecordImageConfirmationResult::failed('persistence_failed');
        }

        return null;
    }

    /**
     * @throws CanonicalImageUploadPersistenceFailed|\RuntimeException
     */
    private function touch_container(int $family_id, int $container_id, string $updated_at): void {
        $table = AA_Canonical_Schema::containers_table_name();
        $this->wpdb->last_error = '';
        $result = $this->wpdb->update(
            $table,
            ['updated_at' => $updated_at],
            [
                'family_id' => $family_id,
                'id' => $container_id,
            ],
            ['%s'],
            ['%d', '%d']
        );

        if ($result === false || $this->wpdb->last_error !== '') {
            throw new \RuntimeException('touch_container failed');
        }
    }

    private function rollback_confirmed(): void {
        $this->wpdb->last_error = '';
        $this->wpdb->query('ROLLBACK');
    }

    private function best_effort_rollback(): bool {
        $this->wpdb->last_error = '';
        return $this->wpdb->query('ROLLBACK') !== false;
    }
}
