<?php
function getsessiondata($sessid) {
  global $vpncmd, $softetherip, $hubname, $apipass;

  exec($vpncmd." ".$softetherip." /SERVER /HUB:".$hubname." /PASSWORD:".$apipass." /CSV /CMD SessionGet ".$sessid,$SessionGet);
  if(strpos($SessionGet[0],"rror occurred") != FALSE) { die("Error - SessionGet resulted in error"); }
  foreach ($SessionGet as $line){
    list($key,$val) = explode(",",$line,2);
    $result[$key] = $val;
  }
  return $result;
}

function getsessiondata2($sessid) {
  global $vpncmd, $softetherip, $hubname, $apipass;

  exec($vpncmd." ".$softetherip." /SERVER /HUB:".$hubname." /PASSWORD:".$apipass." /CSV /CMD SessionGet ".$sessid,$SessionGet);
  if(strpos($SessionGet[0],"rror occurred") != FALSE) { return false; }
  foreach ($SessionGet as $line){
    list($key,$val) = explode(",",$line,2);
    $result[$key] = $val;
  }
  return $result;
}

function getdhcpip($sessid) {
  global $vpncmd, $softetherip, $hubname, $apipass;
  $dhcpok = 0;
  for ($i=0;$i<5;$i++) {
  exec($vpncmd." ".$softetherip." /SERVER /HUB:".$hubname." /PASSWORD:".$apipass." /CSV /CMD IpTable", $IpTable);
    foreach ($IpTable as $line){
      if(strpos($line,$sessid)){
        if(strpos($line,"DHCP")){
          list(,$key,$val) = explode(",",$line);
          list($framedip) = explode(" ",$val);
          $dhcpok=1;
        }
      }
    }
    if ($dhcpok === 1) { return $framedip; }
    sleep(2);  
  }
  return FALSE;
}

function disconnectsession($sessid) {
  global $vpncmd, $softetherip, $hubname, $apipass;
  exec($vpncmd." ".$softetherip." /SERVER /HUB:".$hubname." /PASSWORD:".$apipass." /CMD SessionDisconnect ".$sessid, $output);
}

function radquery($tmpfname,$debug) {
  global $radiussrv, $radiusport, $radiuspass, $radtimeout, $radretry;
  $radsrvcount = count($radiussrv);
  $i = 0;
  $success = false;

  $debugParam = "";
  if($debug === 1) $debugParam = " -x -F ";

  while ($i<$radsrvcount) {
    exec("radclient ".$radiussrv[$i].":".$radiusport." acct -f ".$tmpfname." -r ".$radretry." -t ".$radtimeout." {$debugParam} ".$radiuspass." 2>&1", $output);

    $allOut = implode("\n", $output);

    if (strpos($allOut, 'code 5') !== false) { $success=1; if($debug!==1) {break;} }
    $i++;
  }
  if ($debug === 1) {
    if($success !== 1){
      echo "### softether-radacct testing ###\n";
      echo "We received the following response from the RADIUS server(s):\n";
      print_r($allOut);
      echo "\n";
    }
    
    if ($success === 1) { echo "The test was SUCCESSFUL, we sent accounting data and received OK (code 5) back!\n\n"; } else { echo "The test was UNSUCCESSFUL, please see the logs above for the reason!\n\n"; }
  }

  return $success;
}

pcntl_signal(SIGCHLD, SIG_IGN); //to kill zombie children processes
