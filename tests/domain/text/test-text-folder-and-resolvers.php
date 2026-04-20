<?php
/**
 * AC1–AC15: AA_Text_Folder + staff/service/zone resolvers (Paso 1.9).
 *
 * php tests/domain/text/test-text-folder-and-resolvers.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/text/class-aa-text-folder.php';

if (!class_exists('AssignmentsModel', false)) {
    final class AssignmentsModel {
        public static function get_staff($active_only = true): array {
            return [
                ['id' => 5, 'name' => 'Anahí Temoltzin'],
                ['id' => 9, 'name' => 'Adrian Fernandez'],
            ];
        }

        public static function get_services($active_only = true): array {
            return [
                ['id' => 3, 'name' => 'Cejas', 'duration_minutes' => null, 'price' => null],
                ['id' => 7, 'name' => 'Análisis Clínicos', 'duration_minutes' => null, 'price' => null],
            ];
        }

        public static function get_service_areas($active_only = true): array {
            return [
                ['id' => 1, 'name' => 'Consultorio 1', 'description' => null, 'color' => null],
                ['id' => 2, 'name' => 'Consultorio 2', 'description' => null, 'color' => null],
            ];
        }
    }
}

require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-ai-staff-resolver.php';
require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-ai-service-resolver.php';
require_once __DIR__ . '/../../../includes/services/ai/chat/class-aa-ai-zone-resolver.php';

$total  = 0;
$passed = 0;
$failed = [];

function ac(string $id, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$id}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $failed[] = $id;
        echo "[FAIL] {$id}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

$staff_r   = new AA_AI_Staff_Resolver();
$service_r = new AA_AI_Service_Resolver();
$zone_r    = new AA_AI_Zone_Resolver();

// AC1
$r = $staff_r->resolve('Adrián Fernández');
ac('AC1', $r['status'] === 'resolved' && (int) $r['id'] === 9 && $r['name'] === 'Adrian Fernandez', json_encode($r, JSON_UNESCAPED_UNICODE));

// AC2
$r = $staff_r->resolve('adrian fernandez');
ac('AC2', $r['status'] === 'resolved' && (int) $r['id'] === 9, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC3
$r = $staff_r->resolve('ADRIÁN  FERNÁNDEZ');
ac('AC3', $r['status'] === 'resolved' && (int) $r['id'] === 9, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC4
$r = $staff_r->resolve('Anahí');
ac('AC4', $r['status'] === 'resolved' && (int) $r['id'] === 5, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC5
$r = $staff_r->resolve('Anahi');
ac('AC5', $r['status'] === 'resolved' && (int) $r['id'] === 5, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC6
$r = $service_r->resolve('Análisis Clínicos');
ac('AC6', $r['status'] === 'resolved' && (int) $r['id'] === 7, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC7
$r = $service_r->resolve('analisis clinicos');
ac('AC7', $r['status'] === 'resolved' && (int) $r['id'] === 7, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC8
$r = $service_r->resolve('cejas');
ac('AC8', $r['status'] === 'resolved' && (int) $r['id'] === 3, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC9
$r = $zone_r->resolve('CONSULTORIO 1');
ac('AC9', $r['status'] === 'resolved' && (int) $r['id'] === 1, json_encode($r, JSON_UNESCAPED_UNICODE));

// AC10
$r = $zone_r->resolve('consultorio uno');
ac('AC10', $r['status'] === 'no_match', json_encode($r, JSON_UNESCAPED_UNICODE));

// AC11
$r = $staff_r->resolve('Xyz');
ac('AC11', $r['status'] === 'no_match', json_encode($r, JSON_UNESCAPED_UNICODE));

// AC12
$r1 = $staff_r->resolve('');
$r2 = $staff_r->resolve(null);
ac('AC12', $r1['status'] === 'missing' && $r2['status'] === 'missing', json_encode(['empty' => $r1, 'null' => $r2], JSON_UNESCAPED_UNICODE));

// AC13–AC15 text_folder
ac('AC13', AA_Text_Folder::fold(' Adrián Fernández ') === 'adrian fernandez', AA_Text_Folder::fold(' Adrián Fernández '));
ac('AC14', AA_Text_Folder::fold('ÁÉÍÓÚüñ') === 'aeiouun', AA_Text_Folder::fold('ÁÉÍÓÚüñ'));
ac('AC15', AA_Text_Folder::fold(null) === '', '');

echo "\nResultado: {$passed}/{$total}\n";
exit(empty($failed) ? 0 : 1);
