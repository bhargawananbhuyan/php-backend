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

    /**
     * @route POST /api/products/index.php
     * @access Private
     * @desc Create a new product
     */
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $post_body = file_get_contents('php://input');
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $name = $data->name;
            $price = $data->price;
            $quantity = $data->quantity;

            // if posted by superadmin
            parse_str($_SERVER['QUERY_STRING'], $q);
            $user__id = $q['id'];

            $query = '';
            if (isset($user__id))
                $query = "INSERT INTO products (
                    name, 
                    price, 
                    quantity, 
                    user_id
                ) VALUES (
                    '$name', 
                    $price, 
                    $quantity, 
                    $user__id
                )";
            else
                $query = "INSERT INTO products (
                    name, 
                    price, 
                    quantity, 
                    user_id
                ) VALUES (
                    '$name', 
                    $price, 
                    $quantity, 
                    $user_id
                )";

            mysqli_query($conn, $query);
            $product_id = $conn->insert_id;
            $product_q = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id");
            $product_qres = $product_q->fetch_row();
            if (isset($product_qres) && !empty($product_qres)) {
                echo json_encode([
                    'data' => [
                        'id' => $product_qres[0],
                        'name' => $product_qres[1],
                        'price' => $product_qres[2],
                        'quantity' => $product_qres[3],
                    ],
                    'message' => 'product added successfully'
                ]);
                http_response_code(201);
                die();
            }
        }
    }
    // READ
    else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // if fetched from superadmin
        parse_str($_SERVER['QUERY_STRING'], $q);
        $user__id = $q['id'];

        $productId = $q['productId'];
        if (isset($productId) && !empty($productId)) {
            $query = "SELECT * FROM products WHERE id=$productId";
            $query_result = mysqli_query($conn, $query);
            $product = $query_result->fetch_row();

            echo json_encode([
                'data' => [
                    'id' => $product[0],
                    'name' => $product[1],
                    'price' => $product[2],
                    'quantity' => $product[3],
                ]
            ]);
            http_response_code(200);
            die();
        }

        $query = '';
        if (isset($user__id))
            $query = "SELECT * FROM products WHERE user_id=$user__id";
        else
            $query = "SELECT * FROM products WHERE user_id=$user_id";

        $query_result = mysqli_query($conn, $query);
        $products = $query_result->fetch_all();

        $payload = array();
        foreach ($products as $product) {
            array_push($payload, [
                'id' => $product[0],
                'name' => $product[1],
                'price' => $product[2],
                'quantity' => $product[3],
            ]);
        }

        echo json_encode([
            'data' => $payload
        ]);
        http_response_code(200);
        die();
    }
    // DELETE
    else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        parse_str($_SERVER['QUERY_STRING'], $q);
        $id = $q['id'];

        // check if the product exists
        $query1 = "SELECT * FROM products WHERE id=$id";
        $q_res = mysqli_query($conn, $query1);
        $id1 = $q_res->fetch_row()[0];
        if ($id1 != $id) {
            echo json_encode([
                'error' => "product doesn't exist"
            ]);
            http_response_code(400);
            die();
        }


        $query = "DELETE FROM products WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            echo json_encode([
                'data' => [
                    'id' => $id,
                    'message' => 'product deleted successfully'
                ]
            ]);
            http_response_code(200);
            die();
        }
    }
    // UPDATE
    else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        parse_str($_SERVER['QUERY_STRING'], $q);
        $id = $q['id'];

        // check if the product exists
        $query1 = "SELECT * FROM products WHERE id=$id";
        $q_res = mysqli_query($conn, $query1);
        $id1 = $q_res->fetch_row()[0];
        if ($id1 != $id) {
            echo json_encode([
                'error' => "product doesn't exist"
            ]);
            http_response_code(400);
            die();
        }

        $post_body = file_get_contents('php://input');
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $name = $data->name;
            $price = $data->price;
            $quantity = $data->quantity;

            $query = "UPDATE products SET 
                        name='$name', 
                        price=$price, 
                        quantity=$quantity 
                        WHERE id=$id";

            if (mysqli_query($conn, $query)) {
                echo json_encode([
                    'data' => [
                        'update' => [
                            'id' => $id,
                            'name' => $name,
                            'price' => $price,
                            'quantity' => $quantity
                        ],
                        'message' => 'product updated successfully'
                    ]
                ]);
                http_response_code(200);
                die();
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    die();
}
