<?php
/** Descriptor serializable de una acción de card aportada por una capability. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityCardAction {
    private $capability_key;
    private $action_key;
    private $label;
    private $payload;

    public function __construct(string $capability_key, string $action_key, string $label, array $payload) {
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        $this->action_key = AA_Canonical_Key::assert_valid($action_key, 'capability_action_key');
        $this->label = trim($label);
        if ($this->label === '') {
            throw new \InvalidArgumentException('[invalid_capability_card_action_label] Label must not be empty.');
        }
        foreach ($payload as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key) || !is_scalar($value)) {
                throw new \InvalidArgumentException('[invalid_capability_card_action_payload] Payload must be a flat scalar map.');
            }
        }
        $this->payload = $payload;
    }

    /** @return array{capability_key:string,action_key:string,label:string,payload:array<string,scalar>} */
    public function to_array(): array {
        return [
            'capability_key' => $this->capability_key,
            'action_key' => $this->action_key,
            'label' => $this->label,
            'payload' => $this->payload,
        ];
    }
}
