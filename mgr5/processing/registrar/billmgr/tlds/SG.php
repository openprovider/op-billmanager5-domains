<?php


namespace Billmgr\Tlds;


class SG extends Tdlchecker
{
    protected $availableCountries = array(
        'SG'
    );
    protected $checkTypes = array("admin");
    protected $erMessageForCountryCheck = array("Административный контакт должен быть резидентом Сингапура (SG)",
    "Administrative contact must be a resident of Singapore (SG)");
    protected $check_Country =true;
}