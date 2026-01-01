<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers for JSON and CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") exit(0);

// Include database and controllers
require_once "config/db.php";
require_once "controllers/UsersController.php";
require_once "controllers/ClubsController.php";
require_once "controllers/ActivitiesController.php";
require_once "controllers/RegistrationsController.php";
require_once "controllers/NotificationsController.php";
require_once "controllers/ArchiveController.php";
require_once "controllers/BadgesController.php";

$method = $_SERVER['REQUEST_METHOD'];
$uri = explode("/", trim($_SERVER['REQUEST_URI'], "/"));
$uri = array_values(array_filter($uri));

$apiIndex = array_search('api', $uri);

if ($apiIndex === false) {
    http_response_code(404);
    echo json_encode(["error" => "API endpoint not found"]);
    exit;
}

$uri = array_slice($uri, $apiIndex + 1);
$resource = $uri[0] ?? null;

switch ($resource) {
    case "users":
        handleUsers($method, $uri, $conn);
        break;
    case "clubs":
        handleClubs($method, $uri, $conn);
        break;
    case "activities":
        handleActivities($method, $uri, $conn);
        break;
    case "registrations":
        handleRegistrations($method, $uri, $conn);
        break;
    case "notifications":
        handleNotifications($method, $uri, $conn);
        break;
    case "archive":
        handleArchive($method, $uri, $conn);
        break;
    case "badges":
        handleBadges($method, $uri, $conn);
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Resource not found"]);
}

?>
