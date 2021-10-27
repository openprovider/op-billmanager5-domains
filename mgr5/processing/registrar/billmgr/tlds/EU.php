<?php

namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Tlds\Library\dotEu;

class EU extends Tdlchecker
{
    protected $availableCountries = dotEu::LIST_COUNTRY_EU;

    protected $checkTypes = array("owner");
    protected $erMessageForCountryCheck = array("Только резиденты EU могут регистрировать домены в зоне .eu",
 "Only EU residents can register domains in .eu zone" );
    protected $check_Country = true;
}