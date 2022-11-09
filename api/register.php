<?php

require_once '../cors.php';
require_once '../config/database.php';

/**
 * mysql query to create users table
 * create table users (
 *      id int not null primary key auto_increment,
 *      name varchar(255) not null,
 *      email varchar(255) not null unique,
 *      contact varchar(20) not null unique,
 *      role enum('student', 'businessowner', 'schooladmin', 'superadmin') not null,
 *      password varchar(255) not null
 * );
 */

$post_body = file_get_contents("php://input");

if (isset($post_body) && !empty($post_body)) {
    $data = json_decode($post_body);

    $name = $data->name;
    $email = $data->email;
    $contact = $data->contact;
    $password = $data->password;
    $role = $data->role;

    // validations
    $errors = array();
    if (!isset($name))
        array_push($errors, ['name' => 'name is a required field']);
    if (!isset($email))
        array_push($errors, ['email' => 'email is a required field']);
    if (!isset($contact))
        array_push($errors, ['contact' => 'contact is a required field']);
    if (!isset($password))
        array_push($errors, ['password' => 'password is a required field']);
    if (!isset($role))
        array_push($errors, ['role' => 'role is a required field']);

    if (count($errors) > 0) {
        echo json_encode(['error' => $errors]);
        http_response_code(400);
        exit(1);
    }

    // check if user exists
    $check_email = "SELECT * FROM users WHERE email='$email'";
    $result_check_email = mysqli_query($conn, $check_email);
    if (mysqli_num_rows($result_check_email) > 0) {
        echo json_encode(['error' => 'user already registered']);
        http_response_code(405);
        exit(1);
    }

    // hash password using bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $query = "INSERT INTO users (
                name, 
                email, 
                contact, 
                password, 
                role
            ) VALUES (
                '$name',
                '$email',
                '$contact',
                '$password_hash',
                '$role'
            )";

    if (mysqli_query($conn, $query)) {
        $user_id = $conn->insert_id;
        $user = mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")->fetch_row();

        echo json_encode([
            'data' => [
                'id' => $user[0],
                'name' => $user[1],
                'email' => $user[2],
                'contact' => $user[3],
                'role' => $user[4]
            ],
            'message' => 'user registered successfully'
        ]);
        http_response_code(201);
        exit(0);
    }

    echo json_encode(['error' => 'server error']);
    http_response_code(500);
    exit(1);
}

echo json_encode(['error' => 'invalid request']);
http_response_code(400);
exit(1);
