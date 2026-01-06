<?php
function handleRegistrations($method, $uri, $conn) {
    $id = $uri[1] ?? null;       // for /api/users/login
    $subId = $uri[2] ?? null;    // for /api/registrations/student/{id}

    $data = json_decode(file_get_contents("php://input"), true);
    header("Content-Type: application/json");

    if ($method === "GET" && $id === 'club' && $subId) {
        $sql = "SELECT r.joinedAt, r.status, r.clubId, u.id as userId, u.name, u.email, u.avatar
                FROM registrations r 
                JOIN users u ON r.studentId = u.id 
                WHERE r.clubId = ?";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $subId);
        $stmt->execute();
        $result = $stmt->get_result();

        $registrations = [];

        while ($row = $result->fetch_assoc()) {
            $registrations[] = [
                "id" => uniqid('reg_'),
                "joinedAt" => $row['joinedAt'],
                "status" => $row['status'],
                "clubId" => $row['clubId'],
                "student" => [
                    "id" => $row['userId'],
                    "name" => $row['name'],
                    "email" => $row['email']
                ]
            ];
        }

        echo json_encode($registrations);
        return;
    }

    if ($method === "GET" && $id === 'student' && $subId) {
        $sql = "SELECT r.joinedAt, r.status, r.clubId, u.id as userId, u.name, u.email
                FROM registrations r 
                JOIN users u ON r.studentId = u.id 
                WHERE r.studentId = ?";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $subId);
        $stmt->execute();
        $result = $stmt->get_result();

        $registrations = [];

        while ($row = $result->fetch_assoc()) {
            $registrations[] = [
                "id" => uniqid('reg_'),
                "joinedAt" => $row['joinedAt'],
                "status" => $row['status'],
                "clubId" => $row['clubId'],
                "student" => [
                    "id" => $row['userId'],
                    "name" => $row['name'],
                    "email" => $row['email']
                ]
            ];
        }

        echo json_encode($registrations);
        return;
    }

    if ($method === "GET") {
        $sql = "SELECT r.joinedAt, r.status, r.clubId, u.id as userId, u.name, u.email 
                FROM registrations r 
                JOIN users u ON r.studentId = u.id";
            
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $registrations = [];

        while ($row = $result->fetch_assoc()) {
            $registrations[] = [
                "id" => uniqid('reg_'),
                "joinedAt" => $row['joinedAt'],
                "status" => $row['status'],
                "clubId" => $row['clubId'],
                "student" => [
                    "id" => $row['userId'],
                    "name" => $row['name'],
                    "email" => $row['email']
                ]
            ];
        }

        echo json_encode($registrations);
        return;
    }

    if ($method === "POST") {
        $joinedAt = date("Y-m-d");
        $status = $data['status'] ?? 'pending';
        $sId = $data['studentId'];
        $cId = $data['clubId'];
        
        $stmt = $conn->prepare(
            "INSERT INTO registrations (studentId, clubId, status, joinedAt) VALUES (?,?,?,?)"
        );
        $stmt->bind_param("ssss", $sId, $cId, $status, $joinedAt);
        
        if ($stmt->execute()) {
            echo json_encode([
                "id" => $sId . "-" . $cId, 
                "studentId" => $sId,
                "clubId" => $cId,
                "status" => $status,
                "joinedAt" => $joinedAt
            ]);
        }
        return;
    }

    // Inside your registrations controller/file
    elseif ($method === "PUT") {

        if (!isset($data['studentId'], $data['clubId'], $data['status'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing data"]);
            return;
        }

        $sId = $data['studentId'];
        $cId = $data['clubId'];
        $newStatus = $data['status'];

        $stmt = $conn->prepare(
            "UPDATE registrations SET status = ? WHERE studentId = ? AND clubId = ?"
        );
        
        $stmt->bind_param("sss", $newStatus, $sId, $cId);

        if ($stmt->execute()) {
            echo json_encode([
                "message" => "Registration updated to " . $newStatus,
                "newStatus" => $newStatus
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Database update failed"]);
        }
        return;
    }

    // Inside your registrations handler
    elseif ($method === "DELETE") {
        // Read the JSON body
        $data = json_decode(file_get_contents("php://input"), true);
        
        $sId = $data['studentId'] ?? null;
        $cId = $data['clubId'] ?? null;

        if (!$sId || !$cId) {
            http_response_code(400);
            echo json_encode(["error" => "Missing studentId or clubId"]);
            return;
        }

        $stmt = $conn->prepare("DELETE FROM registrations WHERE studentId = ? AND clubId = ?");
        $stmt->bind_param("ss", $sId, $cId);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Member removed successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Database deletion failed"]);
        }
        return;
    }

    http_response_code(404);
    echo json_encode(["error"=>"Invalid registrations route"]);
}
?>
