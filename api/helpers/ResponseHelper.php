<?php

header("Access-Control-Allow-Origin: https://ry-frontend.vercel.app");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendResponse(array|bool|null $data, int $code = 200): void {
    if (ob_get_length()) ob_clean();

    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    
    echo json_encode($data ?? []);
    exit;
}

function getRequestInput(): array {
    $json = json_decode(file_get_contents("php://input"), true);
    return array_merge($_POST, $json ?? []);
}