<?php

include 'Mail.php';
include 'Mail/mime.php';

include 'db_connect.php';

if (isset($_GET["action"])) {
    $subaction = "";

    if (strpos($_GET["action"], "/") !== false) {
        $parts = explode("/", $_GET["action"]);
        if (count($parts) === 2) {
            $action = $parts[0];
            $subaction = $parts[1];
        }
    } else {
        $action = $_GET["action"];
    }

    switch ($action) {
        case "getassignedvideos":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            
            $sql = "SELECT s.userid, u.reviewer FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //reviewer user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is reviewer
                if ($row['reviewer'] == true) {
                    $reviewerid = $row['userid'];
                    $sql = "SELECT i.id, i.item_key, i.eupsid, i.language, i.characters, i.manifest, i.eventid, i.itemid, i.reviewerid, i.review_done, i.review_quality, i.review_appropriate, i.review_flow, i.review_grammatical, i.review_comments, u.username FROM item_subtitles AS i LEFT JOIN users AS u ON i.userid = u.userid WHERE finalized = TRUE AND (eventid = 5 OR eventid = 6 OR eventid = 7 OR eventid = 8) AND i.reviewerid = ".$reviewerid;
                    $result = $conn->query($sql);

                    while ($row = $result->fetch_assoc()) {
                        $response['results'][] = $row;
                    }
                    CloseCon($conn);
                    
                    header('Content-Type: application/json');
                    print(json_encode($response));
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "Not allowed";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                CloseCon($conn);
                $response['error']['user'] = "Not allowed";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "review":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $itemid = $conn->real_escape_string($subaction);

            $sql = "SELECT s.userid, u.reviewer FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //reviewer user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is reviewer
                if ($row['reviewer'] == true) {
                    $key = generateKey();

                    $sql = "UPDATE item_subtitles SET review_key = '".$key."' WHERE id = ".$itemid;
                    $result = $conn->query($sql);

                    if ($result === false) {
                        CloseCon($conn);
                        $response['error']['reviewer'] = "Could not review subtitle";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                        break;
                    } else {
                        CloseCon($conn);
                        $response['success']['key'] = $key;
                        $response['success']['reviewer'] = "Reviewer can review subtitle";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }    
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "Not allowed";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                CloseCon($conn);
                $response['error']['user'] = "Not allowed";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "details":
            $conn = OpenCon();
            $response = array();
    
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $key = $conn->real_escape_string($subaction);
    
            $sql = "SELECT s.userid, u.reviewer FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
    
            //reviewer user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                    
                //check if the user is reviewer
                if ($row['reviewer'] == true) {
                    $sql = "SELECT eventid, itemid, review_done, language from item_subtitles WHERE review_key = '".$key."'";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        $row = $result->fetch_assoc();
                        $response['results'][] = $row;

                        CloseCon($conn);
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    } else {
                        CloseCon($conn);
                        $response['error']['review'] = "No valid key given";

                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "Not allowed";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                CloseCon($conn);
                $response['error']['user'] = "Not allowed";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "submit":
            $conn = OpenCon();
            $response = array();
    
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $reviewid = $conn->real_escape_string($subaction);
    
            $sql = "SELECT s.userid, u.reviewer FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
    
            //reviewer user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                    
                //check if the user is reviewer
                if ($row['reviewer'] == true) {
                    $json_params = file_get_contents("php://input");
                    $requestData = array();

                    if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                        $requestData = json_decode($json_params);
                    }

                    $general_quality = $conn->real_escape_string($requestData->{'general_quality'});
                    $appropriateness = $conn->real_escape_string($requestData->{'appropriateness'});
                    $subtitles_flow = $conn->real_escape_string($requestData->{'subtitles_flow'});
                    $grammar = $conn->real_escape_string($requestData->{'grammar'});
                    $comments = $conn->real_escape_string($requestData->{'comments'});

                    $sql = "UPDATE item_subtitles SET review_done = TRUE, review_quality = '".$general_quality."', review_appropriate = '".$appropriateness."', review_flow = '".$subtitles_flow."', review_grammatical = '".$grammar."', review_comments = '".$comments."' WHERE review_key = '".$reviewid."'";
                    $result = $conn->query($sql);

                    if ($result === false) {
                        CloseCon($conn);
                        $response['error']['review'] = "Could not submit review";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                        break;
                    } else {
                        CloseCon($conn);

                        $response['success']['review'] = "Review submitted";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }

                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "Not allowed";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                CloseCon($conn);
                $response['error']['user'] = "Not allowed";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        default:
            header("HTTP/1.0 404 Not Found");
    }
} else {    
    header("HTTP/1.0 404 Not Found");
}

function generateKey() {
    $bytes = random_bytes(40);
    return bin2hex($bytes);
}

function isValidJSON($str) {
    json_decode($str);
    return json_last_error() == JSON_ERROR_NONE;
}