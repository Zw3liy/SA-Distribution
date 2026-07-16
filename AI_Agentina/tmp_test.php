<?php
$paths = [
    'C:/Windows',
    'C:\\Windows',
    'C:/',
    'C:\\',
    'C:/Users',
    'C:/Users/HP',
    'C:/Users/HP/OneDrive',
    getcwd(),
    __DIR__,
];
foreach ($paths as $path) {
    echo "PATH=[{$path}]\n";
    echo "  is_dir=" . (is_dir($path) ? 'Y' : 'N') . "\n";
    echo "  exists=" . (file_exists($path) ? 'Y' : 'N') . "\n";
    echo "  realpath=" . (realpath($path) ?: 'FALSE') . "\n";
}
