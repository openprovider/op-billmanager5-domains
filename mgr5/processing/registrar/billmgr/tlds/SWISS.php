<?php


namespace Billmgr\Tlds;


use Billmgr\Responses\ExceptionWithLang;

class SWISS extends Tdlchecker

{
    public function check($contact)
    {

       if ( !$this->IsCompany($contact, array("owner"))){
           throw new ExceptionWithLang(array("Регистрировать домены в зоне .swiss могут только Юридические лица", "Only legal entities can register domains in the .swiss zone"));
       }
       $this->TdlCheckCountryRequired($contact,array("owner"),array("CH"), array("Регистрировать домены в зоне .swiss могут только юридические лица резиденты Швейцарии (CH)",
           "Only residents of Switzerland (CH) can register domains in the .swiss zone"));

        return true;
    }
}