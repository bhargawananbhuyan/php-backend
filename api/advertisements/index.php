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

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $post_body = file_get_contents('php://input');
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $title = $data->title;
            $body = $data->body;

            // if posted by superadmin
            parse_str($_SERVER['QUERY_STRING'], $q);
            $user__id = $q['id'];

            $query = '';
            if (isset($user__id))
                $query = "INSERT INTO advertisements (
                     title, 
                     body, 
                     user_id
                 ) VALUES (
                     '$title', 
                     '$body',  
                     $user__id
                 )";
            else
                $query = "INSERT INTO advertisements (
                     title, 
                     body, 
                     user_id
                 ) VALUES (
                    '$title', 
                     '$body',  
                     $user_id
                 )";

            mysqli_query($conn, $query);
            $ad_id = $conn->insert_id;
            $ad_q = mysqli_query($conn, "SELECT * FROM advertisements WHERE id=$ad_id");
            $ad_qres = $ad_q->fetch_row();
            if (isset($ad_qres) && !empty($ad_qres)) {
                echo json_encode([
                    'data' => [
                        'id' => $ad_qres[0],
                        'title' => $ad_qres[1],
                        'body' => $ad_qres[2],
                    ],
                    'message' => 'advertisement added successfully'
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

        $adId = $q['adId'];
        if (isset($adId) && !empty($adId)) {
            $query = "SELECT * FROM advertisements WHERE id=$adId";
            $query_result = mysqli_query($conn, $query);
            $ad = $query_result->fetch_row();

            echo json_encode([
                'data' => [
                    'id' => $ad[0],
                    'title' => $ad[1],
                    'body' => $ad[2],
                ]
            ]);
            http_response_code(200);
            die();
        }

        $query = '';
        if (isset($user__id))
            $query = "SELECT * FROM advertisements WHERE user_id=$user__id";
        else
            $query = "SELECT * FROM advertisements WHERE user_id=$user_id";

        $query_result = mysqli_query($conn, $query);
        $advertisements = $query_result->fetch_all();

        $payload = array();
        foreach ($advertisements as $advertisement) {
            array_push($payload, [
                'id' => $advertisement[0],
                'title' => $advertisement[1],
                'body' => $advertisement[2],
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

        // check if the advertisement exists
        $query1 = "SELECT * FROM advertisements WHERE id=$id";
        $q_res = mysqli_query($conn, $query1);
        $id1 = $q_res->fetch_row()[0];
        if ($id1 != $id) {
            echo json_encode([
                'error' => "advertisement doesn't exist"
            ]);
            http_response_code(400);
            die();
        }


        $query = "DELETE FROM advertisements WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            echo json_encode([
                'data' => [
                    'id' => $id,
                    'message' => 'advertisement deleted successfully'
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

        // check if the advertisements exists
        $query1 = "SELECT * FROM advertisements WHERE id=$id";
        $q_res = mysqli_query($conn, $query1);
        $id1 = $q_res->fetch_row()[0];
        if ($id1 != $id) {
            echo json_encode([
                'error' => "advertisement doesn't exist"
            ]);
            http_response_code(400);
            die();
        }

        $post_body = file_get_contents('php://input');
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $title = $data->title;
            $body = $data->body;

            $query = "UPDATE advertisements SET title='$title', body='$body' WHERE id=$id";
            if (mysqli_query($conn, $query)) {
                echo json_encode([
                    'data' => [
                        'update' => [
                            'id' => $id,
                            'title' => $title,
                            'body' => $body
                        ],
                        'message' => 'advertisement updated successfully'
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
