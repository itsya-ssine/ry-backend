<?php

function handleUsers($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($method) {
        case "GET":
            return $id ? getUserById($conn, $id) : getAllUsers($conn);

        case "POST":
            if ($id === "login") return loginUser($conn, $input);
            if ($id === "register") return registerUser($conn, $input);
            break;

        case "PUT":
            if ($id === "update") return updateProfile($conn, $input);
            if ($id === "avatar") return updateAvatar($conn, $input);
            break;

        default:
            sendUserResponse(["error" => "Invalid users route"], 404);
    }
}


function loginUser($conn, $input) {
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$email || !$password) {
        sendUserResponse(["error" => "Missing credentials"], 400);
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        sendUserResponse($user);
    } else {
        sendUserResponse(["error" => "Invalid credentials"], 401);
    }
}

function registerUser($conn, $input) {
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$name || !$email || !$password) {
        sendUserResponse(["error" => "Missing fields"], 400);
    }

    $uid = bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, 'student')");
        $stmt->bind_param("ssss", $uid, $name, $email, $hash);
        $stmt->execute();

        sendUserResponse(["id" => $uid, "name" => $name, "email" => $email, "role" => "student"], 201);
    } catch (Exception $e) {
        sendUserResponse(["error" => "Email already exists or database error"], 409);
    }
}

function updateProfile($conn, $input) {
    $userId = $input['id'] ?? null;
    if (!$userId) sendUserResponse(["error" => "User ID required"], 400);

    $stmt = $conn->prepare("SELECT name, bio FROM users WHERE id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) sendUserResponse(["error" => "User not found"], 404);

    $name = $input['name'] ?? $current['name'];
    $bio  = $input['bio'] ?? $current['bio'];

    $upd = $conn->prepare("UPDATE users SET name = ?, bio = ? WHERE id = ?");
    $upd->bind_param("sss", $name, $bio, $userId);
    
    $upd->execute() ? sendUserResponse(["message" => "Updated"]) : sendUserResponse(["error" => "Update failed"], 500);
}

function updateAvatar($conn, $input) {
    $userId = $input['userId'] ?? null;
    $avatar = $input['avatar'] ?? null;

    if (!$userId || !$avatar) sendUserResponse(["error" => "Missing data"], 400);

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("ss", $avatar, $userId);
    
    $stmt->execute() ? sendUserResponse(["success" => true, "avatar" => $avatar]) : sendUserResponse(["error" => "Update failed"], 500);
}

function getUserById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, name, email, role, bio, avatar FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $user ? sendUserResponse($user) : sendUserResponse(["error" => "User not found"], 404);
}

function getAllUsers($conn) {
    $res = $conn->query("SELECT id, name, email, role FROM users");
    sendUserResponse($res->fetch_all(MYSQLI_ASSOC));
}


function sendUserResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}