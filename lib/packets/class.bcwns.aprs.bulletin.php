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



class BCWNS_APRS_Bulletin extends BCWNS_APRS_BasePacket
{
  private $_msg;
  private $_dest;
  function __construct($msg, $blnid, $groupname="")
  {
  
    parent::__construct();
    
    $group = substr($groupname, 0, 5);
    if($blnid<0 || $blnid > 9){
      throw new Exception("Bulletin group ID must be 0-9");
    }
    
    $this->_msg = $msg;
    $this->_dest = sprintf("BLN%d%-5s", $blnid, $group);
    $this->setCode(":");
    $this->setMaximumTransmissions(1);
    $this->setRetryInterval(0);
    $this->setAckCode("");	// no ack.
  
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
