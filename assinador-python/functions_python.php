<?php
// functions_python.php - Funções de integração com Python

function loadAssinadorPythonEnv() {
    static $env = null;

    if ($env !== null) {
        return $env;
    }

    $env = [];
    $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath)) {
        return $env;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }

        if (($commentPos = strpos($value, ' #')) !== false) {
            $value = substr($value, 0, $commentPos);
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");
        $env[$key] = $value;
    }

    return $env;
}

function getAssinadorPythonSetting($key, $default = null) {
    $env = loadAssinadorPythonEnv();

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $env[$key] ?? $default;
}

function getPythonServiceBaseUrl($public = false) {
    if ($public) {
        return rtrim(
            getAssinadorPythonSetting(
                'PYTHON_SERVICE_PUBLIC_URL',
                getAssinadorPythonSetting('PYTHON_SERVICE_INTERNAL_URL', 'http://127.0.0.1:5000')
            ),
            '/'
        );
    }

    return rtrim(
        getAssinadorPythonSetting('PYTHON_SERVICE_INTERNAL_URL', 'http://127.0.0.1:5000'),
        '/'
    );
}

function getAssinadorPdo() {
    $host = getAssinadorPythonSetting('DB_HOST', '127.0.0.1');
    $port = getAssinadorPythonSetting('DB_PORT', '3306');
    $database = getAssinadorPythonSetting('DB_NAME', 'sisged_gea');
    $user = getAssinadorPythonSetting('DB_USER', 'root');
    $password = getAssinadorPythonSetting('DB_PASSWORD', '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function normalizarPosicaoAssinatura($posicao = []) {
    return [
        'x' => (float) ($posicao['x'] ?? 20),
        'y' => (float) ($posicao['y'] ?? 50),
        'pagina' => (int) ($posicao['pagina'] ?? $posicao['page'] ?? 1),
        'width' => (float) ($posicao['width'] ?? $posicao['largura'] ?? 80),
        'height' => (float) ($posicao['height'] ?? $posicao['altura'] ?? 30)
    ];
}

function obterPythonExecutavel() {
    $candidatos = [];

    if (!empty(getenv('PYTHON_BIN'))) {
        $candidatos[] = getenv('PYTHON_BIN');
    }

    $baseDir = __DIR__;
    $candidatos[] = $baseDir . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $candidatos[] = $baseDir . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
    $candidatos[] = 'python';

    foreach ($candidatos as $candidato) {
        if ($candidato === 'python' || is_file($candidato)) {
            return $candidato;
        }
    }

    return 'python';
}

/**
 * Chama o serviço Python para assinar um documento
 * 
 * @param string $token Token de sessão já validado
 * @param int $certificadoId ID do certificado no banco
 * @param string $senha Senha do certificado
 * @param array $posicao Posição da assinatura [x, y, pagina, largura, altura]
 * @return array Resultado da operação
 */
function assinarComPython($token, $certificadoId, $senha, $posicao = []) {
    $pythonApiUrl = getPythonServiceBaseUrl(false) . '/api/sign';

    // Preparar dados para envio
    $dados = [
        'token' => $token,
        'certificado_id' => $certificadoId,
        'senha' => $senha,
        'posicao' => normalizarPosicaoAssinatura($posicao)
    ];
    
    // Chamar API Python via cURL
    $ch = curl_init($pythonApiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout de 60 segundos
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    
    if ($erro) {
        return ['success' => false, 'message' => "Erro cURL: $erro"];
    }
    
    if ($httpCode != 200) {
        return ['success' => false, 'message' => "HTTP Error: $httpCode"];
    }
    
    return json_decode($resposta, true);
}

/**
 * Chama o validador Python
 * 
 * @param string $pdfPath Caminho do PDF assinado
 * @return array Resultado da validação
 */
function validarComPython($pdfPath) {
    $pythonApiUrl = getPythonServiceBaseUrl(false) . '/validate/api/validate';
    
    $dados = ['pdf_path' => $pdfPath];
    
    $ch = curl_init($pythonApiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $resposta = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($resposta, true);
}

/**
 * Verifica se o serviço Python está ativo
 */
function pythonServiceStatus() {
    $ch = curl_init(getPythonServiceBaseUrl(false) . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

/**
 * Executa assinatura via CLI quando o serviço Flask não está disponível.
 *
 * @param string $pdfPath Caminho completo do PDF
 * @param string $certPath Caminho completo do certificado
 * @param string $senha Senha do certificado
 * @param array $posicao Posição da assinatura
 * @return array Resultado da operação
 */
function assinarComPythonCLI($pdfPath, $certPath, $senha, $posicao = []) {
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'cli_sign.py';

    if (!is_file($scriptPath)) {
        return ['success' => false, 'message' => 'Script CLI de assinatura não encontrado'];
    }

    $outputPath = dirname($pdfPath) . DIRECTORY_SEPARATOR . 'assinado_' . date('Ymd_His') . '.pdf';
    $pythonBin = obterPythonExecutavel();
    $positionJson = json_encode(
        normalizarPosicaoAssinatura($posicao),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    $commandParts = [
        escapeshellarg($pythonBin),
        escapeshellarg($scriptPath),
        '--pdf', escapeshellarg($pdfPath),
        '--cert', escapeshellarg($certPath),
        '--password', escapeshellarg($senha),
        '--output', escapeshellarg($outputPath),
        '--position', escapeshellarg($positionJson)
    ];

    $output = [];
    $exitCode = 1;
    exec(implode(' ', $commandParts) . ' 2>&1', $output, $exitCode);

    $rawOutput = trim(implode("\n", $output));
    $result = json_decode($rawOutput, true);

    if (is_array($result)) {
        return $result;
    }

    return [
        'success' => false,
        'message' => $rawOutput !== '' ? $rawOutput : 'Falha ao executar assinatura via CLI',
        'exit_code' => $exitCode
    ];
}