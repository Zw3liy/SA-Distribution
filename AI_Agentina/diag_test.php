<?php
$dir = __DIR__;
echo "DIR=" . $dir . "
";
$path = $dir . '/includes/init.php';
echo "PATH=" . $path . "
";
echo "DIR_BYTES=" . implode(',', array_map('ord', str_split($dir))) . "
";
echo "PATH_BYTES=" . implode(',', array_map('ord', str_split($path))) . "
";
var_dump(file_exists($path));
var_dump(is_readable($path));
var_dump(realpath($path));
