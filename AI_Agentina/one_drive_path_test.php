<?php
$paths = [
    'C:\\Users\\HP\\OneDrive',
    'C:\\Users\\HP\\OneDrive\\Desktop',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution',
    '\\\\?\\C:\\Users\\HP\\OneDrive',
    '\\\\?\\C:\\Users\\HP\\OneDrive\\Desktop',
    '\\\\?\\C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini',
    '\\\\?\\C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution',
    'C:/Users/HP/OneDrive',
    'C:/Users/HP/OneDrive/Desktop',
    'C:/Users/HP/OneDrive/Desktop/Zwelithini',
    'C:/Users/HP/OneDrive/Desktop/Zwelithini/SA Distribution',
];
foreach ($paths as $path) {
    echo "PATH=[{$path}]\n";
    echo "  is_dir=" . (is_dir($path) ? 'Y' : 'N') . "\n";
    echo "  exists=" . (file_exists($path) ? 'Y' : 'N') . "\n";
    echo "  realpath=" . (realpath($path) ?: 'FALSE') . "\n";
}
