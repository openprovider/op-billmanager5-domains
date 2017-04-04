<?php
namespace Billmgr\Responses;
use Billmgr;


class TuneServiceProfile extends Billmgr\Response{
    private $xml;
    private $ctype;
    private $tld;
    private $externalFields;

    /**
     * @param $stdinXML
     * @param $tld
     * @param string $ctype
     */
    public function __construct( $stdinXML, $tld, $ctype = ContactTypes::TYPE_OWNER  ){
        $this->xml = new \SimpleXMLElement( $stdinXML );
        $this->setTld( $tld );
        $this->setCtype( $ctype );
        parent::__construct(true);
    }

    /**
     * @return bool
     */
    public function isNew(){
        return (string)$this->xml->id == "";
    }


    public function getFields(){
        return $this->xml->metadata->form->field;
    }

    public function setReadOnly( \SimpleXMLElement $field ){
        foreach ( $field->children() as $child ){
            /* @var \SimpleXMLElement $child */
            if(!isset($child["readonly"]) ){
                $child->addAttribute("readonly", "yes");
            } else {
                $child["readonly"] = "yes";
            }
        }
    }


    public function addSelectField( $name, $values, $required=false, $nameText=null, $description=null ){
        $names = array();
        if(isset($this->xml->metadata->form->page)){
            foreach ( $this->xml->metadata->form->page as $page ){
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == $this->getCtype() ) {
                    $currentName = $page["name"] . "_" . $name;
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


    /**
     * @return \SimpleXMLElement
     */
    private function getAdditionalPage(){
        $page = $this->xml->xpath("/doc/metadata/form/page[@name='domain_additional_field']");
        if(
            !isset($page[0]) ||
            !($page[0] instanceof \SimpleXMLElement)
        ){
            /* @var \SimpleXMLElement $page*/
            $page = $this->xml->metadata->form->addChild( "page" );
            $page->addAttribute("name", "domain_additional_field");
            $page->addAttribute("collapsed", "yes");
            $this->setMessage( "domain_additional_field", "Дополнительная информация" );

            return $page;
        }

        return $page[0];
    }

    public function addAdditionalSelectField( $name, $values, $required=false, $nameText=null, $description=null ){
        if($this->getCtype() == "owner" && isset($this->xml->metadata->form->page) ){
            foreach ( $this->xml->metadata->form->page as $page ){
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == $this->getCtype() ) {
                    $name = $page["name"] . "_additionaldomaininfo_" . $name;
                    $this->addNodeSelectField($page, $name, $required);
                }
            }

            $list = $this->xml->addChild("slist");
            $list->addAttribute("name", htmlspecialchars($name));
            foreach ($values as $key => $value) {
                $list->addChild("val", htmlspecialchars($value))
                    ->addAttribute("key", htmlspecialchars($key));
            }

            if( $nameText != null ) {
                $this->setMessage($name, $nameText);
            }

            if( $description != null){
                $this->setMessage("hint_" . $name, $description);
            }
        }
    }


    public function addAdditionalInputField( $name, $defValue = "", $required=false, $nameText=null, $description=null ){

        if($this->getCtype() == "owner" && isset($this->xml->metadata->form->page) ){
            foreach ( $this->xml->metadata->form->page as $page ){
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == $this->getCtype() ) {
                    $name = $page["name"]. "_additionaldomaininfo_" . $name;
                    $this->addNodeInputField($page, $name, $defValue, $required);
                }
            }

            if($defValue != ""){
                $this->setMessage("placeholder_" . $name, $defValue);
            }
            if( $nameText != null ) {
                $this->setMessage($name, $nameText);
            }

            if( $description != null){
                $this->setMessage( $name, $description);
            }
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

    public function addInputField( $name, $defValue = "", $required=false, $nameText=null, $description=null ){
        $names = array();
        if(isset($this->xml->metadata->form->page)){
            foreach ( $this->xml->metadata->form->page as $page ){
                /* @var \SimpleXMLElement $page*/
                if($page["name"] == $this->getCtype() ) {
                    $currentName = $page["name"] . "_" . $name;
                    $names[] = $currentName;
                    $this->addNodeInputField($page, $currentName, $defValue, $required);

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
            $this->addNodeInputField( $this->xml->metadata->form, $name, $defValue, $required);
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
     */
    private function addNodeInputField($node, $name, $defValue = "", $required=false ){

        $field = $node->addChild("field");
        $field->addAttribute("name",  $name );

        $select = $field->addChild("input");
        $select->addAttribute("type", "text");
        $select->addAttribute("name", htmlspecialchars($name) );
        if( $required ){
            $select->addAttribute("required", "yes");
        }
    }

    /**
     * @param string $message
     */
    public function setUpdateWarning($message = null ){

        $message = $message == null ?
            "Редактируемый профиль используется одной или несколькими услугами. В случае изменения данных профиля, внесенные изменения будут автоматически переданы регистратору доменов"
            : $message;

        $updateWarning = $this->xml->xpath("/doc/metadata/form/field[@name='update_warning']");
        if( count($updateWarning) == 0 ) {
            /* @var \SimpleXMLElement $field*/
            $field = new \SimpleXMLElement("<field/>");//$this->xml->metadata->form->addChild("field");
            $field->addAttribute("name", "update_warning");
            $field->addAttribute("remove_if", "new");
            $field->addAttribute("noname", "yes");
            $field->addAttribute("formwidth", "yes");

            $testArea = $field->addChild("textdata");
            $testArea->addAttribute("warning", "yes");
            $testArea->addAttribute("name", "update_warning");
            $testArea->addAttribute("type", "msg");

            $this->xmlAppfirst( $this->xml->metadata->form, $field );
        }

        $updateWarningMessage = $this->xml->xpath("/doc/messages/msg[@name='update_warning']");

        if( count($updateWarningMessage) == 0 ){
            /* @var \SimpleXMLElement $msg*/
            $msg = $this->xml->messages->addChild("msg", htmlspecialchars($message));
            $msg->addAttribute("name", "update_warning");
        }else {
            $updateWarningMessage[0][0] = htmlspecialchars($message);
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

    public function addError( $error ){

    }

    /**
     * @param \SimpleXMLElement $to
     * @param \SimpleXMLElement $from
     */
    private function xmlAppfirst(\SimpleXMLElement $to, \SimpleXMLElement $from) {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->insertBefore($toDom->ownerDocument->importNode($fromDom, true), $toDom->firstChild);
    }

    /**
     * @return mixed
     */
    public function getCtype()
    {
        return $this->ctype;
    }

    /**
     * @param mixed $ctype
     */
    public function setCtype($ctype)
    {
        $this->ctype = $ctype;
    }

    /**
     * @param \SimpleXMLElement $to
     * @param \SimpleXMLElement $from
     */
    private function xmlAppLast(\SimpleXMLElement $to, \SimpleXMLElement $from) {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->insertBefore($toDom->ownerDocument->importNode($fromDom, true), $toDom->lastChild);
    }


    /**
     * @return string
     */
    public function getXMLString(){
        return $this->xml->asXML();
    }

    /**
     * @return mixed
     */
    public function getTld()
    {
        return $this->tld;
    }

    /**
     * @param mixed $tld
     */
    public function setTld($tld)
    {
        $this->tld = $tld;
    }

    /**
     * @return array
     */
    public function getExternalFields()
    {
        return $this->externalFields;
    }

    /**
     * @param array $externalFields
     */
    public function setExternalFields($externalFields)
    {
        $this->externalFields = $externalFields;
    }

    /**
     * @param  $externalFields
     */
    public function setExternalField($externalFields)
    {
        $this->externalFields[] = $externalFields;
    }
}