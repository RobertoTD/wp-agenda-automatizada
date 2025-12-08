<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Agenda Automatizada</title>
    <!-- Tailwind build will be linked later -->
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'assets/app.css'; ?>" />
</head>

<body class="bg-gray-50 text-gray-900">
    <div id="aa-app" class="min-h-screen flex">
        <aside class="w-64 bg-white shadow-md border-r p-6">
            <h2 class="text-xl font-semibold mb-6">Agenda Automatizada</h2>
            <nav class="space-y-2">
                <button class="block w-full text-left px-3 py-2 rounded-md hover:bg-gray-100">Dashboard</button>
                <button class="block w-full text-left px-3 py-2 rounded-md hover:bg-gray-100">Configuración</button>
                <button class="block w-full text-left px-3 py-2 rounded-md hover:bg-gray-100">Asistente</button>
                <button class="block w-full text-left px-3 py-2 rounded-md hover:bg-gray-100">Reportes</button>
            </nav>
        </aside>

        <main class="flex-1 p-8 overflow-auto">
            <h1 class="text-2xl font-bold mb-6">Panel Administrativo</h1>
            <div class="bg-white shadow rounded-xl p-6">
                <p>Interfaz base del nuevo panel dentro del iframe.</p>
            </div>
        </main>
    </div>

    <script src="<?php echo plugin_dir_url(__FILE__) . 'assets/app.js'; ?>"></script>
</body>
</html>
