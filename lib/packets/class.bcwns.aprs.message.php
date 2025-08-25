<?php
/*
 * class.bcwns.aprs.message.php : phpAPRS Message Packet
 * Matthew Asham, VE7UDP <matthewa@bcwireless.net>
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
 * 
 */



class BCWNS_APRS_Message extends BCWNS_APRS_BasePacket
{
  private $_msg;
  private $_dest;
  function __construct($msg,$dest="NOBODY")
  {
    parent::__construct();
    $this->_msg = $msg;
    $this->_dest = $dest;
    $this->setCode(":");
    $this->setMaximumTransmissions(5);
    $this->setRetryInterval(40);
    $this->setAckCode(rand(0,999));    
  
  }

  function constructPacket()
  {

///    $this->_ack = rand(0,999);
    $msg=$this->_msg;
//    $msg=str_replace(":","-",$this->_msg);
    $msg=str_replace("|"," ",$msg);
    $msg=str_replace("~","-",$msg);
    $ret=sprintf("%-9s",$this->_dest);
    
    
    $ret.=":".$msg;//."{".$this->_ack;
//    $msg.="{".$this->_ack;
    return($ret);
  }
  
  static function parsePacket($hdr)
  {
    $dest = trim(substr($hdr['aprsdat'],0,strpos($hdr['aprsdat'],":")));
    $msg = trim(substr($hdr['aprsdat'],strpos($hdr['aprsdat'],":")+1));      
    if(strpos($msg,"{")!==FALSE){
      $ack = substr($msg,strpos($msg,"{")+1);
//      $this->debug( "Ack code is $ack");
      $msg=substr($msg,0,strpos($msg,"{"));
    }
    
    
    $res=array(
      "frame"=>$hdr,
      "txtdest"=>$dest,
      "msg"=>$msg
    );
    
    if(isset($ack)){
      $res['ack'] = $ack;
    }
    
  }
  
}
?>
