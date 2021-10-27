<?php


namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Tlds\Library\dotEu;

class HU extends Tdlchecker
{
    protected $availableCountries = dotEu::LIST_COUNTRY_EU;
    private $msg1 = array( "Контакт администратора должен быть резедентом Венгрии (HU)",
         "Admin contact must be a resident of Hungary (HU)");
    private $msg2 = array("Для юридических лиц контакт владельца должен быть резедентом стран EU",
        "For legal entities, the owner’s contact must be a resident of EU countries");

        public function check($contact)
        {
            $this->TdlCheckCountryRequired($contact, array("admin"), array("HU"),$this->msg1);

            if ($this->IsCompany($contact, array("owner"))){
                $this->TdlCheckCountryRequired($contact, array("owner"), $this->availableCountries,$this->msg2);
            }
            return true;
        }
}