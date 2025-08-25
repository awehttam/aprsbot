<?php
function aprsbot_handlemice($hdr,$line)
{
	echo "RECEIVED MICE\n";
	print_r($hdr);	
	// TODO: Implemented mic-e decoder
}

function aprsbot_handleposition($hdr,$line)
{

	global $mqtt;
	global $stats;	
	
	switch($hdr['code']){
		case('!');
		
			$dat=$hdr['aprsdat'];
			$latr = substr($dat,0,7);
			$longr = substr($dat,9,9);
	
			$lat=aprs2dec($latr);
			$long=aprs2dec($longr);
			$symbol = substr($dat,25,1);
			$symtable = substr($dat,6,1);
	

		break;
		
		case('@');
			//020806z4919.69N/12310.00W-/A=000410SharkRF openSPOT3
		
		case('/');
		case('=');
		default:
//			echo "Don't know how to handle\n";
//			print_r($hdr);
			return(FALSE);
		break;


	}
	
	@$stats['positions']++;

	//echo "got positioon\n";
//	print_r($hdr);
	
	$now=time();
	
//function lastseen_record($callsign,$lat,$long,$symbol,$data)
	$lh = lastseen_getbycallsign($hdr['src']);
	if($lh){
//		print_r($lh);
		
		$lastseen = $lh['lastseen'];
		$tdiff = $now - $lastseen;
//		echo "Seen before! $tdiff seconds ago! \n";
	}
	
	if(lastseen_record($hdr['src'],$lat,$long,$symtable." ".$symbol,$line,$now)==FALSE){
		echo "  failed to record last heard for this station\n";
	}
	
return;	
	mqtt_publish("position", array(
		'nid'=>8,	// Network ID 8
		'nuid'=>$hdr['src'],	// Network user ID
		'lat'=>aprs2dec($lat),
		'lng'=>aprs2dec($long),
		'rawdata'=>$dat
	));
		
}

