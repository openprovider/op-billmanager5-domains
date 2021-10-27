<?php
namespace Billmgr\Tlds;

use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Responses\ExceptionWithLang;
use Billmgr\Tlds\Library\dotEu;

class BG extends Tdlchecker
{
    protected $availableCountries = dotEu::LIST_COUNTRY_EU;
    protected $checkTypes = array("owner");
    protected $erMessage = array( "Только юридические лица резиденты EU могут регистрировать домены в зоне .bg",
        "Only legal entities resident in the EU can register domains in the .bg zone" );
    protected $check_Country =true;

    public function check($contact)
    {
        if ($this->IsCompany($contact, $this->checkTypes )){
            return $this->TdlCheckCountryRequired($contact, $this->checkTypes ,$this->availableCountries, $this->erMessage );
        }else{
            throw new ExceptionWithLang($this->erMessage);
        }

    }
}