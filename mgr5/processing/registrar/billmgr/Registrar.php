<?php
namespace Billmgr;
use Billmgr\Responses\ContactTypes;
use Billmgr\Responses\Error;
use Billmgr\Responses\ExceptionWithLang;
use Billmgr\Responses\Success;
use Billmgr\Tlds\Tldcheck;
use Config;
use Modules;

class Registrar{

    const STATUS_P_RENEW = "P_RENEW";
    const STATUS_ACTIVE = "ACTIVE";

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
     *
     * @return array|mixed
     */
    public function getAuthInfoFromProcessingList(){
        $moduleList = DBApi::getProcessingList(["module"=>"pm" . \Config::$REGNAME]);


        if(count($moduleList) > 0){
            return array_shift($moduleList);
        }
        return array();
    }

    public function getModuleId(){
        if( Request::getInstance()->getModule() != "" ){
            return  Request::getInstance()->getModule();
        } elseif( Request::getInstance()->getItem() != "" ) {
            $itemInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
            if( ( $moduleId = (string)$itemInfo->processingmodule ) == "" ){
                $priceInfo = DBApi::getPriceInfo( $itemInfo->pricelist );
                $moduleId = (string)$priceInfo['processingmodule'];
            }
            return $moduleId;
        }

        return null;
    }

    public static function getCalledModuleId(){
        if( Request::getInstance()->getModule() != "" ){
            return  Request::getInstance()->getModule();
        } elseif( Request::getInstance()->getItem() != "" ) {
            $itemInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
            if( ( $moduleId = (string)$itemInfo->processingmodule ) == "" ){
                $priceInfo = DBApi::getPriceInfo( $itemInfo->pricelist );
                $moduleId = (string)$priceInfo['processingmodule'];
            }
            return $moduleId;
        }

        return null;
    }

    private function getAuthInfo(){
        if( ($module_id = $this->getModuleId()) == null ){
            return array();
        }
        
        return DBApi::getModuleInfo($module_id);
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function features(){
        $module = $this->getRegistrarModule();

        $futures = array(
            "check_connection", "suspend", "getbalance", "import", "resume", "close", "setparam", "sync_item", "tune_service", "get_contact_type", "tune_service_profile", "validate_service_profile", "service_profile_update"
        );
        //"open","prolong", "transfer", "update_ns", "whois"

        if(is_callable(array( $module, "getFutures"))){
            $modulefutures = $module->getFutures();
        } else {
            $modulefutures = array("open","prolong", "transfer", "update_ns", "uploaddocs", "contactverify", "uploadext", "checkdomaindoc",);
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

        $fileIdList = [];

        foreach ( $xml->profile->file as $file ){
            $fileIdList[] = (string)$file["id"];
        }


        return new Responses\ContactVerify($fileIdList);
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function uploadext(){
        return new Responses\UploadExt();
    }


    /**
     * @param $module
     * @param $moduleId
     * @return Responses\Balance|Error
     */
    private function getModuleBalance($module, $moduleId ){
        if (!is_callable(array($module, "getBalance"))) {
            return new Responses\Error(new \Exception("Method not implemented", 500));
        }

        $balanceInfo = $module->getBalance();

        if (isset($balanceInfo["amount"]) && isset($balanceInfo["currency"])) {
            Api::setProcessingBalance( $moduleId, $balanceInfo["amount"], $balanceInfo["currency"]);
        }

        return new Responses\Balance($balanceInfo);
    }


    public function getbalance(){
        if( Request::getInstance()->getModule() != null ) {
            return $this->getModuleBalance($this->getRegistrarModule(), Request::getInstance()->getModule());
        } else {
            $pmInfo = Database::getInstance()->query("SELECT `id` FROM `processingmodule` WHERE `module`='" . Database::getInstance()->escape("pm" . \Config::$REGNAME) . "'");

            while( $row = mysqli_fetch_assoc($pmInfo)){
                try {
                    $moduleInfo = DBApi::getModuleInfo($row["id"]);
                    $module = $this->getRegistrarModule($moduleInfo);

                    $this->getModuleBalance( $module, $row["id"] );

                }catch (\Exception $nothing){}
            }

            return new Success();
        }
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function import(){
        $module = $this->getRegistrarModule();

        $domainNamesList = explode("\\", trim(Request::getInstance()->getSearchstring()));

        foreach ($domainNamesList as $domainName) {
            try{
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
                    if(isset($importResult->ok)){
                        throw new \Exception("Import " . $domain->getName() . " failed! Tariff for " . $domain->getTLD() . " with moduleID #" . Request::getInstance()->getModule() . " not found!");
                    }
                    throw new \Exception("Import " . $domain->getName() . " failed!");
                }


                if (is_callable(array($module, "get_domain_contact")) && !$nocontact) {
                    $contact = $module->get_domain_contact($domain);
                    \logger::dump("importedContact", $contact, \logger::LEVEL_DEBUG);
                    if(isset($contact["severalContacts"])){
                        foreach ($contact["severalContacts"] as $type => $contactInfo){
                            $contactResult = Api::importContact(Request::getInstance()->getModule(), $type, $contactInfo);
                            \logger::dump("contactResult", $contactResult->asXML(), \logger::LEVEL_DEBUG);
                            $profileToItem = Api::assignDomainContact((string)$importResult->service_id, (string)$contactResult->profile_id, $type );
                            \logger::dump("profileToItem", $profileToItem->asXML(), \logger::LEVEL_DEBUG);
                        }
                    }else{
                        $contactResult = Api::importContact(Request::getInstance()->getModule(), "owner", $contact);
                        \logger::dump("contactResult", $contactResult->asXML(), \logger::LEVEL_DEBUG);
                        $profileToItem = Api::assignDomainContact((string)$importResult->service_id, (string)$contactResult->profile_id);
                        \logger::dump("profileToItem", $profileToItem->asXML(), \logger::LEVEL_DEBUG);
                    }

                }

                if (is_callable(array($module, "post_import"))) {
                    $module->post_import($domain);
                }
            }catch (\Exception $nothing){}
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
        $module = $this->getRegistrarModule( $this->getAuthInfoFromProcessingList());
        \logger::dump("tune_service getParams" , Request::getInstance()->getParams());


        $tld = null;
        foreach ( Request::getInstance()->getParams() as $key ){
            $tld = $key;
        }
        $domain = Request::getInstance()->getParam($tld);

        $result = new Responses\TuneDomainService(
            Request::getInstance()->getStdin(),
            $tld,
            new \Domain($domain)
        );
        if(is_callable(array($module,"getTunService"))){
           $module->getTunService($result ) ;
        }
        return $result;
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
            $contactInfo = DBApi::getContactInfo( $contactId );

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
        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );


        try {
            $nsList = DBApi::getNSS($domainInfo->id);
            $domain = new \BillmanagerDomain($domainInfo->domain);
            $domain->setExtendedFields($domainInfo->extendedFields);
            $result = $module->update_ns($domain, $nsList);

            if ($result["result"] != "success") {
                throw new \Exception("UpdateNS error: " . $result["descr"], 500);
            }
        }catch (\Exception $ex){
            if( ($ex instanceof \TemporaryException) && $ex->getRetryTime() != null ){
                throw $ex;
            }
            try{
                $dinfo = $module->info_domain(new \Domain($domainInfo->domain));
                if($dinfo["result"] == "success"){
                    Api::setNSS( Request::getInstance()->getItem(), $dinfo["nss"]);
                }
            }catch (\Exception $nothing){}
            if( !($module instanceof manual) ) {
                Api::sendRequest("service.saveparam", array(
                    "elid" => Request::getInstance()->getItem(),
                    "name" => "ns_update_error",
                    "value" => date("Y-m-d H:i:s"),
                    "crypted" => "off",
                    "sok" => "ok",
                ));
            }

            throw $ex;
        }

        if( isset($domainInfo->service_status) && in_array($domainInfo->service_status, array(2,3)) ) {
            if (!empty($nsList)) {
                $this->setDomainStatus("ACTIVE");
            } else {
                $this->setDomainStatus("NOT_DELEGATE");
            }
        }
        Api::sendRequest("service.postupdatens", array(
            "elid" => Request::getInstance()->getItem(),
            "sok" => "ok",
        ));
        Api::sendRequest("service.saveparam", array(
            "elid" => Request::getInstance()->getItem(),
            "name" => "ns_update_error",
            "value" => "",
            "crypted" => "off",
            "sok" => "ok",
        ));
        //May 22 14:48:02 [545:1] sbin_utils INFO QUERY: func=service.saveparam&sok=ok&elid=917735&name=ns%5Fupdate%5Ferror&value=2019%2D05%2D22%2014%3A48%3A02%20&crypted=off
        //func=service.postupdatens&sok=ok&elid=91773

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function getauthcode(){
        $module = $this->getRegistrarModule();
        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );


        if( (string)$domainInfo->service_status == "6" ){
            throw new Exception("Request authcode denied",500,false);
        }

        if(!is_callable(array($module,"getAuthCode"))){
            return new Responses\AuthCode( "Generating authcode not supported" );
        }

        if( $this->isDomainInStopList( new \Domain((string)$domainInfo->domain) ) ){
            throw new \Exception("Request authcode denied");
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

        $xml = new \SimpleXMLElement(Request::getInstance()->getStdin());


        $classname = "Billmgr\\Tlds\\" . mb_strtoupper(Request::getInstance()->getParams()[0], "UTF-8");
        \logger::write("TLD $classname");
        if(class_exists($classname)) {
            $claz = new $classname();
            if($claz instanceof Tldcheck) {
                $claz->check($xml);
            }
        }
        if(is_callable(array($module,"validateProfile"))){
            //$contact_info = Api::getContactInfo($xml->owner_contact_select);
            return new Responses\TuneConnection( $module->validateProfile( $xml) );
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
        
        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );

        if( (string)$domainInfo->service_profile_owner == "" ){
            return new Responses\Success();
        }
        $profileWarnings = Database::getInstance()->getProfileWarnings((string)$domainInfo->service_profile_owner );
        if( count($profileWarnings) > 0 ){
            \logger::dump("Found transfer contact warnings", $profileWarnings,\logger::LEVEL_WARNING);
            return new Responses\Success();
        }

        $contactInfo = DBApi::getContactInfo( (string)$domainInfo->service_profile_owner );
        \logger::dump("ExternalInfo", $contactInfo["external_id"][ $this->getModuleId() ]);
        if(isset($contactInfo["external_id"][ $this->getModuleId() ])){
            $contactInfo["contact_id"] = $contactInfo["external_id"][ $this->getModuleId() ][0]["id"];
            $contactInfo["contact_id_list"] = $contactInfo["external_id"][ $this->getModuleId() ];
        }

        $domainDBInfo = Database::getInstance()->getDomainInfo( $domainInfo->id );
        /* @var \Module $module*/
        $domain = new \BillmanagerDomain($domainInfo->domain);
        $domain->setExtendedFields($domainInfo->extendedFields);
        try {
            $result = $module->transfer_domain(
                $domain,
                DBApi::getNSS($domainInfo->id),
                $contactInfo,
                ($domainInfo->period / 12) < 0 ? 1 : $domainInfo->period / 12,
                array("authCode" => isset($domainDBInfo["main_domain_auth_code"]) ? $domainDBInfo["main_domain_auth_code"] : $domainDBInfo["auth_code"])
            );
        } catch (\TransferException $ex) {
            Api::createTask(2,
                "TransferException
                Subject: " . $ex->getSubject() . "\n" .
                "Body: " . $ex->getBody() . "\n" .
                "Module: " . \Config::$REGNAME . "\n" .
                "Code: " . $ex->getCode() . "\n" .
                "Reason: " . $ex->getMessage() . "\n" .
                "Trace: " . $ex->getTraceAsString() . "\n\n" .
                "LogFile: " . \logger::$filename
            );
            $ex->cancelTransfer(Request::getInstance()->getItem(), Database::getInstance());
            throw $ex;
        }

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
            $openResult = Api::domainOpen(Request::getInstance()->getItem());

            if(
                $openResult instanceof \SimpleXMLElement &&
                isset($openResult->error["object"]) &&
                in_array((string)$openResult->error["object"], array("main_domain_admin_email", "main_domain_admin_phone"))
            ){
                Api::setItemInfo( "domain", Request::getInstance()->getItem(), array(
                    "main_domain_admin_phone"=>"+7 000 000-00-00",
                    "main_domain_admin_email"=>"noreply@example.net"
                ));
                Api::domainOpen(Request::getInstance()->getItem());
            }


            $expDate =  strtotime("+1 year");
            //P_TRANSFER

            $status = "ACTIVE";
            try{
                $dinfo = $module->info_domain( new \Domain($domainInfo->domain) );
                if( isset($dinfo["expire"]) ) {
                    $expDate = $dinfo["expire"];
                }

                if( isset($dinfo["status"]) ) {
                    $status = $dinfo["status"];
                    if ($status == "ACTIVE" && empty($dinfo["nss"])) {
                        $status = "NOT_DELEGATE";
                    }
                }
            }catch (\Exception $nothing){}

            if( isset($result["status"]) ){
                $status = $result["status"];
            }


            $this->setDomainStatus( $status );
            Api::setExpires(Request::getInstance()->getItem(), date("Y-m-d", $expDate ) );
        }
        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function open(){
        $module = $this->getRegistrarModule();

        $retrys = 5;
        do{

            $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );

            $domain = new \BillmanagerDomain($domainInfo->domain);

            $domain->setExtendedFields($domainInfo->extendedFields);
            if( trim($domain->getPunycode()) == "" ){
                sleep(1);
            }
        }while( trim($domain->getPunycode()) == "" && $retrys-- > 0 );

        if( trim($domain->getPunycode()) == "" ){
            throw new \TemporaryException("Domain is empty!", 500);
        }
        $loadedContacts = array();
        $contactInfo = DBApi::getContactInfo( $domainInfo->service_profile_owner );
        if( !isset($contactInfo["id"]) || trim($contactInfo["id"]) == "" ){
            throw new \TemporaryException("Contact is empty!", 500);
        }
        $loadedContacts[ $contactInfo["id"] ] = $contactInfo;
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
                    $contactInfo["additionalContacts"][$type] = isset($loadedContacts[(string)$domainInfo->{"service_profile_" . $type}]) ?
                        $loadedContacts[(string)$domainInfo->{"service_profile_" . $type}] :
                        DBApi::getContactInfo( $domainInfo->{"service_profile_" . $type} );
                    if( !isset($contactInfo["additionalContacts"][$type]["id"]) || trim($contactInfo["additionalContacts"][$type]["id"]) == "" ){
                        throw new \TemporaryException("Contact '$type' is empty!", 500);
                    }
                    $loadedContacts[(string)$domainInfo->{"service_profile_" . $type}] = $contactInfo["additionalContacts"][$type];
                    if(isset($contactInfo["additionalContacts"][$type]["external_id"][ $this->getModuleId() ])){
                        $contactInfo["additionalContacts"][$type]["contact_id"] = $contactInfo["additionalContacts"][$type]["external_id"][ $this->getModuleId() ][0]["id"];
                    }


                }
            }
        }

        try {
            $result = $module->reg_domain($domain, DBApi::getNSS($domainInfo->id), $contactInfo, $domainInfo->period / 12);
        }catch (RegistrationUnavailableException $ex){

            $dbQuery = Database::getInstance();

            $runningOperations = DBApi::getRunningOperation( Request::getInstance()->getRunningoperation() );
            \logger::dump("runningOperations", $runningOperations, \logger::LEVEL_DEBUG);

            if($ex instanceof DomainAlreadyRegistered ){
                if (!isset($runningOperations["trycount"]) || $runningOperations["trycount"] != 1 ){
                    throw new \Exception($ex->getMessage());
                }
            }

            $expenses = $dbQuery->getItemExpense(Request::getInstance()->getItem());
            $moduleInfo = DBApi::getModuleInfo($this->getModuleId());
            Api::createTask( (string)$moduleInfo['department'],"Refund registration\n" .
                "Command: " .Request::getInstance()->getCommand() . "\n" .
                "Item: " . Request::getInstance()->getItem() . "\n" .
                "Module: #" . (string)$moduleInfo['id'] . " " . (string)$moduleInfo['name'] . "\n" .
                "LogId: " . \logger::getRand() . "\n" .
                "LogFile: " . \logger::$filename);
            \logger::dump("Delete expenses", $expenses, \logger::LEVEL_DEBUG);
            $count = 0;
            foreach ($expenses as $expens){
                if (isset($expens["id"])){
                    Api::deleteExpense($expens["id"]);
                    $count++;
                    if( $count >= 2 ) {
                        break;
                    }
                }
            }
            Api::deleteRunningOperation(Request::getInstance()->getRunningoperation() );
            Api::postClose(Request::getInstance()->getItem());

            throw $ex;
        }

        if($result["result"] != "success"){
            throw new \Exception("Register error: " . $result["descr"], 500);
        }


        $this->setWhoisPrivacyProtection( $module, $domainInfo );

        if( isset($result["pending"]) && $result["pending"]== true ){
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


        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
        if(is_callable(array($module,"setSuspend"))){
            $module->setSuspend( new \Domain($domainInfo->domain) );
        }

        Api::postSuspend( Request::getInstance()->getItem() );

        $itemSuspendParams = mysqli_fetch_assoc( Database::getInstance()->query("SELECT `expiredate`, `suspenddate` FROM item WHERE `id`='" . Database::getInstance()->escape( Request::getInstance()->getItem() ) . "'") );

        if( !isset( $itemSuspendParams["expiredate"] ) || !isset($itemSuspendParams["suspenddate"]) || $itemSuspendParams["expiredate"] != $itemSuspendParams["suspenddate"] ){
            $dObject = new \Domain($domainInfo->domain);

            $skipWarning = false;
            if( !$skipWarning ) {
                $moduleInfo = DBApi::getModuleInfo($this->getModuleId());
                Api::createTask((string)$moduleInfo['department'], "Expired date != suspended date\n" .
                    "Expire date: " . $itemSuspendParams["expiredate"] . "\n" .
                    "Suspended date: " . $itemSuspendParams["suspenddate"] . "\n" .
                    "Command: " . Request::getInstance()->getCommand() . "\n" .
                    "Item: " . Request::getInstance()->getItem() . "\n" .
                    "Module: #" . (string)$moduleInfo['id'] . " " . (string)$moduleInfo['name'] . "\n" .
                    "LogId: " . \logger::getRand() . "\n" .
                    "LogFile: " . \logger::$filename);
            }
        }


        return new Responses\Success();
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function resume(){
        $module = $this->getRegistrarModule();


        if(is_callable(array($module,"setResume"))){
            $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
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
            $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
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

        $domainInfo = null;
        if(is_callable(array($module,"setParam"))){
            $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );

            $module->setParam(new \Domain($domainInfo->domain), DBApi::getNSS($domainInfo->id), DBApi::getContactInfo( $domainInfo->service_profile_owner ), $domainInfo->period / 12 );

        }

        $this->setWhoisPrivacyProtection( $module, $domainInfo );


        Api::postSetparam( Request::getInstance()->getItem() );

        return new Responses\Success();
    }

    /**
     * @param Modules\Registrar $module
     * @param null $domainInfo
     * @throws \Exception
     */
    private function setWhoisPrivacyProtection($module, $domainInfo = null ){
        if(is_callable(array($module,"setWhoisPrivacyProtection"))){
            if( $domainInfo == null ){
                $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
            }

            $details = DBApi::getPriceDetails( (string)$domainInfo->pricelist );

            $privacyProtectionAddonId = null;

            if( !empty($details) ) {
                foreach ($details as $elem) {
                    if (isset($elem["name"]) && (string)$elem["name"] == "Whois Privacy Protection" && (string)$elem["active"] == "on") {
                        $privacyProtectionAddonId = (string)$elem["id"];
                        break;
                    }
                }
            }

            if( $privacyProtectionAddonId == null ){
                \logger::write("Addon id for 'Whois Privacy Protection' not found in list: " . json_encode($details), \logger::LEVEL_WARNING);
                //throw new \Exception("Addon id for 'Whois Privacy Protection' not found in list: " . $details->asXML());
            } else {
                $fieldName = "addon_$privacyProtectionAddonId";

                $resultPP = $module->setWhoisPrivacyProtection(new \Domain((string)$domainInfo->domain),
                    isset($domainInfo->$fieldName) && (string)$domainInfo->$fieldName == "on"
                );

                \logger::dump("RESULT_PP", $resultPP, \logger::LEVEL_INFO);
            }
        }
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function prolong(){
        $module = $this->getRegistrarModule();

        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );

        $domain = new \BillmanagerDomain($domainInfo->domain);

        $domain->setExtendedFields($domainInfo->extendedFields);

        $registryInfo = null;
        try{
            $registryInfo = $module->info_domain( new \Domain($domainInfo->domain));
        }catch (\Exception $nothing ){}

        try {
            $result = $module->renew_domain($domain, $domainInfo->period / 12);
        }catch (ProlongUnavailableException $er ){
            if( (string)$domainInfo->account == "1842" ){
                Api::setExpires(Request::getInstance()->getItem(), date("Y-m-d", $er->getDateExpires()));
                Api::postProlong( Request::getInstance()->getItem() );

                return new Responses\Success();
            }

            $dbQuery = Database::getInstance();

            $runningOperations = DBApi::getRunningOperation( Request::getInstance()->getRunningoperation() );
            \logger::dump("runningOperations", $runningOperations, \logger::LEVEL_DEBUG);
            if( $er->getDateExpires() != null ) {
                Api::setExpires(Request::getInstance()->getItem(), date("Y-m-d", $er->getDateExpires()));
            }
            if (
                isset($runningOperations["trycount"]) && $runningOperations["trycount"] == 1 ||
                $er instanceof RestoreRequiredException
            ){
                $expenses = $dbQuery->getExpenseProlongForDay(Request::getInstance()->getItem(), 7);
                $moduleInfo = DBApi::getModuleInfo($this->getModuleId());
                Api::createTask( (string)$moduleInfo['department'],"Refund prolong\n" .
                    "Command: " .Request::getInstance()->getCommand() . "\n" .
                    "Item: " . Request::getInstance()->getItem() . "\n" .
                    "Module: #" . (string)$moduleInfo['id'] . " " . (string)$moduleInfo['name'] . "\n" .
                    "LogId: " . \logger::getRand() . "\n" .
                    "LogFile: " . \logger::$filename);
                \logger::dump("Delete expenses", $expenses, \logger::LEVEL_DEBUG);
                foreach ($expenses as $prolongId){
                    if (isset($prolongId["id"])){
                        Api::deleteExpense($prolongId["id"]);
                        break;
                    }
                }
            } else {
                $er = new \Exception($er->getMessage());
            }


            Api::deleteRunningOperation(Request::getInstance()->getRunningoperation() );

            //Api::postProlong(Request::getInstance()->getItem());
            //Api::deleteRunningOperation(Request::getInstance()->getRunningoperation() );
            if( !($er instanceof RestoreRequiredException) ) {
                $this->setDomainStatus(static::STATUS_ACTIVE);
            }

            throw $er;

        }catch (\TemporaryException $tu){
            $this->setDomainStatus( static::STATUS_P_RENEW );
            \logger::dump("Process info", DBApi::getRunningOperation( Request::getInstance()->getRunningoperation() ), \logger::LEVEL_INFO);
            throw $tu;
        }
        if($result["result"] != "success"){
            throw new \Exception("Prolong error: " . $result["descr"], 500);
        }

        if( isset($result["expire"]) ){
            $newExpireDate = $result["expire"];
        }elseif(isset($registryInfo["expire"])){
            $newExpireDate = date("Y-m-d", strtotime("+" . (string)$domainInfo->period . " month", $registryInfo["expire"] ));
        }else{
            $newExpireDate =  date("Y-m-d", strtotime("+" . (string)$domainInfo->period . " month", strtotime( (string)$domainInfo->expiredate ) ));
        }

        if(isset($registryInfo["status"])) {
            if ($registryInfo["status"] == "ACTIVE" && empty($registryInfo["nss"])) {
                $dinfo["status"] = "NOT_DELEGATE";
            }
            $this->setDomainStatus($registryInfo["status"]);
        } else {
            $this->setDomainStatus(static::STATUS_ACTIVE);
        }

        Api::setExpires(Request::getInstance()->getItem(), $newExpireDate );
        Api::postProlong( Request::getInstance()->getItem() );

        return new Responses\Success();
    }


    /**
     * @return Response
     * @throws \Exception
     */
    public function sync_item(){
        $module = $this->getRegistrarModule();

        $domainInfo = DBApi::getDomainInfo( Request::getInstance()->getItem() );
        if( $domainInfo->status == 4){
            return new Responses\Success();
        }

        $checkProlong = true;

        if( $module instanceof Modules\Interfaces\CheckCanSync ){
            $checkProlong = false;
            try {
                if ( !$module->canSync(new \Domain($domainInfo->domain)) ) {
                    return new Responses\Error(new \Exception("Module returns that sync currently unavailable"));
                }
            }catch (CheckSyncUnavailableException $ex){
                $checkProlong = true;
            }catch (\Exception $ex){
                return new Responses\Error(new \Exception("Sync error: " . $ex->getMessage(), $ex->getCode()));
            }
        }
        if( $checkProlong ){
            if ((string)$domainInfo->service_status == "7") { // status=7 => P_RENEW
                $cnt = mysqli_fetch_assoc(Database::getInstance()->query("SELECT COUNT(*) as 'cnt' FROM `runningoperation` WHERE `item`='" .
                    Database::getInstance()->escape(Request::getInstance()->getItem()) . "' AND `intname`='prolong'"));
                if ($cnt["cnt"] > 0) {
                    return new Responses\Error(new \Exception("Found prolong operation recently, sync unavailable"));
                }
            }

            $haveRecentlyProlong = mysqli_fetch_assoc(Database::getInstance()->query("SELECT COUNT(*) as 'cnt' FROM `expense` WHERE `operation`='prolong' AND `item`='" .
                Database::getInstance()->escape(Request::getInstance()->getItem()) . "' AND " .
                "(`realdate`=CURRENT_DATE OR ((`realdate` + INTERVAL 1 DAY)=CURRENT_DATE AND HOUR(NOW()) < 3))"));

            if ($haveRecentlyProlong["cnt"] > 0) {
                \logger::write("Found prolong operation recently, sync unavailable. Count: {$haveRecentlyProlong["cnt"]}", \logger::LEVEL_WARNING);

                return new Responses\Error(new \Exception("Found prolong operation recently, sync unavailable"));
            }

            $haveRecentlyResumeAction = mysqli_fetch_assoc(Database::getInstance()->query("SELECT COUNT(*) as 'cnt' FROM `history_item` WHERE `reference`='" . Database::getInstance()->escape(Request::getInstance()->getItem()) . "' AND `request_action`='service.postresume' AND `changedate` > NOW() - INTERVAL 1 DAY"));
            if( $haveRecentlyResumeAction["cnt"] > 0 ){
                \logger::write("Found resume operation recently, sync unavailable. Count: {$haveRecentlyResumeAction["cnt"]}",\logger::LEVEL_WARNING);

                return new Responses\Error(new \Exception("Found prolong operation recently, sync unavailable"));
            }

        }




        $dObject = new \Domain($domainInfo->domain);

        $extId = mysqli_fetch_assoc( Database::getInstance()->query("SELECT `value` as 'externalid' FROM `itemparam` WHERE `item`='" .
            Database::getInstance()->escape( Request::getInstance()->getItem() ). "' AND `intname`='" .
            Database::getInstance()->escape( 'externalid' ). "'") );

        if( isset($extId["externalid"]) && trim($extId["externalid"]) != "" ){
            $dObject->setExternalId( $extId["externalid"] );
        }
        try {
            $dinfo = $module->info_domain($dObject);
            \logger::dump("REGISTRY_INFO", $dinfo, \logger::LEVEL_INFO);
        }catch (\Exception $ex){
            if( $ex instanceof \TemporaryException && $ex->getRetryTime() != null ){
                $ex->setWithTask( false );
                throw  $ex;
            }
            if( $ex->getCode() == 404 && (string)$domainInfo->service_status  != "5"){
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
            if( (string)$domainInfo->status == 3 && $dinfo["expire"] > strtotime("+1 week") ){
                Api::postResume( Request::getInstance()->getItem() ); // fix wrong service status on prolonged domain
            }
        }

        if( isset($dinfo["externalid"]) ){
            Api::setParam(Request::getInstance()->getItem(), "externalid", $dinfo["externalid"]);
        }

        if( isset($dinfo["dnssec"]) ){
            if( count($dinfo["dnssec"]) > 0 ){

                $stringify = $this->serializeDNSSEC($dinfo["dnssec"]);
                Database::getInstance()->query("INSERT INTO `domaindnssec` (`domain`,`dnssec`) VALUES (" .
                    "'" . Database::getInstance()->escape(Request::getInstance()->getItem()) . "'," .
                    "'" . Database::getInstance()->escape($stringify) . "'" .
                    ") ON DUPLICATE KEY UPDATE `dnssec` = VALUES(dnssec)");
            } else {
                Database::getInstance()->query("UPDATE `domaindnssec` SET `dnssec`='' WHERE `domain`='" . Database::getInstance()->escape(Request::getInstance()->getItem()) . "'");
            }
        }


        if($dinfo["status"] == "ACTIVE" && empty($dinfo["nss"])){
            $dinfo["status"] = "NOT_DELEGATE";
        }
        $this->setDomainStatus( $dinfo["status"] );

        return new Responses\Success();
    }

    /**
     * @param \Domain $domain
     * @return bool
     * @throws \Exception
     */
    private function isDomainInStopList($domain ){
        $systemStopList = fopen(__DIR__ . "/../StopList.list", "r");

        $found = false;
        while( $line = fgets($systemStopList) ){
            if( trim($line) == "" ){
                continue;
            }
            if( $domain->getName() == (new \Domain($line))->getName() ){
                $found = true;
                \logger::write("Domain " . $domain->getName() . " found in system StopList!", \logger::LEVEL_WARNING);
                break;
            }
        }

        return $found;
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