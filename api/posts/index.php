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
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $qs);

                $postId = $qs['postId'];
                $q = $qs['user'];

                $query = '';
                if (!empty($q) && $q == 'admin')
                    $query = "SELECT * FROM posts WHERE id=$postId";
                else
                    $query = "SELECT * FROM posts WHERE id=$postId AND created_by=$user_id";

                $post = mysqli_query(
                    $conn,
                    $query
                )->fetch_row();
                echo json_encode([
                    'data' => [
                        'id' => intval($post[0]),
                        'title' => $post[1],
                        'body' => $post[2],
                        'created_by' => intval($post[3])
                    ]
                ]);
                http_response_code(200);
                die();
            }

            $query = "SELECT * FROM posts WHERE created_by=$user_id";

            /* Get all posts for signed in user */
            $posts = mysqli_query($conn, $query)->fetch_all();
            $payload = array();
            foreach ($posts as $post) {
                array_push($payload, [
                    'id' => intval($post[0]),
                    'title' => $post[1],
                    'body' => $post[2],
                ]);
            }
            echo json_encode([
                'data' => $payload
            ]);
            http_response_code(200);
            die();
            break;

        case "POST":
            /* Get post body */
            $rawBody = file_get_contents("php://input");
            if (isset($rawBody) && !empty($rawBody)) {
                /* Parse body */
                $data = json_decode($rawBody);

                $title = $data->title;
                $body = $data->body;

                $query = "INSERT INTO posts (
                    title,
                    body,
                    created_by
                ) VALUES (
                    '$title',
                    '$body',
                    $user_id
                )";

                if (mysqli_query($conn, $query)) {
                    echo json_encode([
                        'data' => [
                            'id' => intval($conn->insert_id),
                            'title' => $title,
                            'body' => $body,
                            'created_by' => intval($user_id)
                        ]
                    ]);
                    http_response_code(201);
                    die();
                }
            }
            break;

        case "PATCH":
            /* Get post id */
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $qs);
                $postId = $qs['postId'];

                /* Get and parse post body */
                $rawBody = file_get_contents("php://input");
                if (isset($rawBody) && !empty($rawBody)) {
                    $data = json_decode($rawBody);

                    $title = $data->title;
                    $body = $data->body;

                    $query = "UPDATE posts SET
                        title = '$title',
                        body = '$body'
                        WHERE id=$postId
                    ";

                    if (mysqli_query($conn, $query)) {
                        echo json_encode([
                            'data' => [
                                'id' => $postId,
                                'title' => $title,
                                'body' => $body,
                                'created_by' => $user_id
                            ]
                        ]);
                        http_response_code(200);
                        die();
                    }
                }
            }
            break;

        case "DELETE":
            /* Get post id */
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $qs);
                $postId = $qs['postId'];

                $query = "DELETE FROM posts WHERE id=$postId";
                if (mysqli_query($conn, $query)) {
                    echo json_encode([
                        'data' => [
                            'id' => intval($postId)
                        ]
                    ]);
                    http_response_code(200);
                    die();
                }
            }
            break;

        default:
            echo json_encode([
                'error' => 'method not allowed'
            ]);
            http_response_code(405);
            die();
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
