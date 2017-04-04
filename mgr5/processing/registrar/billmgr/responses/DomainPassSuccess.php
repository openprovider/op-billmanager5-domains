<?php
namespace Billmgr\Responses;
use Billmgr;

class DomainPassSuccess extends Billmgr\Response{

    public function __construct()
    {
        parent::__construct(true);
    }

    
    protected function makeXMLParams()
    {
        $xml = $this->getEmptyXML();
        $xml->addChild("domainpass", "ok");
        
        return $xml;
    }
}