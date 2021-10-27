<?php


namespace Billmgr\Responses;

use Billmgr\Response;

class TuneDomainService  extends Response{

    private $xml;
    private $tld;
    private $domain;


    /**
     * TuneService constructor.
     * @param $xml
     * @param $tld
     * @param \Domain $domain
     */
    public function __construct($xml, $tld, $domain) {
        $this->xml = new \SimpleXMLElement($xml);
        $this->tld = $tld;
        $this->domain = $domain;
        parent::__construct(true);
    }
    public function addsLists($names, $values){

        foreach ($names as $fieldName ) {
            $list = $this->xml->addChild("slist");
            $list->addAttribute("name", htmlspecialchars($fieldName));
            foreach ($values as $key => $value) {
                $list->addChild("val", htmlspecialchars($value))
                    ->addAttribute("key", htmlspecialchars($key));
            }
        }
    }
    public function addSelectField( $name, $values, $required=false, $nameText=null, $description=null ){
        $names = array();
        if(isset($this->xml->metadata->form->page)){
            foreach ( $this->xml->metadata->form->page as $page ){
                $domain = explode(".", $this->getDomain()->getName());
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == "page_".$domain[0] ."____________" . $this->getTld() ) {
                    $currentName = "domainparam_" . $domain[0] ."____________" . $this->getTld() ."_" . $name;
                    $names[] = $currentName;
                    $this->addNodeSelectField($page, $currentName, $required);


                    $useOwner = $page->xpath("field[@name='" . $page["name"] . "_contact_use_first']");
                    if(
                        isset($useOwner[0]) &&
                        $useOwner[0] instanceof \SimpleXMLElement
                    ){
                        /* @var \SimpleXMLElement $if*/
                        $if = $useOwner[0]->input->addChild("if");
                        $if->addAttribute("value", "on");
                        $if->addAttribute("hide", htmlspecialchars( $currentName ) );
                    }
                }
            }
        } else {
            $names[] = $name;
            $this->addNodeSelectField( $this->xml->metadata->form, $name, $required);
        }


        foreach ($names as $fieldName ) {
            $list = $this->xml->addChild("slist");
            $list->addAttribute("name", htmlspecialchars($fieldName));
            foreach ($values as $key => $value) {
                $list->addChild("val", htmlspecialchars($value))
                    ->addAttribute("key", htmlspecialchars($key));
            }
        }

        if( $nameText != null ) {
            foreach ($names as $fieldName ) {
                $this->setMessage($fieldName, $nameText);
            }
        }

        if( $description != null){
            foreach ($names as $fieldName ) {
                $this->setMessage("hint_" . $fieldName, $description);
            }
        }
    }

    public function addInputField( $name, $defValue = "", $required=false, $nameText=null, $description=null, $type="text" ){
        $names = array();
        if(isset($this->xml->metadata->form->page)){

            foreach ( $this->xml->metadata->form->page as $page ){
                $domain = explode(".", $this->getDomain()->getName());
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == "page_".$domain[0] ."____________" . $this->getTld()  ) {
                    $currentName = "domainparam_" . $domain[0] ."____________" . $this->getTld() ."_" . $name;
                    $names[] = $currentName;
                    $this->addNodeInputField($page, $currentName, $defValue, $required, $type);
                }
            }
        } else {
            $names[] = $name;
            $this->addNodeInputField( $this->xml->metadata->form, $name, $defValue, $required, $type);
        }

        if($defValue != ""){
            foreach ($names as $fieldName ) {
                $this->setMessage("placeholder_" . $fieldName, $defValue);
            }
        }
        if( $nameText != null ) {
            foreach ($names as $fieldName ) {
                $this->setMessage($fieldName, $nameText);
            }
        }

        if( $description != null){

            foreach ($names as $fieldName ) {
                $this->setMessage("hint_" . $fieldName, $description);
            }
        }
    }
    /**
     * @param \SimpleXMLElement $node
     * @param $name
     * @param string $defValue
     * @param bool $required
     * @param string $type
     */
    protected function addNodeInputField($node, $name, $defValue = "", $required=false, $type="text" ){

        $field = $node->addChild("field");
        $field->addAttribute("name",  $name );

        $select = $field->addChild("input");
        $select->addAttribute("type", $type);
        $select->addAttribute("name", htmlspecialchars($name) );
        if( $required ){
            $select->addAttribute("required", "yes");
        }
    }
    /**
     * @param \SimpleXMLElement $node
     * @param $name
     * @param bool $required
     */
    private function addNodeSelectField($node, $name, $required=false){
        $field = $node->addChild("field");
        $field->addAttribute("name",  $name );
        $select = $field->addChild("select");
        $select->addAttribute("name", htmlspecialchars($name) );
        if( $required ){
            $select->addAttribute("required", "yes");
        }

    }
    public function setMessage( $name, $value ){
        $updateWarningMessage = $this->xml->xpath("/doc/messages/msg[@name='$name']");

        if( count($updateWarningMessage) == 0 ){
            /* @var \SimpleXMLElement $msg*/
            $msg = $this->xml->messages->addChild("msg", htmlspecialchars($value));
            $msg->addAttribute("name", htmlspecialchars($name));
        }else {
            $updateWarningMessage[0][0] = htmlspecialchars($value);
        }
    }
    public function getNamesForList($fieldName){
        $domain = explode(".", $this->getDomain()->getPunycode());
        return array("main_domain_" . $fieldName , "domainparam_" . $domain[0] ."____________" . $this->getTld() ."_" . $fieldName);
    }

    /**
     * @return mixed
     */
    public function getXml() {
        return $this->xml;
    }

    /**
     * @param mixed $xml
     */
    public function setXml($xml) {
        $this->xml = $xml;
    }

    /**
     * @return mixed
     */
    public function getTld() {
        return $this->tld;
    }

    /**
     * @param mixed $tld
     */
    public function setTld($tld) {
        $this->tld = $tld;
    }

    /**
     * @return \Domain
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @param \Domain $domain
     */
    public function setDomain($domain) {
        $this->domain = $domain;
    }
    /**
     * @return string
     */
    public function getXMLString(){
        return $this->xml->asXML();
    }
}