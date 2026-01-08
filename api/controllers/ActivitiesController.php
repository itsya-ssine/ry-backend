<?php

function handleActivities($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "GET":
            if ($id === "recent") getRecentActivities($conn);
            elseif ($id) getActivityById($conn, $id);
            else getAllActivities($conn);
            break;

        case "POST":
            $id ? updateActivity($conn, $id, $input) : createActivity($conn, $input);
            break;

        case "DELETE":
            deleteActivity($conn, $id);
            break;
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
    
    $activity ? sendResponse($activity) : sendResponse(["error" => "Activity not found"], 404);
}

function createActivity($conn, $input) {
    $aid = uniqid("a");
    $data = prepareActivityData($input);

    try {
        $stmt = $conn->prepare(
            "INSERT INTO activities (id, title, description, date, location, image) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $aid, $data['title'], $data['desc'], $data['date'], $data['loc'], $data['img']);
        $stmt->execute();

        sendResponse(["success" => true, "activityId" => $aid], 201);
    } catch (Exception $e) {
        sendResponse(["error" => "Creation failed", "details" => $e->getMessage()], 500);
    }
}

function updateActivity($conn, $id, $input) {
    $stmt = $conn->prepare("SELECT title FROM activities WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) {
        sendResponse(["error" => "Activity not found"], 404);
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE activities SET title=?, description=?, location=?, date=? WHERE id=?"
        );
        $stmt->bind_param("sssss", $input['title'], $input['description'], $input['location'], $input['date'], $id);
        $stmt->execute();

        sendResponse(["message" => "Activity updated successfully", "id" => $id]);
    } catch (Exception $e) {
        sendResponse(["error" => "Update failed", "details" => $e->getMessage()], 500);
    }
}

function deleteActivity($conn, $id) {
    if (!$id) {
        sendResponse(["error" => "ID required"], 400);
    }
    
    $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    
    sendResponse(["message" => "Activity deleted successfully"]);
}