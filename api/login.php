<?php

require_once '../cors.php';
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

$post_body = file_get_contents("php://input");

if (isset($post_body) && !empty($post_body)) {
    $data = json_decode($post_body);

    $email = $data->email;
    $password = $data->password;

    // validations
    $errors = array();
    if (!isset($email))
        array_push($errors, ['email' => 'email is a required field']);
    if (!isset($password))
        array_push($errors, ['password' => 'password is a required field']);

    if (count($errors) > 0) {
        echo json_encode(['errors' => $errors]);
        http_response_code(400);
        exit(1);
    }

    // check if user exists
    $check_user = "SELECT * FROM users WHERE email='$email'";
    $check_user_result = mysqli_query($conn, $check_user);
    if (mysqli_num_rows($check_user_result) > 0) {
        // check password
        if (!password_verify($password, end($check_user_result->fetch_row()))) {
            echo json_encode(['error' => 'incorrect password']);
            http_response_code(405);
            exit(1);
        }

        // assign token
        $issued_at = new DateTimeImmutable();
        $jwt_payload = [
            'iat' => $issued_at->getTimestamp(),
            'iss' => 'localhost',
            'nbf' => $issued_at->getTimestamp(),
            'exp' => $issued_at->modify('+1 day')->getTimestamp(),
            'id' => reset(mysqli_query($conn, $check_user)->fetch_row()),
        ];
        $token = JWT::encode($jwt_payload, JWT_SECRET, 'HS512');
        echo json_encode(['token' => $token]);
        http_response_code(200);
        exit(0);
    }

    echo json_encode(['error' => 'user not registered']);
    http_response_code(405);
    exit(1);
}

echo json_encode(['error' => 'invalid request']);
http_response_code(400);
exit(1);
