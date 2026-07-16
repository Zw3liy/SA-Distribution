<?php

$base = __DIR__;
$paths = [
    $base,
    $base . '/includes',
    $base . '/includes/init.php',
    str_replace('/', '\\', $base),
    str_replace('/', '\\', $base) . '\\includes',
    str_replace('/', '\\', $base) . '\\includes\\init.php',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution\\includes',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution\\includes\\init.php',
    'C:/Users/HP/OneDrive/Desktop/Zwelithini/SA Distribution',
    'C:/Users/HP/OneDrive/Desktop/Zwelithini/SA Distribution/includes',
    'C:/Users/HP/OneDrive/Desktop/Zwelithini/SA Distribution/includes/init.php',
];

foreach ($paths as $path) {
    echo "PATH=[$path]\n";
    echo "  is_dir=" . (is_dir($path) ? 'Y' : 'N') . "\n";
    echo "  is_file=" . (is_file($path) ? 'Y' : 'N') . "\n";
    echo "  exists=" . (file_exists($path) ? 'Y' : 'N') . "\n";
    echo "  realpath=" . (realpath($path) ?: 'FALSE') . "\n";
    echo "  stat=";
    if (@stat($path) === false) {
        echo 'FALSE';
    } else {
        echo 'OK';
    }
    echo "\n\n";
}
