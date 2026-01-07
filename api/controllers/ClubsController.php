<?php

function handleClubs($method, $uri, $conn) {
    $id = $uri[1] ?? null;
    $subId = $uri[2] ?? null;
    $input = getRequestInput();

    switch ($method) {
        case "GET":
            if ($id === "manager" && $subId) {
                getClubByManager($conn, $subId);
            } elseif ($id) {
                getClubById($conn, $id);
            } else {
                getAllClubs($conn);
            }
            break;

        case "POST":
            $id ? updateClub($conn, $id, $input) : createClub($conn, $input);
            break;

        case "PUT":
            if ($id && $subId) changeClubManager($conn, $id, $subId);
            break;

        case "DELETE":
            deleteClub($conn, $id);
            break;

        default:
            sendResponse(["error" => "Method not allowed"], 405);
    }
}

function getAllClubs($conn) {
    $res = $conn->query("SELECT * FROM clubs");
    sendResponse($res->fetch_all(MYSQLI_ASSOC));
}

function getClubByManager($conn, $managerId) {
    $stmt = $conn->prepare("SELECT id FROM clubs WHERE managerId = ? LIMIT 1");
    $stmt->bind_param("s", $managerId);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc();

    $club ? sendResponse($club) : sendResponse(["error" => "No club managed"], 404);
}

function getClubById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc();

    $club ? sendResponse($club) : sendResponse(["error" => "Club not found"], 404);
}

function createClub($conn, $input) {
    $cid = uniqid("c");
    $managerId = $input['managerId'] ?? '';
    $img = $input['image'] ?? 'https://res.cloudinary.com/dfnaghttm/image/upload/v1767385651/eor1qixyfbonciki20qr.jpg';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO clubs (id, name, description, managerId, image, category) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $cid, $input['name'], $input['description'], $managerId, $img, $input['category']);
        $stmt->execute();

        updateUserRole($conn, $managerId, 'club_manager');

        $conn->commit();
        sendResponse(["message" => "Club created", "clubId" => $cid], 201);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(["error" => "Failed to create club"], 500);
    }
}

function updateClub($conn, $id, $input) {
    $stmt = $conn->prepare("SELECT image FROM clubs WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) sendResponse(["error" => "Club not found"], 404);

    $img = $input['image'] ?? $current['image'];

    $stmt = $conn->prepare("UPDATE clubs SET name=?, description=?, category=?, image=? WHERE id=?");
    $stmt->bind_param("sssss", $input['name'], $input['description'], $input['category'], $img, $id);
    
    $stmt->execute() ? sendResponse(["message" => "Updated"]) : sendResponse(["error" => "Update failed"], 500);
}

function changeClubManager($conn, $clubId, $newManagerId) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT managerId FROM clubs WHERE id = ?");
        $stmt->bind_param("s", $clubId);
        $stmt->execute();
        $oldManagerId = $stmt->get_result()->fetch_assoc()['managerId'] ?? null;

        if (!$oldManagerId) throw new Exception("Club not found");

        updateUserRole($conn, $oldManagerId, 'student');
        
        $upd = $conn->prepare("UPDATE clubs SET managerId = ? WHERE id = ?");
        $upd->bind_param("ss", $newManagerId, $clubId);
        $upd->execute();
        
        updateUserRole($conn, $newManagerId, 'club_manager');

        $conn->commit();
        sendResponse(["message" => "Manager transfer complete"]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(["error" => $e->getMessage()], 500);
    }
}

function deleteClub($conn, $id) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT managerId FROM clubs WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $managerId = $stmt->get_result()->fetch_assoc()['managerId'] ?? null;

        if (!$managerId) sendResponse(["error" => "Club not found"], 404);

        $del = $conn->prepare("DELETE FROM clubs WHERE id = ?");
        $del->bind_param("s", $id);
        $del->execute();

        $check = $conn->prepare("SELECT id FROM clubs WHERE managerId = ?");
        $check->bind_param("s", $managerId);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            updateUserRole($conn, $managerId, 'student');
        }

        $conn->commit();
        sendResponse(["message" => "Club deleted"]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(["error" => "Delete failed"], 500);
    }
}

function updateUserRole($conn, $userId, $role) {
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("ss", $role, $userId);
    $stmt->execute();
}