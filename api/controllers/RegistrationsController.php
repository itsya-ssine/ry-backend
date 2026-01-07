<?php

function handleRegistrations($method, $uri, $conn) {
    $filter = $uri[1] ?? null;
    $filterId = $uri[2] ?? null;
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($method) {
        case "GET":
            if ($filter === 'club' && $filterId) return getRegistrationsByClub($conn, $filterId);
            if ($filter === 'student' && $filterId) return getRegistrationsByStudent($conn, $filterId);
            return getAllRegistrations($conn);

        case "POST":
            return createRegistration($conn, $input);

        case "PUT":
            return updateRegistrationStatus($conn, $input);

        case "DELETE":
            return removeRegistration($conn, $input);

        default:
            sendRegResponse(["error" => "Method not allowed"], 405);
    }
}


function getAllRegistrations($conn) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id";
    $res = $conn->query($sql);
    sendRegResponse(formatRegistrationRows($res));
}

function getRegistrationsByClub($conn, $clubId) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id WHERE r.clubId = ?";
    return executeFilteredQuery($conn, $sql, $clubId);
}

function getRegistrationsByStudent($conn, $studentId) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id WHERE r.studentId = ?";
    return executeFilteredQuery($conn, $sql, $studentId);
}

function createRegistration($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;
    $status = $input['status'] ?? 'pending';
    $date = date("Y-m-d");

    if (!$sId || !$cId) sendRegResponse(["error" => "Missing IDs"], 400);

    $stmt = $conn->prepare("INSERT INTO registrations (studentId, clubId, status, joinedAt) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $sId, $cId, $status, $date);

    if ($stmt->execute()) {
        sendRegResponse(["success" => true, "joinedAt" => $date], 201);
    } else {
        sendRegResponse(["error" => "Registration failed"], 500);
    }
}

function updateRegistrationStatus($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;
    $status = $input['status'] ?? null;

    if (!$sId || !$cId || !$status) sendRegResponse(["error" => "Missing data"], 400);

    $stmt = $conn->prepare("UPDATE registrations SET status = ? WHERE studentId = ? AND clubId = ?");
    $stmt->bind_param("sss", $status, $sId, $cId);

    $stmt->execute() ? sendRegResponse(["message" => "Status updated to $status"]) : sendRegResponse(["error" => "Update failed"], 500);
}

function removeRegistration($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;

    if (!$sId || !$cId) sendRegResponse(["error" => "Missing IDs"], 400);

    $stmt = $conn->prepare("DELETE FROM registrations WHERE studentId = ? AND clubId = ?");
    $stmt->bind_param("ss", $sId, $cId);

    $stmt->execute() ? sendRegResponse(["message" => "Removed"]) : sendRegResponse(["error" => "Delete failed"], 500);
}


function executeFilteredQuery($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    sendRegResponse(formatRegistrationRows($stmt->get_result()));
}

function formatRegistrationRows($result) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "id" => "reg_" . bin2hex(random_bytes(4)), // Better than uniqid
            "joinedAt" => $row['joinedAt'],
            "status" => $row['status'],
            "clubId" => $row['clubId'],
            "student" => [
                "id" => $row['studentId'],
                "name" => $row['name'],
                "email" => $row['email'],
                "avatar" => $row['avatar'] ?? null
            ]
        ];
    }
    return $data;
}

function sendRegResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}