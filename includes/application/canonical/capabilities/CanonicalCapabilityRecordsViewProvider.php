<?php
/** Una capability puede aportar una proyección de lectura opcional al shell. */
defined('ABSPATH') or die('No direct access');

interface CanonicalCapabilityRecordsViewProvider {
    /** Filtro de la vista normal si la capability está activa; null si no altera la lectura. */
    public function default_filter(string $family_key, int $container_id): ?CanonicalRecordsFilter;
    /** null cuando no reconoce la vista o cuando ésta no está disponible. */
    public function filter_for_view(string $family_key, int $container_id, string $view_key): ?CanonicalRecordsFilter;
    public function owns_view_key(string $view_key): bool;
    /** @return array{key:string,label:string}|null */
    public function available_view(string $family_key, int $container_id, string $view_key): ?array;
    /** @return list<array{key:string,label:string}> */
    public function available_views(string $family_key, int $container_id): array;
}
