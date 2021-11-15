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
        case "registrations":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
        
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $eventid = $conn->real_escape_string($subaction);

                $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);

                //admin user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    
                    //check if the user is admin
                    if ($row['admin'] == true) {
                        $sql = "SELECT r.registrationid, r.fullname, r.email, r.native_languages, r.foreign_languages, r.accepted, r.rejected, r.nationality, r.signup_hash, r.signupfirststep, r.signupsecondstep, u.username, u.europeana_known, u.location, u.gender, u.age, u.professional_background, u.email_updates FROM registrations AS r LEFT JOIN users as u ON r.email = u.email WHERE eventid = ".$eventid;
                        $result = $conn->query($sql);

                        //people are registered
                        if (mysqli_num_rows($result) > 0) {
                            //loop over all registrations                    
                            while ($row = $result->fetch_assoc()) {
                                $response['results'][] = $row;
                            }

                            CloseCon($conn);
                            header('Content-Type: application/json');
                            print(json_encode($response));
                        } else {
                            CloseCon($conn);
                            $response['results'] = [];
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
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "approve":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $registrationid = $conn->real_escape_string($subaction);

            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $sql = "UPDATE registrations SET accepted = TRUE WHERE registrationid = ". $registrationid;
                    $result = $conn->query($sql);

                    if(!$result) {
                        $response['error']['database'] = "Database error";
                    } else {
                        $sql = "SELECT fullname, email FROM registrations WHERE registrationid = ".$registrationid;
                        $result = $conn->query($sql);

                        if (mysqli_num_rows($result) === 1) {
                            $response['success']['message'] = "Registration approved";
                            $row = $result->fetch_assoc();
                            $fullname = $row['fullname'];
                            $email = $row['email'];

                            // sent approved email
                            $approvedHTMLMail = file_get_contents("mail/templates/approved_rome.html");
                            $approvedHTMLMail = str_replace("{{fullname}}", $fullname, $approvedHTMLMail);

                            $approvedTextMail = file_get_contents("mail/templates/approved_rome.txt");
                            $approvedTextMail = str_replace("{{fullname}}", $fullname, $approvedTextMail);

                            sendMail($approvedTextMail, $approvedHTMLMail, "You are in!", $email);
                        } else {
                            $response['error']['database'] = "Database error";
                        }
                    }

                    header('Content-Type: application/json');
                    print(json_encode($response));

                    CloseCon($conn);
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
        case "reject":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $registrationid = $conn->real_escape_string($subaction);
            
            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $sql = "UPDATE registrations SET rejected = TRUE WHERE registrationid = ". $registrationid;
                    $result = $conn->query($sql);

                    if(!$result) {
                        $response['error']['database'] = "Database error";
                    } else {
                        $sql = "SELECT fullname, email FROM registrations WHERE registrationid = ".$registrationid;
                        $result = $conn->query($sql);

                        if (mysqli_num_rows($result) === 1) {
                            $response['success']['message'] = "Registration rejected";
                            $row = $result->fetch_assoc();
                            $fullname = $row['fullname'];
                            $email = $row['email'];

                            // sent rejected email
                            $rejectedHTMLMail = file_get_contents("mail/templates/rejected_rome.html");
                            $rejectedHTMLMail = str_replace("{{fullname}}", $fullname, $rejectedHTMLMail);

                            $rejectedTextMail = file_get_contents("mail/templates/rejected_rome.txt");
                            $rejectedTextMail = str_replace("{{fullname}}", $fullname, $rejectedTextMail);

                            sendMail($rejectedTextMail, $rejectedHTMLMail, "You are on a waiting list...", $email);
                        } else {
                            $response['error']['database'] = "Database error";
                        }
                    }

                    header('Content-Type: application/json');
                    print(json_encode($response));

                    CloseCon($conn);
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
        case "allowaccounts":
            //send sign up for every account of the event that has not yet a sign_up hash in the database
            $conn = OpenCon();
            $response = array();
    
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $eventid = $conn->real_escape_string($subaction);

            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
    
            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                    
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $sql = "SELECT * FROM registrations WHERE accepted = TRUE AND signup_hash IS NULL AND signupfirststep = FALSE AND eventid = ".$eventid;
                    $result = $conn->query($sql);

                    $counter = 0;

                    if (mysqli_num_rows($result) > 0) {
                        //loop over all registrations for this event that are approved and did not yet receive their signup hash (and neither went through the first / second step)                   
                        while ($row = $result->fetch_assoc()) {
                            $counter++;
                            $registrationid = $row['registrationid'];
                            $fullname = $row['fullname'];
                            $email = $row['email'];

                            //update user so he can create an account
                            $signup_hash = generateSignupId();

                            $sql = "UPDATE registrations SET signup_hash = '".$signup_hash."' WHERE registrationid = ". $registrationid;
                            $result2 = $conn->query($sql);
                            
                            if(!$result2) {
                                $response['error']['database'] = "Database error";
                                header('Content-Type: application/json');
                                print(json_encode($response));    
                                break;
                            } else {
                                $response['success']['message'] = "Registration updated";

                                // sent welcome email
                                $createacountHTMLMail = file_get_contents("mail/templates/createaccount.html");
                                $createacountHTMLMail = str_replace("{{fullname}}", $fullname, $createacountHTMLMail);
                                $createacountHTMLMail = str_replace("{{signup_hash}}", $signup_hash, $createacountHTMLMail);

                                $createacountTextMail = file_get_contents("mail/templates/createaccount.txt");
                                $createacountTextMail = str_replace("{{fullname}}", $fullname, $createacountTextMail);
                                $createacountTextMail = str_replace("{{signup_hash}}", $signup_hash, $createacountTextMail);

                                sendMail($createacountTextMail, $createacountHTMLMail, "Complete your subtitle-a-thon account", $email);
                            }
                        }  
                    }

                    //check also users who already have an account but also signed up for this event
                    $sql = "SELECT * FROM registrations AS r LEFT JOIN users AS u ON r.email = u.email WHERE r.accepted = TRUE AND r.signup_hash IS NULL AND r.signupfirststep = TRUE AND r.signupsecondstep = TRUE AND r.eventid = ".$eventid." AND NOT EXISTS (SELECT 1 FROM users_events AS ue WHERE ue.eventid = ".$eventid." AND ue.userid = u.userid)";
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) > 0) {
                        //loop over all registrations that have already an created account but don't have this event in the users_event table
                        while ($row = $result->fetch_assoc()) {
                            $counter++;
                            $userid = $row['userid'];
                            $fullname = $row['fullname'];
                            $email = $row['email'];
                            $sql2 = "INSERT INTO users_events(userid, eventid) VALUES (".$userid.", ".$eventid.")";
                            $result2 = $conn->query($sql2);

                            if(!$result2) {
                                $response['error']['database'] = "Database error";
                                header('Content-Type: application/json');
                                print(json_encode($response));    
                                break;
                            } else {
                                // sent user linked to event email
                                $createacountHTMLMail = file_get_contents("mail/templates/userlinkedtoevent.html");
                                $createacountHTMLMail = str_replace("{{fullname}}", $fullname, $createacountHTMLMail);
 
                                $createacountTextMail = file_get_contents("mail/templates/userlinkedtoevent.txt");
                                $createacountTextMail = str_replace("{{fullname}}", $fullname, $createacountTextMail);

                                sendMail($createacountTextMail, $createacountHTMLMail, "You joined the Subtitle-a-thon Rome event", $email);
                            }
                        }
                    }

                    CloseCon($conn);
                    $response['success']['message'] = "Send out ".$counter." sign ups";
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
        case "eventdetails":
            $response = array();
    
            if ($subaction !== "") {
                $conn = OpenCon();
        
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $eventid = $conn->real_escape_string($subaction);
            
                $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);
        
                //admin user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                        
                    //check if the user is admin
                    if ($row['admin'] == 1) {
                        $sql = "SELECT * FROM events where eventid = ".$eventid;
                        $result = $conn->query($sql);

                        if (mysqli_num_rows($result) > 0) {
                            $row = $result->fetch_assoc();
                            $response['success'] = $row;

                            $languages = explode(",",$row['allowed_languages']);
                            $response['success']['allowed_languages'] = $languages;

                            CloseCon($conn);
                            header('Content-Type: application/json');
                            print(json_encode($response));
                        } else {
                            CloseCon($conn);
                            $response['error']['user'] = "Not found";
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
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "seteventdetails":
            $conn = OpenCon();
            $response = array();
    
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $eventid = $conn->real_escape_string($subaction);

            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
    
            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                    
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $json_params = file_get_contents("php://input");
                    $requestData = array();

                    if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                        $requestData = json_decode($json_params);
                    }

                    $europeana_collection = $conn->real_escape_string($requestData->{'europeanasetid'});
                    $languagesstring = "";
                    foreach ($requestData->{'allowedlanguages'} as $language) {
                        $languagesstring .= $language->iso . ",";
                    }

                    if (strlen($languagesstring) > 0) {
                        $languagesstring = substr($languagesstring, 0, strlen($languagesstring)-1);
                    }

                    $sql = "UPDATE events SET europeana_collection = '".$europeana_collection."', allowed_languages = '".$languagesstring."' WHERE eventid = ".$eventid;
                    $result = $conn->query($sql);

                    if(!$result) {
                        $response['error']['database'] = "Database error";
                    } else {
                        $response['success']['message'] = "Account created";
                    }

                    CloseCon($conn);
                    $response['success']['event'] = "Event updated";
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
        case "getsubmittedvideos":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
        
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $eventid = $conn->real_escape_string($subaction);
    
                $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);
        
                //admin user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                        
                    //check if the user is admin
                    if ($row['admin'] == 1) {
                        $sql = "SELECT i.id, i.item_key, i.eupsid, i.language, i.characters, i.manifest, i.eventid, i.itemid, i.reviewerid, i.review_done, i.review_quality, i.review_appropriate, i.review_flow, i.review_grammatical, i.review_comments, u.username FROM item_subtitles AS i LEFT JOIN users AS u ON i.userid = u.userid WHERE finalized = TRUE AND eventid = ".$eventid;
                        $result = $conn->query($sql);

                        $conn2 = OpenCon2();

                        while ($row = $result->fetch_assoc()) {
                            $sql = "SELECT id FROM embeds WHERE videoid = '".$row['manifest']."' AND eupsid = '".$row['eupsid']."'";
                            $result2 = $conn2->query($sql);

                            if (mysqli_num_rows($result2) === 1) {
                                $row2 = $result2->fetch_assoc();
                                $row['embed'] = $row2['id'];

                                $sql = "UPDATE embeds SET width = 640, height = 480 WHERE videoid = '".$row['manifest']."' AND eupsid = '".$row['eupsid']."'";
                                $result3 = $conn2->query($sql);
                            }
                            $response['results'][] = $row;
                        }
                        CloseCon($conn);
                        CloseCon($conn2);
                    
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
            } else {
                $response['error']['event'] = "key not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "getreviewers":
            $conn = OpenCon();
            $response = array();
        
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $eventid = $conn->real_escape_string($subaction);
    
            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
        
            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                        
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $sql = "SELECT userid, username FROM users WHERE reviewer = TRUE";
                    $result = $conn->query($sql);
                    
                    while ($row = $result->fetch_assoc()) {
                        $response['results'][] = $row;
                    }

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
        case "setreviewer":
            $conn = OpenCon();
            $response = array();
            
            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
        
            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);
            
            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                            
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $json_params = file_get_contents("php://input");
                    $requestData = array();

                    if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                        $requestData = json_decode($json_params);
                    }

                    $reviewer = $conn->real_escape_string($requestData->{'reviewer'});
                    $itemid = $conn->real_escape_string($requestData->{'itemid'});

                    $sql = "UPDATE item_subtitles SET reviewerid = ".$reviewer." WHERE id = ".$itemid;
                    $result = $conn->query($sql);

                    if(!$result) {
                        $response['error']['database'] = "Database error";
                    } else {
                        $response['success']['message'] = "Reviewer assigned";
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
        case "sendopeningsessiondetails":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $registrationid = $conn->real_escape_string($subaction);

            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $sql = "SELECT fullname, email FROM registrations WHERE registrationid = ".$registrationid;
                    $result = $conn->query($sql);

                    if (mysqli_num_rows($result) === 1) {
                        $row = $result->fetch_assoc();
                        $fullname = $row['fullname'];
                        $email = $row['email'];
                        $response['success']['message'] = $fullname;

                        // sent opening session details email
                        $HTMLMail = file_get_contents("mail/templates/rome/opening_session_details.html");
                        $HTMLMail = str_replace("{{fullname}}", $fullname, $HTMLMail);

                        $TextMail = file_get_contents("mail/templates/rome/opening_session_details.txt");
                        $TextMail = str_replace("{{fullname}}", $fullname, $TextMail);

                        sendMail($TextMail, $HTMLMail, "Are you ready?", $email);
                    } else {
                        $response['error']['database'] = "Database error";
                    }
            
                    header('Content-Type: application/json');
                    print(json_encode($response));

                    CloseCon($conn);
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
        case "sendconfirmationlink":
                $conn = OpenCon();
                $response = array();
    
                $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                $registrationid = $conn->real_escape_string($subaction);
    
                $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                $result = $conn->query($sql);
    
                //admin user is authenticated
                if (mysqli_num_rows($result) === 1) {
                    $row = $result->fetch_assoc();
                    
                    //check if the user is admin
                    if ($row['admin'] == 1) {
                        $sql = "SELECT fullname, email FROM registrations WHERE registrationid = ".$registrationid;
                        $result = $conn->query($sql);
    
                        if (mysqli_num_rows($result) === 1) {
                            $row = $result->fetch_assoc();
                            $fullname = $row['fullname'];
                            $email = $row['email'];
                            $response['success']['message'] = $fullname;
    
                            // sent opening session details email
                            $HTMLMail = file_get_contents("mail/templates/rome/confirmation_link.html");
                            $HTMLMail = str_replace("{{fullname}}", $fullname, $HTMLMail);
    
                            $TextMail = file_get_contents("mail/templates/rome/confirmation_link.txt");
                            $TextMail = str_replace("{{fullname}}", $fullname, $TextMail);
    
                            sendMail($TextMail, $HTMLMail, "Kick-off session details", $email);
                        } else {
                            $response['error']['database'] = "Database error";
                        }
                
                        header('Content-Type: application/json');
                        print(json_encode($response));
    
                        CloseCon($conn);
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
        case "downloadsubtitles":
            $conn = OpenCon();
            $response = array();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $eupsid = $conn->real_escape_string($requestData->{'eupsid'});
            $manifest = $conn->real_escape_string($requestData->{'manifest'});
            $language = $conn->real_escape_string($requestData->{'language'});
            $id = $conn->real_escape_string($requestData->{'id'});

            $sql = "SELECT s.userid, u.admin FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //admin user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //check if the user is admin
                if ($row['admin'] == 1) {
                    $conn2 = OpenCon2();

                    $sql = "SELECT * from subtitles WHERE eupsid = '".$eupsid."' AND videoid = '".$manifest."' AND language = '".$language."' ORDER BY start";
                    $result = $conn2->query($sql);

                    $subtitleFile = "WEBVTT Kind: subtitles; Language: ".substr($language, 0, 2)."\r\n";
                    $manifestUrl = parse_url($manifest);
                    $manifestQueryParts = explode("=", $manifestUrl['query']);
                    $fileName = substr(urldecode($manifestQueryParts[1]), 0, strpos(urldecode($manifestQueryParts[1]), "?"))."_".substr($language, 0, 2).".vtt";

                    //fix for more then 2 line breaks, vtt requires this to have the same timecode as multiple newlines are not supported
                    while ($row = $result->fetch_assoc()) {
                        if (preg_match_all("/(\\r\\n|\\r|\\n){2,}/", $row['text'], $matches, PREG_OFFSET_CAPTURE)) {
                            $start = 0;

                            print_r($row['text']);

                            print_r($matches);

                            foreach ($matches[0] as $key => $match) {
                                $length = strlen(utf8_decode(substr($row['text'],$start,($match[1]-$start))));
                                $text = trim(substr($row['text'],$start,$length));
                                $start = $matches[1][$key][1];
                                print("next start = ".$start."\r\n");

                                $subtitleFile .= "\r\n".formatTime($row['start'])." --> ".formatTime($row['end'])."\r\n".$text."\r\n";
                            }

                            print("last start = ".$start."\r\n");
                            $text = trim(substr($row['text'],$start));
                            print("last text from ".$row['text']." = ".$text."\r\n");
                            $subtitleFile .= "\r\n".formatTime($row['start'])." --> ".formatTime($row['end'])."\r\n".$text."\r\n";
                        } else {
                            $subtitleFile .= "\r\n".formatTime($row['start'])." --> ".formatTime($row['end'])."\r\n".$row['text']."\r\n";
                        }
                    }
                    CloseCon($conn);
                    CloseCon($conn2);

                    header("Cache-Control: private");
                    header("Content-Type: text/plain; charset=utf-8");
                    header("Content-Length: ".mb_strlen($subtitleFile, '8bit'));
                    header("Access-Control-Expose-Headers: Content-Disposition");
                    header("Content-Disposition: attachment; filename=".$fileName);
                    print($subtitleFile);
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

function isValidJSON($str) {
    json_decode($str);
    return json_last_error() == JSON_ERROR_NONE;
}

function sendMail($text, $html, $subject, $to) {
    $crlf = "\n";
    $hdrs = array(
                'From' => '"Subtitle-a-thon" <no-reply@subtitleathon.eu>',
                'Reply-To' => 'no-reply@subtitleathon.eu',
                'To' => $to,
                'Subject' => $subject,
                'Date' => date("r"),
                'Content-Transfer-Encoding' => '8bit',
                'Content-Type' => 'text/html; charset="UTF-8"'
                );

    $mime = new Mail_mime(array('eol' => $crlf));

    $mime->setTXTBody($text);
    $mime->setHTMLBody($html);

    $mimeparams['text_encoding']="8bit";
    $mimeparams['text_charset']="UTF-8";
    $mimeparams['html_charset']="UTF-8";
    $mimeparams['head_charset']="UTF-8";

    $body = $mime->get($mimeparams);
    $hdrs = $mime->headers($hdrs);

    $mailParams = "-f no-reply@subtitleathon.eu";

    $mail =& Mail::factory('mail', $mailParams);
    $result = $mail->send($to, $hdrs, $body);
}

function generateSignupId() {
    $bytes = random_bytes(32);
    return bin2hex($bytes);
}

function formatTime($time) {
    $time = $time < 0 ? 0 : $time;
  
    $hours = floor($time / 3600000);
    $minutes = floor($time / 60000);
    $seconds = floor(($time % 60000) / 1000);
    
    $timestring = $hours > 0 ? $hours.":" : "";
    $timestring .= $minutes < 10 ? "0".$minutes.":" : $minutes.":";
    $timestring .= $seconds < 10 ? "0".$seconds : $seconds;
  
    $milliseconds = floor($time % 1000);
    if ($milliseconds < 10) { 
        $timestring .= ".00" . $milliseconds;
    } else if ($milliseconds < 100) {
        $timestring .= ".0" . $milliseconds;
    } else {
        $timestring .= "." . $milliseconds;
    }  
  
    return $timestring;
  }

?>