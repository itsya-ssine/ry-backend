<?php
declare(strict_types=1);

require_once __DIR__ . "/helpers/ResponseHelper.php";

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once "config/db.php";

$controllers = [
    'Users', 'Clubs', 'Activities', 'Registrations', 
    'Notifications', 'Archive', 'Badges'
];

foreach ($controllers as $ctrl) {
    require_once "controllers/{$ctrl}Controller.php";
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($path, '/'))));

$apiIndex = array_search('api', $segments);

if ($apiIndex === false) {
    sendResponse(["error" => "API endpoint not found"], 404);
}

$uriParams = array_slice($segments, $apiIndex + 1);
$resource = $uriParams[0] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    "users"         => "handleUsers",
    "clubs"         => "handleClubs",
    "activities"    => "handleActivities",
    "registrations" => "handleRegistrations",
    "notifications" => "handleNotifications",
    "archive"       => "handleArchive",
    "badges"        => "handleBadges",
];

if (isset($routes[$resource])) {
    $handler = $routes[$resource];
    $handler($method, $uriParams, $conn);
} else {
    sendResponse(["error" => "Resource '$resource' not found"], 404);
}