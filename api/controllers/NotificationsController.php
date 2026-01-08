<?php

function handleNotifications($method, $uri, $conn) {
    $receiverId = $uri[1] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "GET":
            if ($receiverId === "sender" && isset($uri[2])) {
                getSenderInfo($conn, $uri[2]);
            } else {
                getNotifications($conn, $receiverId);
            }
            break;

        case "POST":
            createNotifications($conn, $input);
            break;

        case "DELETE":
            deleteNotifications($conn, $receiverId, $uri[2] ?? null);
            break;

        default:
            sendResponse(["error" => "Method not allowed"], 405);
    }
}

function getSenderInfo($conn, $senderId) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("s", $senderId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && $user['role'] === 'admin') {
        sendResponse(["name" => "ADMINISTRATION"]);
    }

    $stmt = $conn->prepare("SELECT name FROM clubs WHERE managerId = ?");
    $stmt->bind_param("s", $senderId);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc();

    sendResponse(["name" => $club ? $club['name'] : "System"]);
}

function getNotifications($conn, $receiverId) {
    if (!$receiverId) sendResponse([]);

    $stmt = $conn->prepare("SELECT * FROM notifications WHERE receiverId = ? ORDER BY date DESC");
    $stmt->bind_param("s", $receiverId);
    $stmt->execute();
    
    sendResponse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function createNotifications($conn, $input) {
    $senderId = $input['senderId'] ?? null;
    $receivers = $input['receiverId'] ?? null;
    $message = $input['message'] ?? '';
    $type = $input['type'] ?? 'info';

    if (!$senderId || !$receivers || !$message) {
        sendResponse(["error" => "Informations are required"], 400);
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
        sendResponse(["message" => "Broadcast successful", "count" => count($receiverList)], 201);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(["error" => "Broadcast failed"], 500);
    }
}

function deleteNotifications($conn, $receiverId, $notifId = null) {
    if (!$receiverId) sendResponse(["error" => "Receiver ID required"], 400);

    if ($notifId) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND receiverId = ?");
        $stmt->bind_param("ss", $notifId, $receiverId);
    } else {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE receiverId = ?");
        $stmt->bind_param("s", $receiverId);
    }

    $stmt->execute() ? sendResponse(["message" => "Cleared"]) : sendResponse(["error" => "Delete failed"], 500);
}