<?php
defined('ABSPATH') or die('No direct access');
final class CanonicalFreeShellPost {
    public static function register(): void { foreach (['save_list','delete_list','save_record','delete_record'] as $op) add_action('admin_post_aa_canonical_free_'.$op, [__CLASS__, $op]); }
    private static function allow(): void { if (!current_user_can('manage_options')) wp_die('Permisos insuficientes.', 'Error', ['response'=>403]); check_admin_referer('aa_canonical_free'); }
    private static function repo(): CanonicalFreeRepository { if (!class_exists('CanonicalFreeRepository')) require_once dirname(__DIR__,2).'/repositories/CanonicalFreeRepository.php'; return new CanonicalFreeRepository(); }
    private static function return_url(?int $container=null): string { $args=['action'=>'aa_iframe_content','module'=>'canonical_shell']; if ($container) {$args['view']='records';$args['container_id']=$container;} return add_query_arg($args,admin_url('admin-post.php')); }
    private static function text(string $key,bool $required=false): ?string { $v=isset($_POST[$key]) ? trim(wp_unslash((string)$_POST[$key])) : ''; if($required&&$v==='') wp_die('El título es obligatorio.','Error',['response'=>400]); return $v===''?null:$v; }
    public static function save_list(): void { self::allow(); $id=isset($_POST['id'])?(int)$_POST['id']:null; $id=$id?:null; self::repo()->save_list($id,(string)self::text('title',true),self::text('details')); wp_safe_redirect(self::return_url()); exit; }
    public static function delete_list(): void { self::allow(); self::repo()->delete_list((int)($_POST['id']??0)); wp_safe_redirect(self::return_url()); exit; }
    public static function save_record(): void { self::allow(); $container=(int)($_POST['container_id']??0); $id=isset($_POST['id'])?(int)$_POST['id']:null; self::repo()->save_record($id?:null,$container,(string)self::text('title',true),self::text('details')); wp_safe_redirect(self::return_url($container)); exit; }
    public static function delete_record(): void { self::allow(); $container=(int)($_POST['container_id']??0); self::repo()->delete_record((int)($_POST['id']??0),$container); wp_safe_redirect(self::return_url($container)); exit; }
}
