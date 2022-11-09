<?php
require_once '../../cors.php';
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $query = "SELECT id, name FROM clubs";
        $clubs = mysqli_query($conn, $query)->fetch_all();

        $payload = array();

        foreach ($clubs as $club) {
            array_push($payload, [
                'id' => intval($club[0]),
                'name' => $club[1]
            ]);
        }

        echo json_encode([
            'data' => $payload
        ]);
        http_response_code(200);
        die();
    }
} catch (Exception $e) {
    echo $e->getMessage();
    die();
}
