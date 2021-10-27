<?php

namespace Billmgr\Tlds;

use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Tlds\Library\zipcodesBerlin;

class BERLIN extends Tdlchecker
{
    protected $availableCountries = array(
        'DE',
    );
    protected $availablePostCodes = zipcodesBerlin::LIST_ZIPCODES_BERLIN;
    protected $checkTypes = array("admin");
    protected $erMessageForCountryCheck = array("Административный контакт должен быть резедентом Германии (DE)",
         "Administrative contact must be resident in Germany (DE)"   );
    protected $erMessageForPostCode = array("Почтовый индекс контактного лица администратора должен принадлежать городу Берлин",
     "Administrator contact zip code must be in Berlin");
    protected $check_Country = true;
    protected $check_Postcode = true;
}