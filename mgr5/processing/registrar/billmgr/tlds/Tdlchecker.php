<?php



namespace Billmgr\Tlds;


use Billmgr\Database;


use Billmgr\Responses\Error;
use Billmgr\Responses\ExceptionWithLang;
use Billmgr\Responses\TuneServiceProfile;
use libs\Socket\Exceptions\Exception;

abstract class Tdlchecker  implements Tldcheck {




    protected $checkTypes = array(
        "admin"
    );

    protected $check_Country = false;
    protected $availableCountries = array();
    protected $erMessageForCountryCheck=array("Ошибка" ,  "Error");



    protected $check_Postcode = false;
    protected $availablePostCodes = array();
    protected $erMessageForPostCode=array("Ошибка" ,  "Error");
    /**
     * @inheritDoc//owner_countryOfCitizenship
     */
    public function check($contact) {

        if ($this->check_Country){
            $this->TdlCheckCountryRequired($contact, $this->checkTypes,$this->availableCountries, $this->erMessageForCountryCheck);


        }
        if ($this->check_Postcode){
            $this->TdlCheckРostcodeRequired($contact, $this->checkTypes , $this->availablePostCodes, $this->erMessageForPostCode);
        }

        return true;
    }

    public function getUserLanguage($contact){

/*
 *
 *         \logger::dump("1RERE12121212", $contact, \logger::LEVEL_DEBUG);
        throw new \Error("qwqwee",0, "qwqw");
        throw new \Exception();
       // \logger::dump("saasdasdaa", $s, \logger::LEVEL_DEBUG);

 */
        /*
         *         $s = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><doc></doc>');
        $er = $s->addChild("error");
        $er->addAttribute("type","validate_service_profile");
        $er->addAttribute("code","0");
        $er->addAttribute("lang","en");
        $er->addChild("msg","HELP ERORRRRRR");
         *
         *
         *
        \logger::dump("1212121212", $contact, \logger::LEVEL_DEBUG);
        \logger::dump("xml211221", $xml, \logger::LEVEL_DEBUG);
        return $xml->lang;
        */
    }

    public function TdlCheckCountryRequired($contact, $checkTypes, $availableCountries, $erMessage){
        $ids = array();

        foreach ($checkTypes as $type){
            $type = $type . "_location_country";
            if( isset( $contact->$type ) ) {
                $ids[] = "'" . Database::getInstance()->escape((string)$contact->$type) . "'";

            }
        }

        $countryInfoQueryResult = Database::getInstance()->query("SELECT * FROM `country` WHERE `id` IN (" . implode(",", $ids) . ")");


        while( $row = mysqli_fetch_assoc($countryInfoQueryResult) ){
            if(!in_array(mb_strtoupper($row["iso2"], "UTF-8"), $availableCountries)) {
                throw new ExceptionWithLang($erMessage);
            }
        }

        return true;
    }

    public function TdlCheckРostcodeRequired($contact, $checkTypes, $availablePostCodes, $erMessage){
        $postcodes = array();

        foreach ($checkTypes as $type){
            $type = $type . "_location_postcode";
            if( isset( $contact->$type ) ) {
                array_push($postcodes,trim((string)$contact->$type));
            }
        }

        foreach ($postcodes as $postcode){
            if(!in_array($postcode, $availablePostCodes)) {
                throw new ExceptionWithLang($erMessage);
            }
        }

        return true;
    }

    public function IsCompany($contact, $checkTypes){
        foreach ($checkTypes as $t){
            $type = $t . "_profiletype";
            if(isset($contact->$type) && (string)$contact->$type == "1") {
                return false;
            }
        }
        return true;
    }
}