<?php
// Coloque como assinatura/debug.php e abra /assinatura/debug.php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP version: " . PHP_VERSION . PHP_EOL;
echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'enabled' : 'MISSING') . PHP_EOL;
echo "Fileinfo extension: " . (extension_loaded('fileinfo') ? 'enabled' : 'MISSING') . PHP_EOL;
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . PHP_EOL;
echo "file_uploads: " . ini_get('file_uploads') . PHP_EOL;
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . PHP_EOL;
echo "post_max_size: " . ini_get('post_max_size') . PHP_EOL;
echo "memory_limit: " . ini_get('memory_limit') . PHP_EOL;
echo "max_execution_time: " . ini_get('max_execution_time') . PHP_EOL;
echo "date.timezone: " . ini_get('date.timezone') . PHP_EOL;
echo PHP_EOL;
echo "disable_functions: " . ini_get('disable_functions') . PHP_EOL;
echo PHP_EOL;
echo "extensions list (partial):" . PHP_EOL;
$ext = get_loaded_extensions();
sort($ext);
echo implode(", ", $ext) . PHP_EOL;