<?php

function handleBadges($method, $uri, $conn) {
    $action = $uri[1] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "POST":
            if ($action === "add") {
                addBadge($conn, $input);
            } elseif ($action === "id") { 
                getBadgesByUser($conn, $input);
            } else {
                sendResponse(["error" => "Action not found"], 404);
            }
            break;

        default:
            sendResponse(["error" => "Method not allowed"], 405);
    }
}

function getBadgesByUser($conn, $input) {
    $userId = $input['userId'] ?? null;

    if (!$userId) {
        sendResponse(["error" => "userId is required"], 400);
    }

    $stmt = $conn->prepare("SELECT * FROM badges WHERE studentId = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    sendResponse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function addBadge($conn, $input) {
    $studentId = $input['studentId'] ?? null;
    $badgeData = $input['data'] ?? null;

    if (!$studentId || !$badgeData) {
        sendResponse(["error" => "Missing studentId or badge data"], 400);
    }

    $newBadgeId = bin2hex(random_bytes(18));
    $type = $badgeData['type'] ?? 'achievement';

    $stmt = $conn->prepare("INSERT INTO badges (id, type, name, icon, studentId, clubId) VALUES (?, ?, ?, ?, ?, ?)");
    
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
        sendResponse(["status" => "success", "id" => $newBadgeId], 201);
    } else {
        sendResponse(["error" => "Database execution failed"], 500);
    }
}