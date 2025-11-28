const fs = require('fs');
const path = require('path');

// --- CONFIGURACIÓN ---
const outputFileName = 'contexto_plugin.txt';
// Carpetas que SÍ queremos leer (basado en tu arquitectura)
const targetDirs = ['assets', 'includes', 'views', 'templates'];
// Extensiones que nos importan
const allowedExtensions = ['.php', '.js', '.css', '.md'];

// Función para leer archivos recursivamente
function readDir(dir) {
    let results = [];
    const list = fs.readdirSync(dir);

    list.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);

        if (stat && stat.isDirectory()) {
            // Si la carpeta es una de las que queremos o está dentro de ellas
            if (targetDirs.some(target => filePath.includes(target))) {
                results = results.concat(readDir(filePath));
            }
        } else {
            const ext = path.extname(file);
            if (allowedExtensions.includes(ext)) {
                results.push(filePath);
            }
        }
    });
    return results;
}

// Ejecución
console.log('Empaquetando tu plugin...');
// Primero leemos el archivo raíz del plugin
let allFiles = [];
if (fs.existsSync('wp-agenda-automatizada.php')) {
    allFiles.push(path.join(__dirname, 'wp-agenda-automatizada.php'));
}

// Luego leemos las carpetas
targetDirs.forEach(dir => {
    const fullPath = path.join(__dirname, dir);
    if (fs.existsSync(fullPath)) {
        allFiles = allFiles.concat(readDir(fullPath));
    }
});

let outputContent = "ARQUITECTURA DEL PLUGIN WORDPRESS:\n\n";

allFiles.forEach(file => {
    const relativePath = path.relative(__dirname, file);
    const content = fs.readFileSync(file, 'utf8');
    
    // Formato que AI Studio entiende perfecto
    outputContent += `\n========================================\n`;
    outputContent += `FILE: ${relativePath}\n`;
    outputContent += `========================================\n`;
    outputContent += content;
    outputContent += `\n\n`;
});

fs.writeFileSync(outputFileName, outputContent);
console.log(`¡LISTO! Se creó el archivo "${outputFileName}". Sube ESTE archivo a AI Studio.`);