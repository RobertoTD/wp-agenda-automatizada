<?php
/**
 * DTO público de imagen canónica confirmada (IMG-3b / IMG-4).
 *
 * Sin storage_path, hash, mime, intents ni permisos de upload.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordImagePublicDto {

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,width:int,height:int,byte_size:int,created_at:string}
     */
    public static function from_row(array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'width' => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'byte_size' => (int) ($row['byte_size'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id:int,width:int,height:int,byte_size:int,created_at:string}>
     */
    public static function list_from_rows(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dto = self::from_row($row);
            if ($dto['id'] < 1) {
                continue;
            }
            $out[] = $dto;
        }

        return $out;
    }
}
