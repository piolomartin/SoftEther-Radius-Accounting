<?php
// SE specific settings
$apipass = ""; // softether hub password
$hubname = ""; // softether hub name
$softetherip = ""; // softether hub address
$vpncmd = "/opt/vpncmd/vpncmd";

// radius specific settings
$radiussrv = array(""); // radius server addresses, 1 or more for backup
$radiuspass = ""; // radius secret
$radiusport = "1813"; // radius server accounting port
$radtimeout = "5"; // radius query timeout in seconds, can be floating point number - normally should be 3, and up to 10 on slow networks
$radretry = "2"; // radius query retries in integer, if query timeouts
 
// other settings
$database = "/opt/sessions.db"; // temporary database location
$tmpdir = "/tmp"; // temporary directory

?>
