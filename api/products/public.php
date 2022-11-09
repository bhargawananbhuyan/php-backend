<?php

require_once '../../cors.php';
require_once '../../config/database.php';

// Get the data for public consumption
try {
    $query = "SELECT * FROM products";
    $products = mysqli_query($conn, $query)->fetch_all();

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
} catch (Exception $e) {
    echo $e->getMessage();
    die();
}
