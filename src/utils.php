<?php

$logfh=fopen("aprsbot.log", "a+");
function dolog($str)
{
 global $logfh;
 global $daemonize;
 $date = date('r');
 fprintf($logfh, "%s : %s\n", $date, $str);
 
 if(!$daemonize){
  decho($date." ".$str."\n");
  readline_on_new_line ();
 }

}

function decho($str)
{
 global $daemonize;
 if($daemonize){
  return;
 }
 
  global $use_readline;
 
 if(is_array($str)){
  print_r($str);
 } else if(is_object($str)){
  var_dump($str);
 } else {
  echo $str;
 }
  
  
  if($use_readline){
   readline_on_new_line();
   readline_redisplay();
  }
}


function bln($aprs, $msg, $i, $groupname="BLN", $from = FALSE, $path = FALSE)
{
 $msg=trim($msg);
 if($msg==""){
  return;
 }
 
  $msgo = new BCWNS_APRS_Bulletin($msg, $i, $groupname);
//  $msg->setCallsign(MYCALL);
  if($from==FALSE)
    $msgo->setCallsign(MYCALL);
  else
    $msgo->setCallsign($from);

  if($path!=FALSE){
    $msgo->setPath($path);
  }

  $aprs->sendPacket($msgo, PATH);
  
  dolog("Transmit bulletin #$i: $msg");
}


function txtmsg($aprs,$msg,$dest,$from=FALSE,$path=FALSE, $doack=TRUE)
{
  global $stats;
 
  $msgo = new BCWNS_APRS_Message($msg,$dest);
//  $msg->setCallsign(MYCALL);


 
  if($from==FALSE)
   $from = MYCALL;
   
  $msgo->setCallsign($from);

  if($path!=FALSE){
    $msgo->setPath($path);
  }
  
  if($doack==FALSE){
   $msgo->setAckCode("");
  }  
  $aprs->sendPacket($msgo, PATH);
  @$stats['lastsent'][$dest]++;
  
  dolog("Transmit message to '$dest' from '$from' via '$path' : '$msg'");
//  echo "Send $msg to $dest\n";
}

function aprs2dec($ap)
{
  $dir = substr($ap,-1,1);
  $sec = intval(substr($ap,-3,2));
  $min = intval(substr($ap,strpos($ap,".")-2,2));
  $hr  = intval(substr($ap,0,strpos($ap,".")-2));

  $latd=$hr;
   $latm=$min + $sec / 60;

   $z=$latd + ($latm/60);

   if(strlen($ap)==9 && $dir=="W"){
     return( "-".round($z,5));
   }
   return(round($z,5));

}

                              
function check_pid()
{
 try {
  $dat = intval(@file_get_contents("aprsbot.pid"));
 } catch (Exception $ex){
  return(FALSE);
 }
 return($dat);
}

function make_pid()
{
 $pid=getmypid();
 if($pid===FALSE){
  throw new Exception("getmypid not defined?");
 }
 
 $fh=fopen("aprsbot.pid", "w+");
 if(!$fh){
  throw new Exception("can't write aprsbot.pid");
 }
 
 fwrite($fh, $pid);
 fclose($fh);
 
}

function bcn($aprs, $beacon_call, $beacon_lat, $beacon_lng, $beacon_symbol, $beacon_status, $beacon_params=null)
{
 if($beacon_params!=NULL){
  foreach($beacon_params as $var=>$val){
   $beacon_status = str_replace($var,$val,$beacon_status);
  }
 }
      $beacon  = new BCWNS_APRS_Item($beacon_lat, $beacon_lng, $beacon_call, $beacon_symbol, $beacon_status);//BEACON_LATITUDE,BEACON_LONGITUDE,MYCALL,BEACON_SYMBOL,BEACON_STATUS);
      $beacon->setCallsign(MYCALL);    
      
      $aprs->sendPacket($beacon, PATH);
      dolog("BEACON: $beacon_call> $beacon_lat $beacon_lng $beacon_symbol $beacon_status");
}


function db_init()
{
 global $pdo;

  $dbname = MYCALL.".sq3";
  if(!file_exists($dbname)){
   $init=TRUE;
  } else {
   $init=FALSE;
  }
  
  try {
    $pdo = new PDO("sqlite:".$dbname);
    
  } catch(Exception $ex){
    echo "Failed to create sqlite3 database :(";
    exit(-2);
  
  }
  
  if($init){
   // hmm - check if it exists - we need a versions table
   $sql = $pdo->prepare("CREATE TABLE beacons(id INTEGER PRIMARY KEY, callsign TEXT, latitude REAL, longitude REAL, symbol TEXT, data TEXT, lastsent INTEGER, expires INTEGER, createdby TEXT)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE UNIQUE INDEX IF NOT EXISTS beacon_idx ON beacons(callsign,latitude,longitude)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS callsign_idx ON beacons(callsign)");
   $sql->execute();
  
  
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS loc_idx ON beacons(latitude, longitude)");
   $sql->execute();
 
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS lastsent_idx ON beacons(lastsent)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS expires_idx ON beacons(expires)");
   $sql->execute(); 
   

   // last seen  last heard
   $sql = $pdo->prepare("CREATE TABLE lastseen(id INTEGER PRIMARY KEY, callsign TEXT, latitude REAL, longitude REAL, symbol TEXT, data TEXT, lastseen INTEGER)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE UNIQUE INDEX IF NOT EXISTS lastseen_idx ON lastseen(callsign)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS callsign_idx ON lastseen(callsign)");
   $sql->execute();
  
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS loc_idx ON lastseen(latitude, longitude)");
   $sql->execute();
 
   $sql = $pdo->prepare("CREATE INDEX IF NOT EXISTS lastseen_idx ON lastseen(lastseen)");
   $sql->execute();
   
   
   
  }
  
  return(TRUE);
}

function lastseen_record($callsign,$lat,$long,$symbol,$data, $now)
{
 global $pdo;
 
 // requires sqlite3 2.24
 
 // sigh. postgres i guess
 //return;
 
 $sql = $pdo->prepare("INSERT INTO lastseen(callsign,latitude,longitude,symbol,data,lastseen) VALUES(:callsign,:latitude,:longitude,:symbol,:data,:lastseen)");
 if($sql==FALSE){
  echo "do prepare fail?\n";
  dolog("sql failure inserting last seen: " . $sql->lastErrorMsg());
  return;
 }
 $sql->bindValue(":callsign", $callsign);
 $sql->bindValue(":latitude", $lat);
 $sql->bindValue(":longitude", $long);
 $sql->bindValue(":symbol", $symbol);
 $sql->bindValue(":data", $data);
 $sql->bindValue(":lastseen", $now);
 $res = $sql->execute();
 if($res==FALSE){
//  echo "beacon record fail\n";
  // poor mans upsert
  
  $sql=$pdo->prepare("UPDATE lastseen SET latitude=:latitude,longitude=:longitude,symbol=:symbol,data=:data,lastseen=:lastseen WHERE callsign=:callsign");
   $sql->bindValue(":callsign", $callsign);
   $sql->bindValue(":latitude", $lat);
   $sql->bindValue(":longitude", $long);
   $sql->bindValue(":symbol", $symbol);
   $sql->bindValue(":data", $data);
   $sql->bindValue(":lastseen", $now);
   if($sql->execute()==FALSE){
    echo "beacon update fail!!\n";
    return(FALSE);
   }
 }
 
 return(TRUE);
}

function lastseen_getbycallsign($callsign)
{
 global $pdo;
 
 $sql = $pdo->prepare("SELECT * FROM lastseen WHERE callsign=:callsign ORDER BY lastseen DESC LIMIT 1");
 if($sql==FALSE){
  echo "failed to prepare select\n";
  return(FALSE);
 }
 $sql->bindValue(":callsign", $callsign);
 
 
 $res=$sql->execute();

 if($res==FALSE){
  echo "get exec fail $callsign\n";
  return(FALSE);
 } else {
  return(@$sql->fetchAll(PDO::FETCH_ASSOC)[0]);
 }
}

function lastseen_stats()
{
 global $pdo;
 
 $sql = $pdo->prepare("SELECT COUNT(callsign) as c FROM lastseen WHERE lastseen < :whence");
 $sql->bindValue(":whence", time() - 60*60);
 $res = $sql->execute();
 $ret = $sql->fetchAll(PDO::FETCH_ASSOC)[0];
// print_r($ret);
 $res = "stns:".$ret['c'];
 return($res);
 
}
