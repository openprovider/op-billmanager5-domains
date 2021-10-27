<?php


namespace Billmgr\Tlds;


use Billmgr\Tlds\Library\dotFr;

class TF extends Tdlchecker
{

    protected $availableCountries = dotFr::LIST_COUNTRY_FR;
    protected $checkTypes = array("owner" , "admin");
    protected $erMessageForCountryCheck = array("Регистрировать домены в зоне .re могут только резиденты стран членов EU, EEA или заморских территорий Франции",
        "Only residents of EU, EEA, or overseas territories of France can register domains in .re");
    protected $check_Country = true;
}
