<?php
/**
 * Canonical Shell View Composer — Composition root WP del shell de lectura (SB1-2B / SB1-3B).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalShellManifest')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellManifest.php';
}
if (!class_exists('CanonicalShellReadResult')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellReadResult.php';
}
if (!class_exists('CanonicalShellRecordsReadResult')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellRecordsReadResult.php';
}
if (!class_exists('ReadCanonicalShellContainersUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/ReadCanonicalShellContainersUseCase.php';
}
if (!class_exists('ReadCanonicalShellRecordsUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/ReadCanonicalShellRecordsUseCase.php';
}
if (!class_exists('ReadCanonicalShellAllContainersUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/ReadCanonicalShellAllContainersUseCase.php';
}
if (!class_exists('CanonicalShellAggregatedReadResult')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellAggregatedReadResult.php';
}
if (!class_exists('CanonicalAggregatedContainersPage')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalAggregatedContainersPage.php';
}
if (!class_exists('AA_Canonical_Aggregated_Containers_Adapter')) {
    require_once __DIR__ . '/class-aa-canonical-aggregated-containers-adapter.php';
}
if (!class_exists('CanonicalRelationalRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}
if (!class_exists('CanonicalReadGateway')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadGateway.php';
}
if (!class_exists('CanonicalPage')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalPage.php';
}
if (!class_exists('CanonicalRecordsPage')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalRecordsPage.php';
}
if (!class_exists('AA_Canonical_Read_Binding_Registry')) {
    require_once __DIR__ . '/class-aa-canonical-read-binding-registry.php';
}
if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
    require_once dirname(__DIR__) . '/wp/class-aa-canonical-shell-base-url-policy.php';
}
if (!class_exists('AA_Canonical_Family_Definition')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-family-definition.php';
}
if (!class_exists('AA_Canonical_Read_Binding_Bootstrap')) {
    require_once __DIR__ . '/class-aa-canonical-read-binding-bootstrap.php';
}

final class AA_Canonical_Shell_View_Composer {

    public const PREVIEW_FAMILY_KEY = 'shell_preview';
    public const SHELL_VIEW_CONTAINERS = 'containers';
    public const SHELL_VIEW_RECORDS = 'records';

    public static function is_preview_enabled(): bool {
        return defined('AA_CANONICAL_SHELL_PREVIEW') && \AA_CANONICAL_SHELL_PREVIEW === true;
    }

    /**
     * @return array<string,mixed>
     */
    public static function compose_family(
        AA_Canonical_Family_Definition $family,
        int $page
    ): array {
        $identity = new CanonicalReadIdentity($family->key());
        $manifest = new CanonicalShellManifest($identity, $family);

        $binding = new AA_Canonical_Read_Binding_Registry();
        AA_Canonical_Read_Binding_Bootstrap::register_productive($binding);
        $gateway = new CanonicalReadGateway($binding);
        $result = (new ReadCanonicalShellContainersUseCase($gateway))->execute($manifest, $page);

        return self::build_containers_view_data($result, false, $page, null);
    }

    /**
     * Listado general de contenedores de familias enabled (alcance «Todas las listas»).
     *
     * @param list<AA_Canonical_Family_Definition> $enabled_families
     * @return array<string,mixed>
     */
    public static function compose_all_containers(array $enabled_families, int $page): array {
        $port = new AA_Canonical_Aggregated_Containers_Adapter(new CanonicalRelationalRepository());
        $result = (new ReadCanonicalShellAllContainersUseCase($port))->execute($enabled_families, $page);

        return self::build_aggregated_containers_view_data($result, $enabled_families, $page);
    }

    public static function compose_preview(int $page): array {
        if (!self::is_preview_enabled()) {
            throw new \LogicException('[preview_disabled] Preview adapter must not load when constant is off.');
        }

        require_once __DIR__ . '/preview/class-aa-canonical-shell-preview-adapter.php';

        $manifest = self::build_preview_manifest();
        $binding = new AA_Canonical_Read_Binding_Registry();
        $binding->register($manifest->identity(), new AA_Canonical_Shell_Preview_Adapter());
        $gateway = new CanonicalReadGateway($binding);
        $result = (new ReadCanonicalShellContainersUseCase($gateway))->execute($manifest, $page);

        return self::build_containers_view_data($result, true, $page, null);
    }

    /**
     * @return array<string,mixed>
     */
    public static function compose_family_records(
        AA_Canonical_Family_Definition $family,
        int $container_id,
        int $page,
        int $containers_page,
        ?string $lists_scope = null,
        string $records_view = 'pending'
    ): array {
        $identity = new CanonicalReadIdentity($family->key());
        $manifest = new CanonicalShellManifest($identity, $family);

        $binding = new AA_Canonical_Read_Binding_Registry();
        $completion_state = null;
        if ($family->key() === 'action') {
            $completion_state = ($records_view === 'completed');
        }
        AA_Canonical_Read_Binding_Bootstrap::register_productive($binding, $completion_state);
        $gateway = new CanonicalReadGateway($binding);
        $result = (new ReadCanonicalShellRecordsUseCase($gateway))->execute(
            $manifest,
            $container_id,
            $page
        );

        return self::build_records_view_data(
            $result,
            false,
            $container_id,
            $containers_page,
            $lists_scope,
            $records_view
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function compose_preview_records(
        int $container_id,
        int $page,
        int $containers_page
    ): array {
        if (!self::is_preview_enabled()) {
            throw new \LogicException('[preview_disabled] Preview adapter must not load when constant is off.');
        }

        require_once __DIR__ . '/preview/class-aa-canonical-shell-preview-adapter.php';

        $manifest = self::build_preview_manifest();
        $binding = new AA_Canonical_Read_Binding_Registry();
        $binding->register($manifest->identity(), new AA_Canonical_Shell_Preview_Adapter());
        $gateway = new CanonicalReadGateway($binding);
        $result = (new ReadCanonicalShellRecordsUseCase($gateway))->execute(
            $manifest,
            $container_id,
            $page
        );

        return self::build_records_view_data($result, true, $container_id, $containers_page, null);
    }

    private static function build_preview_manifest(): CanonicalShellManifest {
        $identity = new CanonicalReadIdentity(self::PREVIEW_FAMILY_KEY);
        $family = new AA_Canonical_Family_Definition(
            self::PREVIEW_FAMILY_KEY,
            'Demostración del shell'
        );

        return new CanonicalShellManifest($identity, $family);
    }

    /**
     * @param list<AA_Canonical_Family_Definition> $enabled_families
     * @return array<string,mixed>
     */
    private static function build_aggregated_containers_view_data(
        CanonicalShellAggregatedReadResult $result,
        array $enabled_families,
        int $containers_page
    ): array {
        $state = $result->state();
        $page = $result->page();

        if ($state === CanonicalShellAggregatedReadResult::STATE_CONTRACT_ERROR && function_exists('status_header')) {
            status_header(500);
        }

        $available_families = [];
        $icons_by_key = [];
        foreach ($enabled_families as $family) {
            if ($family instanceof AA_Canonical_Family_Definition) {
                $available_families[] = [
                    'family_key' => $family->key(),
                    'label' => $family->label(),
                ];
                $icons_by_key[$family->key()] = $family->icon_key();
            }
        }

        $items_view = [];
        $page_num = null;
        $per_page = null;
        $total = null;
        $total_pages = null;
        $has_previous = false;
        $has_next = false;
        $prev_url = '';
        $next_url = '';

        if ($page instanceof CanonicalAggregatedContainersPage) {
            $page_num = $page->page();
            $per_page = $page->per_page();
            $total = $page->total();
            $total_pages = $page->total_pages();
            $has_previous = $page->has_previous();
            $has_next = $page->has_next();
            $tz = self::resolve_display_timezone();
            $effective_containers_page = $page_num !== null ? $page_num : $containers_page;

            foreach ($page->items() as $item) {
                $container = $item->container();
                $iso = $container->updated_at_canonical();
                $fk = $item->family_key();
                $items_view[] = [
                    'id' => $container->id(),
                    'title' => $container->title(),
                    'details' => $container->details(),
                    'updated_at_iso' => $iso,
                    'updated_at_display' => self::format_display_datetime($container->updated_at(), $tz),
                    'family_key' => $fk,
                    'family_label' => $item->family_label(),
                    'family_icon_key' => isset($icons_by_key[$fk]) ? (string) $icons_by_key[$fk] : '',
                    'records_url' => AA_Canonical_Shell_Base_Url_Policy::build_records_url(
                        $fk,
                        $container->id(),
                        null,
                        $effective_containers_page > 1 ? $effective_containers_page : null,
                        AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL
                    ),
                ];
            }

            if ($has_previous) {
                $prev_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url($page_num - 1);
            }
            if ($has_next) {
                $next_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url($page_num + 1);
            }
        }

        return [
            'shell_view' => self::SHELL_VIEW_CONTAINERS,
            'lists_scope' => AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL,
            'read_state' => $state,
            'family_label' => 'Todas las listas',
            'qualified_key' => '',
            'is_preview' => false,
            'preview_banner' => null,
            'preview_enabled' => self::is_preview_enabled(),
            'preview_url' => self::is_preview_enabled()
                ? AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null)
                : null,
            'available_families' => $available_families,
            'items_view' => $items_view,
            'page' => $page_num,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_previous' => $has_previous,
            'has_next' => $has_next,
            'prev_url' => $prev_url,
            'next_url' => $next_url,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_containers_view_data(
        CanonicalShellReadResult $result,
        bool $is_preview,
        int $containers_page,
        ?string $lists_scope
    ): array {
        $manifest = $result->manifest();
        $state = $result->state();
        $page = $result->page();

        if ($state === CanonicalShellReadResult::STATE_CONTRACT_ERROR && function_exists('status_header')) {
            status_header(500);
        }

        $preview_enabled = self::is_preview_enabled();
        $preview_url = $preview_enabled
            ? AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null)
            : null;

        $items_view = [];
        $page_num = null;
        $per_page = null;
        $total = null;
        $total_pages = null;
        $has_previous = false;
        $has_next = false;
        $prev_url = '';
        $next_url = '';

        if ($page instanceof CanonicalPage) {
            $page_num = $page->page();
            $per_page = $page->per_page();
            $total = $page->total();
            $total_pages = $page->total_pages();
            $has_previous = $page->has_previous();
            $has_next = $page->has_next();
            $tz = self::resolve_display_timezone();
            $effective_containers_page = $page_num !== null ? $page_num : $containers_page;

            foreach ($page->items() as $container) {
                $iso = $container->updated_at_canonical();
                $items_view[] = [
                    'id' => $container->id(),
                    'title' => $container->title(),
                    'details' => $container->details(),
                    'updated_at_iso' => $iso,
                    'updated_at_display' => self::format_display_datetime($container->updated_at(), $tz),
                    'family_key' => $manifest->identity()->family_key(),
                    'family_label' => $manifest->family_label(),
                    'family_icon_key' => $manifest->family()->icon_key(),
                    'records_url' => self::build_records_nav_url(
                        $manifest,
                        $is_preview,
                        $container->id(),
                        null,
                        $effective_containers_page,
                        null
                    ),
                ];
            }

            if ($has_previous) {
                $prev_url = self::build_containers_nav_url($manifest, $is_preview, $page_num - 1);
            }
            if ($has_next) {
                $next_url = self::build_containers_nav_url($manifest, $is_preview, $page_num + 1);
            }
        }

        return [
            'shell_view' => self::SHELL_VIEW_CONTAINERS,
            'lists_scope' => null,
            'read_state' => $state,
            'family_label' => $manifest->family_label(),
            'qualified_key' => $manifest->qualified_key(),
            'is_preview' => $is_preview,
            'preview_banner' => $is_preview
                ? 'Demostración del shell — datos temporales que no pertenecen a ninguna familia del producto.'
                : null,
            'preview_enabled' => $preview_enabled,
            'preview_url' => $preview_url,
            'available_families' => [],
            'items_view' => $items_view,
            'page' => $page_num,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_previous' => $has_previous,
            'has_next' => $has_next,
            'prev_url' => $prev_url,
            'next_url' => $next_url,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_records_view_data(
        CanonicalShellRecordsReadResult $result,
        bool $is_preview,
        int $container_id,
        int $containers_page,
        ?string $lists_scope,
        string $records_view = 'pending'
    ): array {
        $manifest = $result->manifest();
        $state = $result->state();
        $records_page = $result->page();
        $container = $result->container();

        if ($state === CanonicalShellRecordsReadResult::STATE_CONTRACT_ERROR && function_exists('status_header')) {
            status_header(500);
        }
        if (
            $state === CanonicalShellRecordsReadResult::STATE_CONTAINER_NOT_FOUND
            && function_exists('status_header')
        ) {
            status_header(404);
        }

        $preview_enabled = self::is_preview_enabled();
        $preview_url = $preview_enabled
            ? AA_Canonical_Shell_Base_Url_Policy::build_preview_url(null)
            : null;

        $back_url = self::build_containers_return_url(
            $manifest,
            $is_preview,
            $containers_page,
            $lists_scope
        );

        $parent_view = null;
        $items_view = [];
        $page_num = null;
        $per_page = null;
        $total = null;
        $total_pages = null;
        $has_previous = false;
        $has_next = false;
        $prev_url = '';
        $next_url = '';

        if ($container instanceof AA_Canonical_Container) {
            $tz = self::resolve_display_timezone();
            $parent_view = [
                'id' => $container->id(),
                'title' => $container->title(),
                'details' => $container->details(),
                'updated_at_iso' => $container->updated_at_canonical(),
                'updated_at_display' => self::format_display_datetime($container->updated_at(), $tz),
            ];
        }

        if ($records_page instanceof CanonicalRecordsPage) {
            $page_num = $records_page->page();
            $per_page = $records_page->per_page();
            $total = $records_page->total();
            $total_pages = $records_page->total_pages();
            $has_previous = $records_page->has_previous();
            $has_next = $records_page->has_next();
            $tz = self::resolve_display_timezone();

            foreach ($records_page->items() as $record) {
                $items_view[] = [
                    'id' => $record->id(),
                    'title' => $record->title(),
                    'details' => $record->details(),
                    'updated_at_iso' => $record->updated_at_canonical(),
                    'updated_at_display' => self::format_display_datetime($record->updated_at(), $tz),
                ];
            }

            if ($has_previous) {
                $prev_url = self::build_records_nav_url(
                    $manifest,
                    $is_preview,
                    $container_id,
                    $page_num - 1,
                    $containers_page,
                    $lists_scope,
                    $records_view
                );
            }
            if ($has_next) {
                $next_url = self::build_records_nav_url(
                    $manifest,
                    $is_preview,
                    $container_id,
                    $page_num + 1,
                    $containers_page,
                    $lists_scope,
                    $records_view
                );
            }
        }

        $capability_contributions = [];
        if (!$is_preview && $container_id >= 1) {
            $enriched = self::enrich_records_with_capabilities(
                $manifest->identity()->family_key(),
                $container_id,
                $items_view
            );
            $items_view = $enriched['items_view'];
            $capability_contributions = $enriched['capability_contributions'];
        }

        return [
            'shell_view' => self::SHELL_VIEW_RECORDS,
            'lists_scope' => $lists_scope === AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL
                ? AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL
                : null,
            'read_state' => $state,
            'family_label' => $manifest->family_label(),
            'family_icon_key' => $manifest->family()->icon_key(),
            'qualified_key' => $manifest->qualified_key(),
            'is_preview' => $is_preview,
            'preview_banner' => $is_preview
                ? 'Demostración del shell — datos temporales que no pertenecen a ninguna familia del producto.'
                : null,
            'preview_enabled' => $preview_enabled,
            'preview_url' => $preview_url,
            'container_id' => $container_id,
            'containers_page' => $containers_page,
            'container' => $parent_view,
            'back_url' => $back_url,
            'items_view' => $items_view,
            'capability_contributions' => $capability_contributions,
            'records_view' => $records_view,
            'pending_view_url' => !$is_preview
                ? AA_Canonical_Shell_Base_Url_Policy::build_records_url($manifest->identity()->family_key(), $container_id, null, $containers_page > 1 ? $containers_page : null, $lists_scope, null)
                : '',
            'completed_view_url' => !$is_preview && $manifest->identity()->family_key() === 'action'
                ? AA_Canonical_Shell_Base_Url_Policy::build_records_url($manifest->identity()->family_key(), $container_id, null, $containers_page > 1 ? $containers_page : null, $lists_scope, 'completed')
                : '',
            'page' => $page_num,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_previous' => $has_previous,
            'has_next' => $has_next,
            'prev_url' => $prev_url,
            'next_url' => $next_url,
        ];
    }

    /**
     * @param list<array<string,mixed>> $items_view
     * @return array{
     *   items_view: list<array<string,mixed>>,
     *   capability_contributions: array<string, array{offered:bool,list_sum?:array{status:string,value?:string}}>
     * }
     */
    private static function enrich_records_with_capabilities(
        string $family_key,
        int $container_id,
        array $items_view
    ): array {
        $registry = AA_Canonical_Capability_Page_Contributor_Bootstrap::bootstrap();
        $enricher = new CanonicalCapabilityShellRecordsEnricher($registry);

        return $enricher->enrich($family_key, $container_id, $items_view);
    }

    private static function build_containers_nav_url(
        CanonicalShellManifest $manifest,
        bool $is_preview,
        int $page
    ): string {
        if ($is_preview) {
            return AA_Canonical_Shell_Base_Url_Policy::build_preview_url($page > 1 ? $page : null);
        }

        return AA_Canonical_Shell_Base_Url_Policy::build_url(
            $manifest->identity()->family_key(),
            $page > 1 ? $page : null
        );
    }

    private static function build_containers_return_url(
        CanonicalShellManifest $manifest,
        bool $is_preview,
        int $page,
        ?string $lists_scope
    ): string {
        if ($is_preview) {
            return AA_Canonical_Shell_Base_Url_Policy::build_preview_url($page > 1 ? $page : null);
        }
        if ($lists_scope === AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL) {
            return AA_Canonical_Shell_Base_Url_Policy::build_module_url($page > 1 ? $page : null);
        }

        return AA_Canonical_Shell_Base_Url_Policy::build_url(
            $manifest->identity()->family_key(),
            $page > 1 ? $page : null
        );
    }

    private static function build_records_nav_url(
        CanonicalShellManifest $manifest,
        bool $is_preview,
        int $container_id,
        ?int $page,
        int $containers_page,
        ?string $lists_scope = null,
        string $records_view = 'pending'
    ): string {
        $page_arg = ($page !== null && $page > 1) ? $page : null;
        $containers_arg = $containers_page > 1 ? $containers_page : null;
        $scope_arg = ($lists_scope === AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL)
            ? AA_Canonical_Shell_Base_Url_Policy::LISTS_SCOPE_ALL
            : null;

        if ($is_preview) {
            return AA_Canonical_Shell_Base_Url_Policy::build_preview_records_url(
                $container_id,
                $page_arg,
                $containers_arg
            );
        }

        return AA_Canonical_Shell_Base_Url_Policy::build_records_url(
            $manifest->identity()->family_key(),
            $container_id,
            $page_arg,
            $containers_arg,
            $scope_arg
            , $records_view === 'completed' ? 'completed' : null
        );
    }

    private static function resolve_display_timezone(): \DateTimeZone {
        if (function_exists('get_option')) {
            $option = get_option('aa_timezone');
            if (is_string($option) && $option !== '') {
                try {
                    return new \DateTimeZone($option);
                } catch (\Exception $e) {
                    // Fall through to WordPress timezone.
                }
            }
        }

        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new \DateTimeZone('UTC');
    }

    private static function format_display_datetime(
        \DateTimeImmutable $utc,
        \DateTimeZone $tz
    ): string {
        $timestamp = $utc->getTimestamp();
        if (function_exists('wp_date')) {
            return (string) wp_date('j M Y, H:i', $timestamp, $tz);
        }

        return $utc->setTimezone($tz)->format('j M Y, H:i');
    }
}
