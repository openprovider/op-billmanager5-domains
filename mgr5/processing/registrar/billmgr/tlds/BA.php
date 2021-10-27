<?php

namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

class BA  extends Tdlchecker
{
    protected $availableCountries = array(
        'BA'
    );

    protected $checkTypes = array("owner","admin");
    protected $erMessageForCountryCheck =array("Контакты владельца и администратора должны быть резедентами Боснии и Герцеговины (BA)" ,
         "The owner and administrator contacts must be residents of Bosnia and Herzegovina (BA)" );
    protected $check_Country =true;
}