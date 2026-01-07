<?php

function handleNotifications($method, $uri, $conn) {
    $receiverId = $uri[1] ?? null;
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($method) {
        case "GET":
            if ($receiverId === "sender" && isset($uri[2])) return getSenderInfo($conn, $uri[2]);
            return getNotifications($conn, $receiverId);

        case "POST":
            return createNotifications($conn, $input);

        case "PUT":
            return updateNotification($conn, $receiverId, $uri[2] ?? null, $input);

        case "DELETE":
            return deleteNotifications($conn, $receiverId, $uri[2] ?? null);

        default:
            sendNotifResponse(["error" => "Method not allowed"], 405);
    }
}


function getSenderInfo($conn, $senderId) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("s", $senderId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && $user['role'] === 'admin') {
        sendNotifResponse(["name" => "ADMINISTRATION"]);
    }

    $stmt = $conn->prepare("SELECT name FROM clubs WHERE managerId = ?");
    $stmt->bind_param("s", $senderId);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc();

    $displayName = $club ? $club['name'] : "System";
    sendNotifResponse(["name" => $displayName]);
}

function getNotifications($conn, $receiverId) {
    if (!$receiverId) sendNotifResponse([]);

    $stmt = $conn->prepare("SELECT * FROM notifications WHERE receiverId = ? ORDER BY date DESC");
    $stmt->bind_param("s", $receiverId);
    $stmt->execute();
    
    sendNotifResponse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function createNotifications($conn, $input) {
    $senderId  = $input['senderId'] ?? null;
    $receivers = $input['receiverId'] ?? null;
    $message   = $input['message'] ?? '';
    $type      = $input['type'] ?? 'info';

    if (!$senderId || !$receivers || !$message) {
        sendNotifResponse(["error" => "Missing required notification fields"], 400);
    }

    $receiverList = is_array($receivers) ? $receivers : [$receivers];
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO notifications (id, type, senderId, receiverId, message) VALUES (?, ?, ?, ?, ?)");

        foreach ($receiverList as $rId) {
            $id = "n_" . bin2hex(random_bytes(8));
            $stmt->bind_param("sssss", $id, $type, $senderId, $rId, $message);
            $stmt->execute();
        }

        $conn->commit();
        sendNotifResponse(["message" => "Broadcast successful", "count" => count($receiverList)], 201);
    } catch (Exception $e) {
        $conn->rollback();
        sendNotifResponse(["error" => "Broadcast failed", "details" => $e->getMessage()], 500);
    }
}

function updateNotification($conn, $receiverId, $notifId, $input) {
    if (!$notifId || !$receiverId) sendNotifResponse(["error" => "IDs required"], 400);

    $type = $input['type'] ?? 'info';
    $stmt = $conn->prepare("UPDATE notifications SET type = ? WHERE id = ? AND receiverId = ?");
    $stmt->bind_param("sss", $type, $notifId, $receiverId);
    
    $stmt->execute() ? sendNotifResponse(["message" => "Updated"]) : sendNotifResponse(["error" => "Failed"], 500);
}

function deleteNotifications($conn, $receiverId, $notifId = null) {
    if (!$receiverId) sendNotifResponse(["error" => "Receiver ID required"], 400);

    if ($notifId) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND receiverId = ?");
        $stmt->bind_param("ss", $notifId, $receiverId);
    } else {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE receiverId = ?");
        $stmt->bind_param("s", $receiverId);
    }

    $stmt->execute() ? sendNotifResponse(["message" => "Cleared"]) : sendNotifResponse(["error" => "Delete failed"], 500);
}


function sendNotifResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}