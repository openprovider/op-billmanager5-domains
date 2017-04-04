<?php
namespace Billmgr;
use Billmgr\Responses\ContactTypes;
use Modules;

class Registrar{

    use \curl;

    /**
     * @return Modules\Registrar
     * @throws \Exception
     */
    private function getRegistrarModule( $authinfo = array() ){

        $class = "Modules\\" .  \Config::$REGNAME ;
            
        if(class_exists( $class )){            
            return new $class( empty($authinfo) ? $this->getAuthInfo() : $authinfo );
        }

        throw new \Exception( "Module '" . \Config::$REGNAME . "' not found", 500 );
    }

    /**
     * @WARNING UNSAFE! It can lead to recursive call
     *
     * @return array|mixed
     */
    public function getAuthInfoFromProcessingList(){
        $moduleList = Api::getProcessingList();

        $ids = array();
        foreach ( $moduleList->elem as $elem ){
            if( (string)$elem->module == "pm" . \Config::$REGNAME ){
                $moduleInfo = array();
                foreach ($elem as $key => $inf){
                    $moduleInfo[(string)$key] = (string)$inf;
                }
                $ids[] = $moduleInfo;
            }
        }

        if(count($ids) == 1){
            $minfo = Api::getModuleInfo( $ids[0]["id"] );

            $moduleInfo = array();
            foreach ($minfo as $key => $inf){
                $moduleInfo[$key] = (string)$inf;
            }
            return $moduleInfo;
        } elseif(count($ids) > 1) {
            $minfo = Api::getModuleInfo( $ids[0]["id"] );

            $moduleInfo = array();
            foreach ($minfo as $key => $inf){
                $moduleInfo[$key] = (string)$inf;
            }
            return $moduleInfo;
        }
        return array();
    }

    public function getModuleId(){
        if( Request::getInstance()->getModule() != "" ){
            return  Request::getInstance()->getModule();
        } elseif( Request::getInstance()->getItem() != "" ) {
            $itemInfo = Api::getDomainInfo( Request::getInstance()->getItem() );
            return (string)$itemInfo->processingmodule;
        }

        return null;
    }

    private function getAuthInfo(){
        if( ($module_id = $this->getModuleId()) == null ){
            return array();
        }
        
        $minfo = Api::getModuleInfo($module_id);

        $moduleInfo = array();
        foreach ($minfo as $key => $inf){
            $moduleInfo[$key] = (string)$inf;
        }
        return $moduleInfo;
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function features(){
        $module = $this->getRegistrarModule();

        $futures = array(
            "check_connection", "suspend", "import", "resume", "close", "setparam", "sync_item", "tune_service", "get_contact_type", "tune_service_profile", "validate_service_profile", "service_profile_update"
        );
        //"open","prolong", "transfer", "update_ns", "whois"

        if(is_callable(array( $module, "getFutures"))){
            $modulefutures = $module->getFutures();
        } else {
            $modulefutures = array("open","prolong", "transfer", "update_ns", "uploaddocs", "contactverify", "uploadext", "checkdomaindoc");
        }

        foreach ( $modulefutures as $mf ){
            $futures[] = $mf;
        }

        $authParams = array( "url" => false, "login" => false, "password" => true );
        if(is_callable(array( $module, "getAuthParams"))){
            $authParams = $module->getAuthParams();
        }

        
        return new Responses\Features($futures,$authParams);
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function check_connection(){
        $moduleInfo = array();
        $xml = new \SimpleXMLElement( Request::getInstance()->getStdin() );
        $r = $xml->xpath("/doc/processingmodule");

        foreach ($r[0] as $key =>$value){
            $moduleInfo[ (string)$key ] = (string)$value;
        }

        if( !$this->getRegistrarModule( $moduleInfo )->test() ){
            throw new \Exception("AuthTest failed!");
        }

        return new Responses\Success();
    }




    /**
     * @return Response
     * @throws \Exception
     */
    public function contactverify(){
        $xml = new \SimpleXMLElement(Request::getInstance()->getStdin());

        return new Responses\ContactVerify(array(
            $xml->profile->file["id"]
        ));
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function uploadext(){
        return new Responses\UploadExt();
    }





    /**
     * @return Response
     * @throws \Exception
     */
    public function import(){
        $module = $this->getRegistrarModule();

        $domainNamesList = explode("\\", trim(Request::getInstance()->getSearchstring()));

        foreach ($domainNamesList as $domainName) {
            $domainName = trim($domainName);
            $nocontact = false;
            if (strpos($domainName, "nocontact.") === 0) {
                $nocontact = true;
                $domainName = trim(str_replace("nocontact.", "", $domainName));
            }
            $domain = new \Domain($domainName);

            $dinfo = $module->info_domain($domain);

            $importResult = Api::importDomainService($domain->getName(), Request::getInstance()->getModule(), date("Y-m-d", $dinfo["expire"]));

            if (!isset($importResult->service_id) || (string)$importResult->service_id == "") {
                throw new \Exception("Import " . $domain->getName() . " failed!");
            }


            if (is_callable(array($module, "get_domain_contact")) && !$nocontact) {
                $contact = $module->get_domain_contact($domain);
                \logger::dump("importedContact", $contact, \logger::LEVEL_DEBUG);
                $contactResult = Api::importContact(Request::getInstance()->getModule(), "owner", $contact);
                \logger::dump("contactResult", $contactResult->asXML(), \logger::LEVEL_DEBUG);

                Api::assignDomainContact($importResult->service_id, $contactResult->profile_id);
            }
        }

        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function tune_connection(){
        $module = $this->getRegistrarModule();

        if(is_callable(array($module,"getTunConnection"))){
            return new Responses\TuneConnection( $module->getTunConnection( Request::getInstance()->getStdin() ) );
        }
        return new Responses\TuneConnection( Request::getInstance()->getStdin() );
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function tune_service(){
        $module = $this->getRegistrarModule();

        if(is_callable(array($module,"getTunService"))){
            return new Responses\TuneConnection( $module->getTunService( Request::getInstance()->getStdin() ) );
        }
        return new Responses\TuneConnection( Request::getInstance()->getStdin() );
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function tune_service_profile(){
        $module = $this->getRegistrarModule( $this->getAuthInfoFromProcessingList() );


        $tld = null;
        foreach ( Request::getInstance()->getParams() as $key ){
            if( $key == "profileid")
                continue;

            $tld = $key;
        }

        \logger::dump("CONTACT_TYPE",  Request::getInstance()->getParam( $tld ));
        $result = new Responses\TuneServiceProfile(
            Request::getInstance()->getStdin(),
            $tld,
            Request::getInstance()->getParam( $tld ) == null ? Responses\ContactTypes::TYPE_OWNER :  Request::getInstance()->getParam( $tld )
        );

        if(is_callable(array($module,"tunProfile"))){
            $module->tunProfile( $result );
        }

        if( is_callable(array($module,"update_contact")) && Request::getInstance()->getParam("profileid") !== null){
            $result->setUpdateWarning();
        }

        return $result;
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function service_profile_update(){
        $module = $this->getRegistrarModule();

        $contactId = Request::getInstance()->getParams()[0];
        if( is_callable(array($module,"update_contact")) && $contactId != null){
            $contactInfo = Api::getContactInfo( $contactId );

            if(isset($contactInfo["external_id"][ $this->getModuleId() ])){
                $contactInfo["contact_id"] = $contactInfo["external_id"][ $this->getModuleId() ][0]["id"];
                $module->update_contact( $contactInfo );
            }
        }

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function update_ns(){
        $module = $this->getRegistrarModule();
        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        $result = $module->update_ns(new \Domain($domainInfo->domain), Api::getNSS($domainInfo->id));

        if($result["result"] != "success"){
            throw new \Exception("UpdateNS error: " . $result["descr"], 500);
        }

        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function domainpass(){
        $module = $this->getRegistrarModule();
        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        if(!is_callable(array($module,"passDomain"))){
            throw new \Exception("Changing domain administrator not supported", 500);
        }


        $contactInfo = Api::getContactInfo( Request::getInstance()->getParam("contact") );
        if(isset($contactInfo["external_id"][ $this->getModuleId() ])){
            $contactInfo["contact_id"] = $contactInfo["external_id"][ $this->getModuleId() ][0]["id"];
        }


        $result = $module->passDomain(new \Domain((string)$domainInfo->domain), $contactInfo);


        if(isset($result["contact_id"]) && $result["contact_id"]!=""){
            Api::setContactExternalId( $contactInfo["id"], $result["contact_id"], $this->getModuleId());
        }

        if($result["result"] != "success"){
            throw new \Exception("domainPass error: " . $result["descr"], 500);
        }

        return new Responses\DomainPassSuccess();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function getauthcode(){
        $module = $this->getRegistrarModule();
        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        if(!is_callable(array($module,"getAuthCode"))){
            new Responses\AuthCode( "Generating authcode not supported" );
            //throw new \Exception("Generating authcode not supported", 500);
        }

        try {
            $result = $module->getAuthCode(new \Domain((string)$domainInfo->domain));
        }catch (\Exception $ex){
            if( $ex->getCode() == 404){
                $result = array(
                    "result" => "success",
                    "authcode" => "Domain not found"
                );
            } else {
                throw $ex;
            }
        }

        if($result["result"] != "success"){
            throw new \Exception("getAuthCode error: " . $result["descr"], 500);
        }

        return new Responses\AuthCode( $result["authcode"] );
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function validate_service_profile(){
        $module = $this->getRegistrarModule();

        if(is_callable(array($module,"validateProfile"))){
            $xml = new \SimpleXMLElement(Request::getInstance()->getStdin());
            $contact_info = Api::getContactInfo($xml->owner_contact_select);
            return new Responses\TuneConnection( $module->validateProfile( $contact_info ) );
        }

        return new Responses\TuneConnection( Request::getInstance()->getStdin() );
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function get_contact_type(){
        $module = $this->getRegistrarModule( $this->getAuthInfoFromProcessingList() );

        $contactTypes = new Responses\ContactTypes( Request::getInstance()->getTld(), array("owner") );

        if(is_callable(array($module,"getContactTypes"))){
            $module->getContactTypes( $contactTypes );
        }
        
        return $contactTypes;
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function transfer(){
        $module = $this->getRegistrarModule();
        
        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );
        $contactInfo = Api::getContactInfo( (string)$domainInfo->service_profile_owner );
        \logger::dump("ExternalInfo", $contactInfo["external_id"][ $this->getModuleId() ]);
        if(isset($contactInfo["external_id"][ $this->getModuleId() ])){
            $contactInfo["contact_id"] = $contactInfo["external_id"][ $this->getModuleId() ][0]["id"];
            $contactInfo["contact_id_list"] = $contactInfo["external_id"][ $this->getModuleId() ];
        }

        $domainDBInfo = Database::getInstance()->getDomainInfo( $domainInfo->id );
        $result = $module->transfer_domain(
            new \Domain($domainInfo->domain),
            Api::getNSS($domainInfo->id),
            $contactInfo,
            ($domainInfo->period / 12) < 0 ? 1 : $domainInfo->period / 12,
            array( "authCode" => $domainDBInfo["auth_code"] )
        );

        if(isset($result["contact_id"]) && $result["contact_id"]!=""){
            Api::setContactExternalId( $contactInfo["id"], $result["contact_id"], $this->getModuleId());
        }

        if($result["result"] != "success" && $result["result"] != "pending"){
            if(isset($result["client"]) && $result["client"]){
                throw new \ClientException($result["descr"]);
            }
            throw new \Exception("Transfer error: " . $result["descr"], 500);
        }
        if($result["result"] != "pending") {
            Api::domainOpen(Request::getInstance()->getItem());
            $this->setDomainStatus("ACTIVE");
        }
        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function open(){
        $module = $this->getRegistrarModule();

        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        $domain = new \Domain($domainInfo->domain);

        $contactInfo = Api::getContactInfo( $domainInfo->service_profile_owner );
        if(isset($contactInfo["external_id"][ $this->getModuleId() ])){
            $contactInfo["contact_id"] = $contactInfo["external_id"][ $this->getModuleId() ][0]["id"];
        }

        $contactTypes = null;
        if(is_callable(array($module,"getContactTypes"))){
            $contactTypes = new Responses\ContactTypes($domain->getTLD(), array("owner") );
            $module->getContactTypes( $contactTypes );

            foreach ($contactTypes->getTypes() as $type ){
                if($type == "owner")
                    continue;

                if( isset( $domainInfo->{"service_profile_" . $type} ) ) {
                    $contactInfo["additionalContacts"][$type] = Api::getContactInfo( $domainInfo->{"service_profile_" . $type} );
                    if(isset($contactInfo["additionalContacts"][$type]["external_id"][ $this->getModuleId() ])){
                        $contactInfo["additionalContacts"][$type]["contact_id"] = $contactInfo["additionalContacts"][$type]["external_id"][ $this->getModuleId() ][0]["id"];
                    }
                }
            }
        }


        $result = $module->reg_domain($domain, Api::getNSS($domainInfo->id), $contactInfo, $domainInfo->period / 12 );

        if($result["result"] != "success"){
            throw new \Exception("Register error: " . $result["descr"], 500);
        }

        if( isset($out["pending"]) && $out["pending"]== true ){
            \logger::write("PENDING_WAIT", \logger::LEVEL_WARNING);
            return new Responses\Success();
        }

        if(isset($result["contact_id"]) && $result["contact_id"]!=""){
            Api::setContactExternalId( $contactInfo["id"], $result["contact_id"], $this->getModuleId());
        }

        if($contactTypes instanceof Responses\ContactTypes){
            foreach ($contactTypes->getTypes() as $type ) {
                if ($type == "owner")
                    continue;

                if( isset($result[ $type  . "_id"]) && isset($domainInfo->{"service_profile_" . $type}) ){
                    Api::setContactExternalId( (string)$domainInfo->{"service_profile_" . $type}, $result[ $type  . "_id"], $this->getModuleId());
                }
            }
        }

        Api::domainOpen( Request::getInstance()->getItem() );
        $this->setDomainStatus("P_REGISTER");

        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function suspend(){
        $module = $this->getRegistrarModule();


        if(is_callable(array($module,"setSuspend"))){
            $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );
            $module->setSuspend( new \Domain($domainInfo->domain) );
        }

        Api::postSuspend( Request::getInstance()->getItem() );

        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function resume(){
        $module = $this->getRegistrarModule();


        if(is_callable(array($module,"setResume"))){
            $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );
            $module->setResume( new \Domain($domainInfo->domain) );
        }

        Api::postResume( Request::getInstance()->getItem() );

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function close(){
        $module = $this->getRegistrarModule();


        if(is_callable(array($module,"setClose"))){
            $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );
            $module->setClose( new \Domain($domainInfo->domain) );
        }

        Api::postClose( Request::getInstance()->getItem() );

        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function setparam(){
        $module = $this->getRegistrarModule();


        if(is_callable(array($module,"setParam"))){
            $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

            $module->setParam(new \Domain($domainInfo->domain), Api::getNSS($domainInfo->id), Api::getContactInfo( $domainInfo->service_profile_owner ), $domainInfo->period / 12 );

        }

        Api::postSetparam( Request::getInstance()->getItem() );

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function prolong(){
        $module = $this->getRegistrarModule();

        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        $result = $module->renew_domain(new \Domain($domainInfo->domain), $domainInfo->period / 12 );

        if($result["result"] != "success"){
            throw new \Exception("Prolong error: " . $result["descr"], 500);
        }

        Api::postProlong( Request::getInstance()->getItem() );

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function sync_item(){
        $module = $this->getRegistrarModule();

        $domainInfo = Api::getDomainInfo( Request::getInstance()->getItem() );

        try {
            $dinfo = $module->info_domain(new \Domain($domainInfo->domain));
        }catch (\Exception $ex){
            if( $ex->getCode() == 404 ){
                $this->setDomainStatus("NOT_FOUND");
            }
            return new Responses\Error(new \Exception("Sync error: " . $ex->getMessage(), $ex->getCode()));
        }
        if($dinfo["result"] != "success"){
            return new Responses\Error(new \Exception("Sync error: " . $dinfo["descr"], 500));//throw new \Exception("Sync error: " . $dinfo["descr"], 500);
        }
        
        Api::setNSS( Request::getInstance()->getItem(), $dinfo["nss"]);
        if(isset($dinfo["expire"]) && $dinfo["expire"]!="" ) {
            Api::setExpires(Request::getInstance()->getItem(), date("Y-m-d", $dinfo["expire"] ) );
            if( (string)$domainInfo->status == 3 && $dinfo["expire"] > strtotime("+3 month") ){
                Api::postResume( Request::getInstance()->getItem() ); // fix wrong service status on prolonged domain
            }
        }
        if($dinfo["status"] == "ACTIVE" && empty($dinfo["nss"])){
            $dinfo["status"] = "NOT_DELEGATE";
        }
        $this->setDomainStatus( $dinfo["status"] );

        return new Responses\Success();
    }


    private function setDomainStatus( $status ){
        //"ACTIVE", "NOT_DELEGATE", "P_REGISTER", "P_RENEW", "P_TRANSFER", "NOT_FOUND"
        $sid = null;
        switch ($status){
            case "ACTIVE":
                $sid = 2;
                break;
            case "NOT_DELEGATE":
                $sid = 3;
                break;
            case "P_REGISTER":
                $sid = 5;
                break;
            case "P_RENEW":
                $sid = 7;
                break;
            case "P_TRANSFER":
                $sid = 6;
                break;
            case "NOT_FOUND":
                $sid = 4;
                break;
        }

        if( $sid != null ){
            Api::setStatus( Request::getInstance()->getItem(), $sid );
        }
    }
}