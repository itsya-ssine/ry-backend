<?php

function handleRegistrations($method, $uri, $conn) {
    $filter = $uri[1] ?? null;
    $filterId = $uri[2] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "GET":
            if ($filter === 'club' && $filterId) {
                getRegistrationsByClub($conn, $filterId);
            } elseif ($filter === 'student' && $filterId) {
                getRegistrationsByStudent($conn, $filterId);
            } else {
                getAllRegistrations($conn);
            }
            break;

        case "POST":
            createRegistration($conn, $input);
            break;

        case "PUT":
            updateRegistrationStatus($conn, $input);
            break;

        case "DELETE":
            removeRegistration($conn, $input);
            break;

        default:
            sendResponse(["error" => "Method not allowed"], 405);
    }
}

function getAllRegistrations($conn) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id";
    $res = $conn->query($sql);
    sendResponse(formatRegistrationRows($res));
}

function getRegistrationsByClub($conn, $clubId) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id WHERE r.clubId = ?";
    executeFilteredQuery($conn, $sql, $clubId);
}

function getRegistrationsByStudent($conn, $studentId) {
    $sql = "SELECT r.*, u.name, u.email, u.avatar FROM registrations r 
            JOIN users u ON r.studentId = u.id WHERE r.studentId = ?";
    executeFilteredQuery($conn, $sql, $studentId);
}

function createRegistration($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;
    $status = $input['status'] ?? 'pending';
    $date = date("Y-m-d");

    if (!$sId || !$cId) sendResponse(["error" => "Missing IDs"], 400);

    $stmt = $conn->prepare("INSERT INTO registrations (studentId, clubId, status, joinedAt) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $sId, $cId, $status, $date);

    if ($stmt->execute()) {
        sendResponse(["success" => true, "joinedAt" => $date], 201);
    } else {
        sendResponse(["error" => "Registration failed"], 500);
    }
}

function updateRegistrationStatus($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;
    $status = $input['status'] ?? null;

    if (!$sId || !$cId || !$status) sendResponse(["error" => "Missing data"], 400);

    $stmt = $conn->prepare("UPDATE registrations SET status = ? WHERE studentId = ? AND clubId = ?");
    $stmt->bind_param("sss", $status, $sId, $cId);

    $stmt->execute() ? sendResponse(["message" => "Status updated"]) : sendResponse(["error" => "Update failed"], 500);
}

function removeRegistration($conn, $input) {
    $sId = $input['studentId'] ?? null;
    $cId = $input['clubId'] ?? null;

    if (!$sId || !$cId) sendResponse(["error" => "Missing IDs"], 400);

    $stmt = $conn->prepare("DELETE FROM registrations WHERE studentId = ? AND clubId = ?");
    $stmt->bind_param("ss", $sId, $cId);

    $stmt->execute() ? sendResponse(["message" => "Removed"]) : sendResponse(["error" => "Delete failed"], 500);
}

function executeFilteredQuery($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    sendResponse(formatRegistrationRows($stmt->get_result()));
}

function formatRegistrationRows($result) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
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