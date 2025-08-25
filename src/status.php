<?php

function aprsbot_handlestatus($hdr,$line)
{
	$dat=$hdr['aprsdat'];
 	$time = substr($dat,1,7);
 	$lat = substr($dat,7,8);
 	$long = substr($dat,16,9);
 	$symbol = substr($dat,26,1);

	$lat = aprs2dec($lat);
	$long = aprs2dec($long);	

	// 
}


function aprsbot_getstatuscfg()
{
	$dat = trim(str_replace(array("\n", "\r"), "", file_get_contents("status.txt")));
	echo "read $dat\n";
	if($dat==FALSE){
		$symbol = $txt = '';
	}  else {
		$symbol = trim(substr($dat, 0, 2));
		$txt = trim(substr($dat, 2));
	}
	
	if($symbol=='' ){
		$symbol='/B';
	}
	
	if($txt==''){
		$txt = "Just another network node";
	}
	
	$ret=array(
		'symbol'=>$symbol,
		'txt' => $txt
	);
	
	return($ret);	
}
