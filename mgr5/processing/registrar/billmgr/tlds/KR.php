<?php


namespace Billmgr\Tlds;


class KR extends Tdlchecker
{
    protected $check_Country = true;
    protected $checkTypes = array("owner");
    protected $availableCountries = array("KR");
    protected $erMessageForCountryCheck=array("Регистрировать домены в зоне .kr могут только резиденты Республики Кореи (KR)" ,
        "Only residents of the Republic of Korea (KR) can register domains in .kr zone"
    );
}