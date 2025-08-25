#!/usr/bin/php
<?php
chdir(__DIR__);

$igpstatus = json_decode(@file_get_contents("https://ve7igp.com/_datafiles/VE7IGP.status.json"), TRUE);
if(!$igpstatus){
    echo "No status was decodable\n";
    exit(-1);
}

echo "Received IGP status:\n";

print_r($igpstatus);
echo "\n";
echo date('r', $igpstatus['wxat'])."\n";
$tdiff = time() - $igpstatus['wxat'];
echo "wxat time diffirence is ".$tdiff."\n";
//if($tdiff < 60*60*2){	// 2 hours
    $wxa = trim($igpstatus['wxa']);
    if($wxa){
        $str=trim(date('M j H:i')." ".$igpstatus['wxa']);
    } else {
        $str = '';
    }
    
    if($str==''){
        if($tdiff > 86400/2 ){
            echo "File is pretty stale - deleting blnwxa.txt";
            @unlink("blnwxa.txt");
        }
        echo "no status";
    } else {
        echo "updated status";
        file_put_contents("blnwxa.txt", $str);
    }
//}

exit(0);
