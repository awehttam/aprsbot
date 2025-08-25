<?php

$ratelimiter=array();

function ratelimit_add($callsign, $what)
{
  global $ratelimiter;
  $callsign=strtoupper($callsign);
  
  $ratelimiter[$callsign][$what] = time();
}

/**
 * Checks the rate limiter.  Very primitive
 * If $callsign is seen within $howoften, returns FALSE, otherwise returns TRUE
 */
function ratelimit_check($callsign, $what, $howoften)
{
  
  global $ratelimiter;


//  decho($ratelimiter);

  if(!isset($ratelimiter[$callsign][$what])){
    return(TRUE);
  }
  
  $last = $ratelimiter[$callsign][$what];
  
  $tdiff = time() - $last;
  
  decho ("tdiff = $tdiff time=".time()." last=$last\n");
  if($tdiff < $howoften){
    decho ("  $tdiff < $howoften\n");
    return(FALSE);
  }  
  decho( "ratelimit check return true\n");
  
  return(TRUE);
}


function aprsbot_handlemessage($hdr,$line)
{
  global $aprs;
  global $stats;
  
  // XXX:  This stuff should be handled in the packet class not here.
  $dest = trim(substr($hdr['aprsdat'],0,strpos($hdr['aprsdat'],":")));
  $msg = trim(substr($hdr['aprsdat'],strpos($hdr['aprsdat'],":")+1));

  $src = $hdr['src'];

//  decho($hdr);  
  // end of stuff that doesnt belong here.
  
  // Ignore messages that aren't intended for me
  if( strtoupper($dest)!=MYCALL){
    return;
  }


  
  if(strpos($msg,"{")!==FALSE){
    $ackcode = substr($msg,strpos($msg,"{")+1);
    decho ("Ack code is $ackcode");
    $msg=substr($msg,0,strpos($msg,"{"));
      $m = new BCWNS_APRS_Message("ack".$ackcode,$hdr['src']);//$line);
      $m->setCallsign(MYCALL);
      $m->setAckCode("");   // don't tag an ack as requiring an ack.
      $m->setMaximumTransmissions(1);
      $aprs->sendPacket($m, "WIDE1-1");//PATH);
      dolog("Sending message ack to $src");

  }
  if(substr($msg,0,3)=="ack" && strpos($msg," ")==FALSE){
//    decho ("Ignore ack: $msg");
    dolog("Received message ack '$msg' from '$src' nothing more to do");
    return;
  }
  
  if(substr($msg,0,3)=="rej"){
    // TODO:  Check if this is legit.  
    
    dolog("Received message rejection '$msg' from '$src' - ignoring");
    @$stats['rejects']++;
    
    if(!isset($stats['lastseen'][$src])){
        @$stats['fake_rejects']++;
        @$stats['stn_errors']["FAKE_REJECTS"][$src]++;
    }
    
    return;
  }
  
  $argv=explode(" ",$msg);
  echo $hdr['src'].">".$hdr['path_full']." $msg\n";
  
if(0){
  mqtt_publish("commands", array(
      'network_id'=>8,
      'network_userid'=>$hdr['src'],
      'command'=>implode(' ', $argv)
  ));
  }
  // TODO:  Insert rate limiting
  

  dolog("Received message from $src to $dest : '$msg'");  

  $rl = 15;

  if(ratelimit_check($src, $rl, $argv[0])==FALSE){
    dolog("DEBUG: $src failed rate check $rl - ignoring");
    return;
  }
  
  ratelimit_add($src, $argv[0]);

  switch(strtolower($argv[0])){
  
    case('?aprs?');
      txtmsg($aprs, "?aprsp ", $hdr['src']);
    break;
    case('credits');
    case('about');
    case('abt');
    case('version');
    case('?about');
    case('?ver');
      $helpmsg=MYCALL." gateway v".VERSION." by VE7UDP - ve7udp@yahoo.com";
      txtmsg($aprs, $helpmsg, $hdr['src']);
    break;


    case('?aprsp');
      global $lastbeacon;
      $lastbeacon = time() - BEACON_INTERVAL;      
    break;
    
    
    case('qsl');    
    case('?');
    case('help');
      $helpmsg="Experimental!  some cmds: abt, date, id, identify, lh, path, ping, [redacted]";
      txtmsg($aprs,$helpmsg, $hdr['src']);
    break;    

    case('date');
    case('time');
      txtmsg($aprs, "the current date is: ".date('r'),$hdr['src']);
    break;
    
    case('path');
      txtmsg($aprs, "your path was: ".$hdr['path_full'],$hdr['src']);
    break;

    case('qru');    	// this should be sit rep
      txtmsg($aprs, "sitrep not available", $hdr['src']);
    break;
    
    case('ping');
      txtmsg($aprs, "pong",$hdr['src']);
    break;

    case('lastheard');
    case('lh');    
    case('id');
      if(!isset($argv[1]) || trim($argv[1])==""){
        break;	// ignore them.
      }
      $call=$argv[1];
      $lh=lastseen_getbycallsign($call);
      if($lh){
        $tdiff=time()-$lh['lastseen'];
        $tdiff=$tdiff/3600;
        txtmsg($aprs,"$call seen at ".$lh['latitude']."/".$lh['longitude']." ".$tdiff." hours ago");
      } else {
        txtmsg($aprs, "call $call not heard");
      }
      
//      txtmsg($aprs, "no profile available (yet)", $hdr['src']);
    break;
    
    case('identify');
      txtmsg($aprs, "unable to identify", $hdr['src']);
    break;
    
    default;	// Note: This needs to be rate limited
//      txtmsg($aprs, "i know not what you mean", $hdr['src']);
  }
  
  return(TRUE);
  
}
