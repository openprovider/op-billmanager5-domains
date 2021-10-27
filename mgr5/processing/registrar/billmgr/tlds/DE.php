<?php

namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

class DE extends Tdlchecker {
    protected $availableCountries = array(
        'DE'
    );
    protected $checkTypes = array("admin");
    protected $erMessageForCountryCheck = array("Административный контакт должен быть резидентом Германии (DE)",
       "Administrative contact must be resident in Germany (DE)" );
    protected $check_Country =true;
}