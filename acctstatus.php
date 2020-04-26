<?php
require_once("settings.php");
require_once("functions.php");

$fpLock = fopen('acctstatus.lock', 'a+');
if(!flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "Unable to obtain lock\n";
    exit(-1);
}

$nasIp = file_get_contents("https://ifconfig.me");
if(!$nasIp) die("Invalid NASIP");

$db = new SQLite3($database);
$results = $db->query('SELECT * FROM sessions');

while ($row = $results->fetchArray()) {
    $sessid = $row["sessionid"];
    $startTime = $row['acctstarttime'];
    $username = $row['username'];
    $nasPort = $row['nasport'];
    $clientIp = $row['clientip'];
    $frameIp = $row['framedip'];

    list($time1,,$time2) = explode(" ",$row['acctstarttime']);
    $sessiontime = time() - strtotime($time1." ".$time2);

    $session = getsessiondata2($sessid);
    $acctsessionid = md5($sessid.$startTime);

    if( $session === false ){
        echo "Zombie Session {$acctsessionid}\n";

        // GET Stats from session_stats table
        $sessionStats = $db->query('SELECT * FROM session_stats WHERE sessionid=\''.$sessid.'\'');
        $sessionStatsRow = $sessionStats->fetchArray();

        $indata=0;
        $outdata=0;
        if($sessionStatsRow){
            $indata = $sessionStatsRow['indata'];
            $outdata = $sessionStatsRow['outdata'];
        }

        $tmpfname = tempnam($tmpdir, "acctstatustmp_");
        $handle = fopen($tmpfname, "w");

        $packet = "Service-Type = Framed-User"."\n".
            "Framed-Protocol = PPP"."\n".
            "NAS-Port = {$nasPort}\n".
            "NAS-Port-Type = Async"."\n".
            "User-Name = '{$username}'"."\n".
            "Acct-Session-Id = '{$acctsessionid}'"."\n".
            "Framed-IP-Address = {$frameIp}\n".
            "Acct-Authentic = RADIUS"."\n".
            "Event-Timestamp = ".time()."\n".
            "Acct-Session-Time = ".$sessiontime."\n".
            "Acct-Input-Octets = {$indata}\n".
            "Acct-Output-Octets = {$outdata}\n".
            "Acct-Status-Type = Stop"."\n".
            "NAS-Identifier = '{$nasIp}'"."\n".
            "Acct-Delay-Time = 0"."\n".
            "NAS-IP-Address = {$nasIp}\n".
            "Acct-Terminate-Cause = Admin-Reset\n";

        fwrite($handle, $packet);
        fclose($handle);
        $radResponse = radquery($tmpfname, 1);
        unlink($tmpfname);

        if($radResponse){
            $db->exec("DELETE FROM sessions WHERE sessionid='{$sessid}'");
            $db->exec("DELETE FROM session_stats WHERE sessionid='{$sessid}'");
        }

        continue;
    }

    
    $tmpfname = tempnam($tmpdir, "acctstatustmp_");
    $handle = fopen($tmpfname, "w");

    $indata = $session["Outgoing Data Size"];
    $indata = str_replace("\"", "", $indata);
    $indata = str_replace(",", "", $indata);
    $indata = trim(str_replace("bytes", "", $indata));

    $outdata = $session["Incoming Data Size"];
    $outdata = str_replace("\"", "", $outdata);
    $outdata = str_replace(",", "", $outdata);
    $outdata = trim(str_replace("bytes", "", $outdata));

    $packet = "Service-Type = Framed-User"."\n".
            "Framed-Protocol = PPP"."\n".
            "NAS-Port = {$nasPort}\n".
            "NAS-Port-Type = Async"."\n".
            "User-Name = '{$username}'"."\n".
            "Acct-Session-Id = '{$acctsessionid}'"."\n".
            "Framed-IP-Address = {$frameIp}\n".
            "Acct-Authentic = RADIUS"."\n".
            "Event-Timestamp = ".time()."\n".
            "Acct-Session-Time = ".$sessiontime."\n".
            "Acct-Input-Octets = ".$indata."\n".
            "Acct-Output-Octets = ".$outdata."\n".
            "Acct-Status-Type = Interim-Update"."\n".
            "NAS-Identifier = '{$nasIp}'"."\n".
            "Acct-Delay-Time = 0"."\n".
            "NAS-IP-Address = {$nasIp}\n";

    fwrite($handle, $packet);
    fclose($handle);
    radquery($tmpfname, 1);
    unlink($tmpfname);

    $db->exec('CREATE TABLE IF NOT EXISTS session_stats (sessionid varchar(255), indata varchar (255), outdata varchar (255), PRIMARY KEY(sessionid))');
    $db->exec("REPLACE INTO session_stats(sessionid,indata,outdata) VALUES('{$sessid}','{$indata}','{$outdata}')");
}

$db->close();
fclose($fpLock);

exit(0);