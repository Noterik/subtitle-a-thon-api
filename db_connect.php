<?php
function OpenCon()
 {
 $dbhost = "";
 $dbuser = "";
 $dbpass = "";
 $db = "";
 $conn = new mysqli($dbhost, $dbuser, $dbpass,$db) or die("Connect failed: %s\n". $conn -> error);
 $conn->set_charset("utf8mb4");

 return $conn;
 }

function CloseCon($conn)
 {
 $conn -> close();
 }

 function OpenCon2() {
    $dbhost = "";
    $dbuser = "";
    $dbpass = "";
    $db = "";
    $conn = new mysqli($dbhost, $dbuser, $dbpass,$db) or die("Connect failed: %s\n". $conn -> error);
    $conn->set_charset("utf8mb4");

    return $conn;
 }

 function getPepper() {
     return file_get_contents("pepper");
 }

?>