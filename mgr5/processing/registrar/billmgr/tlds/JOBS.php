<?php


namespace Billmgr\Tlds;
use Billmgr\Api;
use Billmgr\Database;
use Billmgr\Request;
use Billmgr\Responses\ExceptionWithLang;

class JOBS extends Tdlchecker
{
    public function check($contact)
    {
        if ($this->IsCompany($contact, array("owner"))){
            return true;
        }else{
            throw new ExceptionWithLang(array("Регистрировать домены в зоне .jobs могут только юридисеские лица","Only legal entities can register domains in the .jobs zone"));
        }

    }
}