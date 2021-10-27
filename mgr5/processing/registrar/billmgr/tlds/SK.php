<?php


namespace Billmgr\Tlds;


use Billmgr\Tlds\Library\dotEu;

class SK extends Tdlchecker
{
    protected $check_Country = true;
    protected $availableCountries = dotEu::LIST_COUNTRY_EU;
    protected $checkTypes = array("owner");
    protected $erMessageForCountryCheck = array("Регистрировать домены в зоне .sk могут резиденты стран членов EU",
        "Residents of EU member states can register domains in the .sk zone");
}