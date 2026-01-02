<?php
function handleActivities($method, $uri, $conn) {
    $id = $uri[1] ?? null;       // for /api/users/login
    $subId = $uri[2] ?? null;    // for /api/registrations/student/{id}

    $data = json_decode(file_get_contents("php://input"), true);

    // GET /activities
    if ($method === "GET" && !$id) {
        $res = $conn->query("SELECT * FROM activities");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    }

   // GET /activities/recent
    elseif ($method === "GET" && $id === "recent") {
        $stmt = $conn->prepare("SELECT * FROM activities ORDER BY date LIMIT 8");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        echo json_encode($activities);
    }

    // GET /activities/{id}
    elseif ($method === "GET" && $id) {
        $stmt = $conn->prepare("SELECT * FROM activities WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc());
    }

    // POST /activities
    elseif ($method === "POST" && !$id) {
        $aid = uniqid("a");
        
        $imageName = 'uploads/default-activity.jpg'; 

        try {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $date = $_POST['date'] ?? '';
            $location = $_POST['location'] ?? 'ENSA KHOURIBGA';

            $stmt = $conn->prepare(
                "INSERT INTO activities (id, title, description, date, location, image)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->bind_param(
                "ssssss",
                $aid,
                $title,
                $description,
                $date,
                $location,
                $imageName
            );
            $stmt->execute();
            $stmt->close();

            echo json_encode([
                "message" => "Activity created",
                "activityId" => $aid,
                "imageName" => $imageName
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "error" => "Failed to create activity",
                "details" => $e->getMessage()
            ]);
        }
    }

    elseif ($method === "POST" && $id) {
        $fetchStmt = $conn->prepare("SELECT image FROM activities WHERE id = ?");
        $fetchStmt->bind_param("s", $id);
        $fetchStmt->execute();
        $currentActivity = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();

        if (!$currentActivity) {
            http_response_code(404);
            echo json_encode(["error" => "Activity not found"]);
            return;
        }

        $imagePath = $currentActivity['image'];

        $conn->begin_transaction();

        try {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $location = $_POST['location'] ?? 'ENSA KHOURIBGA';
            $date = $_POST['date'] ?? '';

            $stmt = $conn->prepare(
                "UPDATE activities SET title=?, description=?, location=?, date=?, image=? WHERE id=?"
            );
            
            $stmt->bind_param(
                "ssssss",
                $title,
                $description,
                $location,
                $date,
                $imagePath,
                $id
            );

            if ($stmt->execute()) {
                $conn->commit();
                echo json_encode([
                    "message" => "Activity updated successfully",
                    "id" => $id,
                    "imagePath" => $imagePath
                ]);
            } else {
                throw new Exception("Update failed execution");
            }
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => "Failed to update activity", "details" => $e->getMessage()]);
        }
    }

    // DELETE /activities/{id}
    elseif ($method === "DELETE" && $id) {
        $stmt = $conn->prepare("DELETE FROM activities WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        echo json_encode(["message" => "Activity deleted"]);
    }
}
