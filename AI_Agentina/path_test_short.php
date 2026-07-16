<?php
$paths = [
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution\\includes',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution\\includes\\init.php',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SADIST~1\\INCLUD~1.PHP',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SADIST~1\\includes\\init.php',
    'C:\\Users\\HP\\OneDrive\\Desktop\\Zwelithini\\SA Distribution\\..\\SADIST~1\\includes\\init.php',
];
foreach ($paths as $path) {
    echo "PATH=[$path]\n";
    echo "  is_dir=" . (is_dir($path) ? 'Y' : 'N') . "\n";
    echo "  exists=" . (file_exists($path) ? 'Y' : 'N') . "\n";
    echo "  is_file=" . (is_file($path) ? 'Y' : 'N') . "\n";
    echo "  realpath=" . (realpath($path) ?: 'FALSE') . "\n";
}
