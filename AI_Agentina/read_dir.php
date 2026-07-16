<?php
$dir = __DIR__;
echo "DIR=" . $dir . "\n";
$entries = scandir($dir);
if ($entries === false) {
    echo "SCANDIR_FAILED\n";
} else {
    echo "SCANDIR_COUNT=" . count($entries) . "\n";
    foreach ($entries as $entry) {
        echo "ENTRY=[{$entry}]\n";
    }
}
$path = $dir . '/includes/init.php';
echo "PATH=" . $path . "\n";
echo "IS_FILE=" . (is_file($path) ? 'Y' : 'N') . "\n";
echo "FILE_EXISTS=" . (file_exists($path) ? 'Y' : 'N') . "\n";
echo "REALPATH=" . (realpath($path) ?: 'FALSE') . "\n";
