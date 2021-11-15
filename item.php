<?php

include 'db_connect.php';

if (isset($_GET["action"])) {
    $subaction = "";
    $subsubaction = "";

    if (strpos($_GET["action"], "/") !== false) {
        $parts = explode("/", $_GET["action"]);
        if (count($parts) === 2) {
            $action = $parts[0];
            $subaction = $parts[1];
        } else if (count($parts) === 3) {
            $action = $parts[0];
            $subaction = $parts[1];
            $subsubaction = $parts[2];
        }
    } else {
        $action = $_GET["action"];
    }

    switch ($action) {
        case "getreservedsubtitles":
            $response = array();

            if ($subaction !== "" && $subsubaction !== "") {
                $conn = OpenCon();
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);

                $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    $userid = $row['userid'];

                    $eventid = $conn->real_escape_string($subaction);
                    $itemid  = $conn->real_escape_string($subsubaction);

                    $sql = "SELECT language, userid FROM item_subtitles WHERE eventid = ".$eventid." AND itemid = '". $itemid ."'";
                    $result = $conn->query($sql);
                    
                    //subtitles found
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = $result->fetch_assoc()) {
                            //don't block out users own subtitles
                            if ($row['userid'] != $userid) {    
                                unset($row['userid']);
                            }                         
                            $response['results'][] = $row;
                        }                    
                    } 

                    CloseCon($conn);

                    header('Content-Type: application/json');
                    print(json_encode($response));
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "User is not logged in";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] = "Event or item id not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }

            break;
        case "reservesubtitle":
            $response = array();
            $conn = OpenCon();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $eventid = $conn->real_escape_string($requestData->{'eventid'});
            $itemid = $conn->real_escape_string($requestData->{'itemid'});
            $language = $conn->real_escape_string($requestData->{'language'});

            if ($eventid !== "" && $itemid !== "" && $language !== "") {
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);

                $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    $userid = $row['userid'];

                    $sql = "SELECT COUNT(id) AS id FROM item_subtitles WHERE userid = '".$userid."' AND finalized = FALSE";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) == 1) {
                        $row = $result->fetch_assoc();

                        if ($row['id'] > 2) {
                            $response['error']['item'] = "You can reserve up to 3 items, please submit an item before selecting another";
                            CloseCon($conn);
                            header('Content-Type: application/json');
                            print(json_encode($response));
                            break;
                        }
                    }

                    $sql = "SELECT userid, item_key FROM item_subtitles WHERE eventid = ".$eventid." AND itemid = '". $itemid ."' AND language = '".$language."'";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        $row = $result->fetch_assoc();
                        //subtitle already reserved by user, that is fine
                        if ($userid == $row['userid']) {
                            $response['success']['key'] = $row['item_key'];
                            $response['success']['user'] = "User successfully reserved subtitle";
                        } else {
                            $response['error']['item'] = "Item already reserved";
                        }
                        CloseCon($conn);
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    } else {
                        $key = generateKey();
                        $sql = "INSERT INTO item_subtitles (userid, eventid, itemid, language, item_key) VALUES (".$userid.", ".$eventid.", '".$itemid."', '".$language."', '".$key."')";
                        $result = $conn->query($sql);

                        if ($result === false) {
                            CloseCon($conn);
                            $response['error']['user'] = "Could not reserve subtitle";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                            break;
                        } else {
                            CloseCon($conn);
                            $response['success']['key'] = $key;
                            $response['success']['user'] = "User successfully reserved subtitle";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                        }                    
                    }
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "User is not logged in";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                CloseCon($conn);
                $response['error']['event'] = "Event or item id not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "details":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
                $key = $conn->real_escape_string($subaction);

                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);

                $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    $userid = $row['userid'];

                    //Check key
                    $sql = "SELECT eventid, itemid, finalized, language from item_subtitles WHERE userid = ".$userid." AND item_key = '".$key."'";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        $row = $result->fetch_assoc();
                        $response['results'][] = $row;

                        CloseCon($conn);
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    } else {
                        CloseCon($conn);
                        $response['error']['item'] = "No valid key given";

                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                } else {
                    CloseCon($conn);
                    $response['error']['user'] = "User is not logged in";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "finalize":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
                $key = $conn->real_escape_string($subaction);

                $json_params = file_get_contents("php://input");
                $requestData = array();

                if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                    $requestData = json_decode($json_params);
                }

                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $eupsid = $conn->real_escape_string($requestData->{'eupsid'});
                $manifest = $conn->real_escape_string($requestData->{'manifest'});

                $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    $userid = $row['userid'];

                    //Check key
                    $sql = "SELECT id from item_subtitles WHERE userid = ".$userid." AND item_key = '".$key."'";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        $sql = "UPDATE item_subtitles SET finalized = true, eupsid = '".$eupsid."', subtitle_submitted = NOW() WHERE userid = ".$userid." AND item_key = '".$key."'";
                        $result = $conn->query($sql);
                    
                        if ($result === false) {
                            CloseCon($conn);
                            $response['error']['item'] = "Could not finalize subtitle";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                            break;
                        } else {
                            $sql = "SELECT language FROM item_subtitles WHERE userid = ".$userid." AND item_key = '".$key."'";
                            $result = $conn->query($sql);
                            if (mysqli_num_rows($result) === 1) {
                                $row = $result->fetch_assoc();
                                $lng = $row['language'];

                                $conn2 = OpenCon2();

                                $sql = "SELECT text,start,end FROM subtitles WHERE videoid = '".$manifest."' AND eupsid = '".$eupsid."' AND language = '".$lng."'";
                                $result = $conn2->query($sql);

                                $duration = 0;
                                $characters = 0;

                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $characters += strlen($row['text']);
                                        
                                        $start = $row['start'];
                                        $end = $row['end'];

                                        $duration += ($end - $start);
                                    }    
                                    CloseCon($conn2);
                                }
                            }

                            $sql = "UPDATE item_subtitles SET characters = ".$characters.", milliseconds = ".$duration." WHERE userid = ".$userid." AND item_key = '".$key."'";
                            $result = $conn->query($sql);

                            CloseCon($conn);

                            $response['success']['item'] = "Subtitles finalized";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                        }                    
                    } else {
                        CloseCon($conn);
                        $response['error']['item'] = "No valid key given";

                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                }  else {
                    CloseCon($conn);
                    $response['error']['user'] = "User is not logged in";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "inprogress":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
                $key = $conn->real_escape_string($subaction);

                $json_params = file_get_contents("php://input");
                $requestData = array();

                if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                    $requestData = json_decode($json_params);
                }

                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $eupsid = $conn->real_escape_string($requestData->{'eupsid'});
                $manifest = $conn->real_escape_string($requestData->{'manifest'});

                $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    $userid = $row['userid'];

                    //Check key
                    $sql = "SELECT id from item_subtitles WHERE userid = ".$userid." AND item_key = '".$key."'";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        $sql = "UPDATE item_subtitles SET first_subtitle_saved = true, eupsid = '".$eupsid."', manifest =  '".$manifest."' WHERE userid = ".$userid." AND item_key = '".$key."'";
                        $result = $conn->query($sql);
                    
                        if ($result === false) {
                            CloseCon($conn);
                            $response['error']['item'] = "Could not update item";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                            break;
                        } else {
                            CloseCon($conn);
                            $response['success']['item'] = "Item updated";
                            header('Content-Type: application/json');
                            print(json_encode($response));
                        }                    
                    } else {
                        CloseCon($conn);
                        $response['error']['item'] = "No valid key given";

                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                }  else {
                    CloseCon($conn);
                    $response['error']['user'] = "User is not logged in";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        default:
            break;
    }    
}

function generateKey() {
    $bytes = random_bytes(40);
    return bin2hex($bytes);
}

function isValidJSON($str) {
    json_decode($str);
    return json_last_error() == JSON_ERROR_NONE;
}

?>