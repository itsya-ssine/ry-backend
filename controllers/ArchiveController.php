<?php
function handleArchive($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    $subId = $uri[2] ?? null;

    if ($method === "GET" && $id === "activities") {
        $stmt = $conn->prepare("SELECT * FROM activities_archive ORDER BY date");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        echo json_encode($activities);
    }
}