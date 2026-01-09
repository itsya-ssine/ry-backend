<?php

function handleArchive($method, $uri, $conn) {
    $resourceType = $uri[1] ?? null;

    switch ($method) {
        case "GET":
            if ($resourceType === "activities") {
                getArchivedActivities($conn);
            } else {
                sendResponse(["error" => "Archive not found"], 404);
            }
            break;

        case "POST":
            if ($resourceType === "activities" && isset($uri[2])) {
                archiveActivity($uri[2], $conn);
            } else {
                sendResponse(["error" => "Invalid archive request"], 400);
            }
            break;

        default:
            sendResponse(["error" => "Method not allowed"], 405);
            break;
    }
}


function getArchivedActivities($conn) {
    try {
        $sql = "SELECT * FROM activities_archive ORDER BY date DESC";
        $res = $conn->query($sql);
        
        if (!$res) {
            throw new Exception($conn->error);
        }

        sendResponse($res->fetch_all(MYSQLI_ASSOC));
        
    } catch (Exception $e) {
        sendResponse([
            "success" => false, 
            "error" => "Failed to fetch archive", 
            "details" => $e->getMessage()
        ], 500);
    }
}

function archiveActivity($activityId, $conn) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $budget = isset($_POST['budget']) ? (float)$_POST['budget'] : null;

    try {
        $stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
        $stmt->bind_param("s", $activityId);
        $stmt->execute();
        $activityRes = $stmt->get_result();
        
        if ($activityRes->num_rows === 0) {
            sendResponse(["error" => "Activity not found"], 404);
            return;
        }

        $activity = $activityRes->fetch_assoc();

        $stmt = $conn->prepare("INSERT INTO activities_archive (id, title, description, date, location, image, rating, budget) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "ssssssid", 
            $activity['id'], 
            $activity['title'], 
            $activity['description'], 
            $activity['date'], 
            $activity['location'], 
            $activity['image'], 
            $rating,
            $budget
        );
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
        $stmt->bind_param("s", $activityId);
        $stmt->execute();

        sendResponse(["success" => true, "message" => "Activity archived successfully"]);

    } catch (Exception $e) {
        sendResponse([
            "success" => false, 
            "error" => "Failed to archive activity", 
            "details" => $e->getMessage()
        ], 500);
    }
}