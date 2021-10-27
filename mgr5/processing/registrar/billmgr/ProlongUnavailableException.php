<?php


namespace Billmgr;


use Billmgr\Responses\ExceptionWithLang;

class ProlongUnavailableException extends \ClientException {

    protected $not_available_for_prolong = "Здравствуйте,\n\n\nДата истечения срока регистрации: {paid-till}. Домен не доступен для продления до {paid-prolong}.";
    protected $message;
    protected $code;
    protected $dateExpires;


    /**
     * ProlongUnavailableException constructor.
     * @param $dateExpires
     * @param $code
     * @param $domanename 2020-09-06
     */
    public function __construct($dateExpires, $code , $domanename)
    {

        $this->setDateExpires(strtotime($dateExpires));
        $dateProlong =  date("Y-m-d",strtotime("-2 month" , strtotime($dateExpires)));
        $this->message =    str_replace("{paid-till}", date("Y-m-d", strtotime($dateExpires)), $this->not_available_for_prolong);
        $this->message =  str_replace("{paid-prolong}", $dateProlong, $this->message);
        $this->setSubject("Операция продления домена $domanename не выполнена");
        $this->setBody($this->message);
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getDateExpires()
    {
        return $this->dateExpires;
    }

    /**
     * @param mixed $dateExpires
     */
    public function setDateExpires($dateExpires)
    {
        $this->dateExpires = $dateExpires;
    }
}