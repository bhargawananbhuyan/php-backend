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

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            $orders = mysqli_query($conn, "SELECT * FROM orders WHERE user_id=$user_id")->fetch_all();

            $payload = array();
            foreach ($orders as $order) {
                array_push($payload, [
                    'id' => $order[0],
                    'status' => $order[2]
                ]);
            }
            echo json_encode([
                'data' => $payload
            ]);
            die();
            break;

        case "POST":
            // checkout
            parse_str($_SERVER['QUERY_STRING'], $qs);
            $cartId = $qs['cartId'];

            $query = "INSERT INTO orders (cart_id, user_id, `status`) VALUES ($cartId, $user_id, 'paid')";
            if (mysqli_query($conn, $query)) {
                mysqli_query($conn, "UPDATE carts SET status='purchased' WHERE id=$cartId");

                echo json_encode([
                    'data' => 'checkout successful'
                ]);
                http_response_code(201);
                die();
            }

        default:
            json_encode([
                'error' => 'method not allowed'
            ]);
            http_response_code(405);
            die();
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
