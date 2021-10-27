<?php


namespace Billmgr\Tlds;


class NO extends Tdlchecker
{
    protected $check_Country = true;
    protected $checkTypes = array("owner");
    protected $availableCountries = array("NO");
    protected $erMessageForCountryCheck=array("Регистрировать домены в зоне .no могут только резиденты Норвегии (NO)",
        "Only residents of Norway (NO) can register domains in the .no zone");
}