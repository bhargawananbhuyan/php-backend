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
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);

            // if queried for a user
            $userId = $q['userId'];
            if (isset($userId) && !empty($userId)) {
                $query = "SELECT * FROM users WHERE id='$userId'";
                $query_result = mysqli_query($conn, $query);
                $user = $query_result->fetch_row();
                echo json_encode([
                    'data' => [
                        'id' => intval($user[0]),
                        'name' => $user[1],
                        'email' => $user[2],
                        'contact' => $user[3],
                        'role' => $user[4]
                    ]
                ]);
                http_response_code(200);
                die();
            }

            $role = $q['role'];

            $query = "SELECT * FROM users WHERE role='$role'";
            $query_result = mysqli_query($conn, $query);
            $users = $query_result->fetch_all();

            $payload = array();

            foreach ($users as $user) {
                array_push($payload, [
                    'id' => intval($user[0]),
                    'name' => $user[1],
                    'email' => $user[2],
                    'contact' => $user[3],
                    'role' => $user[4]
                ]);
            }

            echo json_encode(['data' => $payload]);
            die();
        } else {
            $query = "SELECT * FROM users WHERE id=$user_id";
            $query_result = mysqli_query($conn, $query);
            $user = $query_result->fetch_row();
            echo json_encode([
                'data' => [
                    'id' => intval($user[0]),
                    'name' => $user[1],
                    'email' => $user[2],
                    'contact' => $user[3],
                    'role' => $user[4]
                ]
            ]);
            http_response_code(200);
            exit(0);
        }
    }
    // UPDATE
    else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        // get the user id to update
        parse_str($_SERVER['QUERY_STRING'], $q);
        $user__id = $q['userId'];

        // parse post body
        $post_body = file_get_contents('php://input');

        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);
            $name = $data->name;
            $email = $data->email;
            $contact = $data->contact;
            $password = $data->password;

            $hashed_password = '';
            $query = '';
            if (isset($password) && !empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $query = "UPDATE users 
                SET name='$name',
                    email='$email',
                    contact='$contact',
                    password='$hashed_password'
                WHERE id=$user__id
            ";
            }

            $query = "UPDATE users 
                SET name='$name', 
                    email='$email', 
                    contact='$contact' 
                WHERE id=$user__id";

            if (mysqli_query($conn, $query)) {
                $updated_user = mysqli_query($conn, "SELECT * FROM users WHERE id=$user__id")->fetch_row();
                echo json_encode([
                    'data' => [
                        'id' => intval($updated_user[0]),
                        'name' => $updated_user[1],
                        'email' => $updated_user[2],
                        'contact' => $updated_user[3],
                        'role' => $updated_user[4]
                    ],
                    'message' => 'user updated successfully'
                ]);
                http_response_code(200);
                die();
            }
        }
    }
    // DELETE
    else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        // get the user id to delete
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
