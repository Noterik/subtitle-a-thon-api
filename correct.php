<?php

include 'Mail.php';
include 'Mail/mime.php';

include 'db_connect.php';

$conn = OpenCon();
$sql = "SELECT * FROM registrations WHERE accepted = true AND registrationid > 77 AND signupfirststep = TRUE AND eventid = 6";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $fullname = $row['fullname'];
    $signup_hash = $row['signup_hash'];
    $email = $row['email'];

    // sent registration email
    $registrationHTMLMail = file_get_contents("mail/templates/createaccount.html");
    $registrationHTMLMail = str_replace("{{fullname}}", $fullname, $registrationHTMLMail);
    $registrationHTMLMail = str_replace("{{signup_hash}}", $signup_hash, $registrationHTMLMail);

    $registrationTextMail = file_get_contents("mail/templates/createaccount.txt");
    $registrationTextMail = str_replace("{{fullname}}", $fullname, $registrationTextMail);
    $registrationTextMail = str_replace("{{signup_hash}}", $signup_hash, $registrationTextMail);

    sendMail($registrationTextMail, $registrationHTMLMail, "Complete your subtitle-a-thon account", $email);

}

CloseCon($conn);

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

?>