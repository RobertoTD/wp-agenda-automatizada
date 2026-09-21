<?php
defined('ABSPATH') or die('No direct access');
final class CanonicalCompletedRecordsFilter implements CanonicalRecordsFilter {
    private $completed;
    public function __construct(bool $completed) { $this->completed = $completed; }
    public function count(CanonicalRelationalRepository $repository, int $container_id): int { return $repository->count_records_by_completion($container_id, $this->completed); }
    public function list(CanonicalRelationalRepository $repository, int $container_id, int $page, int $per_page): array { return $repository->list_records_by_completion($container_id, $page, $per_page, $this->completed); }
}
