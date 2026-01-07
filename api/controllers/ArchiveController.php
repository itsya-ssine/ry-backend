<?php

function handleArchive($method, $uri, $conn) {
    $resourceType = $uri[1] ?? null;
    $id = $uri[2] ?? null;

    switch ($method) {
        case "GET":
            if ($resourceType === "activities") {
                return getArchivedActivities($conn);
            }
            sendArchiveResponse(["error" => "Archive category not found"], 404);
            break;

        default:
            sendArchiveResponse(["error" => "Method not allowed"], 405);
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

        sendArchiveResponse($res->fetch_all(MYSQLI_ASSOC));
    } catch (Exception $e) {
        sendArchiveResponse([
            "success" => false, 
            "error" => "Failed to fetch archive", 
            "details" => $e->getMessage()
        ], 500);
    }
}


function sendArchiveResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}