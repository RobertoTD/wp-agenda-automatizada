<?php
if (!defined('ABSPATH')) exit;

function aa_build_availability_url($email) {
    $backend_url = 'http://localhost:3000/calendar/availability';

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $future = new DateTime('+6 months', new DateTimeZone('UTC'));

    $params = [
        'domain'  => $email,
        'timeMin' => $now->format(DateTime::ATOM),
        'timeMax' => $future->format(DateTime::ATOM),
    ];

    return $backend_url . '?' . http_build_query($params);
}

// Inyectar datos al JS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'horariosapartados',
        plugin_dir_url(__FILE__) . 'js/horariosapartados.js',
        ['jquery'],
        '1.0',
        true
    );

    $email = get_option('aa_google_email', '');
    $availability_url = aa_build_availability_url($email);

    wp_localize_script('horariosapartados', 'aa_backend', [
        'url' => $availability_url
    ]);
});
