<?php
function handleClubs($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    $subId = $uri[2] ?? null;

    $data = json_decode(file_get_contents("php://input"), true);

    // GET /clubs
    if ($method === "GET" && !$id) {
        $res = $conn->query("SELECT * FROM clubs");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    }

    // GET /clubs/manager/{userId}
    elseif ($method === "GET" && $id === "manager" && $subId) {
        $managerId = $subId;

        $stmt = $conn->prepare(
            "SELECT id
            FROM clubs
            WHERE managerId = ?
            LIMIT 1"
        );
        $stmt->bind_param("s", $managerId);
        $stmt->execute();

        $result = $stmt->get_result();
        $club = $result->fetch_assoc();

        if ($club) {
            echo json_encode($club);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User does not manage any club"]);
        }
    }

    // GET /clubs/{id}
    elseif ($method === "GET" && $id) {
        $stmt = $conn->prepare("SELECT * FROM clubs WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc());
    }

    // POST /clubs
    elseif ($method === "POST" && !$id) {
        $cid = uniqid("c");
        
        $imagePath = $_POST['image'] ?? 'https://res.cloudinary.com/dfnaghttm/image/upload/v1767385651/eor1qixyfbonciki20qr.jpg'; 

        $conn->begin_transaction();
        try {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $managerId = $_POST['managerId'] ?? '';
            $category = $_POST['category'] ?? 'General';

            $stmt = $conn->prepare(
                "INSERT INTO clubs (id, name, description, managerId, image, category)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->bind_param("ssssss", $cid, $name, $description, $managerId, $imagePath, $category);
            $stmt->execute();
            $stmt->close();

            $updateStmt = $conn->prepare("UPDATE users SET role = 'club_manager' WHERE id = ?");
            $updateStmt->bind_param("s", $managerId);
            $updateStmt->execute();
            $updateStmt->close();

            $conn->commit();
            echo json_encode([
                "message" => "Club created and manager role updated",
                "clubId" => $cid,
                "imagePath" => $imagePath
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => "Failed to create club", "details" => $e->getMessage()]);
        }
    }

    // PUT /clubs/{id}/{managerId}
    elseif ($method === "PUT" && $id && $subId) {
        $conn->begin_transaction();

        try {
            $fetchStmt = $conn->prepare("SELECT managerId FROM clubs WHERE id = ?");
            $fetchStmt->bind_param("s", $id);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $currentClub = $result->fetch_assoc();

            if (!$currentClub || !$currentClub['managerId']) {
                throw new Exception("Club not found.");
            }

            $roleStmt = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
            $roleStmt->bind_param("s", $currentClub['managerId']);
            $roleStmt->execute();

            $clubStmt = $conn->prepare("UPDATE clubs SET managerId = ? WHERE id = ?");
            $clubStmt->bind_param("ss", $subId, $id);
            $clubStmt->execute();

            $newRoleStmt = $conn->prepare("UPDATE users SET role = 'club_manager' WHERE id = ?");
            $newRoleStmt->bind_param("s", $subId);
            $newRoleStmt->execute();

            $conn->commit();
            echo json_encode(["message" => "Manager updated successfully"]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    // PUT /clubs/{id}
    elseif ($method === "POST" && $id) {
        $fetchStmt = $conn->prepare("SELECT image FROM clubs WHERE id = ?");
        $fetchStmt->bind_param("s", $id);
        $fetchStmt->execute();
        $currentClub = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();

        if (!$currentClub) {
            http_response_code(404);
            echo json_encode(["error" => "Club not found"]);
            return;
        }

        $imagePath = $_POST['image'] ?? $currentClub['image'];

        $conn->begin_transaction();
        try {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? 'General';

            $stmt = $conn->prepare(
                "UPDATE clubs SET name=?, description=?, category=?, image=? WHERE id=?"
            );
            
            $stmt->bind_param("sssss", $name, $description, $category, $imagePath, $id);

            if ($stmt->execute()) {
                $conn->commit();
                echo json_encode([
                    "message" => "Club updated successfully",
                    "clubId" => $id,
                    "imagePath" => $imagePath
                ]);
            } else {
                throw new Exception("Execute failed");
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => "Failed to update club", "details" => $e->getMessage()]);
        }
    }

    // DELETE /clubs/{id}
    elseif ($method === "DELETE" && $id) {
        $getManager = $conn->prepare("SELECT managerId FROM clubs WHERE id = ?");
        $getManager->bind_param("s", $id);
        $getManager->execute();
        $result = $getManager->get_result();
        $club = $result->fetch_assoc();

        if ($club) {
            $managerId = $club['managerId'];

            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("DELETE FROM clubs WHERE id = ?");
                $stmt->bind_param("s", $id);
                $stmt->execute();

                $checkOtherClubs = $conn->prepare("SELECT id FROM clubs WHERE managerId = ?");
                $checkOtherClubs->bind_param("s", $managerId);
                $checkOtherClubs->execute();
                
                if ($checkOtherClubs->get_result()->num_rows === 0) {
                    $updateRole = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
                    $updateRole->bind_param("s", $managerId);
                    $updateRole->execute();
                }

                $conn->commit();
                echo json_encode(["message" => "Club deleted and roles updated"]);
            } catch (Exception $e) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(["error" => "Deletion failed"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Club not found"]);
        }
    }
}
