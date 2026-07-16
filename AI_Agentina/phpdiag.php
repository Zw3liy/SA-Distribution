<?php

echo "OPEN_BASEDIR=" . ini_get('open_basedir') . "\n";
echo "CWD=" . getcwd() . "\n";
echo "DIR=" . __DIR__ . "\n";
echo "DIR_IS_DIR=" . (is_dir(__DIR__) ? 'Y' : 'N') . "\n";
echo "INCLUDES_IS_DIR=" . (is_dir(__DIR__ . '/includes') ? 'Y' : 'N') . "\n";
$path = __DIR__ . '/includes/init.php';
echo "PATH=" . $path . "\n";
echo "FILE_EXISTS=" . (file_exists($path) ? 'Y' : 'N') . "\n";
echo "IS_READABLE=" . (is_readable($path) ? 'Y' : 'N') . "\n";
echo "REALPATH=" . realpath($path) . "\n";
