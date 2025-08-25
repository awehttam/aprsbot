<?php
/* Matthew Asham, VE7UDP <matthewa@bcwireless.net>
 * 
 * This file is part of phpAPRS.
 * 
 * phpAPRS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * 
 * phpAPRS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with phpAPRS.  If not, see <http://www.gnu.org/licenses/>.
 */


/*

For reference only - not used

not complete either

define("APRSCODE_MICE",0x1c);
define("APRSCODE_MICE_OLD",0x1d);
define("APRSCODE_POSITION",'!');
define("APRSCODE_WX_PEETBROSUII",'#');
define("APRSCODE_GPS",'$');

define("APRSCODE_ITEM",')');
define("APRSCODE_TEST",',');
define("APRSCODE_POSITION_TS",'/');
define("APRSCODE_MESSAGE",":");
define("ARPSCODE_OBJECT",";");
define("APRSCODE_CAPABILITIES","<");
define("APRSCODE_POSITION_NOTS","=");
define("ARPSCODE_STATUS",">");
define("APRSCODE_QUERY",'?');
define("APRSCODE_TELEMETRY","T");
define("APRSCODE_USERDATA","{");
define("APRSCODE_THIRDPARTY","}");
*/


interface BCWNS_APRS_Packet {
  public function getCode();
  public function setCode($c);
  public function getCallsign();
  public function setCallsign($c);
  public function constructPacket();
  
}

require "class.bcwns.aprs.basepacket.php";
require "packets/class.bcwns.aprs.item.php";
require "packets/class.bcwns.aprs.message.php";
require "packets/class.bcwns.aprs.position.php";
require "packets/class.bcwns.aprs.bulletin.php";


class BCWNS_APRS {

  private $call;
  private $passcode;

  private $s;
  private $_connected;	// true or false
  private $_connd;	// connection try delays
  private $_lastcona;	// last connection attempt
  
  private $_server_comments = [];
  
  
  private $_timeout;
  
  
  private $_inputdat;
  private $_inputlen;

  private $_readbufsize = 2048;//2048;//512;//500;//12;//1024;//8096;
  
  private $_outbuffer;
  
  private $_version;
  public $_debug;

  private $_maxxmit;

  private $_ppm=[];
  private $_lastppm=0;
  

  
  function __construct()
  {

    $this->_timeout = 5;  
//    $this->_debug=TRUE;
    
    $this->_codes = array(
      0x1c=>"APRSCODE_MICE",
      0x1d=>"APRSCODE_MICE_OLD",
      '!'=>"APRSCODE_POSITION",
      ':'=>"APRSCODE_MESSAGE",
      "/"=>"APRSCODE_STATUS",
      
      "@"=>"APRSCODE_WEATHER",
//      ";"=>"APRSCODE_OBJECT",
      
      "="=>"APRSCODE_POSITION_NOTS",
      "@"=>"APRSCODE_POSITION_TS",
      
//      "\$"=>"APRSCODE_WEATHER",
//      "*"=>"APRSCODE_WEATHER",
//      "_"=>"APRSCODE_WEATHERNOPOS"

      "SERVER_COMMENT"=>"APRSCODE_SERVER_COMMENT"
    );
    
    $this->_version="2.0";
    
    $this->_maxxmit = 5;	// transmit a maximum of 5 times
    $this->_lastcona=1;
    $this->_connd=15;
  }
  
  function connect($host,$port,$call,$passcode=FALSE, $filter="")
  {

      if($this->_connected==true){
        $this->debug("Already connected!");
        return;
      }
      
      $lastconc = intval(intval(time()) - intval($this->_lastcona));
      if( $lastconc < $this->_connd){
        $this->debug("Last connection attempt was $lastconc seconds ago, waiting..");
        return(FALSE);
      }

      $this->call = $call;
      if($passcode==FALSE){
        $this->passcode = $this->MakePassCode($this->call);
      } else {
        $this->passcode = $passcode;
        $this->debug("Specified a passcode instead of using auto generated code: ".$this->MakePassCode($this->call));
      }
      if($this->passcode==""){
        if($this->_debug){
          $this->debug("No passcode specified! $passcode");
          exit;
        }
      }
      
      $this->server = $host;
      $this->port = $port;

      $this->s = socket_create(AF_INET,SOCK_STREAM,getprotobyname("tcp"));//stream_socket_client("tcp://$host:$port",$errno,$errstr,$this->_timeout);
      
      if(socket_set_option($this->s, SOL_TCP, TCP_NODELAY, TRUE)==FALSE){
        $this->debug("Unable to set NO_DELAY");
      }
      
      
      $this->_lastcona = time();
      $res=socket_connect($this->s,$host,$port);
      if($res==FALSE){
        $errno = socket_last_error();
        $errstr = socket_strerror($errno);
        socket_close($this->s);
        $this->debug( "Connect: $host: $port : $errno $errstr");
        return(FALSE);
      }	
      
      $this->_connected = true;
      $loginstr = "user ".$this->call." pass ".$this->passcode." vers phpAPRS ".$this->_version;
      if($filter){
        $loginstr.=" filter $filter";
      }
      $loginstr.="\n";
      
      $this->_send($loginstr);

//      if(socket_set_nonblock($this->s)==FALSE){
//        $this->debug("Unable to set non blocking");
//      }

      
      return(TRUE);
  }
  
  // marks the connection as disconnected
  function _disconnect()
  {
    $this->debug("_disconnect called\n");
    $this->_connected = false;
    socket_shutdown($this->s,2);
    socket_close($this->s);
    
  }
  
  function _send($dat)
  {
    $res=socket_send($this->s,$dat,strlen($dat),0);
    if($res<=0){
      $this->debug("socket send returned $res");
//      echo "Socket send returned $res\n";
      $this->_disconnect();
    } else {
      $this->debug("sent ($res): $dat");
    }
    return($res);
  }

  function filter($filter)
  {
    $this->_send("#filter $filter\r\n");
  }  
  
  function sendServer($txt)
  {
    $this->_send($txt."\r\n");
  }
  
  function sendPacket($obj,$path="APRS",$do=TRUE)
  //$from,$path,$code,$dat)
  {
    
    
    if(!is_object($obj)){
      $this->debug("object isn't an object");
      return(FALSE);
    }
    
    if(!$obj instanceof BCWNS_APRS_BasePacket){
      $this->debug("obj is not an instance of BCWNS_APRS_BasePacket");
      return(FALSE);
    }
    $pkt = $obj->constructPacket();
    if($pkt=="") {
      $this->debug("packet construction returned nothing on obj");
      return(FALSE);
    }
    $code=$obj->getCode();
    if($code==""){
      $this->debug("object does not have an aprs code");
      return(FALSE);
    }
    $tpath = $obj->getPath();
    if($tpath!=FALSE){
      $path = $tpath;
    }
    $this->_outbuffer[] = array(
      "from"=>$obj->getCallsign(),
      "data"=>$obj->constructPacket(),
      "path"=>$path,
      "code"=>$code,
      "stime"=>time(),
      "send"=>$do,
      "maxt"=>$obj->getMaximumTransmissions(),
      "retintval"=>$obj->getRetryInterval(),
      "txack"=>$obj->getAckCode(),
      "obj"=>&$obj
    );
    $this->debug(print_r($this->_outbuffer,TRUE));
  }
  
  
  function getOutQueueLen()
  {
//    print_r($this->_outbuffer);
    return(count($this->_outbuffer));
  }  
  
  function _processOut()
  {
//    $this->debug("in processout");
    if(empty($this->_outbuffer))
      return(FALSE);
      
    foreach($this->_outbuffer as $idx=>$arr){

      if(isset($arr['txc']) && ($arr['txc'] >= $arr['maxt'] || $arr['obj']->isAcked() == TRUE)){	// maximum transmissions exceeded, or packet was acknowledged
//        $this->debug("Remove $idx from outbuffer");
        unset($this->_outbuffer[$idx]);
        continue;
      }

      // if the interval time has elapsed, or if the packet has not yet been sent - send it.
      if( intval(intval(time()) - intval(@$arr['txtime'])) > $arr['retintval'] || $arr['txtime']==0){
        $this->debug("Process send $idx");
        $pkt = $arr['from'].">".$arr['path'].":".$arr['code'].$arr['data'];
        if($arr['txack']!="")
          $pkt.="{".$arr['txack'];

        $pkt.="\r\n";

        if($arr['send']==TRUE){
          $this->_send($pkt);
        } else {
          $this->debug("Debug send (not sending): $pkt");
        }
        
        if(!$arr['retintval']){	// No retry interval - remove it immediately (eg: for BLN)
          unset($this->_outbuffer[$idx]);
          continue;
        }
        @$this->_outbuffer[$idx]['txtime'] = time();
        @$this->_outbuffer[$idx]['txc']++;
      } else {
        $this->debug("ignore $idx\n");
      }
    }
  }
  
  function _processStats()
  {
    if(!$this->_lastppm){
      $this->_lastppm=time();
      return;
    }
    
    $tdiff = time() - $this->_lastppm;
    if($tdiff >=60){
      error_log(" aprs statistics (pp/$tdiff): ".print_r($this->getStats(), TRUE));
      error_log("memory_get_usage() = ".memory_get_usage());
      error_log("memory_get_peak_usage() = ".memory_get_peak_usage());
      $this->_ppm=[];
      
      $this->_lastppm=time();
    }
  }
  
  
  function _processMessageAck($hdr)
  {
     // processes acks.. 
     $mhdr = BCWNS_APRS_Message::parsePacket($hdr);
     
     if($hdr['src'] =='VE7UDP-4')
       $this->debug("in processmessageack: ".print_r($mhdr,TRUE));
    if(empty($this->_outbuffer))
      return;
     foreach($this->_outbuffer as $idx=>$arr){
       if($arr['obj']->getCallsign() == $mhdr['txtdest'] && $mhdr['ack'] == "" && $mhdr['msg']=="ack".$arr['obj']->getAckCode()){
         $this->debug("Msg $idx recv ack ".$pkt['ack']);
         $arr['obj']->setAcked();
       }
     }
     return;   
  }
  
  function ioloop($wait=5)
  {
    
    $e = NULL;
//    $e[] = $this->s;
    $read[] = $this->s;//=array($this->s);
    
    if($this->_connected==false){
      if($this->connect($this->server,$this->port,$this->call,$this->passcode)==FALSE){
        $this->debug("Re-connection attempt failed");
        return(FALSE);
      };
    }
    
    
//  print_r(stream_get_meta_data($this->s));
//    $this->debug("before select");
    $res=socket_select($read,$e,$e,0);
    if($res===FALSE){
      $this->debug( "select error");
      return(FALSE);
    }
    
    if($res==0){
      // no messages
//      $this->debug("empty");
      $this->_processOut();
      return(FALSE);
    }
    
    
    
    //$this->_inputdat .= 
    $res=socket_recv($this->s,$buf,$this->_readbufsize,MSG_DONTWAIT);	// return immediately if no data available (disconnect?)
    if($res==FALSE){	// This should return false or 0 if the socket had an error OR was disconnected (if we get to this place in code after the select, that is)
      $this->debug("socket_recv returned false: ".socket_last_error()." ".socket_strerror(socket_last_error()));
      $this->_disconnect();
//      $this->debug( "Read 0 after select");
      return;
    }
    
    

    if($res>0){
      $this->debug( "Read ($res): $buf");    
      $this->_inputlen +=$res;
      $this->_inputdat.=$buf;
    }
    $this->process();
    $this->_processOut();    
    $this->_processStats();
  }
  
  function process()
  {
  
    $offt = strrpos($this->_inputdat,"\n");
    if($offt==strlen($this->_inputdat)){
      $seg=$this->_inputdat;
    } else {
      $seg=substr($this->_inputdat,0,$offt);
    }
    
    
    $this->_inputdat = substr($this->_inputdat,$offt+1);
    $this->_inputlen -= strlen($seg);


    $foo=explode("\n",$seg);
    foreach($foo as $line){
    
      if($line==''){
        continue;
      }
    
      // do something about $Line
//      echo "Process: $line\n";

      if($line[0]=='#'){
      
        $this->debug( "Server comment: $line");
        
        // buggy
        //  count(): Parameter must be an array or an object that implements Countable (on count() call below)
        //
        
        //array_push($this->_server_comments, $line);
        //if(count($this->_server_comments)>20){
        //  $this->_server_comments = array_pop($this->_server_comments);
        //  if($this->_server_comments==FALSE){
        //    $this->_server_comments=[];
        //  }
        //}
        
        if(isset($this->callbacks["SERVER_COMMENT"])){
          call_user_func($this->callbacks["SERVER_COMMENT"]['*'],$line);
        }
        
        continue;
      }
      
      
       $hdr = $this->parseHeader($line);
       if($hdr==FALSE){
         $this->debug( "Header decode fail");
         continue;
       }
      
       @$this->_ppm['all']++; 
//       if($hdr['src']=='VE7UDP-4')

         $this->debug("Inbound message code: ".$hdr['code']." from ".$hdr['src']);
         
       @$this->_ppm[$hdr['code']]++;
       
       if($hdr['code'] == ':'){
         $this->_processMessageAck($hdr);
       }
       
       if(strlen($hdr['code'])==1){	// This is a real APRS symbol (internal stuff will be longer than 1 char
       
         // Is there a call back for a specific path?
         if(isset($this->callbacks[$hdr['code']][$hdr['path'][0]])){
           $func = $this->callbacks[$hdr['code']][$hdr['path'][0]];
           if($func!="") {
             $res = call_user_func($func, $hdr, $line);
             if($res==FALSE){
               error_log("Unable to call specific path call back for ".$hdr['code']);
             }
           }
         }
       
         // Pass it on to the wild card handler too
         if(@isset($this->callbacks[$hdr['code']]['*'])){
           $res = call_user_func($this->callbacks[$hdr['code']]['*'], $hdr, $line);
           if($res!=FALSE){
             error_log("Unable to call wild card call back handler for ".$hdr['code']);
           }
         }
         
         

      } // is a single symbol
    }	// the i/o buffer
    
    return;
  // old
  
    $offt= strpos($this->_inputdat,"\n"); 
    
    $seg = substr($this->_inputdat,0,$offt);
    $this->debug( "Offt is $offt, seg was $seg");
    $this->debug( "_dat len is ".$this->_inputlen);
    $this->_inputdat = substr($this->_inputdat,$offt+1);
    $this->_inputlen-=$offt;
//    echo "Work on $seg\n";
  }
  
  function parseHeader($dat)
  {
    if(!$dat || $dat[0]=='#'){
      return(FALSE);
    }
    $hdr['src']=substr($dat,0,strpos($dat,">"));
    $hdr['path_full']=substr($dat,strpos($dat,">")+1,strpos($dat,":")-strlen($hdr['src'])-1);
    $hdr['path']=explode(",",$hdr['path_full']);
    $hdr['code'] = substr($dat,strpos($dat,":")+1,1);
    $hdr['aprsdat'] = substr($dat,strpos($dat,":")+2);
    return($hdr);
  
  }
  
  function addCallback($code,$dest,$func)
  {
    if(strlen($code)>1){
      foreach($this->_codes as $c=>$name){
        if($name==$code)
          $code=$c;
      }
    }
    
//    if(strlen($code)>1){
//      $this->debug( "Unknown named code: $code");
//      return(FALSE);
//    }
    $this->callbacks[$code][$dest]=$func;
  }
  
  function debug($str)
  {
    if($this->_debug==TRUE) {
      echo "debug: ".date('r')."  $str\n";
    }
  }
  

  function MakePassCode($call)
  {
    if(strpos($call,'-')!==FALSE){
      $lcall = strtoupper(substr($call,0,strpos($call,'-')));
    } else {
      $lcall = strtoupper($call);
    }

    $len = strlen($lcall);
    $i2=0;
    $hash = 0x73e2;
    while($i2<$len){
      $hash ^= ord($lcall[$i2++]) << 8;
      $hash ^= ord($lcall[$i2++]);
    }
    return($hash &  0x7fff);
  
  }  
  
  function getStats($stats=FALSE)
  {
    if($stats!=FALSE){
      return(@$this->_ppm[$stats]);
    }
    return($this->_ppm);
  }
  
  function getServerComments()
  {
    return($this->_server_comments);
  }
  
}


?>