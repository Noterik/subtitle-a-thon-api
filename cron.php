<?php

include 'db_connect.php';

$conn = OpenCon();

//Clear all reserved subtitles older then 1 hour that have not yet a single subtitle in them
$sql = "DELETE FROM item_subtitles WHERE first_subtitle_saved = FALSE AND reserved < (NOW() - INTERVAL 1 HOUR)";
$result = $conn->query($sql);

print(date("F j, Y, G:i:s").": ".mysqli_num_rows($result)." items deleted");

//create statistics
$sql = "SELECT characters, milliseconds FROM item_subtitles WHERE finalized = TRUE AND eventid = 6";
$result = $conn->query($sql);

$characters = 0;
$milliseconds = 0;

if (mysqli_num_rows($result) > 0) {
    //loop over all events
    while ($row = $result->fetch_assoc()) {
        $characters += $row['characters'];
                                    
        $milliseconds += $row['milliseconds'];
    }
}

$data = array();
$data['characters'] = $characters;
$data['milliseconds'] = $milliseconds;

$sql = "SELECT COUNT(DISTINCT language) AS languages FROM item_subtitles WHERE finalized = TRUE AND eventid = 6";
$result = $conn->query($sql);

if (mysqli_num_rows($result) == 1) {
    $row = $result->fetch_assoc();
    $data['languages'] = $row['languages'];
}

file_put_contents("/var/www/api.subtitleathon.eu/statistics_6.json", json_encode($data));

$sql = "select 
t.username,
t.characters
from (
select users.username, SUM(item_subtitles.characters) as characters
from item_subtitles
inner join users on item_subtitles.userid = users.userid
where eventid = 6 and finalized = true
group by users.userid
order by characters DESC LIMIT 5
) t";
$result = $conn->query($sql);

$data = array();

if (mysqli_num_rows($result) > 0) {
    //loop over all events
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

file_put_contents("/var/www/api.subtitleathon.eu/leaderboard_6.json", json_encode($data));

$sql2 = "select
    username,
    userid
    from users
    where reviewer = true";

$result2 = $conn->query($sql2);

$reviewers = array();

if (mysqli_num_rows($result2) > 0) {
    //loop over all events
    while ($row = $result2->fetch_assoc()) {
        $reviewers[] = $row;
    }
}

$string = file_get_contents("lang.json");
$json = json_decode($string, true);
$languages = $json['locales'];

$europeanaAPIUrl= "https://api.europeana.eu/set/2171.json?wskey=api2demo&profile=itemDescriptions";
$collection = file_get_contents($europeanaAPIUrl);
$collectionJson = json_decode($collection, true);
$items = $collectionJson['items'];

$sql = "select
t.characters, 
t.language, 
t.review_done, 
t.review_quality,
t.review_appropriate, 
t.review_flow, 
t.review_grammatical, 
t.review_comments, 
t.username,
t.reviewerid,
t.itemid
from (
    select 
    u.username, 
    i.characters, 
    i.language, 
    i.review_done, 
    i.review_quality, 
    i.review_appropriate,
    i.review_flow, 
    i.review_grammatical, 
    i.review_comments,
    i.reviewerid,
    i.itemid
    from item_subtitles as i
    inner join users as u on i.userid = u.userid
    where i.eventid = 6 and i.finalized = true
) t";

$result = $conn->query($sql);

$data = array();

if (mysqli_num_rows($result) > 0) {
    //loop over all events
    while ($row = $result->fetch_assoc()) {
        $key = array_search($row['reviewerid'], array_column($reviewers, 'userid'));

        $row['reviewer'] = $reviewers[$key]['username'];
        unset($row['reviewerid']);

        $key = array_search($row['itemid'], str_replace("/","-",array_column($items, 'id')));
        $row['title'] = $items[$key]['title'][0];
        unset($row['itemid']);

        $row['languageid'] = $row['language'];
        $key = array_search($row['language'], array_column($languages, 'iso'));
        $row['language'] = $languages[$key]['name'];

        $data[] = $row;
    }
}

//create excel
$xls = "";

$i = 1;
foreach ($data as $row) {
   if ($i == 1) {
      // COLUMN HEADERS
      $xls .= implode("\t", array_keys($row)) . "\r\n";
   }
   // DATA ROWS
   array_walk($row, __NAMESPACE__ . '\cleanData');
   $xls .= implode("\t", array_values($row)) . "\r\n";
   $i++;
}

$xls = mb_convert_encoding($xls, 'UTF-16LE', 'UTF-8');

file_put_contents("/var/www/api.subtitleathon.eu/scores.xls", $xls);

function cleanData(&$str) {
    $str = preg_replace("/\t/", " ", $str);
    $str = preg_replace("/\r?\n/", " ", $str);
}

?>