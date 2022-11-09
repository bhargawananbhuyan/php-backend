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

    // READ
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // is called from admin -> parse query string to get user id

        // get clubs where the logged in student isn't a member of
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $qs);
            $q = $qs['q'];

            if ($q == "all") {
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
            } else {
                $clubId = intval($q);
                $query = "SELECT id, name FROM clubs WHERE id=$clubId AND creator_id=$user_id";

                $club = mysqli_query($conn, $query)->fetch_row();

                echo json_encode([
                    'data' => [
                        'id' => intval($club[0]),
                        'name' => $club[1]
                    ]
                ]);
                http_response_code(200);
                die();
            }
        }


        $query = "SELECT clubs.id, clubs.name, users.id, users.name 
        FROM clubs JOIN users WHERE clubs.creator_id=users.id AND creator_id=$user_id";

        // created clubs
        $clubs = mysqli_query(
            $conn,
            $query
        )->fetch_all();

        if (!empty($clubs)) {
            $payload = array();
            foreach ($clubs as $club) {
                $club_id = $club[0];
                $members = mysqli_query(
                    $conn,
                    "SELECT * FROM clubs_students as cs JOIN users as u WHERE cs.user_id=u.id AND cs.club_id=$club_id"
                )->fetch_all();
                $members_payload = array();
                foreach ($members as $member) {
                    array_push($members_payload, [
                        'id' => intval($member[3]),
                        'name' => $member[4],
                        'email' => $member[5],
                        'contact' => $member[6],
                    ]);
                }

                array_push($payload, [
                    'id' => intval($club_id),
                    'name' => $club[1],
                    'members' => $members_payload
                ]);
            }
            echo json_encode([
                'user' => [
                    'id' => intval($clubs[0][2]),
                    'name' => $clubs[0][3],
                ],
                'data' => $payload
            ]);
            http_response_code(200);
            die();
        } else {
            echo json_encode([
                'error' => 'no clubs found'
            ]);
            http_response_code(404);
            die();
        }
    }
    // CREATE
    else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $post_body = file_get_contents("php://input");
        if (isset($post_body) && !empty($post_body)) {
            $data = json_decode($post_body);

            $name = $data->name;

            $query = "INSERT INTO clubs (name, creator_id) VALUES ('$name', $user_id)";
            if (mysqli_query($conn, $query)) {
                $club_inserted = $conn->insert_id;
                $new_club = mysqli_query($conn, "SELECT * FROM clubs WHERE id=$club_inserted")->fetch_row();
                echo json_encode([
                    'data' => [
                        'id' => intval($new_club[0]),
                        'name' => $new_club[1],
                        'creator_id' => intval($new_club[2])
                    ]
                ]);
                http_response_code(200);
                die();
            }
        }
    }
    // UPDATE
    else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        // get clubId
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);
            $club_id = $q['clubId'];

            // parse body
            $rawBody = file_get_contents("php://input");
            if (isset($rawBody) && !empty($rawBody)) {
                $data = json_decode($rawBody);

                $name = $data->name;

                $query = "UPDATE clubs SET name='$name' WHERE id=$club_id AND creator_id=$user_id";
                if (mysqli_query($conn, $query)) {
                    json_encode([
                        'data' => [
                            'id' => intval($club_id),
                            'name' => $name
                        ]
                    ]);
                    http_response_code(200);
                    die();
                }
            }
        }
    }
    // DELETE
    else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        // get club id
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $q);
            $id = $q['clubId'];
            $query = "DELETE FROM clubs WHERE id=$id";
            if (mysqli_query($conn, $query)) {
                echo json_encode([
                    'data' => [
                        'id' => intval($id)
                    ],
                    'message' => 'club deleted successfully'
                ]);
                die();
            }
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
