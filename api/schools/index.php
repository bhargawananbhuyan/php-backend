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

    // READ
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT s.id, s.name, u.id, u.name 
            FROM schools AS s 
            JOIN users AS u 
            ON s.admin_id=u.id AND s.admin_id=$user_id";

        // created schools
        $schools = mysqli_query(
            $conn,
            $query
        )->fetch_all();

        if (!empty($schools)) {
            $payload = array();
            foreach ($schools as $school) {
                $school_id = $school[0];
                $students = mysqli_query(
                    $conn,
                    "SELECT * FROM schools_students as ss JOIN users as u WHERE ss.student_id=u.id AND ss.school_id=$school_id"
                )->fetch_all();
                $students_payload = array();
                foreach ($students as $student) {
                    array_push($students_payload, [
                        'id' => intval($student[3]),
                        'name' => $student[4],
                        'email' => $student[5],
                        'contact' => $student[6],
                    ]);
                }

                array_push($payload, [
                    'id' => intval($school_id),
                    'name' => $school[1],
                    'students' => $students_payload
                ]);
            }
            echo json_encode([
                'studentadmin' => [
                    'id' => intval($schools[0][2]),
                    'name' => $schools[0][3],
                ],
                'data' => $payload
            ]);
            http_response_code(200);
            die();
        } else {
            echo json_encode([
                'error' => 'no schools found'
            ]);
            http_response_code(404);
            die();
        }
    }
    // CREATE
    else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // check if school exists
        if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM schools WHERE admin_id=$user_id")) > 0) {
            echo json_encode([
                'error' => 'school already exists'
            ]);
            http_response_code(400);
            die();
        }

        $post_body = file_get_contents("php://input");
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $name = $data->name;

            $query = "INSERT INTO schools (name, admin_id) VALUES ('$name', $user_id)";
            if (mysqli_query($conn, $query)) {
                $school_inserted = $conn->insert_id;
                $new_school = mysqli_query($conn, "SELECT * FROM schools WHERE id=$school_inserted")->fetch_row();
                echo json_encode([
                    'data' => [
                        'id' => intval($new_school[0]),
                        'name' => $new_school[1],
                        'creator_id' => intval($new_school[2])
                    ]
                ]);
                http_response_code(200);
                die();
            }
        }
    }
    // UPDATE
    else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        // get school id
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);
            $school_id = $q['schoolId'];

            // parse body
            $rawBody = file_get_contents("php://input");
            if (isset($rawBody) && !empty($rawBody)) {
                $data = json_decode($rawBody);

                $name = $data->name;

                $query = "UPDATE schools SET name='$name' WHERE id=$school_id AND admin_id=$user_id";
                if (mysqli_query($conn, $query)) {
                    json_encode([
                        'data' => [
                            'id' => intval($school_id),
                            'name' => $name
                        ]
                    ]);
                    http_response_code(200);
                    die();
                }
            }
        }
    }
    // DELETE
    else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        // get school id
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);
            $id = $q['schoolId'];
            $query = "DELETE FROM schools WHERE id=$id";
            if (mysqli_query($conn, $query)) {
                echo json_encode([
                    'data' => [
                        'id' => intval($id)
                    ],
                    'message' => 'school deleted successfully'
                ]);
                die();
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
