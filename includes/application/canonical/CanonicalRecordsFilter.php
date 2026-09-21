<?php
/** Filtro de registros aportado por una capability, antes de paginar. */
defined('ABSPATH') or die('No direct access');

interface CanonicalRecordsFilter {
    public function count(CanonicalRelationalRepository $repository, int $container_id): int;
    /** @return list<array<string,mixed>> */
    public function list(CanonicalRelationalRepository $repository, int $container_id, int $page, int $per_page): array;
}
