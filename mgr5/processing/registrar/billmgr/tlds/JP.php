<?php


namespace Billmgr\Tlds;


class JP extends Tdlchecker
{
    protected $check_Country = true;
    protected $availableCountries = array("JP");
    protected $erMessageForCountryCheck=array("Административный контакт должен быть резедентом Японии", "Administrative contact must be resident in Japan");
}