<?php
require_once("settings.php");
require_once("functions.php");

$nasIp = file_get_contents("https://ifconfig.me");
if(!$nasIp) die("Invalid NASIP");

$sessid = $argv[1];
$outdata = $argv[2];
$indata = $argv[3];

$db = new SQLite3($database);
$db->busyTimeout(5000);
$sessid = $db->escapeString($sessid);
$results = $db->querySingle("SELECT * FROM sessions WHERE sessionid = '".$sessid."'", true);
if($results == FALSE) { die("Error - could not find sessionid");} // not good, session may remain zombie in RADIUS, but cannot properly identify to close so meh - maybe close all sessions for user and wait for next interim-update to fix?

list($time1,,$time2) = explode(" ",$results['acctstarttime']);
$sessiontime = time() - strtotime($time1." ".$time2);

$acctsessionid = md5($sessid.$results['acctstarttime']);

$tmpfname = tempnam($tmpdir, "acctstoptmp_");
$handle = fopen($tmpfname, "w");

$packet = "Service-Type = Framed-User"."\n".
          "Framed-Protocol = PPP"."\n".
          "NAS-Port = ".$results['nasport']."\n".
          "NAS-Port-Type = Async"."\n".
          "User-Name = '".$results['username']."'"."\n".
          "Calling-Station-Id = '".$results['clientip']."'"."\n".
          "Called-Station-Id = '".$nasIp."'"."\n".
          "Acct-Session-Id = '".$acctsessionid."'"."\n".
          "Framed-IP-Address = ".$results['framedip']."\n".
          "Acct-Authentic = RADIUS"."\n".
          "Event-Timestamp = ".time()."\n".
          "Acct-Session-Time = ".$sessiontime."\n".
          "Acct-Input-Octets = ".$indata."\n".
          "Acct-Output-Octets = ".$outdata."\n".
          "Acct-Status-Type = Stop"."\n".
          "NAS-Identifier = '".$nasIp."'"."\n".
          "Acct-Delay-Time = 0"."\n".
          "NAS-IP-Address = ".$nasIp."\n";
fwrite($handle, $packet);
fclose($handle);
$radResponse = radquery($tmpfname, 1);
unlink($tmpfname);

if($radResponse)
    $db->exec("DELETE FROM sessions WHERE sessionid = '".$sessid."'");

$db->close();
exit(0);

?>
