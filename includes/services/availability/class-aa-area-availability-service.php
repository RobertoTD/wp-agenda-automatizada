<?php
/**
 * SHIM de compatibilidad — DEPRECATED.
 *
 * `AA_Area_Availability_Service` se ha movido a su capa canónica:
 *   includes/domain/availability/class-aa-area-availability-service.php
 *
 * Este archivo se conserva para no romper consumidores legacy que aún
 * hacen `require_once` apuntando a esta ruta. Carga el archivo nuevo y
 * delega.
 *
 * Migración: cuando un consumidor se toque por otra razón, actualizar
 * su `require_once` (o, mejor, reemplazarlo por autoload) para apuntar a
 * `includes/domain/availability/class-aa-area-availability-service.php`
 * y eliminar este shim cuando ningún consumidor lo cargue ya.
 *
 * @deprecated Usar la ruta `includes/domain/availability/`.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Area_Availability_Service')) {
    require_once __DIR__ . '/../../domain/availability/class-aa-area-availability-service.php';
}
