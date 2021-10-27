<?php


namespace Billmgr\Tlds;






class MA extends Tdlchecker
{
    protected $check_Country = true;
    protected $checkTypes = array("admin");
    protected $availableCountries = array("MA");
    protected $erMessageForCountryCheck=array("Административный контакт должен быть резедентом Марокко (MA)" ,
        "Administrative contact must be a resident of Morocco (MA)");
}

