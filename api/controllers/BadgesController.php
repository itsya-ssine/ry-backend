<?php

function handleBadges($method, $uri, $conn) {
    $action = $uri[1] ?? null;
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($method) {
        case "POST":
            if ($action === "get-by-user") return getBadgesByUser($conn, $input);
            if ($action === "add") return addBadge($conn, $input);
            break;

        default:
            sendBadgeResponse(["error" => "Method or action not allowed"], 405);
    }
}


function getBadgesByUser($conn, $input) {
    $userId = $input['userId'] ?? null;

    if (!$userId) {
        return sendBadgeResponse(["error" => "userId is required"], 400);
    }

    $stmt = $conn->prepare("SELECT * FROM badges WHERE studentId = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    sendBadgeResponse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function addBadge($conn, $input) {
    $studentId = $input['studentId'] ?? null;
    $badgeData = $input['data'] ?? null;

    if (!$studentId || !$badgeData) {
        return sendBadgeResponse(["error" => "Missing studentId or badge data"], 400);
    }

    $newBadgeId = bin2hex(random_bytes(18));
    $type = $badgeData['type'] ?? 'achievement';

    $sql = "INSERT INTO badges (id, type, name, icon, studentId, clubId) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param(
        "ssssss", 
        $newBadgeId,
        $type,
        $badgeData['name'], 
        $badgeData['icon'], 
        $studentId, 
        $badgeData['ClubId']
    );

    if ($stmt->execute()) {
        sendBadgeResponse(["status" => "success", "id" => $newBadgeId], 201);
    } else {
        sendBadgeResponse(["error" => "Database execution failed"], 500);
    }
}


function sendBadgeResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}