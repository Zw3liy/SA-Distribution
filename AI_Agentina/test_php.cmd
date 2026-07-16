@echo off
cd /d "%~dp0"
php.exe -r "var_dump(is_dir('.')); var_dump(scandir('.')); var_dump(file_exists('includes/init.php')); var_dump(is_file('includes/init.php')); var_dump(realpath('includes/init.php'));"
