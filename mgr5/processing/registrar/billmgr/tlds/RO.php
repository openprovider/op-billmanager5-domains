<?php


namespace Billmgr\Tlds;


class RO extends Tdlchecker
{

    protected $availableCountries = array("RO");

    protected $checkTypes = array("owner" );
    protected $erMessageForCountryCheck = array("Регистрировать домены в зоне .ro могут только резиденты Румынии (RO)",
       "Only residents of Romania (RO) can register domains in the .ro zone" );
    protected $check_Country = false;
}