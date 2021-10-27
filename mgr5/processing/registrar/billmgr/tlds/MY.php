<?php


namespace Billmgr\Tlds;


class MY extends Tdlchecker
{
    protected $check_Country = true;
    protected $checkTypes = array("owner");
    protected $availableCountries = array("MY");
    protected $erMessageForCountryCheck=array("Регистрировать домены в зоне .my могут только резиденты Малайзии (MY)" ,
        "Only residents of Malaysia (MY) can register domains in the .my zone");
}