<?php
namespace Modules;
use Billmgr\DomainAlreadyRegistered;
use Billmgr\PremiumDomainException;
use Billmgr\ProlongUnavailableException;
use Billmgr\RestoreRequiredException;

require_once __DIR__ . "/library/openprovider.php";

/**
 * Class openprovider
 *
 * @see https://doc.openprovider.eu/Main_Page
 * @package Modules
 */
class openprovider extends Registrar{
    const SOFT_QUARANTINE = "softRestorePrice";
    const HARD_QUARANTINE = "restorePrice";

    const EXCLUDED_ADDITIONAL_FIELDS = array(
        "idnScript"
    );

    function __construct( $RegInfo ){
        $this->auth_info = array(
            "login" => $RegInfo["login"],
            "password" => $RegInfo["password"],
            "url" => "https://api.openprovider.eu"//$RegInfo["url"]
        );


        \logger::dump("OPENPROVModule init",  $RegInfo, \logger::LEVEL_DEBUG);
    }


    public function getContactTypes( \Billmgr\Responses\ContactTypes $contactTypes ){
        if( $contactTypes->getTld() != null ){

            $idna = new \idna_convert();
            $info = $this->sendCatchedRequest("retrieveExtensionRequest_" . $contactTypes->getTld(),
                "retrieveExtensionRequest",
                array(
                    'withPrice' => 0,
                    'withDescription' => 0,
                    'name' => $idna->encode($contactTypes->getTld()),
                ));

            $contactTypes->setAuthCode( $info["isTransferAuthCodeRequired"] == "yes" );
            if( $info["billingHandleEnabled"] == 1 ){
                $contactTypes->addContactType( \Billmgr\Responses\ContactTypes::TYPE_BILL );
                $contactTypes->addContactType( \Billmgr\Responses\ContactTypes::TYPE_TECH );
                $contactTypes->addContactType( \Billmgr\Responses\ContactTypes::TYPE_ADMIN );
            }
        }
    }

    public function getTunService(\Billmgr\Responses\TuneDomainService $domainParams){
        \logger::write("open getTunService");
        if($domainParams->getTld() != null){
            $additionalFields = $this->getDomainAdditionalFields($domainParams->getTld());

            \logger::dump("getCustomerAdditionalFields" , $additionalFields);
            if(!empty($additionalFields)){
                foreach ($additionalFields as $contactProperty){
                    if( in_array($contactProperty["name"], static::EXCLUDED_ADDITIONAL_FIELDS ) ){
                        continue;
                    }
                    if($contactProperty["type"] == "select"){
                        $values = array();
                        if(!isset($contactProperty["required"]) || $contactProperty["required"] != 1){
                            $values[ "" ] = "Not selected";
                        }
                        foreach ($contactProperty["options"] as $options){
                            $values[$options["value"]] = $options["description"];
                        }

                        $domain = explode(".", $domainParams->getDomain()->getPunycode());
                        $namesLists = array("main_domain_" . $contactProperty["name"] ,
                            "domainparam_" . $domain[0] ."____________" . $domainParams->getTld() ."_" . $contactProperty["name"] );
                        $domainParams->addsLists( $namesLists, $values);

                        /*
                        $domainParams->addSelectField($contactProperty["name"] , $values ,isset($contactProperty["required"]) && $contactProperty["required"] == 1,
                            isset( $contactProperty["label"]) ? $contactProperty["label"] : $contactProperty["description"], $contactProperty["description"]);
                        */
                    }
                }
            }
        }

    }

    /**
     * @param \Billmgr\Responses\TuneServiceProfile $profile
     */
    public function tunProfile(\Billmgr\Responses\TuneServiceProfile $profile ){

        if( $profile->getTld() != null ){

            $additionalFields = $this->getCustomerAdditionalFields( $profile->getTld() );
            \logger::dump("additionalFields",  $additionalFields);
            foreach ( $additionalFields as $contactProperty ){
                if( in_array($contactProperty["name"], static::EXCLUDED_ADDITIONAL_FIELDS ) ){
                    continue;
                }
                if($contactProperty["type"] == "select"){
                    $options = array();

                    foreach ( $contactProperty["options"] as $option ){
                        $options[ $option["value"] ] = $option["description"];
                    }

                    $profile->addSelectField(
                        $contactProperty["name"],
                        $options,
                        isset($contactProperty["required"]) && $contactProperty["required"] == 1,
                        isset( $contactProperty["label"]) ? $contactProperty["label"] : $contactProperty["name"],
                        $contactProperty["description"]
                    );
                } else {
                    $profile->addInputField(
                        $contactProperty["name"],
                        "",
                        isset($contactProperty["required"]) && $contactProperty["required"] == 1,
                        isset( $contactProperty["label"]) ? $contactProperty["label"] : $contactProperty["name"],
                        $contactProperty["description"]
                    );
                }
            }
        }
    }

    /**
     * @param \Domain $domain
     * @param bool $privacyStatus
     * @return array
     * @throws \Exception
     */
    public function setWhoisPrivacyProtection($domain, $privacyStatus ){


        $domainInfo =  $this->info_domain($domain);


        $out = array(
            "result" => "success",
        );

        if( $domainInfo["whoisprivacy"] == $privacyStatus ){
            $out["descr"] = "ALREADY_SAME_STATUS (" . json_encode($privacyStatus) . ")";
        }else{

            \logger::dump("SETTING WhoisPrivacyProtection!", $privacyStatus, \logger::LEVEL_INFO);
            try {
                $this->sendRequest("modifyDomainRequest", array(
                    'domain' => $this->getDomainParams($domain),
                    "isPrivateWhoisEnabled" => $privacyStatus ? 1 : 0,
                ));
            }catch (\Exception $ex) {
                if (strpos($ex->getMessage(), "The domain is not in your account") !== false) {
                    throw new \ClientException("Domain not found");
                }
                throw $ex;
            }
        }

        return $out;
    }


    private function getAdditionalFields( $tld ){
        $domainFields = $this->getDomainAdditionalFields( $tld );
        $customerFields = $this->getCustomerAdditionalFields( $tld );

        $result = array(
            "domain" => $domainFields,
            "customer" => array()
        );

        foreach ( $customerFields as $cField ){
            $find = false;
            foreach ( $domainFields as $dField ){
                if($dField["name"] == $cField["name"]){
                    $find = true;
                    break;
                }
            }

            if( !$find ){
                $result["customer"][] = $cField;
            }
        }

        return $result;
    }


    /**
     * @see https://doc.openprovider.eu/API_Module_Domain_retrieveCustomerAdditionalDataDomainRequest
     *
     * @param $tld
     * @return array
     */
    private function getCustomerAdditionalFields($tld ){
        if(in_array( $tld, array("ru","рф"))){
            return array();
        }
        $idna = new \idna_convert();
        $result = $this->sendCatchedRequest("retrieveCustomerAdditionalDataDomainRequest_$tld","retrieveCustomerAdditionalDataDomainRequest", array(
            "domain" => array(
                "extension" => $idna->encode( $tld )
            )
        ));

        return $result;
    }

    /**
     * @see https://doc.openprovider.eu/API_Module_Domain_retrieveAdditionalDataDomainRequest
     *
     * @param $tld
     * @return array
     */
    private function getDomainAdditionalFields($tld ){
        if(in_array( $tld, array("ru","рф"))){
            return array();
        }
        $idna = new \idna_convert();
        $result = $this->sendCatchedRequest("retrieveAdditionalDataDomainRequest_$tld", "retrieveAdditionalDataDomainRequest", array(
            "domain" => array(
                "extension" => $idna->encode( $tld )
            )
        ));

        return $result;
    }

    /**
     * @param \Domain $domain
     * @return array
     */
    private function getDomainParams($domain ){
        preg_match( "/^\.?([^\.]*)\.(.*)\.?$/", $domain->getName(), $params );
        $idna = new \idna_convert();
        return array(
            'name' =>  $idna->encode(trim($params[1])),
            'extension' => $idna->encode(trim($params[2]))
        );
    }

    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Domain_createDomainRequest
     *
     * @param \BillmanagerDomain $domain
     * @param $nss
     * @param $contact
     * @param int $period
     * @return array
     * @throws \ClientException
     * @throws \Exception
     */
    public function reg_domain($domain, $nss, $contact, $period = 1){
        $out = array();


        if( !isset($contact["contact_id"]) || $contact["contact_id"] == "" || !$this->checkProfile($contact["contact_id"])  ) {
            $result = $this->create_contact( $contact );

            if( $result == false ) {
                throw new \ClientException("Create owner contact error");
            }

            $out["contact_id"] = $contact["contact_id"] = $result["contact_id"];
        }

        $externalIds = array(
            $contact["id"] => $contact["contact_id"]
        );

        foreach ( array( "admin", "tech", "bill" ) as $cType ){
            if( isset($contact["additionalContacts"][ $cType ])){
                $additionalContact = $contact["additionalContacts"][ $cType ];
                if( isset($externalIds[ $additionalContact["id"] ]) ){
                    $contact[ $cType . "_id" ] = $externalIds[ $additionalContact["id"] ];
                } else {
                    if( !isset($additionalContact["contact_id"]) || $additionalContact["contact_id"] == "" || !$this->checkProfile($additionalContact["contact_id"])   ) {
                        $result = $this->create_contact($additionalContact);

                        if ($result == false) {
                            throw new \ClientException("Create $cType contact error");
                        }
                        $out[$cType . "_id"] = $contact[$cType . "_id"] = $externalIds[$additionalContact["id"]] = $result["contact_id"];
                    } else {
                        $contact[$cType . "_id"] = $additionalContact["contact_id"];
                    }
                }
            } else {
                $contact[ $cType . "_id" ] = $contact["contact_id"];
            }
        }


        $p_nss = array();


        foreach ($nss as $ns){
            $tmp = array(
                "name" => $ns["ns"]
            );
            if(isset($ns["ip"])){
                $tmp["ip"] = $ns["ip"];
            }

            $p_nss[] = $tmp;
        }

        $fl1 = null;
        $created = null;

        $request =  array(
            'ownerHandle' => $contact["contact_id"],
            'adminHandle' => $contact["admin_id"],
            'techHandle' => $contact["tech_id"],
            'billingHandle' => $contact["bill_id"],
            'domain' => $this->getDomainParams($domain),
            'period' => $period,
            "nameServers" => $p_nss
        );

        $additionalFields = $this->getAdditionalFields( $request["domain"]["extension"] );
        \logger::dump("domainAdditionalFields" , $domain->getExtendedFields() , \logger::LEVEL_DEBUG);
        $domainAdditionalFields = $domain->getExtendedFields();
        foreach ($additionalFields["domain"] as $field ){
            if(isset($domainAdditionalFields[$field["name"]]) ){
                $domainAdditionalFields[$field["name"]]  = trim($domainAdditionalFields[$field["name"]]);
                if($domainAdditionalFields[$field["name"]] != ""){
                    $request["additionalData"][$field["name"]] = (string)$domainAdditionalFields[$field["name"]];
                }
            }
        }

        if( !empty($additionalFields["customer"]) ) {
            foreach ( $externalIds as $externalId ){
                $billmgrContact = null;

                if( $contact["contact_id"] == $externalId){
                    $billmgrContact = $contact;
                } else {
                    foreach ( array( "admin", "tech", "bill" ) as $cType ){
                        if(
                            isset($contact["additionalContacts"][ $cType ]["contact_id"]) &&
                            $contact["additionalContacts"][ $cType ]["contact_id"] ==  $externalId
                        ){
                            $billmgrContact = $contact["additionalContacts"][ $cType ];
                        }
                    }
                }

                if( $billmgrContact == null )
                    continue;

                $contactInfo = $this->sendRequest("retrieveCustomerRequest", array(
                    "handle" => $externalId,
                    "withAdditionalData" => true,
                ))->getValue();

                $modifyRequest = array(
                    'handle' => $externalId,
                    'extensionAdditionalData' => array()
                );

                $extensionData = array();

                foreach ( $contactInfo["extensionAdditionalData"] as $data ){
                    if( $data["name"] == $request["domain"]["extension"] ){
                        $extensionData = $data["data"];
                    }
                }



                $additionalDataValues = array();
                foreach ($additionalFields["customer"] as $field){
                    if(
                        (!isset( $contactInfo["additionalData"][$field["name"]]) || trim($contactInfo["additionalData"][$field["name"]]) == "") &&
                        (!isset( $extensionData[$field["name"]] ) || trim($extensionData[$field["name"]]) == "")
                    ){
                        if( !empty($billmgrContact["raw"]) &&
                            isset($billmgrContact["raw"][$field["name"]]) &&
                            trim((string)$billmgrContact["raw"][$field["name"]]) != ""
                        ) {
                            $additionalDataValues[$field["name"]] = (string)$contact["raw"][$field["name"]];
                            //$modifyRequest["extensionAdditionalData"][$request["domain"]["extension"]][$field["name"]] = (string)$contact["raw"][$field["name"]];
                        }
                    }
                }
                if( $domain->getTLD() == "es" &&
                    !isset($additionalDataValues["companyRegistrationNumber"]) &&
                    !isset($additionalDataValues["passportNumber"]) &&
                    !isset($additionalDataValues["socialSecurityNumber"])
                ){
                    if( $contact["ctype"] == "company" ){
                        $additionalDataValues["companyRegistrationNumber"] = $contact['inn'];
                    } else {
                        $additionalDataValues["passportNumber"] = $contact['passport_series'];
                    }
                }

                if(!empty($additionalDataValues)){
                    $modifyRequest["extensionAdditionalData"][] = array(
                        "name" => $request["domain"]["extension"],
                        "data" => $additionalDataValues
                    );
                    $this->sendRequest("modifyCustomerRequest", $modifyRequest);
                }
            }
        }

        try {
            $this->sendRequest("createDomainRequest", $request);
        }catch (\Exception $ex){

            if( strpos($ex->getMessage(), "Your account balance is insufficient") !== false) {
                throw new \TemporaryException($ex->getMessage(), 503, true);
            }
            if( strpos($ex->getMessage(), "Billing failure") !== false ){
                \logger::write("OP_EXCEPTION: " . $ex->getMessage(), \logger::LEVEL_ERROR);
                $ex = new \Exception("Bad reply");
            }
            if( strpos($ex->getMessage(), "Registry currently not reachable") !== false ){
                \logger::write("OP_EXCEPTION: " . $ex->getMessage(), \logger::LEVEL_ERROR);
                $ex = new \TemporaryException($ex->getMessage());
            }
            if( strpos($ex->getMessage(), "The domain you want to register is not free") !== false ){
                \logger::write("OP_NOT_FREE: " . $ex->getMessage(), \logger::LEVEL_ERROR);

                $ex = new DomainAlreadyRegistered( $domain);
            }
            if( strpos($ex->getMessage(), "register the premium domain") !== false ){
                \logger::write("OP_NOT_FREE: (PREMIUM)" . $ex->getMessage(), \logger::LEVEL_ERROR);
                $ex = new PremiumDomainException($domain);
            }
            if( strpos($ex->getMessage(), "A contact of type registrant should be located") !== false ){
                preg_match("/(A contact of type registrant should be located[^\.]*)/", $ex->getMessage(), $matches );

                $descr = isset($matches[1]) && trim($matches[1]) != "" ? trim($matches[1]) : "Unavailable country for TLD";
                \logger::write("PROFILE_EXCEPTION: " . $ex->getMessage(), \logger::LEVEL_ERROR);
                $ex = new \ProfileException($descr);
                $ex->addProfileError( $contact["contact_id"], "location_country", $descr);
            }

            throw $ex;
        }
        $out["result"] = "success";

        return $out;
    }


    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Domain_renewDomainRequest
     *
     * @param \Domain $domain
     * @param int $period
     *
     * @return array
     * @throws ProlongUnavailableException
     * @throws \TemporaryException
     */
    public function renew_domain($domain, $period = 1) {


        $this->checkProlongUnavailable($domain);

        $domainInfo = $this->getDomainInfo($domain);
        $pricePeriodStatus = $this->getDomainPricePeriodStatus($domainInfo);
        if ($pricePeriodStatus !== false){
            $tldInfo = $this->getExtensionRequest($domain->getTLD() , $pricePeriodStatus);
            $price = $tldInfo[$pricePeriodStatus]["reseller"]["price"];
            if ($price == "0.00"){
                $this->sendRequest("restoreDomainRequest", array( "domain" => $this->getDomainParams($domain)));

                $out["result"] = "success";

                return $out;
            }else{
                $currency = isset($tldInfo[$pricePeriodStatus]["reseller"]["currency"]) ? $tldInfo[$pricePeriodStatus]["reseller"]["currency"] : "";
                $price = $price . " " . $currency;
                \logger::write("RESTORE_PRICE: " . $price,\logger::LEVEL_INFO);
                throw new RestoreRequiredException(500,$domain->getName());
            }
        }

        try {
            $this->sendRequest("renewDomainRequest", array(
                "domain" => $this->getDomainParams($domain),
                "period" => $period
            ));
        }catch (\Exception $exception ){
            //restoreDomainRequest
            if( strpos($exception->getMessage(), "Your account balance is insufficient") !== false) {
                throw new \TemporaryException($exception->getMessage(), 503, true);
            }
            if( strpos($exception->getMessage(), "Deadline Exceeded") !== false) {
                throw new \TemporaryException($exception->getMessage(), 503, true);
            }
            if( strpos($exception->getMessage(), "This domain cannot be renewed yet") !== false) {
                $domainParams =  $this->getDomainParams($domain);

                $info = $this->sendRequest("searchDomainRequest", array(
                    'extension' => $domainParams["extension"],
                    "domainNamePattern" => $domainParams["name"],
                ) );
                $info = $info->getValue();

                foreach ($info["results"] as $result) {
                    if (
                        $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getName() ||
                        $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getPunycode()
                    ) {
                        if(
                            isset($result["renewalDate"]) &&
                            trim($result["renewalDate"]) != "" &&
                            strtotime($result["renewalDate"]) !== false &&
                            strtotime($result["renewalDate"]) > time() &&
                            date("Y-m-d") != date("Y-m-d", strtotime($result["renewalDate"]))
                        ){
                            throw new ProlongUnavailableException($result["renewalDate"],500,$domain->getName());
                        }
                        break;
                    }
                }

                throw new \TemporaryException($exception->getMessage(), 503, false);
            }else{
                throw new $exception;
            }
        }


        $out["result"] = "success";

        return $out;
    }


    /**
     * @param string $profileId
     * @return bool
     */
    private function checkProfile($profileId ){
        try {
            $profileInfo = $this->sendRequest("retrieveCustomerRequest", array(
                "handle" => $profileId
            ));

            $profileInfo = $profileInfo->getValue();

            $checkingFields = array(
                "name",
                "address"
            );
            $profileOk = true;

            foreach ($checkingFields as $fieldName) {
                foreach ($profileInfo[$fieldName] as $key => $value) {
                    if ( $value != null && preg_match('/^[a-zA-Z0-9\W]+$/u', $value) === 0) {
                        $profileOk = false;
                        break;
                    }
                }
                if (!$profileOk) {
                    break;
                }
            }
        }catch (\Exception $ex){
            \logger::dump("Check profile $profileId failed", $ex->getMessage(), \logger::LEVEL_WARNING);
            $profileOk = false;
        }

        return $profileOk;
    }

    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Domain_transferDomainRequest
     *
     * @param \BillmanagerDomain $domain
     * @param $nss
     * @param $contact
     * @param $period
     * @param array $params
     * @return array
     * @throws \ClientException
     */
    public function transfer_domain($domain, $nss, $contact, $period, $params = array())
    {
        if( !isset($contact["contact_id"]) || $contact["contact_id"] == "" || !$this->checkProfile($contact["contact_id"])  ) {
            $result = $this->create_contact( $contact );

            if( $result == false ) {
                throw new \ClientException("Create owner contact error");
            }

            $out["contact_id"] = $contact["contact_id"] = $result["contact_id"];
        }

        $externalIds = array(
            $contact["id"] => $contact["contact_id"]
        );

        foreach ( array( "admin", "tech", "bill" ) as $cType ){
            if( isset($contact["additionalContacts"][ $cType ])){
                $additionalContact = $contact["additionalContacts"][ $cType ];
                if( isset($externalIds[ $additionalContact["id"] ]) ){
                    $contact[ $cType . "_id" ] = $externalIds[ $additionalContact["id"] ];
                } else {
                    if( !isset($additionalContact["contact_id"]) || $additionalContact["contact_id"] == "" || !$this->checkProfile($additionalContact["contact_id"])  ) {
                        $result = $this->create_contact($additionalContact);

                        if ($result == false) {
                            throw new \ClientException("Create $cType contact error");
                        }
                        $out[$cType . "_id"] = $contact[$cType . "_id"] = $externalIds[$additionalContact["id"]] = $result["contact_id"];
                    } else {
                        $contact[$cType . "_id"] = $additionalContact["contact_id"];
                    }
                }
            } else {
                $contact[ $cType . "_id" ] = $contact["contact_id"];
            }
        }

        $p_nss = array();


        foreach ($nss as $ns){
            $tmp = array(
                "name" => $ns["ns"]
            );
            if(isset($ns["ip"])){
                $tmp["ip"] = $ns["ip"];
            }

            $p_nss[] = $tmp;
        }

        $fl1 = null;
        $created = null;

        $request = array(
            'ownerHandle' => $contact["contact_id"],
            'adminHandle' => $contact["admin_id"],
            'techHandle' => $contact["tech_id"],
            'billingHandle' => $contact["bill_id"],
            'domain' => $this->getDomainParams($domain),
            'period' => $period,
            "nameServers" => $p_nss,
            "authCode" => $params["authCode"]
        );

        $additionalFields = $this->getAdditionalFields( $request["domain"]["extension"] );

        if( !empty($additionalFields["customer"]) ) {
            foreach ( $externalIds as $externalId ){
                $billmgrContact = null;

                if( $contact["contact_id"] == $externalId){
                    $billmgrContact = $contact;
                } else {
                    foreach ( array( "admin", "tech", "bill" ) as $cType ){
                        if(
                            isset($contact["additionalContacts"][ $cType ]["contact_id"]) &&
                            $contact["additionalContacts"][ $cType ]["contact_id"] ==  $externalId
                        ){
                            $billmgrContact = $contact["additionalContacts"][ $cType ];
                        }
                    }
                }

                if( $billmgrContact == null )
                    continue;

                $contactInfo = $this->sendRequest("retrieveCustomerRequest", array(
                    "handle" => $externalId,
                    "withAdditionalData" => 1,
                ))->getValue();

                $modifyRequest = array(
                    'handle' => $externalId,
                    'extensionAdditionalData' => array()
                );

                $extensionData = array();

                foreach ( $contactInfo["extensionAdditionalData"] as $data ){
                    if( $data["name"] == $request["domain"]["extension"] ){
                        $extensionData = $data["data"];
                    }
                }


                foreach ($additionalFields["customer"] as $field){
                    if(
                        (!isset( $contactInfo["additionalData"][$field["name"]]) || trim($contactInfo["additionalData"][$field["name"]]) == "") &&
                        (!isset( $extensionData[$field["name"]] ) || trim($extensionData[$field["name"]]) == "")
                    ){
                        if( !empty($billmgrContact["raw"]) &&
                            isset($billmgrContact["raw"][$field["name"]])
                        ) {
                            $modifyRequest["extensionAdditionalData"][$request["domain"]["extension"]][$field["name"]] = (string)$contact["raw"][$field["name"]];
                        }
                    }
                }

                if(!empty($modifyRequest["extensionAdditionalData"])){
                    $this->sendRequest("modifyCustomerRequest", $modifyRequest);
                }
            }
        }

        try {
            $this->sendRequest("transferDomainRequest", $request);
        }catch (\Exception $ex){
            if(strpos( $ex->getMessage(), "transferred less than 60 days") !== false ){
                throw new \ClientException($ex->getMessage());
            }
            if( strpos($ex->getMessage(), "register the premium domain") !== false ){
                \logger::write("OP_NOT_FREE: (PREMIUM)" . $ex->getMessage(), \logger::LEVEL_ERROR);
                $ex = new PremiumDomainException($domain);
            }

            if(strpos( $ex->getMessage(), "Authorization code is incorrect or missing.") !== false ){
                $authInfoCodeException =  new \AuthInfoCodeException("Authorization error: Invalid AuthInfo code", $domain->getName());
                throw  $authInfoCodeException;
            }
            throw $ex;
        }


        $out["result"] = "success";

        return $out;
    }

    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Domain_modifyDomainRequest
     *
     * @param \Domain $domain
     * @param array $nss
     * @return array
     * @throws \ClientException
     * @throws \Exception
     */
    public function update_ns($domain, $nss = array())
    {
        $out = array();

        $p_nss = array();


        foreach ($nss as $ns){
            $tmp = array(
                "name" => $ns["ns"]
            );
            if(isset($ns["ip"])){
                if(strpos($ns["ip"],":") !== false){
                    $tmp["ip6"] = $ns["ip"];
                } else {
                    $tmp["ip"] = $ns["ip"];
                }
            }

            $p_nss[] = $tmp;
        }


        \logger::dump("NewNSQ", $p_nss, \logger::LEVEL_DEBUG);
        try {
            $this->sendRequest("modifyDomainRequest", array(
                'domain' => $this->getDomainParams($domain),
                "nameServers" => $p_nss,
            ));

        }catch (\Exception $ex) {
            if (strpos($ex->getMessage(), "The domain is not in your account") !== false) {
                throw new \ClientException("Domain not found");
            }
            if (strpos($ex->getMessage(), "Can't resolve IP address from host") !== false) {
                throw new \ClientException("Can't resolve IP address from host");
            }
            throw $ex;
        }
        $out["result"] = "success";

        return $out;

    }

    /** @see https://doc.openprovider.eu/index.php/API_Module_Domain_searchDomainRequest
     * @param $page
     * @return \DomainInfo[]|bool
     * @throws \Exception
     */
    public function getDomainsList($page) {
        $domainsList = [];
        $step = 1000;
        $offset = ($page - 1) * $step;

        $info = $this->sendRequest("searchDomainRequest", array(
            'offset' => $offset,
            "limit" => $step,
        ));

        if ($info == null || !($info instanceof \OP_Reply)) {
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        } else {
            $info = $info->getValue();

            foreach ($info["results"] as $result) {
                if($result['status'] != "DEL" && $result['status'] != "FAI") {
                    $name = $result["domain"]["name"] . "." . $result["domain"]["extension"];
                    $dinfo = new \DomainInfo(
                        $name,
                        $result["autorenew"] == "on",
                        $this->decodeNs($result["nameServers"]),
                        strtotime(trim($result["expirationDate"]))
                    );
                    $domainsList[$dinfo->getDomain()->getPunycode()] = $dinfo;
                }
            }
            return empty($domainsList) ? false : $domainsList;
        }
    }

    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Domain_searchDomainRequest
     *
     * @param \Domain $domain
     *
     * @return array {"result", "status" ("ACTIVE", "NOT_DELEGATE", "P_REGISTER", "P_RENEW", "P_TRANSFER"), "expire", "nss"}
     * @throws \Exception
     */
    public function info_domain($domain)
    {

        $domainParams =  $this->getDomainParams($domain);

        $info = $this->sendRequest("searchDomainRequest", array(
            'extension' => $domainParams["extension"],
            "domainNamePattern" => $domainParams["name"],
        ) );

        if($info == null || ! ($info instanceof \OP_Reply) ){
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        } else {

            $info = $info->getValue();

            $out = array();
            $out["result"] = "error";

            foreach ($info["results"] as $result){
                if(
                    $result["domain"]["name"] . "." . $result["domain"]["extension"]  == $domain->getName() ||
                    $result["domain"]["name"] . "." . $result["domain"]["extension"]  == $domain->getPunycode()
                ){
                    $out["result"] = "success";
                    $out["whoisprivacy"] = isset($result["isPrivateWhoisEnabled"]) && $result["isPrivateWhoisEnabled"] === "1";
                    $out["nss"] = $this->decodeNs($result["nameServers"]);

                    /*if( isset($result["registryExpirationDate"]) && $result["registryExpirationDate"]!="" ){
                        \logger::write("USING registryExpirationDate", \logger::LEVEL_INFO);
                        $out["expire"] = strtotime( $result["registryExpirationDate"] );
                    } else {*/
                    $out["expire"] = strtotime( $result["expirationDate"] );
                    //}



                    if( empty($out["nss"]) ){
                        $out["status"] = "NOT_DELEGATE";
                    } else {
                        $out["status"] = "ACTIVE";
                    }
                    break;
                }
            }

            \logger::dump("SYNC_INFO", $out,\logger::LEVEL_INFO);

            if($out["result"] != "success"){
                throw new \Exception("Domain not found", 404);
            }
        }

        return $out;
    }


    private function decodeNs($in) {
        $nss = [];
        foreach( $in as $ns ){
            $tmp = array(
                "ns" => $ns["name"]
            );

            if( isset($ns["ip"]) && $ns["ip"]!= null){
                $tmp["ip"] = $ns["ip"];
            }
            if( isset($ns["ip6"]) && $ns["ip6"]!= null){
                $tmp["ip"] = $ns["ip6"];
            }

            $nss[] = $tmp;
        }
        return $nss;
    }


    private function getPhoneParams($phone){
        preg_match("/(\+[0-9]+)\s*\(([0-9]+)\)\s*(.*)/", preg_replace("/\s/","",$phone), $matches);

        if( count($matches) < 3 ){
            $onlyNumbers = preg_replace("/[^0-9]/", "", preg_replace("/\s/","",$phone));
            $matches = array(
                1 => substr( $onlyNumbers, 0, 1),
                2 => substr( $onlyNumbers, 1, 3),
                3 => substr( $onlyNumbers, 4),
            );
        }

        return array(
            "countryCode" => $matches[1],
            "areaCode" => $matches[2],
            "subscriberNumber" => str_replace("-","",$matches[3]),
        );
    }

    private function getAddressField( $contact, $fieldName){
        return isset($contact["pa_" . $fieldName]) && trim( $contact["pa_" . $fieldName] ) !="" ? $contact["pa_" . $fieldName] : $contact["la_" . $fieldName];
    }

    /**
     * @see https://doc.openprovider.eu/index.php/API_Module_Customer_createCustomerRequest
     *
     * @param array $contact - billmgr contact format
     * @param \Domain $domain
     * @return array|bool
     * @throws \ClientException
     * @throws \Exception
     */
    private function create_contact($contact, $domain = null) {
        $args = array(
            "phone" => $this->getPhoneParams( $contact["phone"] ),
            "email" =>  $contact["email"],
            'address' => array(
                'street' => $this->translit(str_replace("\"", "",$this->getAddressField( $contact, "address"))),
                'number' => '',
                'suffix' => '',
                'zipcode' => $this->getAddressField( $contact, "postcode"),
                'city' => $this->translit($this->getAddressField( $contact, "city")),
                'state' => '',
                'country' => $contact["iso2"],
            ),
            "gender" => "M",
        );
        if( $contact["fax"] != "" && strlen($contact["fax"]) > 4){
            $args["fax"] = $this->getPhoneParams( $contact["fax"] );
        }

        if(isset($contact["passport"]) && $contact["passport"]!=""){
            $pinfo = explode("выдан", $contact["passport"]);
            $contact["passport_series"] =$pinfo[0];

            $dinfo = explode(", идентификационный номер", $contact["passport"]);
            preg_match("/\s+([0-9.\-]+)$/",$dinfo[0], $match_date);
            $contact["passport_date"] = date("Y-m-d", strtotime($match_date[1]));
            $contact["passport_org"] = preg_replace("/(^[0-9\s]*)|(\s+[0-9.\-]+$)/", "",$contact["passport"]);
        }
        switch ($contact["ctype"]) {
            case "person":
                $args["name"] = array(
                    "initials" => $this->translit(mb_strtoupper( substr(trim($contact["firstname"]),0,1) . " " .  substr(trim($contact["middlename"]),0,1) , "utf-8")),
                    'firstName' => $this->translit($contact["firstname"]),
                    'prefix' => $this->translit($contact["middlename"]),
                    'lastName' => $this->translit($contact["lastname"]),
                );
                $series = $number = "";
                if (preg_match('/^(\d{2}\s*\d{2})\s*(\d{6})$/', trim($contact["passport_series"]), $arr)) {
                    $series = $arr[1];
                    $number = str_replace(' ', '', $arr[2]);
                } elseif(preg_match("/^([^0-9]+)/", trim($contact["passport_series"]), $matches)){
                    $series = trim(str_replace(array("№","-"),"", $matches[1]));
                    $number = trim( str_replace("№","", preg_replace("/^([^0-9]+)/", "", trim($contact["passport_series"]))));

                    if(strpos($contact["passport_org"],"выдан") === false ){
                        $contact["passport_org"] = "выдан " . $contact["passport_org"];
                    }
                    if(trim($series)!=""){
                        $type = "foreignpass";
                    }
                }else {
                    preg_match("/^([0-9]{4})([0-9]*)/", trim($contact["passport_series"]), $arr);
                    $series = $arr[1];
                    $number = str_replace(' ', '', $arr[2]);

                }
//$contact["lastname_ru"] . " " . $contact["firstname_ru"] . " " . $contact["middlename_ru"]
                $args["extensionAdditionalData"] = array(
                    array(
                        'name' => 'ru',
                        'data' => array(
                            'birthDate' => date("Y-m-d", strtotime($contact["birthdate"])),
                            "passportSeries" => $series,
                            'passportNumber' => $number,
                            'passportIssueDate' => $contact["passport_date"],
                            'passportIssuer' => $contact["passport_org"],
                            'mobilePhoneNumber' => $contact["mobile"],
                            'firstNameCyrillic' => $contact["firstname_ru"],
                            'firstNameLatin' => $contact["firstname"],
                            'middleNameCyrillic' => $contact["middlename_ru"],
                            'middleNameLatin' => $contact["middlename"],
                            'lastNameCyrillic' => $contact["lastname_ru"],
                            'lastNameLatin' => $contact["lastname"],
                            'postalAddressCyrillic' => $contact["iso2"] . ", " . $this->getAddressField( $contact, "postcode") . ", " . $this->getAddressField( $contact, "city") . ", " . $this->getAddressField( $contact, "address"),
                        )
                    ),
                );
                $args["additionalData"] = array(
                    "passportNumber" => $series . " " . $number,
                    "birthDate" => date("Y-m-d", strtotime($contact["birthdate"])),
                );
                break;
            case "company":
                $args["name"] = array(
                    "initials" => "Company Manager",
                    'firstName' => "Company",
                    'prefix' => "",
                    'lastName' => "Manager",
                );
                $args["extensionAdditionalData"] = array(
                    array(
                        'name' => 'ru',
                        'data' => array(
                            'taxPayerNumber' => $contact['inn'],
                            'mobilePhoneNumber' => $contact["mobile"],
                            'companyNameCyrillic' => $contact["company_ru"],
                            'companyNameLatin' => $contact["company"],
                            'postalAddressCyrillic' => $contact["iso2"] . ", " . $contact["pa_postcode"] . ", " . $contact["pa_city"] . ", " . $contact["pa_address"],
                            'legalAddressCyrillic' => $contact["iso2"] . ", " . $contact["la_postcode"] . ", " . $contact["la_city"] . ", " . $contact["la_address"],
                        )
                    ),
                );
                $args["companyName"] = $contact["company"];
                //$args["vat"] = $contact['inn'];
                /*$args["additionalData"] = array(
                    "companyRegistrationNumber" => $contact['inn'],
                );*/
                break;
            case "generic":
                $args["name"] = array(
                    "initials" => $this->translit($contact["name"]),
                    'firstName' => $this->translit($contact["firstname"]),
                    'prefix' => "",
                    'lastName' =>$this->translit($contact["lastname"]),
                );
                break;
        }


        \logger::dump("Contact", $contact, \logger::LEVEL_DEBUG);
        \logger::dump("Args", $args, \logger::LEVEL_DEBUG);


        try {
            $contact_id = $this->sendRequest( "createCustomerRequest", $args );
        } catch ( \Exception $ex ) {
            if( $ex->getMessage() == "Bad reply"){
                throw $ex;
            }

            if( strpos($ex->getMessage(), "Invalid zipcode") !== false ){
                $ex = new \ProfileException("Invalid zipcode");
                $ex->addProfileError( $contact["id"], isset($contact["pa_postcode"]) && trim( $contact["pa_postcode"] ) !="" ? "postal_postcode" : "location_postcode", "Invalid zipcode");
                throw $ex;
            }
            if( strpos($ex->getMessage(), "Invalid street") !== false ){
                $ex = new \ProfileException("Invalid street");//postal_address
                $ex->addProfileError( $contact["id"], isset($contact["pa_address"]) && trim( $contact["pa_address"] ) !="" ? "postal_address" : "location_address", "Invalid street");
                throw $ex;
            }

            throw new \ClientException( $ex->getMessage() );
        }

        \logger::dump("OwnerID", $contact_id, \logger::LEVEL_DEBUG);

        $answer = $contact_id->getValue();

        if(isset($answer["handle"]) && $answer["handle"]!=""){
            return array(
                "contact_id" => $answer["handle"]
            );
        }

        return false;
    }
    
    public function balance() {
        $out = array();

        $result = $this->sendRequest( "retrieveResellerRequest" );

        if( !($result instanceof \OP_Reply) ){
            return array(
                "result" => "error"
            );
        }
        
        $value = $result->getValue();

        if(isset($value["balance"])){
            $out["result"] = "success";
            $out["balance"] = $value["balance"]-$value["reservedBalance"];
            $out["credit"] = 0;
            $out["currency"] = "USD";
        } else {
            $out["result"] = "error";
        }

        return $out;
    }


    /**
     * @param \Domain $domain
     * @return \OP_Reply
     * @throws \Exception
     */
    protected function requestEmailVerification($domain ){
        $domainParams = $this->getDomainParams( $domain );

        $info = $this->sendRequest("searchDomainRequest", array(
            'extension' => $domainParams["extension"],
            "domainNamePattern" => $domainParams["name"],
        ) );

        $handler = null;
        if($info == null || ! ($info instanceof \OP_Reply) ){
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        } else {

            $info = $info->getValue();

            $out = array();
            $out["result"] = "error";

            foreach ($info["results"] as $result) {
                if (
                    $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getName() ||
                    $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getPunycode()
                ) {
                    $handler = $result["ownerHandle"];
                }
            }
        }

        if( $handler == null ){
            throw new \Exception("Domain not found");
        }

        $ownerInfo = $this->sendRequest( "retrieveCustomerRequest", array(
            "handle" => $handler
        ));

        $emailForVerification = null;

        if($ownerInfo == null || ! ($ownerInfo instanceof \OP_Reply) ){
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        } else {
            $ownerInfoValue = $ownerInfo->getValue();

            $emailForVerification = $ownerInfoValue["email"];
        }

        if( $emailForVerification == null ){
            throw new \Exception("Owner email not found");
        }

        return $this->sendRequest("startCustomerEmailVerificationRequest", array(
            "email" => $emailForVerification
        ));
    }


    /**
     * @param \Domain $domain
     * @return array
     * @throws \Exception
     */
    public function getAuthCode($domain ){
        $domainParams =  $this->getDomainParams($domain);

        $info = $this->sendRequest("searchDomainRequest", array(
            'extension' => $domainParams["extension"],
            "domainNamePattern" => $domainParams["name"],
        ) )->getValue();

        $domainInfo = null;

        foreach ($info["results"] as $result) {
            if (
                $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getName() ||
                $result["domain"]["name"] . "." . $result["domain"]["extension"] == $domain->getPunycode()
            ) {
                $domainInfo = $result;
                break;
            }
        }

        if( $domainInfo !== null ){
            if( !in_array($domain->getTLD(), array("ru","su","рф")) ){
                if( isset($domainInfo["creationDate"]) && strtotime("+60 day", strtotime($domainInfo["creationDate"])) > time() ){
                    return array(
                        "result" => "success",
                        "authcode" => "Transfer is unavailable until " . date("Y-m-d", strtotime("+60 day", strtotime($domainInfo["creationDate"])))
                    );
                }
            }

            if( isset($domainInfo["registryDetails"]["eppStatuses"]) ) {
                foreach ( $domainInfo["registryDetails"]["eppStatuses"] as $status ){
                    if( $status == "clientTransferProhibited"){
                        try {
                            $this->sendRequest("modifyDomainRequest", array(
                                'domain' => $this->getDomainParams($domain),
                                "isLocked" => 0,
                            ));
                        }catch (\Exception $ex){
                            return array(
                                "result" => "success",
                                "authcode" => "Modify transfer lock failed. Please, contact support."
                            );
                        }
                        break;
                    }
                }
            }
        }

        try {
            $this->sendRequest("resetAuthCodeDomainRequest", array(
                'domain' => $this->getDomainParams($domain),
            ));
            $reply = $this->sendRequest("requestAuthCodeDomainRequest", array(
                'domain' => $this->getDomainParams($domain),
            ));

        }catch (\Exception $ex){
            $throwException = true;
            if( strpos($ex->getMessage(), "Authorization code for this extension cannot be resetted") !== false ){
                $reply = $this->sendRequest("requestAuthCodeDomainRequest", array(
                    'domain' => $this->getDomainParams($domain),
                ));
                $throwException = false;
            }


            if(strpos($ex->getMessage(),"is prohibited during transfer")!==false){
                return array(
                    "result" => "success",
                    "authcode" => "Changing authCode is prohibited during transfer"
                );
            }

            if(strpos($ex->getMessage(),"Action balance low")!==false){
                return array(
                    "result" => "success",
                    "authcode" => "Operation currently unavailable, try again later"
                );
            }

            if(strpos($ex->getMessage(),"The domain is not in your account")!==false){
                return array(
                    "result" => "success",
                    "authcode" => "Domain not found"
                );
            }

            if(strpos($ex->getMessage(),"Auth code sending is denied to unverified email")!==false){
                try{
                    $this->requestEmailVerification( $domain );
                    return array(
                        "result" => "success",
                        "authcode" => "Auth code sending is denied to unverified email. You must verify email."
                    );
                }catch (\Exception $e){
                    if( $e->getCode() == "20001" ){
                        return array(
                            "result" => "success",
                            "authcode" => "Auth code sending is denied to unverified email. You must verify email."
                        );
                    }
                }
            }

            if(strpos($ex->getMessage(),"Cannot do this operation because administrative data")!==false){
                return array(
                    "result" => "success",
                    "authcode" => $ex->getMessage()
                );
            }

            if( $throwException ) {
                throw $ex;
            }
        }
        $value = $reply->getValue();
        if(isset($value["authCode"]) && trim($value["authCode"]) != ""){
            return array(
                "result" => "success",
                "authcode" => $value["authCode"]
            );
        }

        if(!isset($authinfo["success"]) || $authinfo["success"]!=true){
            if(strpos($authinfo["message"],"The domain is not in your account") !== false ){
                return array(
                    "result" => "success",
                    "authcode" => "Domain not found"
                );
            }

            throw new \Exception( trim($authinfo["message"]) == "" ? "Internal server error" : trim($authinfo["message"]));
        }

        throw new \Exception(  "Internal server error" );
    }

    /**
     * @param $command
     * @param array $args
     * @return array OP_Reply->getValue()
     * @throws \Exception
     */
    private function sendCatchedRequest( $catchLabel, $command, $args ){
        if( ($cacheInfo = \Billmgr\Cache::getInstance()->getValue($catchLabel) ) !== null) {
            $info = json_decode( $cacheInfo, true );
            \logger::dump("Loaded from cache ($catchLabel)",  $info, \logger::LEVEL_DEBUG);
        } else {
            $info = $this->sendRequest( $command, $args )->getValue();

            \Billmgr\Cache::getInstance()->setValue($catchLabel, json_encode($info));
        }

        return $info;
    }
    /**
     * @param $domainInfo
     * @return bool|string
     * @throws \Exception
     */
    private function getDomainPricePeriodStatus($domainInfo){
        $domainStatus = false;
        if (mb_strtoupper(trim($domainInfo["openproviderStatus"]), 'utf-8') =="DEL"){
            if( in_array($domainInfo["extension"], array("xn--p1ai","ru")) ){
                return self::SOFT_QUARANTINE;
            }

            if(isset($domainInfo["softQuarantineExpiryDate"]) &&
                strtotime("now") < $domainInfo["softQuarantineExpiryDate"]){
                $domainStatus = self::SOFT_QUARANTINE;
            }elseif (isset($domainInfo["hardQuarantineExpiryDate"]) &&  strtotime("now") < $domainInfo["hardQuarantineExpiryDate"]){
                $domainStatus = self::HARD_QUARANTINE;
            }else{
                throw new \Exception("Fail to get quarantine expiry date");
            }

        }
        return $domainStatus;
    }

    /**
     * @param $domain
     * @return array
     * @throws \Exception
     */
    private function getDomainInfo($domain)
    {

        $domainParams =  $this->getDomainParams($domain);

        $info = $this->sendRequest("searchDomainRequest", array(
            'extension' => $domainParams["extension"],
            "domainNamePattern" => $domainParams["name"],
        ) );

        if($info == null || ! ($info instanceof \OP_Reply) ){
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        } else {

            $info = $info->getValue();

            $out = array();
            $out["result"] = "error";

            foreach ($info["results"] as $result){
                if(
                    $result["domain"]["name"] . "." . $result["domain"]["extension"]  == $domain->getName() ||
                    $result["domain"]["name"] . "." . $result["domain"]["extension"]  == $domain->getPunycode()
                ){
                    $out["result"] = "success";
                    $out["whoisprivacy"] = isset($result["isPrivateWhoisEnabled"]) && $result["isPrivateWhoisEnabled"] === "1";
                    $out["nss"] = $this->decodeNs($result["nameServers"]);

                    $out["expire"] = strtotime( $result["expirationDate"] );
                    if (isset($result["status"])){
                        $out["openproviderStatus"] = $result["status"];
                    }else{
                        throw new \Exception("Fail to get domain status");
                    }


                    $out["extension"] =  $result["domain"]["extension"];
                    $out["softQuarantineExpiryDate"] =  isset($result["softQuarantineExpiryDate"]) ? strtotime($result["softQuarantineExpiryDate"]) : null;
                    $out["hardQuarantineExpiryDate"] =  isset($result["hardQuarantineExpiryDate"]) ? strtotime($result["hardQuarantineExpiryDate"]) : null;

                    if( empty($out["nss"]) ){
                        $out["status"] = "NOT_DELEGATE";
                    } else {
                        $out["status"] = "ACTIVE";
                    }
                    break;
                }
            }

            \logger::dump("DOMAIN_INFO", $out,\logger::LEVEL_INFO);

            if($out["result"] != "success"){
                throw new \Exception("Domain not found", 404);
            }
        }
        return $out;
    }


    /**
     * @param $tld
     * @return array|mixed
     * @throws \Exception
     */
    private function getExtensionRequest($tld , $pricePeriodStatus){
        $tld = mb_strtolower(trim($tld), 'utf-8');
        $idna = new \idna_convert();
        $extensions = array(
            "withPrice" => 1,
            "extensions" => array( $idna->encode($tld)));

        $extensionList  = $this->sendRequest("searchExtensionRequest", $extensions);
        if($extensionList == null || ! ($extensionList instanceof \OP_Reply) ){
            throw new \Exception("Unrecognized response from OPENPROVIDER");
        }else{
            $tldInfo  = $extensionList->getValue();
            $priseInfo = array();
            foreach ($tldInfo["results"] as $result){
                if (isset($result["name"])&& mb_strtolower(trim($result["name"]), "utf-8") ==  $idna->encode($tld)
                    && isset($result["prices"][$pricePeriodStatus]["reseller"]["price"])){
                    $priseInfo = $result["prices"];
                    break;
                }else{
                    throw new \Exception("Fail to get price info");
                }
            }
            return $priseInfo;
        }

    }

    public function getAllExtensionRequest(){
        $extensions = array(
            "onlyNames" => "1",
        );
        $extensionList  = $this->sendRequest("searchExtensionRequest", $extensions);
        \logger::dump("TLDSS" ,$extensionList->getValue() );
        return $extensionList->getValue();
    }


    /**
     * @param $command
     * @param array $args
     * @return \OP_Reply
     * @throws \Exception
     */
    private function sendRequest($command, $args = array()){
        $client = new \OP_API( $this->auth_info["url"] );

        $request = new \OP_Request;
        $request->setCommand($command)
            ->setAuth(array('username' =>  $this->auth_info["login"], 'password' =>  $this->auth_info["password"], 'client' => "billmgr-5" ))
            ->setArgs($args);

        $response = $client->process( $request );

        if( trim($response->getFaultString()) != "" ){
            throw new \Exception($response->getFaultString() . ( trim($response->getValue()) != "" ? ". " . trim($response->getValue()) : ""), $response->getFaultCode());
        }

        return $response;
    }

    /**
     * @return boolean
     */
    public function test() {

        $result = $this->sendRequest("retrieveResellerRequest");

        $value = $result->getValue();

        if(!isset($value["balance"])){
            return false;
        }

        return true;
    }
}