<?php
/**
 * Componentes UI reutilizables para el panel admin de Agenda Automatizada
 * Estos componentes permiten construir interfaces modernas con menos HTML repetido.
 */

/**
 * Renderiza un card contenedor
 */
function aa_card_start($title = '', $description = '') {
    echo '<div class="aa-card p-6 rounded-xl bg-white shadow-sm border border-gray-200 mb-6">';
    
    if ($title || $description) {
        echo '<header class="mb-4">';
        if ($title) echo "<h2 class='text-lg font-semibold mb-1'>$title</h2>";
        if ($description) echo "<p class='text-sm text-gray-600'>$description</p>";
        echo '</header>';
    }

    echo '<div class="aa-card-body">';
}

function aa_card_end() {
    echo '</div></div>';
}

/**
 * Renderiza una sección completa
 */
function aa_section($id, $title, $description = '') {
    echo "<section id='$id' class='aa-section mb-8'>";
    echo "<header class='mb-4'>
            <h2 class='text-xl font-semibold'>$title</h2>";
    if ($description) {
        echo "<p class='text-gray-600 text-sm'>$description</p>";
    }
    echo "</header>";
}

/**
 * Cierra una sección (por si deseas wrappers)
 */
function aa_section_end() {
    echo "</section>";
}

/**
 * Entrada de formulario estandarizada
 */
function aa_input_row($label, $name, $value, $type = 'text') {
    echo "
    <div class='aa-input-row mb-4'>
        <label class='block text-sm font-medium mb-1'>$label</label>
        <input type='$type' name='$name' value='$value'
            class='w-full border border-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500'>
    </div>";
}

/**
 * Botón estándar
 */
function aa_button($label, $type = 'primary') {
    $base = "px-4 py-2 rounded-lg font-medium transition";
    $styles = [
        'primary' => "$base bg-blue-600 text-white hover:bg-blue-700",
        'secondary' => "$base bg-gray-100 hover:bg-gray-200",
        'danger' => "$base bg-red-600 text-white hover:bg-red-700"
    ];

    $class = $styles[$type] ?? $styles['primary'];

    echo "<button class='$class'>$label</button>";
}

?>
