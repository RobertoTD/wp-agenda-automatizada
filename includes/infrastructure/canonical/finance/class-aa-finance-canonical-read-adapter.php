<?php
/**
 * Finance Canonical Read Adapter — Lectura legacy SQL de contenedores/registros Finance (SB1-4B).
 *
 * Proyección canónica exclusiva; no reutiliza repositorios Finance.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Finance
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalReadAdapter')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadAdapter.php';
}
if (!class_exists('CanonicalPage')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalPage.php';
}
if (!class_exists('CanonicalRecordsPage')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalRecordsPage.php';
}
if (!class_exists('CanonicalContainerNotFound')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalContainerNotFound.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once dirname(__DIR__, 3) . '/application/canonical/CanonicalReadGateway.php';
}
if (!class_exists('AA_Canonical_Container')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-container.php';
}
if (!class_exists('AA_Canonical_Record')) {
    require_once dirname(__DIR__, 3) . '/domain/canonical/class-aa-canonical-record.php';
}
if (!class_exists('AA_Finance_Schema')) {
    require_once dirname(__DIR__, 2) . '/wp/FinanceSchema.php';
}

final class AA_Finance_Canonical_Read_Adapter implements CanonicalReadAdapter {

    /** @var object */
    private $wpdb;

    /**
     * @param object|null $wpdb Instancia wpdb; global si null (tests pueden inyectar mock).
     */
    public function __construct($wpdb = null) {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage {
        $this->assert_page_contract($page, $per_page);

        $table = $this->containers_table();
        $total = $this->count_containers($variant_key, $table);
        if ($total === 0) {
            return new CanonicalPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, variant_key, title, details, updated_at
                 FROM `{$table}`
                 WHERE variant_key = %s
                 ORDER BY updated_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $variant_key,
                $per_page,
                $offset
            )
        );
        $this->assert_select_ok($rows);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map_container_row($row);
        }

        return new CanonicalPage(
            $items,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    public function get_container(string $variant_key, int $container_id): AA_Canonical_Container {
        $container_id = $this->assert_positive_id($container_id, 'container_id');

        $table = $this->containers_table();
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, variant_key, title, details, updated_at
                 FROM `{$table}`
                 WHERE variant_key = %s AND id = %d
                 LIMIT 1",
                $variant_key,
                $container_id
            )
        );
        $this->assert_row_query_ok($row);

        if ($row === null) {
            throw new CanonicalContainerNotFound($variant_key, $container_id);
        }

        return $this->map_container_row($row);
    }

    public function list_records(
        string $variant_key,
        int $container_id,
        int $page,
        int $per_page
    ): CanonicalRecordsPage {
        $this->assert_page_contract($page, $per_page);
        $container_id = $this->assert_positive_id($container_id, 'container_id');

        $this->get_container($variant_key, $container_id);

        $table = $this->records_table();
        $total = $this->count_records($container_id, $table);
        if ($total === 0) {
            return new CanonicalRecordsPage([], 1, $per_page, 0, 0, false, false);
        }

        $total_pages = (int) ceil($total / $per_page);
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, container_id, title, details, updated_at
                 FROM `{$table}`
                 WHERE container_id = %d
                 ORDER BY updated_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $container_id,
                $per_page,
                $offset
            )
        );
        $this->assert_select_ok($rows);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map_record_row($row);
        }

        return new CanonicalRecordsPage(
            $items,
            $page,
            $per_page,
            $total,
            $total_pages,
            $page > 1,
            $page < $total_pages
        );
    }

    private function containers_table(): string {
        return $this->wpdb->prefix . AA_Finance_Schema::TABLE_CONTAINERS;
    }

    private function records_table(): string {
        return $this->wpdb->prefix . AA_Finance_Schema::TABLE_RECORDS;
    }

    private function assert_page_contract(int $page, int $per_page): void {
        if ($page < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] page must be >= 1.');
        }
        if ($per_page !== CanonicalReadGateway::PAGE_SIZE) {
            throw new \InvalidArgumentException('[invalid_page_contract] per_page must match gateway PAGE_SIZE.');
        }
    }

    private function assert_positive_id(int $id, string $label): int {
        if ($id < 1) {
            throw new \InvalidArgumentException('[invalid_page_contract] ' . $label . ' must be positive.');
        }

        return $id;
    }

    private function count_containers(string $variant_key, string $table): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE variant_key = %s",
                $variant_key
            )
        );
        $this->assert_count_ok($count);

        return (int) $count;
    }

    private function count_records(int $container_id, string $table): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE container_id = %d",
                $container_id
            )
        );
        $this->assert_count_ok($count);

        return (int) $count;
    }

    /**
     * @param mixed $count
     */
    private function assert_count_ok($count): void {
        if ($this->wpdb->last_error !== '') {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance count query failed.');
        }
        if ($count === null || $count === false) {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance count query returned no value.');
        }
    }

    /**
     * @param mixed $rows
     */
    private function assert_select_ok($rows): void {
        if ($this->wpdb->last_error !== '') {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance select query failed.');
        }
        if ($rows === null) {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance select query failed.');
        }
    }

    /**
     * @param mixed $row
     */
    private function assert_row_query_ok($row): void {
        if ($this->wpdb->last_error !== '') {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance row query failed.');
        }
    }

    /**
     * @param object $row
     */
    private function map_container_row(object $row): AA_Canonical_Container {
        return new AA_Canonical_Container(
            (int) $row->id,
            (string) $row->variant_key,
            (string) $row->title,
            $row->details !== null ? (string) $row->details : null,
            $this->convert_finance_timestamp_to_utc((string) ($row->updated_at ?? ''))
        );
    }

    /**
     * @param object $row
     */
    private function map_record_row(object $row): AA_Canonical_Record {
        return new AA_Canonical_Record(
            (int) $row->id,
            (int) $row->container_id,
            (string) $row->title,
            $row->details !== null ? (string) $row->details : null,
            $this->convert_finance_timestamp_to_utc((string) ($row->updated_at ?? ''))
        );
    }

    private function convert_finance_timestamp_to_utc(string $mysql_local): string {
        if ($mysql_local === '') {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance timestamp is empty.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $mysql_local)) {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance timestamp format invalid.');
        }

        if (!function_exists('wp_timezone')) {
            throw new \InvalidArgumentException('[invalid_page_contract] WordPress timezone unavailable.');
        }

        $local_tz = wp_timezone();
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysql_local, $local_tz);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance timestamp parse failed.');
        }

        if ($parsed->format('Y-m-d H:i:s') !== $mysql_local) {
            throw new \InvalidArgumentException('[invalid_page_contract] Finance timestamp round-trip failed.');
        }

        return $parsed->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
