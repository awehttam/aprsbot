<?php

$mqtt_enabled=FALSE;
$mqtt=FALSE;

function mqtt_init()
{

  global $mqtt;
  global $mqtt_enabled;
  
  if(!$mqtt_enabled){
    return;
  }
    $mqtt_server='iot-gw-a.example.com';
    $mqtt_port=1883;
    $mqtt_clientid=MYCALL;
    $mqtt_username='N0CALL';
    $mqtt_passcode='xyzzy';//
    $mqtt = new Bluerhinos\phpMQTT($mqtt_server, $mqtt_port, $mqtt_clientid);
    if(!$mqtt->connect(true, NULL, $mqtt_username, $mqtt_passcode)){
      echo "Failed to connect to mqtt\n";
      exit(1);
    }

    $mqtt_topics=array();
    $mqtt_topics['/u/n0call/aprs-txraw']  = array('qos' => 0, 'function' => 'mqtt_processapirequest');
    $mqtt->subscribe($mqtt_topics, 0);


}


function mqtt_processapirequest($topic, $msg)
{

  global $mqtt;
  global $mqtt_enabled;
  
  if(!$mqtt_enabled){
    return;
  }  
  echo 'MQTT Msg Recieved: ' . date('r') . "\n";
  echo "\tTopic: {$topic}\n\n";
  echo "\tMessage: $msg\n\n";

  switch($topic){
    case('/u/ve7udp/spanr/aprs-txraw'):
      global $aprs;
      $aprs->_send($msg."\n");
    break;
    
    
    default:
      echo "Unhandled api request\n";
    
  }
}

function mqtt_publish($topic, $data)
{
    global $mqtt;
    if(!$mqtt){
        return(FALSE);
    }
    
  global $mqtt_enabled;
  
  if(!$mqtt_enabled){
    return;
  }
    
    $topic = "/u/ve7udp/spanr/aprs/".$topic;	//".MQTT_TOPICPFX."/".$topic;
    
    $res=$mqtt->publish($topic, json_encode($data));
    return($res);
}

function mqtt_process()
{
  global $mqtt;
  global $mqtt_enabled;
  
  if(!$mqtt_enabled){
    return;
  }
  if($mqtt){
    $mqtt->proc();
  }
}

