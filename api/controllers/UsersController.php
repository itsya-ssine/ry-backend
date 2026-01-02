<?php

function handleUsers($method, $uri, $conn) {
    $id = $uri[1] ?? null;

    $data = json_decode(file_get_contents("php://input"), true);

    if ($method === "POST" && $id === "login") {

        if (!isset($data['email'], $data['password'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing email or password"]);
            return;
        }

        $stmt = $conn->prepare(
            "SELECT * 
             FROM users 
             WHERE email = ?"
        );
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($data['password'], $user['password'])) {
            unset($user['password']);
            echo json_encode($user);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid credentials"]);
        }
        return;
    }

    if ($method === "POST" && $id === "register") {
        try {
            if (!isset($data['name'], $data['email'], $data['password'])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing fields"]);
                return;
            }

            $uid = bin2hex(random_bytes(8));
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "INSERT INTO users (id, name, email, password, role) 
                 VALUES (?, ?, ?, ?, 'student')"
            );
            
            $stmt->bind_param(
                "ssss",
                $uid,
                $data['name'],
                $data['email'],
                $hash
            );

            $stmt->execute();
            $conn->commit();

            echo json_encode([
                "id" => $uid,
                "name" => $data['name'],
                "email" => $data['email'],
                "role" => "student"
            ]);

        } catch (Exception $e) {
            if ($conn->connect_errno === 0) {
                $conn->rollback();
            }

            http_response_code(409);
            echo json_encode(["error" => "Email already exists"]);
        }
        return;
    }

    if ($method === "POST" && $id === "update") {
        $userId = $data['id'] ?? null;
        $newBio = $data['bio'] ?? null;
        $newName = $data['name'] ?? null;

        if (!$userId) {
            http_response_code(400);
            echo json_encode(["error" => "not updated"]);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE users SET bio = ?, name = ? WHERE id = ?");
            $stmt->bind_param(
                "sss",
                $newBio,
                $newName,
                $userId,
            );

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Updated successfully'
                ]);
            } else {
                throw new Exception($stmt->error);
            }

            $stmt->close();

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "error"]);
        }

        return;
    }

    if ($method === "POST" && $id === "avatar") {
        $userId = $_POST['userId'] ?? null;
        $avatarUrl = $_POST['avatar'] ?? null; 

        if (!$userId || !$avatarUrl) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID or Avatar URL missing']);
            return;
        }

        try {
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->bind_param("ss", $avatarUrl, $userId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'avatar' => $avatarUrl
                ]);
            } else {
                throw new Exception("Database update failed");
            }
            $stmt->close();

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        return;
    }

    if ($method === "GET" && $id) {
        $stmt = $conn->prepare(
            "SELECT * 
             FROM users 
             WHERE id = ?"
        );
        $stmt->bind_param("s", $id);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_assoc());
        return;
    }

    if ($method === "GET" && !$id) {
        $res = $conn->query("SELECT * FROM users");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        return;
    }

    http_response_code(404);
    echo json_encode(["error" => "Invalid users route"]);
}
