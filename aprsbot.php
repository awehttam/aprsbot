  <?php

$DEBUG=FALSE;


function usage($argv)
{
  $ret=<<<_EOT_
  
{$argv[0]} TACAPRS - Tactical APRS Bot by VE7UDP
Copyright (c) 2008-2022 Matthew Asham <ve7udp@yahoo.com>

 -D  : become a daemon
 -t  : enter terminal mode

 -v  : debug
 
 
_EOT_;

  echo $ret;
  return 0;
}


if(file_exists("aprsbot.nostart")){
  echo "APRSBot is offline.\n";
  echo "Remove aprsbot.nostart to resume.\n";
//  usage($argv);
  exit(-1);
}
// aprsbot.php : Example phpAPRS bot
declare(ticks = 1);


define("APRSBOT_VERSION","1.07");

require "lib/class.bcwns.aprs.php";
require "src/position.php";
require "src/status.php";
require "src/message.php";
require "src/weather.php";
require "src/utils.php";
require "src/mqtt.php";

require "src/vendor/autoload.php";

if(file_exists("local.aprsbot.cfg.php"))
	require "local.aprsbot.cfg.php";
else
	require "aprsbot.cfg.php";


define("MODE_RECEIVER", 1);	// A bit of a misnomer since we are transmitting acks and some reply text in this proc. This is really aprs->mqtt
define("MODE_TRANSMITTER", 2);	// This is really mqtt -> aprs


$receive_pid = getmypid();
$mode = MODE_RECEIVER;
$stats=array();
$use_readline = FALSE;

function do_bulletins($aprs)
{

  clearstatcache();
  if(!file_exists("bulletins.txt")){
    return;
  }
  
  $blns = file("bulletins.txt");

  $blnid=[];
    
  foreach($blns as $idx=>$txt){
    

    $txt = trim($txt);
    if($txt=="" || @$txt[0]=='#' || @$txt[0]==';'){
      continue;
    }
    if(strpos($txt,"|")!==FALSE){
      $group = substr($txt,0,strpos($txt,"|"));
      $msg = substr($txt,strpos($txt,"|")+1);
    } else {
      $group = "";
      $msg = $txt;
    }
    $i=(int)@$blnid[$group]+1;
    @$blnid[$group]++;
//    echo "group='$group' msg='$msg'\n";

    if(strpos($msg, "%ISS%")!==FALSE){
      if(!file_exists("blniss.txt")){
        decho("blniss.txt missing\n");
        continue;
      } else {
        $msg=str_replace("%ISS%", @file_get_contents("blniss.txt"), $msg);
      }
    }

    if(strpos($msg, "%WXA%")!==FALSE){
      if(!file_exists("blnwxa.txt")){
        decho("blnwxa.txt missing\n");
        continue;
      } else {
        if(time() - filemtime("blnwxa.txt") < 60*60*4){
          $msg=str_replace("%WXA%", @file_get_contents("blnwxa.txt"), $msg);
        } else {
          decho("WXA report is too old");
          continue;
        }
      }
    }



    $msg=trim($msg);
    if($msg==""){
      continue;
    }
    
//    echo "send bln($msg,$i,$group)\n";

    bln($aprs, $msg, $i, $group);//BULLETIN_GROUP);
  }
  
}

$term_mode="readline";	// could be talk, readline

function console($line)
{
  global $term_mode;
  global $aprs;
  
  //decho( "read '$line' ".ord($line)."\n");
  
  if(@$line[0]=='/'){
    if($term_mode=="talk"){
      if(substr($line,0,2)=="/q"){
        switch_terminal_mode("readline");
        echo "Reverted to console mode\n";
  //      $term_mode="readline";
        return;
      }
    }
    
    echo "No commands available yet\n";
    return;
  }
  
  
  if($term_mode=="talk"){
    global $aprs;
    echo "Xmit '$line'\n";
    $aprs->sendServer($line);
    return;
  }
  
  $argv = explode(" ", $line);
  $argc = count($argv);
  $rest = trim(substr($line, strlen($line)));
  
  switch($argv[0]){ //line){
  
    case('help');
    case('?');
    $help=<<<_EOT_

quit - the program
talk - directly to aprs-is server

beacon - rebeacon
bull - re-publish bulletins
stats - show stats


_EOT_;
    echo $help;
    
    break;
    
    case('hcf');
      touch("aprsbot.nostart");
      decho("aprsbot.nostart touched, will not start. going down now!\n");
      exit(0);
    break;
    
    case('debug');
      global $DEBUG;
      $DEBUG=!$DEBUG;
      $aprs->_debug=$DEBUG;
      decho("debug toggled to ".(int)$DEBUG);
    break;
    
    case('beacon');
      global $lastbeacon;
      $lastbeacon = time() - BEACON_INTERVAL;
      decho("beacon rescheduled\n");
    break;
    
    case('bull');
      decho("bulletin publish rescheduled\n");
      do_bulletins($aprs);
    break;
  
    // general "quit" command aliases
    case('bye');
    case('die');
    case('quit');
    case('rage');
    case('exit');
      decho("okee dokee!\n");
      exit(0);
    break;

    case('stats');
    break;
        
    case('talk');
      decho( "You're on the air! Send TNC2-ish frames!\n");
      decho(" to exit type /q\n\n");
      switch_terminal_mode("talk");
      
      
    break;

    case('msg');
      if($argc<2){
        decho("usage:  ".$argv[0]." TOCALL themessge");
        return;
      }
      $to=trim($argv[1]);
      $msg='';
      for($i=2; $i<$argc; $i++){
        $msg.=$argv[$i]." ";
      }
      $msg=trim($msg);
//      decho("sent to '$to' msg '$msg'\n");
      txtmsg($aprs, $to, $msg);
    break;
    
    case('stats');
      decho("coder too lazy to implement\n");
    break;
    
    break;    
    case('');
      return;
    
    default;
      echo "unknown command\n";
    
    
  }
}


function setprocstatus($status)
{
  cli_set_process_title($status);
}


function cleanup()
{
  unlink("aprsbot.pid");
}


function diediedie()
{
  global $aprs;
  
  if(is_object($aprs)){
    @$aprs->sendServer("# Thank you for your service! ");
  }
  
  decho( "Bailing out!\n");
  dolog("Bailing out!  Cleanup..");
  cleanup();
  
  dolog("Saving stats");
  global $stats;
  $fh=fopen("stats.json", "w+");
  fwrite($fh, json_encode($stats));
  fclose($fh);
  dolog("Exiting..");
}

function switch_terminal_mode($mode)
{
  global $use_readline;
  global $term_mode;

  echo "Switched mode from $term_mode ";  
  $term_mode = $mode;
  echo "to $term_mode\n";
  
  
  if($use_readline){
    if($mode=="readline"){
      $prompt=MYCALL;
    } else {
      $prompt="RAW APRS-IS";
    }  
    
    readline_callback_handler_remove();
    readline_callback_handler_install($prompt."> ", "console");      
    
  } else {
    // doesn't apply in daemon mode.
  }

}

function signal_handler($sig, $siginfo)
{
  dolog("Signal!  sig=$sig siginfo=".print_r($siginfo, TRUE));
  if($sig==15 || $sig==2){
    exit(0);
  }
  
  decho( "Unhandled\n");
}





  $opt=getopt("Dhvt?");
  $daemonize=FALSE;
  
  if(isset($opt['v'])){
    $DEBUG=TRUE;
  }
  
  if(isset($opt['t'])){
    $use_readline=TRUE;
  }
  if(isset($opt['h'])||isset($opt['h'])){
    exit(usage($argv));
  }
  
  if(isset($opt['D'])){
    if($use_reaedline==TRUE){
      echo "-D and -t are incompatible.  Pick one.\n";
      exit(-2);
    }
    $daemonize=TRUE;
    $use_readline=FALSE;
  }
  

  if($use_readline==TRUE && !function_exists("readline_callback_read_char")){
    echo "The readline extension does not appear to be installed or you're on Windows.\n";
    echo " = readline disabled.\n";
    $use_readline=FALSE;
  }

  if(getmyuid()==0){
    // become the geobbs user
    echo "WARNING:  Running as root is dangerous, attempting fall back to geobbs user.\n";
    
    $newuser = posix_getpwnam("geobbs");
    if(!$newuser){
      die("Can't find geobbs user to run as\n");
    }
    
    if(!posix_setuid($newuser['uid'])){
      die("Unable to setuid(".$newuser['uid'].")");
    }
  }
   
  if($daemonize){ 	
    echo "Daemonizing..\n";
    $pid = pcntl_fork();
    if($pid<0){
      die("Unable to fork!");
    }
    
    if($pid){
      exit(0);
    }
    
    if(posix_setsid()<0){
      die("setsid failed");
    }
    

    $pid = pcntl_fork();
    if($pid<0){
      exit(EXIT_FAILURE);
    }
    
    if($pid){
      exit(0);
    }
    
    
  }  
  
  // We're now a child.
  

  
  if(check_pid()){
    die( "Unclean shutdown?  aprsbot.pid still exists.\n");
  }
  
  make_pid();

  register_shutdown_function("diediedie");
  pcntl_signal(2, "signal_handler");
  pcntl_signal(15, "signal_handler");


  db_init();
  
  
// The receiver process will be the original process, the forked process will be for receiving commands from mqtt and transmitting

  $aprs = new BCWNS_APRS();
  if($DEBUG){
    $aprs->_debug=TRUE;
  }

  $lastbull=1;
  


//  $aprs->_debug = TRUE;
//$aprs->setDebug(APRSDEBUG_IO);

  dolog("Connecting to ".HOST.":".PORT);
  if($aprs->connect(HOST,PORT,MYCALL,PASSCODE, defined("FILTER") ? FILTER : NULL)==FALSE){
    echo "Connect failed\n";
    exit;
  }
  
  dolog("Connected to ".HOST.":".PORT." as ".MYCALL);
  
  $stats=json_decode(@file_get_contents("stats.json"), TRUE);
  if(!$stats){
    $stats=array();
  }

  setprocstatus(MYCALL." APRS bot ".HOST.":".PORT);
  $lastbeacon = 1;


    mqtt_init();
    
    mqtt_publish("world", "ping", 0);


// Setup our callbacks to process incoming stuff
  $aprs->addCallback("SERVER_COMMENT", "*", function($line){
    decho("The server said: '".trim($line)."' \n");
  });
  
  $aprs->addCallback("APRSCODE_MESSAGE","*","aprsbot_handlemessage");
  $aprs->addCallBack("APRSCODE_STATUS","*","aprsbot_handlestatus");
  $aprs->addCallback("APRSCODE_POSITION","*","aprsbot_handleposition");
  $aprs->addCallback("APRSCODE_WEATHER","*","aprsbot_handleposition");	// probably need dedicated function
  $aprs->addCallback("APRSCODE_POSITION_NOTS","*","aprsbot_handleposition");  
  $aprs->addCallback("APRSCODE_OBJECT", "*", "aprsbot_handleposition");
  $aprs->addCallback("APRSCODE_POSITION_TS","*","aprsbot_handleposition");
  $aprs->addCallback("APRSCODE_MICE", "*", "aprsbot_handlemice");
  
  
//$aprs->addCallback("APRSCODE_WEATHER", "*", "aprsbot_handleweather");

//$aprs->filter("r/49.2600/-122.6100/500 t/poimqstunw");
  if(defined('FILTER')){
    $aprs->filter(FILTER);
  }

  decho("TACAPRS BOT ".APRSBOT_VERSION." running as ".MYCALL."\n");
  decho("Copyright (c) 2008-2022 Matthew Asham / VE7UDP\n");

  $lastbeacon = time() - (BEACON_INTERVAL) + 10;	// Wait 10 seconds before transmitting a beacon.  Our connected state may vary.
  $lastbull = time() - (BULLETIN_INTERVAL) + 12;	// Wait 2 minutes before transmitting a bulletin.  Connection state may vary.
  switch_terminal_mode("readline");
    
  while(1){

  // Beacon every BEACON_INTERVAL seconds
    if(time() - $lastbeacon > BEACON_INTERVAL ) {
//      $beacon_status = BEACON_STATUS." - v".APRSBOT_VERSION;
//      $beacon_status.= " ".@intval($stats['fake_rejects'])."FR";
      $beacon = aprsbot_getstatuscfg();
      
//      exit;
      bcn($aprs, MYCALL, BEACON_LATITUDE, BEACON_LONGITUDE, $beacon['symbol'], $beacon['txt']);
      $lastbeacon = time();
      
      //decho(lastseen_stats()."\n");
      
    }
    

    if(time() - $lastbull > BULLETIN_INTERVAL){
      do_bulletins($aprs);
      $lastbull=time();
    }	
    
    mqtt_process();
    
    $aprs->ioloop(5);	// handle I/O events
    
//    decho(".\n");
    if($use_readline){
      $w=$e=NULL;
      $r=[STDIN];
      $n=stream_select($r, $w, $e, 0, 100);
      if($n && in_array(STDIN,$r)){
        readline_callback_read_char();
      }
      time_nanosleep(0,25000000);

    } else {
//    sleep(1);	// sleep for a second to prevent cpu spinning
      time_nanosleep(0,25000000);
    }
    
//    echo "eol\n";
    
  }
  
  
  dolog("Reached the end, what?");
  

