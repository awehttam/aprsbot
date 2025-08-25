<?php
function aprsbot_handleweather($hdr,$line)
{
//	echo "Header: \n";

//	print_r($hdr);
/**
    [src] => SM6RTN
    [path_full] => APBM1S,TCPIP*,qAS,SK0RMQ-14
    [path] => Array
        (
            [0] => APBM1S
            [1] => TCPIP*
            [2] => qAS
            [3] => SK0RMQ-14
        )

    [code] => @
    [aprsdat] => 101636z5739.06N/01153.28ErPHG3300MMDVM 145.6875/145.0875 CC6
*/
	//echo "\n\n";
	
	echo "Line:  $line\n";
	return;

	switch($hdr['code']){
		case('@');
		break;
		
		case(';');
		break;
		
	}

	$dat=$hdr['aprsdat'];
	$lat = substr($dat,0,7);
	$long = substr($dat,9,9);
	$symbol = substr($dat,25,1);
	$symtable = substr($dat,6,1);
	
}

