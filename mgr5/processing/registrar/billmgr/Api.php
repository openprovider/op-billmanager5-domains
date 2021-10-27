<?php
namespace Billmgr;

/**
 * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D1%80%D0%B5%D0%B3%D0%B8%D1%81%D1%82%D1%80%D0%B0%D1%82%D0%BE%D1%80%D0%BE%D0%B2#.D0.A4.D1.83.D0.BD.D0.BA.D1.86.D0.B8.D0.B8_BILLmanager
 */
use SimpleXMLElement;

class Api{

    public static function setProfileError( $item, $profileType, $profileParam, $errorMessage ){
        return static::sendRequest("service_profile.error", array(
            "item" => $item,
            "type" => $profileType,
            "param" => $profileParam,
            "warning_message" => $errorMessage,
            "sok" => "ok"
        ));
    }

    public static function setProcessingBalance( $processingId, $balance, $currency ){
        return static::sendRequest("processing.savebalance",array(
            "balance" => $balance,
            "currency" => $currency,
            "processingmodule" => $processingId,
            "notify_time" => date("Y-m-d H:i:s"),
            "sok" => "ok",
        ));
    }

    /**
     * @param array $filters
     * @return SimpleXMLElement
     */
    public static function getAccountList( $filters = array() ){
        if( !empty( $filters ) ){
            $filters["filter"] = "on";
        }

        return self::sendRequest("account", $filters);
    }

    /**
     * @param $domainId
     * @return SimpleXMLElement
     */
    public static function deleteDomain( $domainId ){
        return self::sendRequest("domain.delete", array(
            "elid" => $domainId
        ));
    }

    /**
     * @param $itemType
     * @param $itemId
     * @param $su
     *
     * @return SimpleXMLElement
     * @internal param $domainId
     */
    public static function deleteItem( $itemType, $itemId, $su ){
        return self::sendRequest( $itemType . ".delete", array(
            "elid" => $itemId,
            "su" => $su,
            "sok" => "ok"
        ));
    }


    /**
     * @param $userName
     * @return SimpleXMLElement
     */
    public static function getUserContacts($userName ){
        return self::sendRequest( "service_profile", array(
            "su" => $userName
        ));
    }

    /**
     * @return SimpleXMLElement
     */
    public static function getProcessingList(){
        return self::sendRequest( "processing");
    }

    /**
     * @param $runningOperationId
     * @param $moduleInfo
     * @param \Exception $ex
     * @return SimpleXMLElement
     */
    public static function runningOperationError($runningOperationId, $moduleInfo, \Exception $ex ){
        $errorXml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><doc/>");

        /*$errorInfo = $errorXml->addChild("error");
        $errorInfo->addAttribute("date", htmlspecialchars( date("Y-m-d H:i:s") ));
        $errorInfo->addChild("backtrace", htmlspecialchars( $ex->getTraceAsString() ));
        $errorInfo->addChild("log", htmlspecialchars( "LogFile: " . \logger::$filename ));*/

        $module = $errorXml->addChild("processingmodule");
        $module->addAttribute("date", htmlspecialchars( date("Y-m-d H:i:s") ));
        $module->addAttribute("id", htmlspecialchars( (string)$moduleInfo->id ));
        $module->addAttribute("name", htmlspecialchars( (string)$moduleInfo->name ));

        $pmError = $module->addChild("error");
        $pmError->addAttribute("date", htmlspecialchars( date("Y-m-d H:i:s") ));
        $pmError->addChild("backtrace", htmlspecialchars( $ex->getTraceAsString() ));
        $pmError->addChild("log", htmlspecialchars( "LogFile: " . \logger::$filename . " (" . \logger::getRand() . ")" ));

        $param = $pmError->addChild("param", htmlspecialchars($ex->getMessage()));
        $param->addAttribute("name","error");
        $param->addAttribute("type","msg");

        //$errorXml->addChild("custommessage", htmlspecialchars( $ex->getMessage() ) );

        return self::sendRequest( "runningoperation.edit", array(
            "elid" => $runningOperationId,
            "errorxml" => (string)$errorXml->asXML(),
            "sok" => "ok",
        ));
    }


    /**
     * @param $runningOperationId
     * @return SimpleXMLElement
     */
    public static function setManualRunningOperation( $runningOperationId ){
        return self::sendRequest( "runningoperation.edit", array(
            "elid" => $runningOperationId,
            "manual" => "on",
            "sok" => "ok"
        ));
    }

    public static function getRunningOperation( $operationId ){
        return self::sendRequest( "runningoperation.edit", array(
            "elid" => $operationId,
        ));
    }

    /**
     * @param $runningOperationId
     * @return SimpleXMLElement
     */
    public static function setAutoRunningOperation( $runningOperationId ){
        return self::sendRequest( "runningoperation.edit", array(
            "elid" => $runningOperationId,
            "manual" => "off",
            "sok" => "ok"
        ));
    }
    public static function deleteRunningOperation($runningOperationId){
        return self::sendRequest( "runningoperation.delete", array(
            "elid" => $runningOperationId,
        ));
    }


    /**
     * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D1%80%D0%B5%D0%B3%D0%B8%D1%81%D1%82%D1%80%D0%B0%D1%82%D0%BE%D1%80%D0%BE%D0%B2
     *
     * @param $moduleId
     * @param $type
     * @param $contact
     * @return SimpleXMLElement
     */
    public static function importContact($moduleId, $type, $contact ){
        $createInfo =  self::sendRequest( "profile.edit" );
        if(isset( $contact["location_country"] )) {
            $elem = $createInfo->xpath("/doc/slist[@name='country_physical']/val[@image='/manimg/common/flag/" . strtoupper( $contact["location_country"] ) . ".png']");
            $contact["location_country"] = (string)$elem[0]["key"];
        }
        if(isset( $contact["postal_country"] )) {
            $elem = $createInfo->xpath("/doc/slist[@name='country_legal']/val[@image='/manimg/common/flag/" . strtoupper( $contact["postal_country"] ) . ".png']");
            $contact["postal_country"] = (string)$elem[0]["key"];
        }

        $request = $contact;
        if(!isset($request["name"])) {
            $request["name"] = isset($contact["company"]) && trim($contact["company"]) != "" ? $contact["company"] : trim(trim($contact["lastname"]) . " " . trim($contact["firstname"]) . " " . trim($contact["middlename"]));
        }
        $request["module"] = $moduleId;
        $request["type"] = $type;
        $request["sok"] = "ok";

        return self::sendRequest("processing.import.profile",$request);
    }


    public static function setAccountAttitude( $accountId, $attitudeId ){
        return self::sendRequest( "account.edit", array(
            "elid" => $accountId,
            "attitude" => $attitudeId,
            "sok" => "ok"
        ));
    }

    /**
     * @param $email
     * @param $countryISO
     * @param $realname
     * @param $password
     * @param $projectId
     * @param string $label
     * @return SimpleXMLElement
     */
    public static function createAccount( $email, $countryISO, $realname, $password, $projectId, $label = ""){
        $createInfo =  self::sendRequest( "account.edit" );
        $elem = $createInfo->xpath("/doc/slist/val[@image='/manimg/common/flag/" . strtoupper( $countryISO ) . ".png']");

        $country = (string)$elem[0]["key"];
        return self::sendRequest( "account.edit", array(
            "email" => $email,
            "project" => $projectId,
            "country" => $country,
            "realname" => $realname,
            "passwd" => $password,
            "label" => $label,
            "notify" => "off",
            "employee" => "null",
            "sok" => "ok"
        ));
    }


    /**
     * @param $accountName
     * @param $profileId
     * @param $amount
     * @param $currencyId
     * @param $payMethodId
     * @return SimpleXMLElement
     */
    public static function createPayment( $accountName, $profileId, $amount, $currencyId, $payMethodId ){
        $profileInfo =  self::sendRequest( "profile.edit", array(
            "elid" => $profileId
        ) );
        $createPaymentRequest = array(
            "profile" => $profileId,
            "amount" => $amount,
            "payment_currency" => $currencyId,
            "paymethod" => $payMethodId,
            "su" => $accountName,
            "sok" => "ok",
        );

        switch ($profileInfo->profiletype){
            case 1:
                $createPaymentRequest["postcode_physical"] = (string)$profileInfo->postcode_physical;
                $createPaymentRequest["city_physical"] = (string)$profileInfo->city_physical;
                $createPaymentRequest["address_physical"] = (string)$profileInfo->address_physical;
                $createPaymentRequest["country_physical"] = (string)$profileInfo->country_physical;
                break;

            case 2:
            case 3:
                $createPaymentRequest["postcode_legal"] = (string)$profileInfo->postcode_legal;
                $createPaymentRequest["postcode_physical"] = (string)$profileInfo->postcode_physical;
                $createPaymentRequest["city_legal"] = (string)$profileInfo->city_legal;
                $createPaymentRequest["city_physical"] = (string)$profileInfo->city_physical;
                $createPaymentRequest["address_legal"] = (string)$profileInfo->address_legal;
                $createPaymentRequest["address_physical"] = (string)$profileInfo->address_physical;
                $createPaymentRequest["country_legal"] = (string)$profileInfo->country_legal;
                $createPaymentRequest["country_physical"] = (string)$profileInfo->country_physical;
                break;
        }

        return self::sendRequest( "payment.add", $createPaymentRequest);
    }

    /**
     * @param $paymentId
     * @return SimpleXMLElement
     */
    public static function setPaymentPayed( $paymentId ){
        return self::sendRequest( "payment.setpaid", array(
            "elid" => $paymentId
        ));
    }


    /**
     * @param $domain
     * @param $module
     * @param $exiredateYMD
     * @param int $periodMonth
     * @return SimpleXMLElement
     */
    public static function importDomainService($domain, $module, $exiredateYMD, $periodMonth = 12 ){
        preg_match("/^(https?:\/\/)?(\.)?[^\.]+\.(.*)$/", $domain, $zone);
        $zone = mb_strtolower(trim($zone[3]),"UTF-8");

        $zones = self::sendRequest( "tld" );
        $zoneId = (string)$zones->xpath("/doc/elem[name='$zone']")[0]->id;

        return self::sendRequest("processing.import.service", array(
            "module" => $module,
            "import_pricelist_intname" => $zoneId,
            "import_service_name" => $domain,
            "status" => 2,
            "expiredate" => $exiredateYMD,
            "domain" => $domain,
            "service_status" => 2,
            "period" => $periodMonth,
            "sok" => "ok"
        ));
    }


    public static function assignDomainContact($domainId, $contactId, $type = "owner" ){
        return self::sendRequest("service_profile2item.edit", array(
            "service_profile" => $contactId,
            "item" => $domainId,
            "type" => $type,
            "sok" => "ok"
        ));
    }

    public static function domainNS( $domainId, $nservers = array()){
        $request = array(
            "elid" => $domainId,
            "sok" => "ok",
        );

        for( $i=0; $i<4; $i++){
            if( isset( $nservers[$i]) ) {
                $request["ns$i"] = $nservers[$i];
            } else {
                $request["ns$i"] = "";
            }
        }

        return self::sendRequest("domain.ns", $request);
    }

    /**
     *
     * @param $domainName
     * @param $contactId
     * @param $accountId
     * @param $module
     * @param $priceId
     * @param string $note
     * @param null $project
     * @param int $serviceStatus
     * @return SimpleXMLElement
     * @internal param $paymentId
     */
    public static function importDomain( $domainName, $contactId, $accountId, $module, $priceId, $note="", $project=null, $serviceStatus = 2 ){

        $importResult = self::importDomainService($domainName,  $module, date("Y-m-d", strtotime("+12 month")));

        if(!isset($importResult->service_id) || (string)$importResult->service_id == ""){
            return $importResult;
        }
        $assignRequest = array(
            "elid" => (string)$importResult->service_id,
            "account" => $accountId,
            "pricelist_" . (string)$importResult->service_id => $priceId,
            "sok" => "ok",
        );

        if( $project != null ){
            $assignRequest["project_" . (string)$importResult->service_id] = $project;
        }

        $pr = self::sendRequest( "processing.import.assign", $assignRequest);
        usleep(100000);
        $trys = 50;
        do {
            $updateResult = self::sendRequest("domain.edit", array(
                "elid" => (string)$importResult->service_id,
                "service_profile_owner" => $contactId,
                "service_profile_customer" => $contactId,
                "expiredate" => date("Y-m-d", strtotime("+12 month")),
                "status" => 2,
                "service_status" => $serviceStatus,
                "sok" => "ok"
            ));
            usleep(100000);
        }while( (string)$updateResult->doc->createdate == "" && --$trys>0);

        if(trim($note) != ""){
            self::sendRequest("domain.edit", array(
                "elid" => (string)$importResult->service_id,
                "note" => $note,
                "sok" => "ok",
                "su" => "registrar"
            ));
        }

        //registrar

        return $updateResult;
    }

    /**
     *
     * @param $domainName
     * @param $contactsArray
     * @param $accountId
     * @param $module
     * @param $priceId
     * @param string $note
     * @param null $project
     * @return SimpleXMLElement
     * @internal param $paymentId
     */
    public static function importDomainWithContacts( $domainName, $contactsArray, $accountId, $module, $priceId, $note="", $project=null ){
        $importResult = self::importDomainService($domainName,  $module, date("Y-m-d", strtotime("+10 month")));

        if(!isset($importResult->service_id) || (string)$importResult->service_id == ""){
            return $importResult;
        }
        $editRequest = array(
            "elid" => (string)$importResult->service_id,
            "expiredate" => date("Y-m-d", strtotime("+10 month")),
            "status" => 2,
            "service_status" => 2,
            "sok" => "ok"
        );


        $assignRequest = array(
            "elid" => (string)$importResult->service_id,
            "account" => $accountId,
            "pricelist_" . (string)$importResult->service_id => $priceId,
            "sok" => "ok",
        );

        if( $project != null ){
            $assignRequest["project_" . (string)$importResult->service_id] = $project;
        }

        $pr = self::sendRequest( "processing.import.assign", $assignRequest);


        foreach ($contactsArray as $name => $id) {
            static::assignDomainContact( (string)$importResult->service_id,$id, $name);
            //$editRequest["service_profile_$name"] = $id;
        }

        usleep(100000);
        $trys = 50;
        do {
            $updateResult = self::sendRequest("domain.edit", $editRequest);
            usleep(100000);
        }while( (string)$updateResult->doc->createdate == "" && --$trys>0);

        if(trim($note) != ""){
            self::sendRequest("domain.edit", array(
                "elid" => (string)$importResult->service_id,
                "note" => $note,
                "sok" => "ok",
                "su" => "registrar"
            ));
        }
        \logger::dump("Importing result", $updateResult);

        return $updateResult;
    }


    /**
     * @param $accountName
     * @param $person
     * @param $countryISO
     * @param $postcode
     * @param $city
     * @param $address
     * @return \SimpleXMLElement
     */
    public static function createPersonProfile( $accountName, $person, $countryISO, $postcode, $city, $address ){
        $createInfo =  self::sendRequest( "profile.edit" );
        $elem = $createInfo->xpath("/doc/slist[@name='country_physical']/val[@image='/manimg/common/flag/" . strtoupper( $countryISO ) . ".png']");

        $countryId = (string)$elem[0]["key"];
        return self::sendRequest( "profile.edit", array(
            "profiletype" => 1,
            "person" => $person,
            "country_physical" => $countryId,
            "postcode_physical" => $postcode,
            "city_physical" => $city,
            "address_physical" => $address,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }

    /**
     * @param $accountName
     * @param $companyName
     * @param $contactPerson
     * @param $countryISOLegal
     * @param $postcodeLegal
     * @param $cityLegal
     * @param $addressLegal
     * @param $countryISO
     * @param $postcode
     * @param $city
     * @param $address
     * @param $inn
     * @return \SimpleXMLElement
     */
    public static function createOrgProfile( $accountName, $companyName, $contactPerson, $countryISOLegal, $postcodeLegal, $cityLegal, $addressLegal ,
                                             $countryISO, $postcode, $city, $address, $inn ){
        $createInfo =  self::sendRequest( "profile.edit" );
        $elem = $createInfo->xpath("/doc/slist[@name='country_physical']/val[@image='/manimg/common/flag/" . strtoupper( $countryISO ) . ".png']");

        $countryId = (string)$elem[0]["key"];
        $elem = $createInfo->xpath("/doc/slist[@name='country_legal']/val[@image='/manimg/common/flag/" . strtoupper( $countryISOLegal ) . ".png']");

        $countryIdLegal = (string)$elem[0]["key"];
        return self::sendRequest( "profile.edit", array(
            "profiletype" => 2,
            "name" => $companyName,
            "person" => $contactPerson,
            "country_legal" => $countryIdLegal,
            "postcode_legal" => $postcodeLegal,
            "city_legal" => $cityLegal,
            "address_legal" => $addressLegal,
            "country_physical" => $countryId,
            "postcode_physical" => $postcode,
            "city_physical" => $city,
            "address_physical" => $address,
            "vatnum" => $inn,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }


    /**
     * @param $accountName
     * @param $companyName
     * @param $contactPerson
     * @param $countryISOLegal
     * @param $postcodeLegal
     * @param $cityLegal
     * @param $addressLegal
     * @param $countryISO
     * @param $postcode
     * @param $city
     * @param $address
     * @param $inn
     * @return \SimpleXMLElement
     */
    public static function createProprietorProfile( $accountName, $companyName, $contactPerson, $countryISOLegal, $postcodeLegal, $cityLegal, $addressLegal ,
                                                    $countryISO, $postcode, $city, $address, $inn ){
        $createInfo =  self::sendRequest( "profile.edit" );
        $elem = $createInfo->xpath("/doc/slist[@name='country_physical']/val[@image='/manimg/common/flag/" . strtoupper( $countryISO ) . ".png']");

        $countryId = (string)$elem[0]["key"];
        $elem = $createInfo->xpath("/doc/slist[@name='country_legal']/val[@image='/manimg/common/flag/" . strtoupper( $countryISOLegal ) . ".png']");

        $countryIdLegal = (string)$elem[0]["key"];
        return self::sendRequest( "profile.edit", array(
            "profiletype" => 3,
            "name" => $companyName,
            "person" => $contactPerson,
            "country_legal" => $countryIdLegal,
            "postcode_legal" => $postcodeLegal,
            "city_legal" => $cityLegal,
            "address_legal" => $addressLegal,
            "country_physical" => $countryId,
            "postcode_physical" => $postcode,
            "city_physical" => $city,
            "address_physical" => $address,
            "vatnum" => $inn,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }

    /**
     * @param $accountName
     * @param $contactName
     * @param $firstnameRU
     * @param $middlenameRU
     * @param $lastnameRU
     * @param $firstname
     * @param $middlename
     * @param $lastname
     * @param $email
     * @param $phone
     * @param $mobile
     * @param $fax
     * @param $passportSeries
     * @param $passportOrg
     * @param $passportDateYMD
     * @param $birthdateYMD
     * @param $locationCountryISO
     * @param $locationState
     * @param $locationPostcode
     * @param $locationCity
     * @param $locationAddress
     * @param $postalCountryISO
     * @param $postalState
     * @param $postalPostcode
     * @param $postalCity
     * @param $postalAddress
     * @param $postalPerson
     * @return SimpleXMLElement
     * @internal param $accountId
     */
    public static function createPersonalContact(
        $accountName, $contactName, $firstnameRU, $middlenameRU, $lastnameRU, $firstname, $middlename, $lastname,
        $email, $phone, $mobile, $fax, $passportSeries, $passportOrg, $passportDateYMD, $birthdateYMD,
        $locationCountryISO, $locationState, $locationPostcode, $locationCity, $locationAddress,
        $postalCountryISO, $postalState, $postalPostcode, $postalCity, $postalAddress, $postalPerson
    ){
        $createInfo =  self::sendRequest( "service_profile.edit" );

        $elem = $createInfo->xpath("/doc/slist[@name='location_country']/val[@image='/manimg/common/flag/" . strtoupper( $locationCountryISO ) . ".png']");

        $locationCountryId = (string)$elem[0]["key"];

        $elem = $createInfo->xpath("/doc/slist[@name='postal_country']/val[@image='/manimg/common/flag/" . strtoupper( $postalCountryISO ) . ".png']");

        $postalCountryId = (string)$elem[0]["key"];

        return self::sendRequest( "service_profile.edit", array(
            "profiletype" => "1", // юрик 2
            "name" => $contactName,
            "firstname_locale" => $firstnameRU,
            "firstname" => $firstname,
            "middlename_locale" => $middlenameRU,
            "middlename" => $middlename,
            "lastname_locale" => $lastnameRU,
            "lastname" => $lastname,
            "email" => $email,
            "phone" => $phone,
            "mobile" => $mobile,
            "fax" => $fax,
            "passport" => $passportSeries,
            "passport_org" => $passportOrg,
            "passport_date" => $passportDateYMD,
            "birthdate" => $birthdateYMD,
            "private" => "on",
            "location_country" => $locationCountryId,
            "location_state" => $locationState,
            "location_postcode" => $locationPostcode,
            "location_city" => $locationCity,
            "location_address" => $locationAddress,
            "postal_country" => $postalCountryId,
            "postal_state" => $postalState,
            "postal_postcode" => $postalPostcode,
            "postal_city" => $postalCity,
            "postal_address" => $postalAddress,
            "postal_addressee" => $postalPerson,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }


    /**
     * @param $accountName
     * @param $contactName
     * @param $firstnameRU
     * @param $middlenameRU
     * @param $lastnameRU
     * @param $firstname
     * @param $middlename
     * @param $lastname
     * @param $email
     * @param $phone
     * @param $mobile
     * @param $fax
     * @param $passportSeries
     * @param $passportOrg
     * @param $passportDateYMD
     * @param $birthdateYMD
     * @param $locationCountryISO
     * @param $locationState
     * @param $locationPostcode
     * @param $locationCity
     * @param $locationAddress
     * @param $postalCountryISO
     * @param $postalState
     * @param $postalPostcode
     * @param $postalCity
     * @param $postalAddress
     * @param $postalPerson
     * @return SimpleXMLElement
     * @internal param $accountId
     */
    public static function createProprietorContact(
        $accountName, $contactName, $firstnameRU, $middlenameRU, $lastnameRU, $firstname, $middlename, $lastname,
        $email, $phone, $mobile, $fax, $passportSeries, $passportOrg, $passportDateYMD, $birthdateYMD,
        $locationCountryISO, $locationState, $locationPostcode, $locationCity, $locationAddress,
        $postalCountryISO, $postalState, $postalPostcode, $postalCity, $postalAddress, $postalPerson, $inn
    ){
        $createInfo =  self::sendRequest( "service_profile.edit" );

        $elem = $createInfo->xpath("/doc/slist[@name='location_country']/val[@image='/manimg/common/flag/" . strtoupper( $locationCountryISO ) . ".png']");

        $locationCountryId = (string)$elem[0]["key"];

        $elem = $createInfo->xpath("/doc/slist[@name='postal_country']/val[@image='/manimg/common/flag/" . strtoupper( $postalCountryISO ) . ".png']");

        $postalCountryId = (string)$elem[0]["key"];

        return self::sendRequest( "service_profile.edit", array(
            "profiletype" => "3",
            "name" => $contactName,
            "inn" => $inn,
            "firstname_locale" => $firstnameRU,
            "firstname" => $firstname,
            "middlename_locale" => $middlenameRU,
            "middlename" => $middlename,
            "lastname_locale" => $lastnameRU,
            "lastname" => $lastname,
            "email" => $email,
            "phone" => $phone,
            "mobile" => $mobile,
            "fax" => $fax,
            "passport" => $passportSeries,
            "passport_org" => $passportOrg,
            "passport_date" => $passportDateYMD,
            "birthdate" => $birthdateYMD,
            "private" => "on",
            "location_country" => $locationCountryId,
            "location_state" => $locationState,
            "location_postcode" => $locationPostcode,
            "location_city" => $locationCity,
            "location_address" => $locationAddress,
            "postal_country" => $postalCountryId,
            "postal_state" => $postalState,
            "postal_postcode" => $postalPostcode,
            "postal_city" => $postalCity,
            "postal_address" => $postalAddress,
            "postal_addressee" => $postalPerson,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }


    public static function createOrgContact(
        $accountName, $contactName, $orgNameRU, $orgName, $inn, $kpp, $ogrn, $firstnameRU, $middlenameRU,
        $lastnameRU, $firstname, $middlename, $lastname, $email, $phone, $mobile, $fax,
        $locationCountryISO, $locationState, $locationPostcode, $locationCity, $locationAddress,
        $postalCountryISO, $postalState, $postalPostcode, $postalCity, $postalAddress, $postalPerson
    ){
        $createInfo =  self::sendRequest( "service_profile.edit" );

        $elem = $createInfo->xpath("/doc/slist[@name='location_country']/val[@image='/manimg/common/flag/" . strtoupper( $locationCountryISO ) . ".png']");

        $locationCountryId = (string)$elem[0]["key"];

        $elem = $createInfo->xpath("/doc/slist[@name='postal_country']/val[@image='/manimg/common/flag/" . strtoupper( $postalCountryISO ) . ".png']");

        $postalCountryId = (string)$elem[0]["key"];

        return self::sendRequest( "service_profile.edit", array(
            "profiletype" => "2",
            "name" => $contactName,
            "company_locale" => $orgNameRU,
            "company" => $orgName,
            "inn" => $inn,
            "kpp" => $kpp,
            "ogrn" => $ogrn,
            "firstname_locale" => $firstnameRU,
            "firstname" => $firstname,
            "middlename_locale" => $middlenameRU,
            "middlename" => $middlename,
            "lastname_locale" => $lastnameRU,
            "lastname" => $lastname,
            "email" => $email,
            "phone" => $phone,
            "mobile" => $mobile,
            "fax" => $fax,
            "private" => "on",
            "location_country" => $locationCountryId,
            "location_state" => $locationState,
            "location_postcode" => $locationPostcode,
            "location_city" => $locationCity,
            "location_address" => $locationAddress,
            "postal_country" => $postalCountryId,
            "postal_state" => $postalState,
            "postal_postcode" => $postalPostcode,
            "postal_city" => $postalCity,
            "postal_address" => $postalAddress,
            "postal_addressee" => $postalPerson,
            "su" => $accountName,
            "sok" => "ok"
        ));
    }


    public static function commitDelIp( $ipId ){
        return self::sendRequest("service.ip.del", array(
            "elid" => $ipId,
            "sok" => "ok"
        ));
    }


    /**
     * @param $ipId
     * @param $vpsId
     * @return SimpleXMLElement
     */
    public static function getIpInfo($ipId, $vpsId ){
        return self::sendRequest("service.ip.edit", array(
            "elid" => $ipId,
            "plid" => $vpsId
        ));
    }


    /**
     * @param $vpsId
     * @return SimpleXMLElement
     */
    public static function getIpList($vpsId ){
        return self::sendRequest("service.ip", array(
            "elid" => $vpsId
        ));
    }

    /**
     * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D1%87%D0%B8%D0%BA%D0%BE%D0%B2#addip
     *
     * @param $ipId
     * @return SimpleXMLElement
     */
    public static function commitIp($ipId ){
        return self::sendRequest( "service.ip.add.commit", array(
            "elid" => $ipId,
            "sok" => "ok"
        ));
    }


    /**
     * addIp => commitIp
     *
     * @param $ipId
     * @param $ip
     * @param $domain
     * @return SimpleXMLElement
     */
    public static function saveIp($ipId, $ip, $domain ){
        $addIp = self::addIp( $ipId, $ip, $domain );
        return self::commitIp( (string)$addIp->{"ip.id"} );
    }

    /**
     * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D1%87%D0%B8%D0%BA%D0%BE%D0%B2#addip
     *
     * @param $ipId
     * @param $ip
     * @param $domain
     * @return SimpleXMLElement
     */
    public static function addIp($ipId, $ip, $domain ){
        return self::sendRequest( "service.ip.add", array(
            "elid" => $ipId,
            "ip" => $ip,
            "domain" => $domain,
            "sok" => "ok"
        ));
    }

    /**
     * @param $table
     * @return SimpleXMLElement
     */
    public static function clearCache($table ){
        return self::sendRequest("tool.clearcache", array(
            "table" => $table,
            "sok" => "ok"
        ));
    }

    /**
     * @param $processingId
     * @param $xml
     * @return SimpleXMLElement
     */
    public static function setModuleConfig( $processingId, $xml ){
        return self::sendRequest("processing.setconfig", array(
            "config" => $xml,
            "elid" => $processingId,
            "sok" => "ok"
        ));
    }

    /**
     * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D1%87%D0%B8%D0%BA%D0%BE%D0%B2#get_server_config
     *
     * @param $intname - внутреннее наименование шаблона (идентификатор)
     * @param $name - наименование, в том числе локализованные
     * @param $name_ru - (RU) наименование, в том числе локализованные
     * @param $plid - код параметра типа продукта с внутренним именем ostempl для шаблонов ОС, либо соответствующие нужно параметру типу продукта
     * @param $module - код модуля обработки, если требуется одновременное подключение к обработчику услуг
     * @param string $elid  - код значения параметра, если требуется его изменение. Пустое значение при добавлении значения параметра
     *
     * @return SimpleXMLElement
     */
    public static function setItemTypeParam( $intname, $name, $name_ru, $plid, $module, $elid = "" ){
        return self::sendRequest("itemtype.param.value.edit", array(
            "intname" => $intname,
            "name" => $name,
            "name_ru" => $name_ru,
            "plid" => $plid,
            "elid" => $elid,
            "module" => $module,
            "sok" => "ok"
        ));
    }


    /**
     * @param $itemType
     * @return SimpleXMLElement
     */
    public static function getItemTypeParams( $itemType ){
        return self::sendRequest("itemtype.param.value", array(
            "elid" => $itemType
        ));
    }



    /**
     * @param $itemtypeId
     * @return \SimpleXMLElement
     */
    public static function getItemtype( $itemtypeId ){
        return self::sendRequest( "itemtype.edit", array(
            "elid" => $itemtypeId
        ));
    }

    /**
     * @param $clientId
     * @param $adminLogin
     * @param $item
     * @param $department
     * @param $subject
     * @param $body
     * @param int $project
     * @return SimpleXMLElement
     */
    public static function createClientTicket( $clientId, $adminLogin, $item, $department, $subject, $body, $project=1 ){
        return self::sendRequest("ticket_all.edit", array(
            "subject" => $subject,
            "client_department" => $department,
            "item" => $item,
            "message" => $body,
            "ticket_account" => $clientId,
            "su" => $adminLogin,
            "project" => $project,
            "sok" => "ok"
        ));
    }


    /**
     * @param $userId
     * @param $subject
     * @param $message
     * @return SimpleXMLElement
     */
    public static function createNotification( $userId, $subject, $message ){
        return static::sendRequest("notification.edit", array(
            "subject" => $subject,
            "message" => $message,
            "user" => $userId,
            "sok" => "ok"
        ));
    }

    /**
     * @see http://doc.ispsystem.ru/index.php/BILLmanager_API#.D0.A1.D0.BE.D0.B7.D0.B4.D0.B0.D1.82.D1.8C_.D0.B7.D0.B0.D0.B4.D0.B0.D1.87.D1.83
     *
     * @param $departmentId
     * @param $message
     * @return SimpleXMLElement
     */
    public static function createTask($departmentId, $message ){
        return self::sendRequest("task.simple.create", array("elid"=>$departmentId, "specification" => $message, "sok"=> "ok"));
    }

    public static function deleteTask($elidTask ){
        return self::sendRequest("task.delete", array("elid"=>$elidTask));
    }
    public static function deleteTasks($elidTasks ){
        $elidTasks = implode(", " , $elidTasks);
        return self::sendRequest("task.delete", array("elid"=>$elidTasks));
    }
    /**
     *
     * @param $runningOperationId
     * @param $command
     * @param $item
     * @return SimpleXMLElement
     * @throws \Exception
     */
    public static function createOpeartionTask( $runningOperationId, $command, $item ){
        $taskType = self::getTaskType( $command);
        if(isset($taskType->task_type) && (string)$taskType->task_type!="") {
            return self::sendRequest("task.edit", array(
                "item"=>$item,
                "runningoperation" => $runningOperationId,
                "type" => (string)$taskType->task_type,
                "sok"=> "ok"
            ));
        }

        throw new \Exception("Not found taskType");
    }

    /**
     *
     * @param $expenseId
     * @return SimpleXMLElement
     */
    public static function deleteExpense( $expenseId ){
        return self::sendRequest("expense.delete", array(
            "elid" => $expenseId,
            "sok"=> "ok"
        ));
    }

    /**
     *
     * @param $expenseId
     * @return SimpleXMLElement
     */
    public static function getExpense( $expenseId ){
        return self::sendRequest("expense.edit", array(
            "elid" => $expenseId
        ));
    }
    /**
     *
     * @param $expenseId
     * @return SimpleXMLElement
     */
    public static function setExpense( $expenseId, $amount, $su ){
        return self::sendRequest("expense.edit", array(
            "elid" => $expenseId,
            "amount" => $amount,
            "su" => $su,
            "sok" => "ok"
        ));
    }


    /**
     * @param $command
     * @return SimpleXMLElement
     */
    public static function getTaskType($command ){
        return self::sendRequest("task.gettype",array(
            "operation" => $command
        ));
    }

    /**
     * @param $pricelistId
     * @return SimpleXMLElement
     */
    public static function getPriceInfo($pricelistId ){
        return self::sendRequest("pricelist.edit",array(
            "elid" => $pricelistId
        ));
    }

    /**
     * @param $pricelistId
     * @return SimpleXMLElement
     */
    public static function getPriceDetails($pricelistId ){
        return self::sendRequest("pricelist.detail",array(
            "elid" => $pricelistId
        ));
    }

    /**
     * @param $accountId
     * @return SimpleXMLElement
     */
    public static function getAccountInfo($accountId ){
        return self::sendRequest("account.edit",array(
            "elid" => $accountId
        ));
    }
    /**
     * @param $userId
     * @return SimpleXMLElement
     */
    public static function getUserInfo($userId ){
        return self::sendRequest("user.edit",array(
            "elid" => $userId
        ));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function setStatus( $service_id, $status ){
        return self::sendRequest("service.setstatus", array("elid"=>$service_id, "service_status" => $status));
    }

    /**
     * @param $contact_id
     * @param $extid
     * @param $registrar_id
     * @param string $type
     * @param string $password
     * @return SimpleXMLElement
     */
    public static function setContactExternalId($contact_id, $extid, $registrar_id, $type="owner", $password="" ){
        return self::sendRequest("service_profile2processingmodule.edit", array(
                "service_profile"=>$contact_id,
                "externalid" => $extid,
                "processingmodule" => $registrar_id,
                "type" => $type,
                "externalpassword" => $password,
                "sok" => "ok",
            )
        );
    }

    /**
     * @param $module_id
     * @return SimpleXMLElement
     */
    public static function getFraudGatewayInfo( $module_id ){
        return self::sendRequest("fraud_gateway.edit", array("elid"=>$module_id));
    }

    /**
     * @param $module_id
     * @return SimpleXMLElement
     */
    public static function getModuleInfo( $module_id ){
        return self::sendRequest("processing.edit", array("elid"=>$module_id));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function setParam( $service_id, $key, $value ){
        return self::sendRequest("service.saveparam", array("elid"=>$service_id, "name" => $key, "value" => $value));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function postProlong( $service_id ){
        return self::sendRequest("service.postprolong", array("elid"=>$service_id, "sok" => "ok"));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function postSetparam( $service_id ){
        return self::sendRequest("service.postsetparam", array("elid"=>$service_id, "sok" => "ok"));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function postClose( $service_id ){
        return self::sendRequest("service.postclose", array("elid"=>$service_id, "sok"=>"ok"));
    }


    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function postResume( $service_id ){
        return self::sendRequest("service.postresume", array("elid"=>$service_id, "sok" => "ok"));
    }

    /**
     * @param $service_id
     * @return SimpleXMLElement
     */
    public static function postSuspend( $service_id ){
        return self::sendRequest("service.postsuspend", array("elid"=>$service_id, "sok" => "ok"));
    }



    /**
     * @param $domain_id
     * @return SimpleXMLElement
     */
    public static function getDomainInfo( $domain_id ){
        return self::sendRequest("domain.edit", array("elid"=>$domain_id));
    }


    /**
     * @param $service
     * @return SimpleXMLElement
     */
    public static function getItemList( $service ){
        return self::sendRequest( $service );
    }


    /**
     * @param $service
     * @param $serviceId
     * @param $params
     * @return SimpleXMLElement
     */
    public static function setItemInfo( $service, $serviceId, $params ){
        $params["elid"] = $serviceId;
        $params["sok"] = "ok";
        return self::sendRequest( $service . ".edit", $params );
    }


    /**
     * @param $serviceId
     * @param $params
     * @return SimpleXMLElement
     */
    public static function setServiceParam( $serviceId, $params ){
        $params["elid"] = $serviceId;
        $params["sok"] = "ok";
        return self::sendRequest( "service.saveparam", $params );
    }

    /**
     * @param $service
     * @param $domain_id
     * @return SimpleXMLElement
     */
    public static function getItemInfo( $service, $domain_id ){
        return self::sendRequest( $service . ".edit", array("elid"=>$domain_id));
    }

    /**
     * @param $service_id
     * @param $user_id
     * @return SimpleXMLElement
     */
    public static function rollbackPrice( $service_id, $user_id ){
        return self::sendRequest( "service.changepricelist.rollback", array(
            "elid" => $service_id,
            "userid" => $user_id,
            "sok" => "ok",
        ));
    }


    /**
     * @param $domain_id
     * @return SimpleXMLElement
     */
    public static function domainOpen( $domain_id ){
        return self::serviceOpen("domain", $domain_id);
    }

    /**
     * @return SimpleXMLElement
     */
    public static function getConfig(){
        return self::sendRequest("paramlist");
    }


    /**
     * @param $serviceName
     * @param $id
     * @return SimpleXMLElement
     */
    public static function serviceOpen( $serviceName, $id ){
        return self::sendRequest( $serviceName . ".open", array("elid"=>$id, "sok" => "ok"));
    }

    public static function getCC($id) {
        $profile = self::sendRequest("service_profile.edit");
        $xpath_result = $profile->xpath("/doc/slist[@name='location_country']/val[@key='" . $id . "']");
        $iso2_params = array();
        if(isset($xpath_result[0]) &&
            $xpath_result[0] instanceof SimpleXMLElement){
            $attrs = $xpath_result[0]->attributes();
            $iso2_params =  explode("/", (string)($attrs["image"]));
            $iso2_params =explode(".", end( $iso2_params ));
        }
        return trim($iso2_params[0]);
    }



    /**
     * @param $contact_id
     * @return array
     */
    public static function getContactInfo( $contact_id ){

        $profile = self::sendRequest("service_profile.edit", array("elid"=>$contact_id));


        $out = array(
            "ctype" => $profile->profiletype == 2 ? "company" : "person",
            "profiletype" => (string)$profile->profiletype,
        );

        $simpleReplaceFormat = array(
            "id", "name", "account", "company", "company_ru", "firstname", "firstname_ru", "middlename", "middlename_ru",
            "lastname", "lastname_ru", "email", "phone", "fax", "birthdate", "inn", "kpp", "la_state", "la_postcode",
            "la_city", "la_address", "pa_state", "pa_postcode", "pa_city", "pa_address", "mobile", "ogrn", "passport_org","passport_date"
        );

        foreach ( $simpleReplaceFormat as $paramname ){
            $out = self::formatContactProparty($out, $profile, $paramname);
        }
        $out["passport_series"] = (string)$profile->passport;
        $xpath_result = $profile->xpath("/doc/slist[@name='location_country']/val[@key='" . $profile->location_country . "']");
        $iso2_params = array();
        if(isset($xpath_result[0]) &&
            $xpath_result[0] instanceof SimpleXMLElement){
            $attrs = $xpath_result[0]->attributes();
            $iso2_params =  explode("/", (string)($attrs["image"]));
            $iso2_params =explode(".", end( $iso2_params ));
        }
        $xpath_result = $profile->xpath("/doc/slist[@name='postal_country']/val[@key='" . (string)$profile->postal_country . "']");
        if(isset($xpath_result[0]) &&
            $xpath_result[0] instanceof SimpleXMLElement){
            $attrs = $xpath_result[0]->attributes();
            $iso2_postal_params =  explode("/", (string)($attrs["image"]));
            $iso2_postal_params =explode(".", end( $iso2_postal_params ));
            $out["iso2_postal"] = trim($iso2_postal_params[0]);
        }

        $externalInfo = self::sendRequest("service_profile2processingmodule");
        $externalInfo = $externalInfo->xpath("/doc/elem[service_profile[.=" . $out["id"] . "]]");
        foreach ($externalInfo as $element){
            $out["external_id"][(string)$element->processingmodule][] = array(
                "id" => (string)$element->externalid,
                "type" => (string)$element->type,
                "password" => isset($element->externalpassword) ? (string)$element->externalpassword : null
            );
        }

        $out["iso2"] = trim($iso2_params[0]);
        $out["xml"] = $profile;

        return $out;
    }

    private static function formatContactProparty( $array, $profile, $proparty_name){
        $profile_proparty_name = str_replace(array("_ru","la_","pa_"), array("_locale","location_","postal_") , $proparty_name);
        $array[$proparty_name] = (string)$profile->$profile_proparty_name;

        return $array;
    }

    /**
     * @param $domain_id
     * @return array
     */
    public static function getNSS($domain_id ){
        $result = self::sendRequest("domain.ns", array("elid"=>$domain_id));

        $nss = array();
        for( $i=0; $i<4; $i++ ){
            $nsname = "ns$i";
            if( $result->$nsname != "" ){
                $nss[] = self::string2Ns((string)$result->$nsname);
            }
        }

        if( isset( $result->ns_additional ) && trim( (string)$result->ns_additional ) ){
            $additionalNss = explode(" ", (string)$result->ns_additional);

            foreach ( $additionalNss as $nsString){
                $nss[] = self::string2Ns( $nsString );
            }
        }

        return $nss;
    }


    public static function string2Ns( $string ){
        $params = explode( "/", $string );

        if(count($params) > 1){
            $ns = array(
                "ns" => $params[0],
                "ip" => $params[1],
            );
        } else {
            $ns = array(
                "ns" => $params[0],
            );
        }
        $ns["hostparams"] = $params;

        return $ns;
    }
    /**
     * @param $domain_id
     * @return array
     */
    public static function setNSS( $domain_id, $nss ){
        $k=0;

        for( $i=0; $i<4; $i++ ){
            $ns_string = "";
            if(isset($nss[$i])) {
                $ns = $nss[$i];

                if(isset($ns["ns"]) && trim($ns["ns"])!="");
                $ns_string = $ns["ns"];

                if(isset($ns["ip"])){
                    $ns_string .= "/" . $ns["ip"];
                }


            }

            self::setParam( $domain_id, "ns$k", $ns_string);
            $k++;
        }
    }

    /**
     * @param $domain_id
     * @return array
     */
    public static function setExpires( $domain_id, $expiredate ){
        self::sendRequest( "service.setexpiredate", array( "elid" => $domain_id, "expiredate" => $expiredate ));
    }


    /**
     * @param $method
     * @param array $params
     * @param string $out
     * @return SimpleXMLElement
     */
    public static function sendRequest($method, $params = array(), $out= "xml" ){
        $params_line = array();

        foreach ($params as $key => $value){
            $params_line[] ="$key=" . escapeshellarg($value);
        }

        $command = \Config::$MGRCTLPATH . " -o $out -m billmgr " . $method . " " . implode( " ", $params_line );
        \logger::dump("CallBillmgrApi", $command, \logger::LEVEL_ISP_REQUEST);

        $trys = 30;
        do{
            $apiLocked = self::tryLock();
            if(!$apiLocked){
                sleep(1);
            }
        }while( !$apiLocked && $trys-- > 0 );

        $result = shell_exec( $command );

        if($apiLocked){
            self::unLock();
        }

        \logger::dump("Result", $result, \logger::LEVEL_ISP_RESULT);

        return new SimpleXMLElement($result);
    }

    protected static function unLock(){
    }

    protected static function tryLock(){
        return true;
    }

    /**
     * @param $docId
     * @param $item
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public static function domainDocVerified($docId , $item , $adminID){
        $out =  static::sendRequest("domain.doc.verified", array(
            "elid" => $docId,
            "plid" => $item,
            "su" => $adminID ,
        ));
        $ok = $out->xpath("//tparams");
        $err =  $out->xpath("//error");
        if(empty($ok) || !empty($err)){
            Api::getError($out);
        }

        return $out;
    }

    /**
     * @param SimpleXMLElement $xml
     * @throws Exception
     */
    private static function getError($xml){
        $error = $xml->xpath("//error/msg");

        if(!empty($error)){
            $msg = trim((string)$error);
            \logger::dump("error" ,$msg , \logger::LEVEL_ERROR );
            throw new Exception($msg);
        }
        \logger::dump("error" ,$xml->asXML() , \logger::LEVEL_ERROR );
        \logger::write("domain.doc.verified error error" , \logger::LEVEL_ERROR );
        throw new Exception("domain.doc.verified error");
    }
}