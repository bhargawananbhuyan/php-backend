<?php

require_once '../../cors.php';
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
    echo json_encode(['error' => 'unauthorized']);
    http_response_code(405);
    exit(1);
}

$token = $matches[1];
if (!$token) {
    echo json_encode(['error' => 'invalid token']);
    http_response_code(405);
    exit(1);
}

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS512'));
    $user_id = $decoded->id;

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT c.id, c.name FROM clubs as c 
            JOIN clubs_students as cs ON
            c.id=cs.club_id AND cs.user_id=$user_id
        ";
        $clubs = mysqli_query($conn, $query)->fetch_all();

        $payload = array();
        foreach ($clubs as $club) {
            array_push($payload, [
                'id' => intval($club[0]),
                'name' => $club[1]
            ]);
        }

        echo json_encode([
            'data' => $payload
        ]);
        http_response_code(200);
        die();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $postBody = file_get_contents("php://input");
        if (isset($postBody) && !empty($postBody)) {
            $data = json_decode($postBody);

            $club_id = $data->clubId;
            $user__id = $data->userId;

            if (mysqli_num_rows(
                mysqli_query(
                    $conn,
                    "SELECT * FROM clubs_students WHERE club_id=$club_id AND user_id=$user__id"
                )
            ) > 0) {
                echo json_encode([
                    'error' => 'member already exists'
                ]);
                http_response_code(400);
                die();
            }

            $query = "INSERT INTO clubs_students (
                club_id,
                user_id
            ) VALUES (
                $club_id,
                $user__id
            )";

            if (mysqli_query($conn, $query)) {
                $newMemberId = $conn->insert_id;
                $newMember = mysqli_query(
                    $conn,
                    "SELECT c.name FROM clubs AS c 
                        JOIN clubs_students AS cs 
                        ON c.id=cs.club_id 
                        AND cs.id=$newMemberId"
                )->fetch_row();

                echo json_encode([
                    'data' => [
                        'id' => $club_id,
                        'name' => $newMember[0]
                    ]
                ]);
                http_response_code(201);
                die();
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
