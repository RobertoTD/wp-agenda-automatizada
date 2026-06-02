<?php
/**
 * Standalone public maintenance screen.
 *
 * Intentionally does not load the active theme, shortcode UI, admin links or app links.
 */

defined('ABSPATH') or die('No direct access');
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sitio web en preparación</title>
    <style>
        :root {
            color-scheme: light;
            --aa-primary: #8b5cf6;
            --aa-secondary: #6366f1;
            --aa-text: #0f172a;
            --aa-muted: #64748b;
            --aa-muted-light: #94a3b8;
            --aa-surface: #ffffff;
            --aa-bg: #f8fafc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--aa-text);
            background:
                radial-gradient(circle at top left, rgba(139, 92, 246, 0.16), transparent 32rem),
                radial-gradient(circle at bottom right, rgba(99, 102, 241, 0.14), transparent 28rem),
                var(--aa-bg);
        }

        main {
            width: min(100%, 520px);
            padding: 40px 32px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.10);
            text-align: center;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(32px, 8vw, 44px);
            line-height: 1.05;
            letter-spacing: -0.04em;
        }

        p {
            margin: 0;
            color: var(--aa-muted);
            font-size: 17px;
            line-height: 1.65;
        }

        .secondary {
            margin-top: 10px;
            font-size: 15px;
        }

        .provider {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
            color: var(--aa-muted-light);
            font-size: 12px;
            line-height: 1.5;
            letter-spacing: 0.01em;
        }
    </style>
</head>
<body>
    <main aria-labelledby="aa-maintenance-title">
        <h1 id="aa-maintenance-title">Sitio web en preparación</h1>
        <p>Este sitio web estará disponible pronto para que puedas conocer el negocio y agendar citas en línea.</p>
        <p class="secondary">Estamos terminando la configuración para que los clientes puedan reservar de forma automática.</p>
        <p class="provider">Provisto por DEOIA Citas</p>
    </main>
</body>
</html>
