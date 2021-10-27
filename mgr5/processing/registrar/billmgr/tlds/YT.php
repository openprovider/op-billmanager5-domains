<?php


namespace Billmgr\Tlds;


use Billmgr\Tlds\Library\dotFr;

class YT extends Tdlchecker
{

    protected $availableCountries = dotFr::LIST_COUNTRY_FR;
    protected $checkTypes = array("owner" , "admin");
    protected $erMessageForCountryCheck = array("Регистрировать домены в зоне .yt могут только резиденты стран членов EU, EEA или заморских территорий Франции",
    "Only residents of countries of the EU, EEA or overseas territories of France can register domains in the .yt zone");
    protected $check_Country = true;
}