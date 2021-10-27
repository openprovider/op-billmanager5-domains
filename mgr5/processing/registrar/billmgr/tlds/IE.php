<?php


namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;

class IE extends Tdlchecker
{

    private $msg1 = array("Для частных лиц контакт владельца должен быть резедентом Ирландии (IE)",
        "For individuals, the owner contact must be a resident of Ireland (IE)");

    public function check($contact)
    {


        if (!$this->IsCompany($contact, array("owner"))){
            $this->TdlCheckCountryRequired($contact, array("owner"), array("IE"),$this->msg1);
        }

    }


}