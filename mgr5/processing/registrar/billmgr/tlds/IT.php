<?php


namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Tlds\Library\dotIT;

class IT extends Tdlchecker
{
    protected $availableCountries = dotIT::LIST_COUNTRY_IT;
    protected $checkTypes = array("owner", "admin");
    protected $erMessageForCountryCheck = array( "Регистрировать домены в зоне .it могут резиденты стран членов EU, EEA, Ватикана, Сан-Марино",
    "Residents of countries of the EU, EEA, Vatican, San Marino member countries can register domains in the .it zone");
    protected $check_Country = true;
}