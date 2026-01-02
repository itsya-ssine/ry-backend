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

    if ($method === "POST" && $id === "bio") {
        $userId = $data['id'] ?? null;
        $newBio = $data['bio'] ?? null;

        if (!$userId || $newBio === null) {
            http_response_code(400);
            echo json_encode(["error" => "bio not added"]);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->bind_param(
                "ss",
                $newBio,
                $userId,
            );

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Bio updated successfully'
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
        echo json_encode([
            'success' => true
        ]);

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
