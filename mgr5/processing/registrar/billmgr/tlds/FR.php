<?php



namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

use Billmgr\Tlds\Library\dotFr;

class FR extends Tdlchecker
{

    protected $availableCountries = dotFr::LIST_COUNTRY_FR;

    protected $checkTypes = array("owner" , "admin");
    protected $erMessageForCountryCheck = dotFr::ERR_FOR_COUNTRY;
    protected $check_Country = true;
}