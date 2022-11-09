<?php

require_once '../../cors.php';
require_once '../../config/database.php';

// Get the data for public consumption
try {
    $query = "SELECT p.id, p.title, p.body, u.name FROM posts AS p 
                JOIN users as u
                ON p.created_by=u.id";
    $posts = mysqli_query($conn, $query)->fetch_all();

    $payload = array();
    foreach ($posts as $post) {
        array_push($payload, [
            'id' => intval($post[0]),
            'title' => $post[1],
            'body' => $post[2],
            'created_by' => $post[3]
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
