<?php
namespace Billmgr\Responses;
use Billmgr;

class ContactTypes extends Billmgr\Response{
  
    const TYPE_OWNER = "owner";
    const TYPE_CUSTOMER = "customer";
    const TYPE_ADMIN = "admin";
    const TYPE_BILL = "bill";
    const TYPE_TECH = "tech";

    private $types = array();
    private $ns_require;
    private $auth_code;
    private $tld;

    /**
     * @param $tld
     * @param $types
     * @param bool $nsRequire
     * @param bool $authCodeRequire
     */
    public function __construct($tld, $types, $nsRequire = false, $authCodeRequire = true)
    {
        $this->setTld( $tld );
        $this->setTypes($types);
        $this->setNsRequire($nsRequire);
        $this->setAuthCode($authCodeRequire);
        parent::__construct(true);
    }

    public function addContactType( $type ){
        if(!in_array( $type, $this->getTypes() )){
            $this->types[] = $type;
        }
    }

    /**
     * @return mixed
     */
    public function getTld()
    {
        return $this->tld;
    }

    /**
     * @param mixed $zone
     */
    public function setTld($zone)
    {
        $this->tld = $zone;
    }

    protected function makeXMLParams()
    {
        $xml = $this->getEmptyXML();
        
        if($this->getNsRequire()){
            $xml->addAttribute("ns","require");
        }
        if($this->getAuthCode()){
            $xml->addAttribute("auth_code","require");
        }

        $types = $this->getTypes();
        foreach ( $types as $type){
            $xml->addChild("contact_type", $type);
        }
        
        return $xml;
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param mixed $types
     */
    public function setTypes($types)
    {
        $this->types = $types;
    }

    /**
     * @return mixed
     */
    public function getNsRequire()
    {
        return $this->ns_require;
    }

    /**
     * @param mixed $ns_require
     */
    public function setNsRequire($ns_require)
    {
        $this->ns_require = $ns_require;
    }

    /**
     * @return mixed
     */
    public function getAuthCode()
    {
        return $this->auth_code;
    }

    /**
     * @param mixed $auth_code
     */
    public function setAuthCode($auth_code)
    {
        $this->auth_code = $auth_code;
    }
    
    
}