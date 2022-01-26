<?php

include 'Mail.php';
include 'Mail/mime.php';

include 'db_connect.php';

$responseWaitingTime = 500000; // make each request take at leas half a second

$europeanaAPIPreUrl = "https://api.europeana.eu/set/";
$europeanaAPIPostURL = ".json?wskey=api2demo&profile=itemDescriptions";

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
        case "signup":
            $conn = OpenCon();
            $response = array();

            $signupid = $conn->real_escape_string($subaction);

            //check if the signup hash is valid
            $sql = "SELECT signupfirststep, signupsecondstep, email FROM registrations WHERE signup_hash = '".$signupid."'";
            $result = $conn->query($sql);
            CloseCon($conn);

            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                if ($row['signupfirststep'] == '1') {
                    $response['success']['firststepcompleted'] = true;
                }

                if ($row['signupsecondstep'] == '1') {
                    $response['success']['secondstepcompleted'] = true;
                }

                //show signup page
                $response['success']['user'] = "signup can be completed";
                $response['success']['signupid'] = $signupid;
                $response['success']['email'] = $row['email'];

                header('Content-Type: application/json');
                print(json_encode($response));
            } else {
                //not a valid signup id
                $response['error']['user'] = "signup id not valid";

                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "create":
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $username = $conn->real_escape_string($requestData->{'username'});
            $email = $conn->real_escape_string($requestData->{'email'});
            $password = $conn->real_escape_string($requestData->{'password'});
            $signupid = $conn->real_escape_string($requestData->{'signupid'});

            $sql = "SELECT * FROM users WHERE username = '".$username."'";
            $result = $conn->query($sql);

            //username already exists
            if (mysqli_num_rows($result) > 0) {
                CloseCon($conn);

                $response['error']['username'] = "User name already exists";

                header('Content-Type: application/json');
                print(json_encode($response));
                break;
            }

            $sql = "SELECT * FROM users WHERE email = '".$email."'";
            $result = $conn->query($sql); 

            //already account under this email address
            if (mysqli_num_rows($result) > 0) {
                CloseCon($conn);

                $response['error']['email'] = "Email address already in use";

                header('Content-Type: application/json');
                print(json_encode($response));
                break;                
            }

            //validate sign up hash
            $sql = "SELECT * FROM registrations WHERE email = '".$email."' AND signup_hash = '".$signupid."'";
            $result = $conn->query($sql); 
            $eventid = -1;

            //no valid combination found
            if (mysqli_num_rows($result) == 0) {
                $response['error']['email'] = "Email / sign up id not found";

                header('Content-Type: application/json');
                print(json_encode($response));
                break;     
            } else {
                $row = $result->fetch_assoc();
                $eventid = $row['eventid'];
            }

            //hash password with a pepper
            $pwd_peppered = hash_hmac("sha256", $password, getPepper());
            $pwd_hashed = password_hash($pwd_peppered, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users(username, email, password) VALUES ('".$username."', '".$email."', '".$pwd_hashed."')";
            $result = $conn->query($sql);
            
            if(!$result) {
                $response['error']['database'] = "Database error";
            } else {
                $response['success']['message'] = "Account created";
            }

            $sql = "SELECT userid FROM users WHERE username = '".$username."' AND email = '".$email."'";
            $result = $conn->query($sql);
            $userid = -1;

            if (mysqli_num_rows($result) > 0) { 
                $r = $result->fetch_assoc();
                $userid = $r['userid'];
            }

            $sql = "INSERT INTO users_events(userid, eventid) VALUES (".$userid.", ".$eventid.")";
            $result = $conn->query($sql);

            //Signal step 1 of signup has been done
            $sql = "UPDATE registrations SET signupfirststep = TRUE WHERE email = '".$email."' AND signup_hash = '".$signupid."'";
            $result = $conn->query($sql);

            header('Content-Type: application/json');
            print(json_encode($response));

            CloseCon($conn);

            break;
        case "profile":
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $europeana = $conn->real_escape_string($requestData->{'europeana'});
            $location = $conn->real_escape_string($requestData->{'location'});
            $gender = $conn->real_escape_string($requestData->{'gender'});
            $age = $conn->real_escape_string($requestData->{'age'});
            $professional_background = $conn->real_escape_string($requestData->{'background'});
            if ($requestData->{'backgroundother'}) {
                $background_other = $conn->real_escape_string($requestData->{'backgroundother'});

                if (strlen($background_other) > 0) {
                    $professional_background .= ",".$background_other;
                }
            }
            //$professional_experience = $conn->real_escape_string($requestData->{'experience'});
            $email = $conn->real_escape_string($requestData->{'email'});
            $email_updates = $requestData->{'emailupdates'} == "true" ? "TRUE" : "FALSE";
            $signupid = $conn->real_escape_string($requestData->{'signupid'});

            //validate sign up hash
            $sql = "SELECT * FROM registrations WHERE email = '".$email."' AND signup_hash = '".$signupid."'";
            $result = $conn->query($sql); 

            //valid combination found
            if (mysqli_num_rows($result) == 0) {
                CloseCon($conn);
                $response['error']['email'] = "Email / sign up id not found";

                header('Content-Type: application/json');
                print(json_encode($response));
                break;     
            }

            $sql = "UPDATE users SET europeana_known = '".$europeana."', location = '".$location."', gender = '".$gender."', age = '".$age."', professional_background = '".$professional_background."', email_updates = ".$email_updates." WHERE email = '".$email."'";
            $result = $conn->query($sql);
            
            if(!$result) {
                $response['error']['database'] = "Database error";
            } else {
                $response['success']['message'] = "Account created";
            }

            //Get fullname for use in email
            $sql = "SELECT fullname FROM registrations WHERE email = '".$email."' AND signup_hash = '".$signupid."'";
            $result = $conn->query($sql);

            $fullname = "";

            if (mysqli_num_rows($result) > 0) {
                $row = $result->fetch_assoc();
                $fullname = $row['fullname'];
            } else {
                CloseCon($conn);

                $response['error']['database'] = "Database error";
                header('Content-Type: application/json');
                print(json_encode($response));
                break;      
            }

            //Set second step completed and invalidate hash
            $sql = "UPDATE registrations SET signup_hash = NULL, signupsecondstep = TRUE WHERE email = '".$email."' AND signup_hash = '".$signupid."'";
            $result = $conn->query($sql);

            if(!$result) {
                $response['error']['database'] = "Database error";
            } else {
                $response['success']['message'] = "Account created";
            }

            // sent welcome email
            $signupHTMLMail = file_get_contents("mail/templates/signup.html");
            $signupHTMLMail = str_replace("{{fullname}}", $fullname, $signupHTMLMail);
            $signupHTMLMail = str_replace("{{email}}", $email, $signupHTMLMail);

            $signupTextMail = file_get_contents("mail/templates/signup.txt");
            $signupTextMail = str_replace("{{fullname}}", $fullname, $signupTextMail);
            $signupTextMail = str_replace("{{email}}", $email, $signupTextMail);

            sendMail($signupTextMail, $signupHTMLMail, "Your subtitle-a-thon account", $email);

            header('Content-Type: application/json');
            print(json_encode($response));

            CloseCon($conn);

            break;
        case "login":
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $username = $conn->real_escape_string($requestData->{'username'});
            $password = $conn->real_escape_string($requestData->{'password'});

            $pwd_peppered = hash_hmac("sha256", $password, getPepper());
            $pwd_hashed = password_hash($pwd_peppered, PASSWORD_BCRYPT);

            //allow login with both username or email address
            $sql = "SELECT userid,password,admin FROM users WHERE username = '".$username."' OR email = '".$username."'";
            $result = $conn->query($sql);

            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                if (password_verify($pwd_peppered, $row['password'])) {
                    //set session
                    $sessionId = generateSessionId();

                    $sql = "INSERT INTO sessions (sessionid, userid) VALUES ('".$sessionId."', ".$row['userid'].")";
                    $result = $conn->query($sql);

                    CloseCon($conn);
                    
                    $cookie_options = array(
                        'expires' => time() + 60*60*24*30,
                        'path' => '/',
                        'domain' => '.subtitleathon.eu', // leading dot for compatibility or use subdomain
                        'secure' => false, // or false
                        'httponly' => false, // or false
                        'samesite' => 'None' // None || Lax || Strict
                      );

                    setcookie("sessionid", $sessionId, time()+60*60*24*30, "/; SameSite=None", ".subtitleathon.eu", true, true);
                   
                    $response['success']['loginmessage'] = "login successful";

                    header('Content-Type: application/json');
                    print(json_encode($response));
                    break;
                } else {
                    CloseCon($conn);

                    $response['error']['loginmessage'] = "User name or password incorrect";
                    $endtime = microtime();
                    //usleep($responseWaitingTime - ($endtime-$starttime));

                    header('Content-Type: application/json');
                    print(json_encode($response));
                    break;
                }
            } else {
                CloseCon($conn);

                $response['error']['loginmessage'] = "User name or password incorrect";
                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
            }

            break;
        case "authenticate":
            $conn = OpenCon();
            $response = array();
            
            $sessionid = $_COOKIE['sessionid'];

            $sql = "SELECT s.userid, u.username, u.email, u.admin, u.reviewer, u.admin_event FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid WHERE sessionid = '". $conn->real_escape_string($sessionid)."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                
                //update last activity
                $sql = "UPDATE sessions SET userid = ".$row['userid']." WHERE sessionid = '". $conn->real_escape_string()."'";
                $result = $conn->query($sql);

                $response['success']['userid'] = $row['userid'];
                $response['success']['username'] = $row['username'];
                $response['success']['email'] = $row['email'];
                if (boolval($row['admin'])) {
                    $response['success']['admin'] = true;
                }
                if (boolval($row['reviewer'])) {
                    $response['success']['reviewer'] = true;
                }
                if (!is_null($row['admin_event'])) {
                    $response['success']['admin_event'] = $row['admin_event'];
                }

                header('Content-Type: application/json');
                print(json_encode($response));
                break;
            } else {
                $response['error']['authenticate'] = "could not authenticate session";
               
                header('Content-Type: application/json');
                print(json_encode($response));
                break;
            }
            break;
        case "forgotpassword":
            $starttime = microtime();
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $username = $conn->real_escape_string($requestData->{'username'});
            $email = $conn->real_escape_string($requestData->{'email'});

            $resetId = generateResetId();
            $resetLink = "https://www.subtitleathon.eu/new-password?id=".$resetId;

            $sql = "SELECT userid, username, email FROM users WHERE username = '".$username."' OR email = '".$email."'";
            $result = $conn->query($sql);

            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                $username = $row['username'];
                $userid = $row['userid'];
                $email = $row['email'];

                $ip = $_SERVER['REMOTE_ADDR'];
                $browserString = $_SERVER['HTTP_USER_AGENT'];
                $browserInfo = get_browser($browserString, true);
                $browserShortString = $browserInfo['browser']." ".$browserInfo['version']. " on ".$browserInfo['platform'];

                $sql = "INSERT INTO passwordreset (resetid, userid, ip, browser) VALUES('".$resetId."', '".$userid."', '".$conn->real_escape_string($ip)."', '".$conn->real_escape_string($browserString)."')";
                $result = $conn->query($sql);

                CloseCon($conn);

                $geoLcationInfo = getIPLocation($ip);
                $country = $geoLcationInfo->country_name;
                $region = $geoLcationInfo->region_name;

                // sent reset email
                $resetHTMLMail = file_get_contents("mail/templates/resetrequest.html");
                $resetHTMLMail = str_replace("{{username}}", $username, $resetHTMLMail);
                $resetHTMLMail = str_replace("{{resetlink}}", $resetLink, $resetHTMLMail);
                $resetHTMLMail = str_replace("{{browserstring}}", $browserShortString, $resetHTMLMail);
                $resetHTMLMail = str_replace("{{region}}", $region, $resetHTMLMail);
                $resetHTMLMail = str_replace("{{country}}", $country, $resetHTMLMail);
                $resetHTMLMail = str_replace("{{date}}", date("M j Y, G:i T"), $resetHTMLMail);
 
                $resetTextMail = file_get_contents("mail/templates/resetrequest.txt");
                $resetTextMail = str_replace("{{username}}", $username, $resetTextMail);
                $resetTextMail = str_replace("{{resetlink}}", $resetLink, $resetTextMail);
                $resetTextMail = str_replace("{{browserstring}}", $browserShortString, $resetTextMail);
                $resetTextMail = str_replace("{{region}}", $region, $resetTextMail);
                $resetTextMail = str_replace("{{country}}", $country, $resetTextMail);
                $resetTextMail = str_replace("{{date}}", date("M j Y, G:i T"), $resetTextMail);

                sendMail($resetTextMail, $resetHTMLMail, "Password reset for your subtitle-a-thon account", $email);

                $response['success']['user'] = "password reset mail send";
                $response['success']['email'] =  obfuscateEmail($email);

                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
                break;
            }
            CloseCon($conn);

            //TODO: leak if account exists, harmful?
            $response['error']['forgotpassword'] = "account not found";
            $endtime = microtime();
            //usleep($responseWaitingTime - ($endtime-$starttime));

            header('Content-Type: application/json');
            print(json_encode($response));
            break;
        case "reset":
            $starttime = microtime();
            $conn = OpenCon();
            $response = array();

            $resetid = $conn->real_escape_string($subaction);

            //check if reset is requested in the past 24 hours
            $sql = "SELECT * FROM passwordreset WHERE resetid = '".$resetid."' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $result = $conn->query($sql);
            CloseCon($conn);

            if (mysqli_num_rows($result) === 1) {
                //show reset page
                $response['success']['user'] = "password can be updated";
                $response['success']['resetid'] = $resetid;

                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
            } else {
                //no longr a valid reset id
                $response['error']['user'] = "reset id no longer valid";

                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "newpassword":
            $starttime = microtime();
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $resetid = $conn->real_escape_string($requestData->{'resetid'});
            $password = $conn->real_escape_string($requestData->{'password'});

             //check if reset is requested in the past 24 hours
             $sql = "SELECT u.userid AS userid FROM passwordreset AS p LEFT JOIN users AS u ON p.userid = u.userid WHERE resetid = '".$resetid."' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
             $result = $conn->query($sql);

             if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                $userid = $row['userid'];

                 //hash password with a pepper
                $pwd_peppered = hash_hmac("sha256", $password, getPepper());
                $pwd_hashed = password_hash($pwd_peppered, PASSWORD_BCRYPT);

                $sql = "UPDATE users SET password = '".$pwd_hashed."' WHERE userid = ".$userid."";
                $result = $conn->query($sql);

                if(!$result) {
                    $response['error']['database'] = "Database error";
                } else {
                    $response['success']['message'] = "Password updated";
                }
                CloseCon($conn);

                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
             } else {
                CloseCon($conn);
                //no longr a valid reset id
                $response['error']['user'] = "reset id no longer valid";

                $endtime = microtime();
                //usleep($responseWaitingTime - ($endtime-$starttime));

                header('Content-Type: application/json');
                print(json_encode($response));
             }
            break;
        case "logout":
            $conn = OpenCon();
            $response = array();
            
            $sessionid = $_COOKIE['sessionid'];

            $sql = "DELETE FROM sessions WHERE sessionid = '". $conn->real_escape_string($sessionid)."'";
            $result = $conn->query($sql);

            setcookie("sessionid", null, -1, "/; SameSite=None", ".subtitleathon.eu", true, true);

            $response['success']['user'] = "user succesfully logged out";

            header('Content-Type: application/json');
            print(json_encode($response));
            break;
        case "registrate": 
            $conn = OpenCon();
            $response = array();

            $json_params = file_get_contents("php://input");
            $requestData = array();

            if (strlen($json_params) > 0 && isValidJSON($json_params)) {
                $requestData = json_decode($json_params);
            }

            $fullname = $conn->real_escape_string($requestData->{'fullname'});
            $email = $conn->real_escape_string($requestData->{'email'});
            $nationality = $conn->real_escape_string($requestData->{'nationality'});

            $nativelanguagesstring = "";
            $foreignlanguagesstring = "";

            foreach ($requestData->{'nativelanguages'} as $language) {
                $nativelanguagesstring .= $language->iso . ",";
            }

            foreach ($requestData->{'foreignlanguages'} as $language) {
                $foreignlanguagesstring .= $language->iso . ",";
            }

            if (strlen($nativelanguagesstring) === 0) {
                $response['error']['nativelanguages'] = "No native language given";

                header('Content-Type: application/json');
                print(json_encode($response));
                break; 
            }

            if (strlen($foreignlanguagesstring) === 0) {
                $response['error']['foreignlanguages'] = "No foreign language given";

                header('Content-Type: application/json');
                print(json_encode($response));
                break; 
            }

            $nativelanguagesstring = substr($nativelanguagesstring, 0, strlen($nativelanguagesstring)-1);
            $foreignlanguagesstring = substr($foreignlanguagesstring, 0, strlen($foreignlanguagesstring)-1);

            $nativelanguages = $conn->real_escape_string($nativelanguagesstring);
            $foreignlanguages = $conn->real_escape_string($foreignlanguagesstring);

            $sql = "SELECT * FROM registrations WHERE email = '".$email."'";
            $result = $conn->query($sql);

            //email already exists
            if (mysqli_num_rows($result) > 0) {
                $row = $result->fetch_assoc();
                
                //allow existing registrations to register also for other events
                if ($row['eventid'] == "8") {
                    $response['error']['email'] = "Email address already in use";
                } else {
                    $sql = "UPDATE registrations SET eventid = 8, accepted = FALSE, rejected = FALSE, fullname = '".$fullname."', nationality = '".$nationality."', native_languages = '".$nativelanguages."', foreign_languages = '".$foreignlanguages."' WHERE email = '".$email."'";

                    $result = $conn->query($sql);
                
                    if(!$result) {
                        $response['error']['database'] = "Database error";
                    } else {
                        $response['success']['message'] = "Account created";

                        // sent registration email
                        $registrationHTMLMail = file_get_contents("mail/templates/registration.html");
                        $registrationHTMLMail = str_replace("{{fullname}}", $fullname, $registrationHTMLMail);

                        $registrationTextMail = file_get_contents("mail/templates/registration.txt");
                        $registrationTextMail = str_replace("{{fullname}}", $fullname, $registrationTextMail);

                        sendMail($registrationTextMail, $registrationHTMLMail, "Your subtitle-a-thon registration", $email);
                    }
                }

                CloseCon($conn);

                header('Content-Type: application/json');
                print(json_encode($response));
                break; 
            } else {
                $sql = "INSERT INTO registrations(fullname, email, nationality, native_languages, foreign_languages, eventid) VALUES ('".$fullname."', '".$email."', '".$nationality."', '".$nativelanguages."', '".$foreignlanguages."', 8)";
                $result = $conn->query($sql);
                
                if(!$result) {
                    $response['error']['database'] = "Database error";
                } else {
                    $response['success']['message'] = "Account created";

                    // sent registration email
                    $registrationHTMLMail = file_get_contents("mail/templates/registration.html");
                    $registrationHTMLMail = str_replace("{{fullname}}", $fullname, $registrationHTMLMail);

                    $registrationTextMail = file_get_contents("mail/templates/registration.txt");
                    $registrationTextMail = str_replace("{{fullname}}", $fullname, $registrationTextMail);

                    sendMail($registrationTextMail, $registrationHTMLMail, "Your subtitle-a-thon registration", $email);
                }

                header('Content-Type: application/json');
                print(json_encode($response));

                CloseCon($conn);

                break;
            }
            break;
        case "myvideos":
            $response = array();
            $conn = OpenCon();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                $userid = $row['userid'];

                $sql = "SELECT i.itemid, i.item_key, i.eupsid, i.manifest, i.characters, i.language, i.finalized, e.europeana_collection, e.eventid FROM item_subtitles as i LEFT JOIN events AS e ON i.eventid = e.eventid WHERE userid = ".$userid;
                $result = $conn->query($sql);

                //users events
                if (mysqli_num_rows($result) > 0) {
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
                $response['error']['user'] = "User is not logged in";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "list":
                $response = array();
    
                if ($subaction !== "") {
                    $listEvents = explode(",", $subaction);
                    $conn = OpenCon();

                    $completeJson;

                    for ($i = 0; $i < count($listEvents); $i++) {   
                        $eventid = $conn->real_escape_string($listEvents[$i]);

                        $sql = "SELECT * FROM events WHERE eventid = ".$eventid;
                        $result = $conn->query($sql);
        
                        //event exist
                        if (mysqli_num_rows($result) > 0) {
                            $row = $result->fetch_assoc();
                            $europeana_collection = $row['europeana_collection'];
                            $title = $row['title'];
                            
                            $url = $europeanaAPIPreUrl . $europeana_collection . $europeanaAPIPostURL;
                            $content = file_get_contents($url);
                            if ($content !== FALSE) {
                                $json = json_decode($content, true);
                                
                                if ($completeJson === null) {
                                    $json['title'] = $title;
                                    $completeJson = $json;
                                } else {
                                    $completeJson['items'] = array_merge($completeJson['items'], $json['items']);
                                }
                            }
                        }
                    }

                    if ($completeJson === null) {
                       $completeJson = $response['error']['event'] = "Event not found";
                    }

                    CloseCon($conn);

                    header('Content-Type: application/json');
                    print(json_encode($completeJson));
                } else {
                    $response['error']['event'] =  "Event id not defined";
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

function generateSessionId() {
    $bytes = random_bytes(30);
    return bin2hex($bytes);
}

function generateResetId() {
    $bytes = random_bytes(32);
    return bin2hex($bytes);
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

function getIPLocation($ip) {
    $response = file_get_contents("http://api.ipstack.com/".$ip."?access_key=1ccfda79622af913ddbfdcf58013420a");

    $json = json_decode($response);

    return $json;
}

function obfuscateEmail($email) {
    $pattern = "/(.+)@(.+)\.(.+)/";

    preg_match($pattern, $email, $matches);

    if (count($matches) == 4) {
        $name = $matches[1];
        $domain = $matches[2];
        $extension = $matches[3];

        $nameLength = strlen($name);
        if ($nameLength < 4) {
            $name = substr($name, 0, 1).str_repeat("*", $nameLength -1);
        } else {
            $name = substr($name, 0, 2).str_repeat("*", $nameLength - 3).substr($name, $nameLength - 1, 1);
        }

        $domainLength = strlen($domain);
        if ($domainLength < 4) {
            $domain = substr($domain, 0, 1).str_repeat("*", $domainLength -1);
        } else {
            $domain = substr($domain, 0, 2).str_repeat("*", $domainLength - 3).substr($domain, $domainLength - 1, 1);
        }

        return $name."@".$domain.".".$extension;
    }

    return "";
}

?>