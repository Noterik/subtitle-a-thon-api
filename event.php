<?php

include 'Mail.php';
include 'Mail/mime.php';

include 'db_connect.php';

$id = "";
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
        case "join":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
                $eventid = $conn->real_escape_string($subaction);

                $sql = "SELECT * FROM events WHERE eventid = ".$eventid." AND end_date > NOW()";
                $result = $conn->query($sql);

                //event exists and is joinable
                if (mysqli_num_rows($result) > 0) {
                    $row = $result->fetch_assoc();
                    $eventtitle = $row['title'];
                    $startdate = $row['start_date'];
                    $enddate = $row['end_date'];

                    $starttime = strtotime($startdate);
                    $starttimeformatted = date("d-m-Y G:i T", $starttime);

                    $endtime = strtotime($enddate);
                    $endtimeformatted = date("d-m-Y G:i T", $endtime);

                    $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
                    $sql = "SELECT s.userid, u.email, username FROM sessions AS s LEFT JOIN users AS u ON s.userid = u.userid  WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
                    $result = $conn->query($sql);

                    //user is authenticated
                    if (mysqli_num_rows($result) === 1) {
                        $row = $result->fetch_assoc();
                        $userid = $row['userid'];
                        $username = $row['username'];
                        $email = $row['email'];

                        //check if user already joined the event
                        $sql = "SELECT id FROM users_events WHERE userid = ". $userid ." AND eventid = ". $eventid;
                        $result = $conn->query($sql);

                        //user didn't joined event earlier
                        if (mysqli_num_rows($result) === 0) {
                            $sql = "INSERT INTO users_events (userid, eventid) VALUES(". $userid .", ". $eventid .")";
                            $result = $conn->query($sql);
                        
                            if ($result === false) {
                                CloseCon($conn);
                                $response['error']['user'] = "Could not join event";
                                header('Content-Type: application/json');
                                print(json_encode($response));
                                break;
                            } else {
                                CloseCon($conn);

                                // sent join event email
                                $joinedHTMLMail = file_get_contents("mail/templates/joinedevent.html");
                                $joinedHTMLMail = str_replace("{{username}}", $username, $joinedHTMLMail);
                                $joinedHTMLMail = str_replace("{{eventtitle}}", $eventtitle, $joinedHTMLMail);
                                $joinedHTMLMail = str_replace("{{startdate}}", $starttimeformatted, $joinedHTMLMail);
                                $joinedHTMLMail = str_replace("{{enddate}}", $endtimeformatted, $joinedHTMLMail);

                                $joinedTextMail = file_get_contents("mail/templates/joinedevent.txt");
                                $joinedTextMail = str_replace("{{username}}", $username, $joinedTextMail);
                                $joinedTextMail = str_replace("{{eventtitle}}", $eventtitle, $joinedTextMail);
                                $joinedTextMail = str_replace("{{startdate}}", $starttimeformatted, $joinedTextMail);
                                $joinedTextMail = str_replace("{{enddate}}", $endtimeformatted, $joinedTextMail);

                                sendMail($joinedTextMail, $joinedHTMLMail, "You joined the ".$eventtitle, $email);  
                            }
                        }
                        CloseCon($conn);
                        $response['success']['user'] = "User successfully joined event";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    } else {
                        CloseCon($conn);
                        $response['error']['user'] = "User is not logged in";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                } else {
                    CloseCon($conn);
                    $response['error']['event'] = "Event does not exist or is not joinable";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] = "Event id not given";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "joinedevents":
            $response = array();
            $conn = OpenCon();

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //user is authenticated
            if (mysqli_num_rows($result) === 1) {
                $row = $result->fetch_assoc();
                $userid = $row['userid'];

                $sql = "SELECT e.eventid, e.title, e.start_date, e.end_date, e.pagename FROM users_events as u LEFT JOIN events as e ON e.eventid = u.eventid WHERE userid = ".$userid;
                $result = $conn->query($sql);

                //users events
                if (mysqli_num_rows($result) > 0) {
                    //loop over all events
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
                $conn = OpenCon();
                $eventid = $conn->real_escape_string($subaction);

                $sql = "SELECT * FROM events WHERE eventid = ".$eventid." AND start_date <= NOW() AND end_date >= NOW()";
                $result = $conn->query($sql);

                //event exists and is joinable
                if (mysqli_num_rows($result) > 0) {
                    $row = $result->fetch_assoc();
                    $europeana_collection = $row['europeana_collection'];
                    $title = $row['title'];
                    
                    CloseCon($conn);

                    $url = $europeanaAPIPreUrl . $europeana_collection . $europeanaAPIPostURL;
                    $content = file_get_contents($url);
                    if ($content !== FALSE) {
                        $json = json_decode($content);
                        $json->title = $title;

                        header('Content-Type: application/json');
                        print(json_encode($json));
                    } else {
                        $response['error']['event'] = "Event not found on Europeana";
                        header('Content-Type: application/json');
                        print(json_encode($response));
                    }
                } else {
                    CloseCon($conn);

                    $response['error']['event'] =  "Event not found or active";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] =  "Event id not defined";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "availableLanguages":
            $response = array();

            if ($subaction !== "") {
                $conn = OpenCon();
                $eventid = $conn->real_escape_string($subaction);

                $sql = "SELECT * FROM events WHERE eventid = ".$eventid." AND start_date <= NOW() AND end_date >= NOW()";
                $result = $conn->query($sql);

                //event exists and is joinable
                if (mysqli_num_rows($result) > 0) {
                    $row = $result->fetch_assoc();
                   
                    CloseCon($conn);

                    $languages = explode(",",$row['allowed_languages']);
                    $response['success']['allowed_languages'] = $languages;
                    header('Content-Type: application/json');
                    print(json_encode($response));
                } else {
                    CloseCon($conn);

                    $response['error']['event'] =  "Event not found or active";
                    header('Content-Type: application/json');
                    print(json_encode($response));
                }
            } else {
                $response['error']['event'] =  "Event id not defined";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "statistics":
            $response = array();
            $conn = OpenCon();

            $eventid = $conn->real_escape_string($subaction);

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //user is authenticated
            if (mysqli_num_rows($result) === 1) {
                header('Content-Type: application/json');
                print(file_get_contents("/var/www/api.subtitleathon.eu/statistics_".$eventid.".json"));
            } else {
                CloseCon($conn);
                $response['error']['user'] = "User is not logged in";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        case "leaderboard":
            $response = array();
            $conn = OpenCon();

            $eventid = $conn->real_escape_string($subaction);

            $sessionid = $conn->real_escape_string($_COOKIE['sessionid']);
            $sql = "SELECT userid FROM sessions WHERE sessionid = '". $sessionid."' AND created_at BETWEEN NOW() - INTERVAL 30 DAY AND NOW()";
            $result = $conn->query($sql);

            //user is authenticated
            if (mysqli_num_rows($result) === 1) {
                header('Content-Type: application/json');
                print(file_get_contents("/var/www/api.subtitleathon.eu/leaderboard_".$eventid.".json"));
            } else {
                CloseCon($conn);
                $response['error']['user'] = "User is not logged in";
                header('Content-Type: application/json');
                print(json_encode($response));
            }
            break;
        default:
            break;
    }    
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

?>