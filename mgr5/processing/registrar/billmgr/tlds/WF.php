<?php


namespace Billmgr\Tlds;


use Billmgr\Tlds\Library\dotFr;

class WF extends Tdlchecker
{

    protected $availableCountries = dotFr::LIST_COUNTRY_FR;
    protected $checkTypes = array("owner" , "admin");
    protected $erMessageForCountryCheck = array("Регистрировать домены в зоне .wf могут только резиденты стран членов EU, EEA или заморских территорий Франции",
        "Only residents of countries of the EU, EEA or overseas territories of France can register domains in .wf");
    protected $check_Country = true;
}