<?php
/**
 * Card SSR read-only para registro hijo de expediente.
 *
 * Requiere: $aa_record (array con id,title,body,recorded_at,created_at,updated_at).
 */

defined('ABSPATH') or die('No direct access');

$aa_record = isset($aa_record) && is_array($aa_record) ? $aa_record : [];
$aa_record_id = (int) ($aa_record['id'] ?? 0);
$aa_record_title = trim((string) ($aa_record['title'] ?? ''));
if ($aa_record_title === '') {
    $aa_record_title = 'Sin título';
}
$aa_record_body = (string) ($aa_record['body'] ?? '');
$aa_record_recorded_raw = (string) ($aa_record['recorded_at'] ?? '');
$aa_record_datetime_attr = '';
$aa_record_date_display = '—';

if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $aa_record_recorded_raw, $aa_match)) {
    $aa_record_months_es = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $aa_month_index = (int) $aa_match[2] - 1;
    $aa_month_label = $aa_record_months_es[$aa_month_index] ?? null;
    if ($aa_month_label !== null) {
        $aa_record_date_display = (int) $aa_match[3] . '/' . $aa_month_label . '/' . $aa_match[1];
    } else {
        $aa_record_date_display = $aa_record_recorded_raw;
    }

    $aa_hour = isset($aa_match[4]) && $aa_match[4] !== '' ? $aa_match[4] : '00';
    $aa_minute = isset($aa_match[5]) && $aa_match[5] !== '' ? $aa_match[5] : '00';
    $aa_second = isset($aa_match[6]) && $aa_match[6] !== '' ? $aa_match[6] : '00';
    $aa_record_datetime_attr = $aa_match[1] . '-' . $aa_match[2] . '-' . $aa_match[3]
        . 'T' . $aa_hour . ':' . $aa_minute . ':' . $aa_second;
} elseif ($aa_record_recorded_raw !== '') {
    $aa_record_date_display = $aa_record_recorded_raw;
}
?>
<details class="aa-expediente-registro" <?php if ($aa_record_id > 0) : ?>data-registro-id="<?php echo esc_attr((string) $aa_record_id); ?>"<?php endif; ?>>
    <summary class="aa-expediente-registro-summary">
        <div class="aa-expediente-registro-summary-main">
            <span class="aa-expediente-registro-title"><?php echo esc_html($aa_record_title); ?></span>
            <div class="aa-expediente-registro-meta">
                <span class="aa-expediente-registro-folio">Folio #<?php echo esc_html((string) $aa_record_id); ?></span>
                <time
                    class="aa-expediente-registro-date"
                    <?php if ($aa_record_datetime_attr !== '') : ?>datetime="<?php echo esc_attr($aa_record_datetime_attr); ?>"<?php endif; ?>
                ><?php echo esc_html($aa_record_date_display); ?></time>
            </div>
        </div>
    </summary>
    <div class="aa-expediente-registro-panel">
        <div class="aa-expediente-registro-body"><?php echo esc_html($aa_record_body); ?></div>
    </div>
</details>
