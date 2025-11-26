<?php
// file_diag.php - diagnóstico de arquivos em /assinatura/certs/
header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'certs';
$target = $dir . DIRECTORY_SEPARATOR . 'test.pfx';

echo "Diretório verificado: $dir\n";
if (!is_dir($dir)) {
    echo "Pasta não existe ou não é acessível pelo PHP. Verifique caminho e permissões.\n";
    exit;
}

echo "Conteúdo do diretório (scandir):\n";
$listing = scandir($dir);
if ($listing === false) {
    echo "scandir falhou. Pode ser permissão negada ou open_basedir ativo.\n";
} else {
    foreach ($listing as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isFile = is_file($path) ? 'file' : (is_dir($path) ? 'dir' : 'other');
        $exists = file_exists($path) ? 'yes' : 'no';
        $read = is_readable($path) ? 'yes' : 'no';
        $write = is_writable($path) ? 'yes' : 'no';
        $size = is_file($path) ? filesize($path) : 0;
        $real = realpath($path) ?: '(realpath failed)';
        $perms = fileperms($path);
        $perms_octal = $perms ? sprintf('%o', $perms) : '0';
        echo "- $item | type=$isFile | exists=$exists | read=$read | write=$write | size=$size | realpath=$real | perms=0$perms_octal\n";
    }
}

echo "\nVerificando o arquivo alvo: $target\n";
echo 'file_exists: ' . (file_exists($target) ? 'yes' : 'no') . "\n";
echo 'is_file: ' . (is_file($target) ? 'yes' : 'no') . "\n";
echo 'is_readable: ' . (is_readable($target) ? 'yes' : 'no') . "\n";
echo 'is_writable: ' . (is_writable($target) ? 'yes' : 'no') . "\n";
echo 'realpath: ' . (realpath($target) ?: '(realpath failed)') . "\n";

$stat = @stat($target);
if ($stat === false) {
    echo "stat: falhou ou arquivo inacessível.\n";
} else {
    echo "stat: \n";
    print_r($stat);
}

echo "\nConfig open_basedir: " . (ini_get('open_basedir') ?: '(nenhum)') . "\n";

echo "\nUsuário do processo PHP (se disponível):\n";
if (function_exists('get_current_user')) {
    echo "get_current_user(): " . get_current_user() . "\n";
}
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $info = posix_getpwuid(posix_geteuid());
    echo "posix euid info: "; print_r($info);
} else {
    echo "Funções posix não disponíveis para recuperar owner.\n";
}

echo "\nPHP SAPI: " . PHP_SAPI . "\n";
echo "\nFim do diagnóstico.\n";
?>