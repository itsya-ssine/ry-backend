<?php

function handleArchive($method, $uri, $conn) {
    $resourceType = $uri[1] ?? null;

    switch ($method) {
        case "GET":
            if ($resourceType === "activities") {
                getArchivedActivities($conn);
            } else {
                sendResponse(["error" => "Archive category not found"], 404);
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