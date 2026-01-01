<?php
function handleNotifications($method, $uri, $conn) {
    $receiverId = $uri[1] ?? null;

    if ($method === "GET" && $receiverId === "sender") {
        $senderId = $uri[2];

        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("s", $senderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if ($user && $user['role'] === 'admin') {
            http_response_code(200);
            echo json_encode([
                "name" => "ADMINISTRATION"
            ]);
            return;
        }

        $stmt = $conn->prepare("SELECT name FROM clubs WHERE managerId = ?");
        $stmt->bind_param("s", $senderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $club = $res->fetch_assoc();
        $stmt->close();

        if ($club) {
            http_response_code(200);
            echo json_encode([
                "name" => $club['name']
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode([
            "name" => "System"
        ]);
        return;
    }

    if ($method === "GET") {
        if ($receiverId) {
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE receiverId = ? ORDER BY date DESC");
            $stmt->bind_param("s", $receiverId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            echo json_encode($notifications);
            $stmt->close();
        } else {
            echo json_encode([]);
        }
    }

    elseif ($method === "POST") {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $type = $data['type'] ?? 'info';
        $senderId = $data['senderId'] ?? null;
        $receivers = $data['receiverId'] ?? null;
        $message = $data['message'] ?? '';

        if (!$receivers || !$senderId || !$message) {
            http_response_code(400);
            echo json_encode(["error" => "Informations are required"]);
            return;
        }

        $receiverList = is_array($receivers) ? $receivers : [$receivers];

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO notifications (id, type, senderId, receiverId, message) VALUES (?, ?, ?, ?, ?)");

            foreach ($receiverList as $rId) {
                $newNotifId = uniqid("n_") . bin2hex(random_bytes(5));
                $stmt->bind_param("sssss", $newNotifId, $type, $senderId, $rId, $message);
                $stmt->execute();
            }

            $conn->commit();
            
            http_response_code(201);
            echo json_encode([
                "message" => "Broadcast successful",
                "count" => count($receiverList)
            ]);
            
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode([
                "error" => "Failed to process broadcast",
                "details" => $e->getMessage()
            ]);
        }
    }

    // --- ADDED PUT BLOCK FOR UPDATING STATUS ---
    elseif ($method === "PUT" && $receiverId) {
        $notifId = $uri[2] ?? null;
        
        if (!$notifId) {
            http_response_code(400);
            echo json_encode(["error" => "Notification ID required"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $type = $data['type'] ?? 'info';

        $stmt = $conn->prepare("UPDATE notifications SET type = ? WHERE id = ? AND receiverId = ?");
        $stmt->bind_param("sss", $type, $notifId, $receiverId);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Notification updated"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Update failed"]);
        }
        $stmt->close();
    }
    
    elseif ($method === "DELETE" && $receiverId) {
        $notifId = $uri[2] ?? null;

        if ($notifId) {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND receiverId = ?");
            $stmt->bind_param("ss", $notifId, $receiverId);
        } else {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE receiverId = ?");
            $stmt->bind_param("s", $receiverId);
        }

        if ($stmt->execute()) {
            echo json_encode(["message" => "Notifications cleared"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to delete"]);
        }
        $stmt->close();
    }
}