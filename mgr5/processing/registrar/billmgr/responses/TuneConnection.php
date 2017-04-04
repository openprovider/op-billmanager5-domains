<?php
namespace Billmgr\Responses;
use Billmgr;


class TuneConnection extends Billmgr\Response{
    private $xml;
    
    public function __construct( $stdinXML ){
        $this->xml = new \SimpleXMLElement( $stdinXML );
        parent::__construct(true);
    }

    public function getXMLString(){
        return $this->xml->asXML();
    }
}