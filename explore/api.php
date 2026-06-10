<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

chdir(dirname(__DIR__));
require_once 'includes/application_core.php';
require_once 'explore/storage_browser.php';

explore_start_app_session();

if (!explore_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Nao autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = isset($_GET['path']) ? (string) $_GET['path'] : '';
$path = explore_sanitize_path($path);

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(1, min(500, $limit));

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$token = trim($token);

$data = explore_list_r2($path, $limit, $token);

if (isset($data['error'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => $data['error'], 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
