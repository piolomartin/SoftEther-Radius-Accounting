<?php
require_once("settings.php");
require_once("functions.php");

$nasIp = file_get_contents("https://ifconfig.me");
if(!$nasIp) die("Invalid NASIP");

$sessid = $argv[1];

$result = getsessiondata($sessid); // get session details from HUB
$framedip = getdhcpip($sessid); // get DHCP assigned IP from HUB 

if ($framedip === FALSE) { // if user could not get ip with dhcp, disconnect it 
  disconnectsession($sessid);
  exit; 
}

$db = new SQLite3($database);
$db->busyTimeout(5000);
$db->exec('CREATE TABLE IF NOT EXISTS sessions (sessionid varchar(255), username varchar (255), clientip varchar (255), inputoctets varchar (255), ' .
          'outputoctets varchar (255), framedip varchar (255), nasip varchar (255), nasport varchar (255), acctstarttime varchar (255), '.
          'acctsessiontime varchar (255), PRIMARY KEY(sessionid))');
$query = $db->escapeString('INSERT OR REPLACE INTO sessions (sessionid, username, clientip, inputoctets, outputoctets, framedip, nasip, nasport, acctstarttime, acctsessiontime) VALUES ("'.$sessid.'","'.$result["User Name (Authentication)"].'","'.$result["Client IP Address"].'",NULL,NULL,"'.$framedip.'","'.$result["Server IP Address (Reported)"].'","'.$result["Server Port (Reported)"].'","'.$result["Connection Started at"].'",NULL)');
$db->exec($query);

$sessid = $db->escapeString($sessid);
$results = $db->querySingle("SELECT * FROM sessions WHERE sessionid = '".$sessid."'", true);

$acctsessionid = md5($sessid.$results['acctstarttime']);
$tmpfname = tempnam($tmpdir, "acctstarttmp_");
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
          "Acct-Status-Type = Start"."\n".
          "NAS-Identifier = '".$nasIp."'"."\n".
          "Acct-Delay-Time = 0"."\n". // handle?
          "NAS-IP-Address = ".$nasIp."\n";
fwrite($handle, $packet);
fclose($handle);
radquery($tmpfname, 1);
unlink($tmpfname);

$db->close();
exit(0);

?>
