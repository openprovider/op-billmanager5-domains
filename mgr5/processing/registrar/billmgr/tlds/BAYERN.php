<?php

namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

class BAYERN extends Tdlchecker
{
    protected $check_Country =false;


    protected $availableCountries = array(
        'DE'
    );

    protected $checkTypes = array("admin");
    protected $erMessageForCountryCheck = array( "Административный контакт должен быть резедентом Германии (DE)" ,
        "Administrative contact must be resident in Germany (DE)");

}