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
        $query = "SELECT s.id, s.name FROM schools as s 
            JOIN schools_students as ss ON
            s.id=ss.school_id AND ss.student_id=$user_id
        ";
        $schools = mysqli_query($conn, $query)->fetch_all();

        $payload = array();
        foreach ($schools as $school) {
            array_push($payload, [
                'id' => intval($school[0]),
                'name' => $school[1]
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

            $school_id = $data->schoolId;
            $user__id = $data->userId;

            if (mysqli_num_rows(
                mysqli_query(
                    $conn,
                    "SELECT * FROM schools_students WHERE school_id=$school_id AND student_id=$user__id"
                )
            ) > 0) {
                echo json_encode([
                    'error' => 'student already exists'
                ]);
                http_response_code(400);
                die();
            }

            $query = "INSERT INTO schools_students (
                school_id,
                student_id
            ) VALUES (
                $school_id,
                $user__id
            )";

            if (mysqli_query($conn, $query)) {
                $newStudentId = $conn->insert_id;
                $newStudent = mysqli_query(
                    $conn,
                    "SELECT s.name FROM schools AS s 
                        JOIN schools_students AS ss 
                        ON s.id=ss.school_id 
                        AND ss.id=$newStudentId"
                )->fetch_row();

                echo json_encode([
                    'data' => [
                        'id' => $school_id,
                        'name' => $newStudent[0]
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
