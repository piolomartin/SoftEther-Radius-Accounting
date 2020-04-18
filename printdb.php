<?php
require_once("settings.php");
$db = new SQLite3($database);

$results = $db->query('SELECT * FROM sessions');

while ($row = $results->fetchArray()) {
    print_r($row);
}

?>
