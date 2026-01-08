<?php
$host = "";
$db   = "";
$user = "";
$pass = "";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    
    echo json_encode([
        "error" => "Database connection failed",
    ]);
    exit;
}