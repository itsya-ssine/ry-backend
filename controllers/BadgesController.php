<?php
function handleBadges($method, $uri, $conn) {
    $id = $uri[1] ?? null;

    $data = json_decode(file_get_contents("php://input"), true);

    if ($method === "POST" && $id === "id") {
        if (!isset($data['userId'])) {
            echo json_encode(["error" => "No userId provided"]);
            return;
        }

        $stmt = $conn->prepare("SELECT * FROM badges WHERE studentId = ?");

        $stmt->bind_param("s", $data['userId']);
        $stmt->execute();
        $res = $stmt->get_result();

        echo json_encode($res->fetch_all(MYSQLI_ASSOC));

        $stmt->close();
        return;
    }

    if ($method === "POST" && $id === "add") {
        $studentId = $data['studentId'] ?? null;
        $badgeData = $data['data'] ?? null;

        if (!$studentId || !$badgeData) {
            http_response_code(400);
            echo json_encode(["error" => "Missing data"]);
            return;
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
            echo json_encode(["status" => "success", "id" => $newBadgeId]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Execute failed: " . $stmt->error]);
        }
        
        $stmt->close();
        return;
    }
}
