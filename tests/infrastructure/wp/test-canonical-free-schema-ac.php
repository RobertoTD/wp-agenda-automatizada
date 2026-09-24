<?php
define('ABSPATH', __DIR__ . '/');
$root = dirname(__DIR__, 3);
$schema = (string) file_get_contents($root . '/includes/infrastructure/wp/CanonicalSchema.php');
$version = (string) file_get_contents($root . '/includes/infrastructure/wp/Schema.php');
$core = (string) file_get_contents($root . '/includes/application/canonical/core/CanonicalCoreUseCase.php');
function free_schema_assert($label, $condition): void { if (!$condition) { fwrite(STDERR, "[FAIL] {$label}\n"); exit(1); } echo "[ OK ] {$label}\n"; }
$start = strpos($schema, 'private static function install_free_v2(): void');
$end = strpos($schema, '/**', $start + 1);
$free = $start === false || $end === false ? '' : substr($schema, $start, $end - $start);
free_schema_assert('DB_VERSION 40', strpos($version, "DB_VERSION = '40'") !== false);
free_schema_assert('installer libre existe', $free !== '');
free_schema_assert('listas libres sin family_id', strpos($free, 'family_id') === false);
free_schema_assert('registros exigen container_id', strpos($free, 'container_id bigint(20) unsigned NOT NULL') !== false);
free_schema_assert('FK record a lista', strpos($free, "self::records_foreign_key_name()") !== false && strpos($free, "'CASCADE'") !== false);
free_schema_assert('sin tablas de capability', strpos($free, 'capabilit') === false);
free_schema_assert('Application core sin dependencias legacy', strpos($core, 'CanonicalShellManifest') === false && strpos($core, 'family_key') === false && strpos($core, 'CanonicalCapability') === false);
echo "--- Resumen: 7/7 ---\n";
