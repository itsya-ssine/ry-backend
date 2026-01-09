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
            $id ? updateActivity($conn, $id) : createActivity($conn);
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

function createActivity($conn) {
    $title       = $_POST['title'] ?? 'Untitled';
    $description = $_POST['description'] ?? '';
    $date        = $_POST['date'] ?? date('Y-m-d');
    $location    = $_POST['location'] ?? 'ENSA KHOURIBGA';
    $image       = $_POST['image'] ?? "https://res.cloudinary.com/dfnaghttm/image/upload/v1767385774/w7x1o1g2h8v4qjhamx5e.png";
    
    $aid = uniqid("a");

    try {
        $stmt = $conn->prepare(
            "INSERT INTO activities (id, title, description, date, location, image) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $aid, $title, $description, $date, $location, $image);
        $stmt->execute();

        sendResponse(["success" => true, "activityId" => $aid], 201);
    } catch (Exception $e) {
        sendResponse(["error" => "Creation failed", "details" => $e->getMessage()], 500);
    }
}

function updateActivity($conn, $id) {
    $title       = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    $location    = $_POST['location'] ?? null;
    $date        = $_POST['date'] ?? null;

    if (!$id) {
        sendResponse(["error" => "ID required"], 400);
        return;
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE activities SET title=?, description=?, location=?, date=? WHERE id=?"
        );
        
        $stmt->bind_param("sssss", $title, $description, $location, $date, $id);
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