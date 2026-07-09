<?php
/**
 * Confirm Reservation Use Case — persistencia canónica de aa_reservas.estado = confirmed.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';

final class ConfirmReservationUseCase {

    private const OPTIONAL_COLUMNS = ['calendar_uid', 'virtual_link'];

    /** @var callable|null */
    private $reservation_confirmer;

    /** @var callable|null */
    private $post_confirmation_sync;

    /**
     * @param callable|null $reservation_confirmer (int $reservation_id, array $data, array $formats): int|false
     * @param callable|null $post_confirmation_sync (int $reservation_id): void
     */
    public function __construct(
        ?callable $reservation_confirmer = null,
        ?callable $post_confirmation_sync = null
    ) {
        $this->reservation_confirmer = $reservation_confirmer;
        $this->post_confirmation_sync = $post_confirmation_sync;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $reservation_id = (int) ($input['reservation_id'] ?? 0);

        if ($reservation_id < 1) {
            return TaskUseCaseSupport::fail('missing_reservation_id', 'El identificador de la cita es obligatorio.');
        }

        $optional_columns = $this->normalize_optional_columns($input['columns'] ?? []);

        if ($optional_columns === null) {
            return TaskUseCaseSupport::fail('invalid_column', 'Columna no permitida para confirmación.');
        }

        $update_data = array_merge(['estado' => 'confirmed'], $optional_columns);
        $update_formats = $this->build_update_formats($update_data);

        $updated = $this->persist_confirmation($reservation_id, $update_data, $update_formats);

        if ($updated === false) {
            return TaskUseCaseSupport::fail(
                'confirmation_persistence_failed',
                'No se pudo persistir la confirmación de la cita.'
            );
        }

        $this->run_post_confirmation_sync($reservation_id);

        return TaskUseCaseSupport::ok([
            'reservation_id' => $reservation_id,
            'rows_affected' => $updated,
        ]);
    }

    /**
     * @param mixed $columns
     * @return array<string,string>|null
     */
    private function normalize_optional_columns($columns): ?array {
        if (!is_array($columns) || $columns === []) {
            return [];
        }

        $normalized = [];

        foreach ($columns as $key => $value) {
            if (!is_string($key) || !in_array($key, self::OPTIONAL_COLUMNS, true)) {
                return null;
            }

            if (!is_string($value) || $value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string,string> $update_data
     * @return array<int,string>
     */
    private function build_update_formats(array $update_data): array {
        $formats = [];

        foreach (array_keys($update_data) as $column) {
            $formats[] = '%s';
        }

        return $formats;
    }

    /**
     * @param array<string,string> $update_data
     * @param array<int,string> $update_formats
     * @return int|false
     */
    private function persist_confirmation(int $reservation_id, array $update_data, array $update_formats) {
        if ($this->reservation_confirmer !== null) {
            return call_user_func($this->reservation_confirmer, $reservation_id, $update_data, $update_formats);
        }

        global $wpdb;

        $table = $wpdb->prefix . 'aa_reservas';

        return $wpdb->update(
            $table,
            $update_data,
            ['id' => $reservation_id],
            $update_formats,
            ['%d']
        );
    }

    private function run_post_confirmation_sync(int $reservation_id): void {
        if ($this->post_confirmation_sync !== null) {
            ($this->post_confirmation_sync)($reservation_id);
            return;
        }

        require_once dirname(__DIR__) . '/appointments/CompleteAppointmentConfirmationTaskUseCase.php';
        CompleteAppointmentConfirmationTaskUseCase::sync_after_local_confirmation_best_effort($reservation_id);
    }
}
