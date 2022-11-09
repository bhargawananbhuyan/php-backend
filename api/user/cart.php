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
            // pending cart
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $qs);
                $q = $qs['status'];
                if ($q == 'pending') {
                    $cart = mysqli_query(
                        $conn,
                        "SELECT id, status FROM carts WHERE user_id=$user_id AND status='pending'"
                    )->fetch_row();

                    $cartId = $cart[0];

                    $products = mysqli_query(
                        $conn,
                        "SELECT p.id, p.name, p.price, c.status FROM cart_products AS cp 
                            JOIN carts AS c ON c.id = cp.cart_id
                            JOIN products AS p ON p.id=cp.product_id
                            AND c.id=$cartId"
                    )->fetch_all();

                    $payload = array();
                    foreach ($products as $product) {
                        array_push($payload, [
                            'id' => $product[0],
                            'name' => $product[1],
                            'price' => $product[2],
                        ]);
                    }

                    echo json_encode([
                        'cart' => [
                            'id' => $cart[0],
                            'status' => $cart[1]
                        ],
                        'data' => $payload
                    ]);
                    http_response_code(200);
                    die();
                }
            }

            // all carts
            $carts = mysqli_query($conn, "SELECT id, `status` FROM carts WHERE user_id=$user_id")->fetch_all();

            $rootPayload = array();
            foreach ($carts as $cart) {
                $cid = $cart[0];
                $products = mysqli_query(
                    $conn,
                    "SELECT p.id, p.name, p.price, c.status FROM cart_products AS cp 
                        JOIN carts AS c ON c.id = cp.cart_id
                        JOIN products AS p ON p.id=cp.product_id AND c.id=$cid"
                )->fetch_all();

                $payload = array();
                foreach ($products as $product) {
                    array_push($payload, [
                        'id' => $product[0],
                        'name' => $product[1],
                        'price' => $product[2],
                    ]);
                }

                array_push($rootPayload, [
                    'cart' => [
                        'id' => $cart[0],
                        'status' => $cart[1]
                    ],
                    'data' => $payload
                ]);
            }

            echo json_encode([
                'data' => $rootPayload
            ]);
            die();
            break;

        case "POST":
            // add product to cart
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $qs);
                $cartId = $qs['cartId'];
                $productId = $qs['productId'];

                if (isset($cartId) && isset($productId)) {
                    $query = "INSERT INTO cart_products (
                                cart_id, product_id
                            ) VALUES (
                                $cartId, $productId
                            )";
                    if (mysqli_query($conn, $query)) {
                        echo json_encode([
                            'data' => [
                                'id' => intval($conn->insert_id),
                                'product_id' => intval($productId),
                                'cart_id' => intval($cartId)
                            ]
                        ]);
                        http_response_code(201);
                    }
                    die();
                }
            }

            $query = "INSERT INTO carts (user_id) VALUES ($user_id)";
            if (mysqli_query($conn, $query)) {
                $newCartId = $conn->insert_id;

                echo json_encode([
                    'data' => [
                        'id' => intval($newCartId)
                    ]
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
