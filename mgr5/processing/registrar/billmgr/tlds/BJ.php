<?php

namespace Billmgr\Tlds;

use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

class BJ extends Tdlchecker
{
    protected $availableCountries = array(
        'BJ'
    );
    protected $checkTypes = array("owner", "admin");
    protected $erMessageForCountryCheck = array("Контакт владельца и административный  контакт должены быть резидентами республики Бенин (BJ)",
      "Owner contact and administrative contact must be residents of the Republic of Benin (BJ)"  );
    protected $check_Country = true;

}