<?php
/**
 * Migrate Deferred Tasks To Default Bucket Use Case (MC13O-H3B-2).
 *
 * Backfill idempotente: copia intención histórica defer → aa_tasks.default_bucket=secondary.
 * No altera aa_task_state, projection ni defer write path.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';

final class MigrateDeferredTasksToDefaultBucketUseCase {

    /** @var callable|null */
    private $backfill_provider;

    /**
     * @param callable|null $backfill_provider Debe devolver array{matched_count:int,updated_count:int,skipped_count:int}.
     */
    public function __construct(?callable $backfill_provider = null) {
        $this->backfill_provider = $backfill_provider;
    }

    /**
     * @return array{
     *     success:bool,
     *     data?:array{matched_count:int,updated_count:int,skipped_count:int},
     *     error?:array{code:string,message:string}
     * }
     */
    public function execute(): array {
        try {
            $counts = $this->run_backfill();

            if (!is_array($counts)) {
                return TaskUseCaseSupport::fail(
                    'invalid_backfill_result',
                    'El backfill debe devolver un array de conteos.'
                );
            }

            return TaskUseCaseSupport::ok([
                'matched_count' => max(0, (int) ($counts['matched_count'] ?? 0)),
                'updated_count' => max(0, (int) ($counts['updated_count'] ?? 0)),
                'skipped_count' => max(0, (int) ($counts['skipped_count'] ?? 0)),
            ]);
        } catch (\Throwable $exception) {
            error_log('[MigrateDeferredTasksToDefaultBucketUseCase] ' . $exception->getMessage());

            return TaskUseCaseSupport::fail(
                'deferred_bucket_backfill_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * @return array{matched_count:int,updated_count:int,skipped_count:int}
     */
    private function run_backfill(): array {
        if ($this->backfill_provider !== null) {
            $result = call_user_func($this->backfill_provider);

            return is_array($result) ? $result : [];
        }

        return TaskRepository::backfill_deferred_primary_to_secondary_bucket();
    }
}
