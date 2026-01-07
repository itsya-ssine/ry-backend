<?php

function handleUsers($method, $uri, $conn) {
    $action = $uri[1] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "GET":
            $action ? getUserById($conn, $action) : getAllUsers($conn);
            break;

        case "POST":
            if ($action === "login") loginUser($conn, $input);
            elseif ($action === "register") registerUser($conn, $input);
            elseif ($action === "update") updateProfile($conn, $input);
            elseif ($action === "avatar") updateAvatar($conn, $input);
            break;

        default:
            sendResponse(["error" => "Invalid route"], 404);
    }
}

function loginUser($conn, $input) {
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$email || !$password) sendResponse(["error" => "Missing credentials"], 400);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        sendResponse($user);
    } else {
        sendResponse(["error" => "Invalid credentials"], 401);
    }
}

function registerUser($conn, $input) {
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$name || !$email || !$password) sendResponse(["error" => "Missing fields"], 400);

    $uid = bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, 'student')");
        $stmt->bind_param("ssss", $uid, $name, $email, $hash);
        $stmt->execute();
        sendResponse(["id" => $uid, "name" => $name, "email" => $email, "role" => "student"], 201);
    } catch (Exception $e) {
        sendResponse(["error" => "User already exists"], 409);
    }
}

function updateProfile($conn, $input) {
    $userId = $input['id'] ?? null;
    if (!$userId) sendResponse(["error" => "ID required"], 400);

    $stmt = $conn->prepare("SELECT name, bio FROM users WHERE id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) sendResponse(["error" => "Not found"], 404);

    $name = $input['name'] ?? $current['name'];
    $bio = $input['bio'] ?? $current['bio'];

    $upd = $conn->prepare("UPDATE users SET name = ?, bio = ? WHERE id = ?");
    $upd->bind_param("sss", $name, $bio, $userId);
    $upd->execute() ? sendResponse(["success" => true]) : sendResponse(["error" => "Failed"], 500);
}

function updateAvatar($conn, $input) {
    $userId = $input['userId'] ?? null;
    $avatar = $input['avatar'] ?? null;

    if (!$userId || !$avatar) sendResponse(["error" => "Missing data"], 400);

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("ss", $avatar, $userId);
    $stmt->execute() ? sendResponse(["success" => true, "avatar" => $avatar]) : sendResponse(["error" => "Failed"], 500);
}

function getUserById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, name, email, role, bio, avatar FROM users WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $user ? sendResponse($user) : sendResponse(["error" => "Not found"], 404);
}

function getAllUsers($conn) {
    $res = $conn->query("SELECT id, name, email, role FROM users WHERE role != 'admin'");
    sendResponse($res->fetch_all(MYSQLI_ASSOC));
}