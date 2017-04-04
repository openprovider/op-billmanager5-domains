<?php
namespace Billmgr;

class Response{
    private $isSuccess;

    public function __construct( $isSuccess ){
        $this->setIsSuccess($isSuccess);
    }

    /**
     * @return mixed
     */
    public function isSuccess()
    {
        return $this->isSuccess;
    }

    /**
     * @param mixed $isSuccess
     */
    public function setIsSuccess($isSuccess)
    {
        $this->isSuccess = $isSuccess;
    }

    protected function getEmptyXML(){
        return new \SimpleXMLElement("<doc/>");
    }
    public function getXMLString(){
        return $this->makeXMLParams()->asXML();
    }

    /**
     * @return \SimpleXMLElement
     */
    protected function makeXMLParams(){
        return $this->getEmptyXML();
    }
    
    
    protected function setAttribute( \SimpleXMLElement $xml, $key, $value ){
        if( trim($key) !="" && $value !== null && trim($value)!="" ){
            $xml->addAttribute($key, $value);
        }
    }
}