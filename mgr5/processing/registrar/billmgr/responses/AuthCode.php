<?php
namespace Billmgr\Responses;
use Billmgr;

class AuthCode extends Billmgr\Response{

    private $authCode;
    private $message;


    public function __construct( $authCode ) {
        $this->setAuthCode( $authCode );
        $this->setMessage( $message );
        parent::__construct(true);
    }

    /**
     * @return mixed
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    /**
     * @param mixed $authCode
     */
    public function setAuthCode($authCode)
    {
        $this->authCode = $authCode;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }


    protected function makeXMLParams() {
        $xml = $this->getEmptyXML();

        $xml->addChild("authcode", $this->getAuthCode());

        return $xml;
    }
}