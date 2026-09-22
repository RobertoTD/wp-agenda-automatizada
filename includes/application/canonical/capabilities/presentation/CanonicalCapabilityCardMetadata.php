<?php
/** Descriptor tipado de metadata temporal aportada a una card por una capability. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityCardMetadata {
    private $capability_key;
    private $metadata_key;
    private $label;
    private $datetime_utc;

    public function __construct(
        string $capability_key,
        string $metadata_key,
        string $label,
        \DateTimeImmutable $datetime_utc
    ) {
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        $this->metadata_key = AA_Canonical_Key::assert_valid($metadata_key, 'capability_card_metadata_key');
        $label = trim($label);
        if ($label === '' || strlen($label) > 100) {
            throw new \InvalidArgumentException('[invalid_capability_card_metadata_label] Label must be between 1 and 100 characters.');
        }
        $this->label = $label;
        $this->datetime_utc = $datetime_utc->setTimezone(new \DateTimeZone('UTC'));
    }

    /** @return array{capability_key:string,metadata_key:string,label:string,kind:string,datetime_utc:string} */
    public function to_array(): array {
        return [
            'capability_key' => $this->capability_key,
            'metadata_key' => $this->metadata_key,
            'label' => $this->label,
            'kind' => 'datetime',
            'datetime_utc' => $this->datetime_utc->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }
}
