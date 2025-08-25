<?php
// this is an example configuration for aprsbot.  
// you should create a new file named "local.aprsbot.cfg.php" instead of modifying
// this one. 

$opts=getopt("b:");

if(1){
    define("MYCALL","VE7IGP-10");
    define("PASSCODE","XYZZY");


    define("BEACON_LATITUDE","4915.81N");
    define("BEACON_LONGITUDE","12251.88W");
    define("BULLETIN_GROUP", "YVR");    
    define("PATH","WIDE2-2");
    
    define("RAMFS_PATH", "/tmp");	// pointer to ramfs where run-time sqlite database and other volatile files are kept,     
    
} else {
    define("MYCALL","VE7UDP-1");
    define("PASSCODE","22178");

    define("BEACON_LATITUDE","4915.81N");
    define("BEACON_LONGITUDE","12251.88W");
}

//define("BEACON_STATUS","BC Wide DMR Net - Fri Aug 14 @ 8:00 PM - BC1, TG 3027 BC-TRBO or TG 30271 Brandmeister");
define("BEACON_STATUS", "Experimental node");
//define("BEACON_SYMBOL","/;");
define("BEACON_SYMBOL", "/B");
//define("BEACON_SYMBOL", "\L");
define("MQTT_TOPICPFX", "aprs");

//define("HOST","vancouver.aprs2.net");//noam.aprs2.net");

//define("HOST", "ve7igp.com");
define("HOST", "noam.aprs2.net");
define("PORT",14580);
//define("PORT","10152");

define("FILTER", "m/600 t/m");	// I don't recommend anything more than 1000.  A full feed certainly will not work.

//define("PORT",14580);
//define("HOST", "sea-4-gw.foo-games.com");
//define("PORT", 10152);

if(isset($opts['b'])){
    $ival =$opts['b'];
} else {
    $ival=60*29;
}

define("BEACON_INTERVAL",$ival);
define("BULLETIN_INTERVAL", 60*15);
//define("BULLETIN_INTERVAL", 1);
