#!/usr/bin/php
<?php
chdir(__DIR__);

$mtime = @filemtime("/tmp/issstatus.json");
if(!$mtime || time() - $mtime > 86400){
 $dat=file_get_contents("https://ve7igp.com/_datafiles/issbby.json");
 if($dat==FALSE){
     exit(-1);
  }
  $fh=fopen("/tmp/issstatus.json","w+");
  fwrite($fh, $dat);
  fclose($fh);
 $dat = json_decode($dat, TRUE);
} else {
 $dat=json_decode(file_get_contents("/tmp/issstatus.json"), TRUE);
}



$found=[];
foreach($dat['passes'] as $pass){
    $tdiff = time() - $pass['_timet'];
//    echo $tdiff."\n";
//    print_r($pass);
    if($tdiff > 0){
        continue;
    }

    if($tdiff <- 86400 * 7){
        continue;
    }

     $found[] = $pass;

}

//print_r($found);
$idx=0;
//$lines=[
$line='';
foreach($found as $pass){
// $lines[] = "ISS ".$pass['_duration']." minute window ".date('D M j G:i', $pass['_timet']);

 if($pass['_elevation'] < 40){
  continue;
 }
 if($line)
  $line.=", ";
 $line.= $pass['_duration'] .' min '.$pass['_elevation'].'° elv '.date('D M j G:i', $pass['_timet']);
 if(++$idx>=3){
  break;
 }
}
$msg="Next ISS Passes: $line";
$fh=fopen("blniss.txt", "w+");
fwrite($fh,$msg);
fclose($fh);
//echo $msg;


