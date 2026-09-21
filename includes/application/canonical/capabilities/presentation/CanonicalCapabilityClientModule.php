<?php
/** Módulo cliente de una capability, declarado en código para el Shell administrativo. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityClientModule {
    private $capability_key;
    private $asset_path;
    private $ajax_action;
    private $nonce_action;

    public function __construct(string $capability_key, string $asset_path, string $ajax_action, string $nonce_action) {
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        if ($asset_path === '' || $ajax_action === '' || $nonce_action === '') {
            throw new \InvalidArgumentException('[invalid_capability_client_module] Client module fields must not be empty.');
        }
        $this->asset_path = $asset_path;
        $this->ajax_action = $ajax_action;
        $this->nonce_action = $nonce_action;
    }

    public function capability_key(): string { return $this->capability_key; }
    public function asset_path(): string { return $this->asset_path; }
    public function ajax_action(): string { return $this->ajax_action; }
    public function nonce_action(): string { return $this->nonce_action; }
}
