<?php

function handleActivities($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($method) {
        case "GET":
            if ($id === "recent") return getRecentActivities($conn);
            if ($id) return getActivityById($conn, $id);
            return getAllActivities($conn);

        case "POST":
            return $id ? updateActivity($conn, $id, $input) : createActivity($conn, $input);

        case "DELETE":
            return deleteActivity($conn, $id);

        default:
            sendResponse(["error" => "Method not allowed"], 405);
    }
}


function getAllActivities($conn) {
    $res = $conn->query("SELECT * FROM activities");
    sendResponse($res->fetch_all(MYSQLI_ASSOC));
}

function getRecentActivities($conn) {
    $stmt = $conn->prepare("SELECT * FROM activities ORDER BY date DESC LIMIT 8");
    $stmt->execute();
    sendResponse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function getActivityById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    
    $activity ? sendResponse($activity) : sendResponse(["error" => "Not found"], 404);
}

function createActivity($conn, $input) {
    $aid = uniqid("a");
    $data = prepareActivityData($input);

    try {
        $stmt = $conn->prepare(
            "INSERT INTO activities (id, title, description, date, location, image) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $aid, $data['title'], $data['desc'], $data['date'], $data['loc'], $data['img']);
        $stmt->execute();

        sendResponse(["success" => true, "activityId" => $aid], 201);
    } catch (Exception $e) {
        sendResponse(["error" => "Creation failed", "details" => $e->getMessage()], 500);
    }
}

function updateActivity($conn, $id, $input) {
    $stmt = $conn->prepare("SELECT image FROM activities WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) return sendResponse(["error" => "Activity not found"], 404);

    $data = prepareActivityData($input, $current['image']);

    try {
        $stmt = $conn->prepare(
            "UPDATE activities SET title=?, description=?, location=?, date=?, image=? WHERE id=?"
        );
        $stmt->bind_param("ssssss", $data['title'], $data['desc'], $data['loc'], $data['date'], $data['img'], $id);
        $stmt->execute();

        sendResponse(["message" => "Activity updated", "id" => $id]);
    } catch (Exception $e) {
        sendResponse(["error" => "Update failed", "details" => $e->getMessage()], 500);
    }
}

function deleteActivity($conn, $id) {
    if (!$id) return sendResponse(["error" => "ID required"], 400);
    
    $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    sendResponse(["message" => "Activity deleted"]);
}

function prepareActivityData($input, $defaultImg = 'https://res.cloudinary.com/.../default.png') {
    return [
        'title' => $input['title'] ?? '',
        'desc'  => $input['description'] ?? '',
        'date'  => $input['date'] ?? '',
        'loc'   => $input['location'] ?? 'ENSA KHOURIBGA',
        'img'   => $input['image'] ?? $defaultImg
    ];
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}