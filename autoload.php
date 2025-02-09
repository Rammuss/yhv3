<?php
spl_autoload_register(function ($class) {
    // Autoload para PhpSpreadsheet
    $prefixPhpSpreadsheet = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDirPhpSpreadsheet = __DIR__ . '/libs/PhpSpreadsheet/src/PhpSpreadsheet/';
    
    // Autoload para psr/simple-cache
    $prefixPsr = 'Psr\\SimpleCache\\';
    $baseDirPsr = __DIR__ . '/psr/simple-cache/src/'; // Ajusta esta ruta

    // Mapeo de prefijos
    $prefixes = [
        $prefixPhpSpreadsheet => $baseDirPhpSpreadsheet,
        $prefixPsr => $baseDirPsr
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});