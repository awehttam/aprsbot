<?php
// This is an example configuration for aprsbot.  

// This file is an example only and should not be used or modified.
// Instead, copy this file to "local.aprsbot.cfg.php" and tailor to taste.

// You must  create a new file named "local.aprsbot.cfg.php" instead of modifying
// this one.  When local.aprsbot.cfg.php is present, this file will be ignored.  

$opts=getopt("b:");	// optional options for the configuration file

define("MYCALL","VA7BCW-8");
define("PASSCODE","");
define("PATH","WIDE2-2");

define("FILTER", "m/25 t/m");

define("BEACON_LATITUDE", "4923.05N");	// degrees, minutes and hundredths of a minute north.
define("BEACON_LONGITUDE", "12244.81W");	// degrees, minutes and hundredths of a minute west.

define("RAMFS_PATH", "/tmp");	// pointer to ramfs where run-time sqlite database and other volatile files are kept, 

//define("BEACON_STATUS","BC Wide DMR Net - Fri Aug 14 @ 8:00 PM - BC1, TG 3027 BC-TRBO or TG 30271 Brandmeister");
define("BEACON_STATUS", "Experimental node");
//define("BEACON_SYMBOL","/;");
define("BEACON_SYMBOL", "/B");
//define("BEACON_SYMBOL", "\L");
define("MQTT_TOPICPFX", "aprs");

define("HOST", "noam.aprs2.net");
define("PORT",14580);
//define("PORT", 10152);


if(isset($opts['b'])){	
    $ival =$opts['b'];
} else {
    $ival=60*29;
}

define("BEACON_INTERVAL",$ival);
define("BULLETIN_INTERVAL", 60*15);
define("BULLETIN_GROUP", "BLN");
