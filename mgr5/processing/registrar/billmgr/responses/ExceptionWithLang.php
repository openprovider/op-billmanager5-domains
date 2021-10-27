<?php





namespace Billmgr\Responses;




class ExceptionWithLang extends \Exception
{


    /**
     * @var array array("ru" => "Ошибка" , "en" => "Error")
     */
    protected $messagesArray = null;


    protected $message;

    protected $code;

    protected $EvaluableLang = array("ru" , "en");


    /**
     * ExceptionWithLang constructor.
     * @param $messagesArray   array("Ошибка" , "Error")
     * @param string $langDefault = "ru" - default getMessage lang
     * @param int $code
     */
    public function __construct( $messagesArray =array() ,$langDefault ="ru" ,$code = 0 )    {

        if (is_array($messagesArray)){
            $arr= array();
            for ($i = 0; $i < count($this->EvaluableLang); $i++){
                $arr[$this->EvaluableLang[$i]] = isset($messagesArray[$i]) ? $messagesArray[$i] : "";
            }
            $this->messagesArray = $arr;

            $this->message = isset($arr[$langDefault]) ? $arr[$langDefault] : $arr["ru"];
        }else{
            $this->message = $messagesArray;
        }
        $this->code = $code;
    }

    /**
     * @return array array("ru" => "" , "en" => "")
     */
    public function getLangArray(){
        return $this->messagesArray;
    }

    /**
     * @param string "ru" || "en"
     *
     * @return string err
     */
    public function getMessageLang($lang = "ru"){
        return isset($this->getLangArray()[strtolower($lang)]) ? $this->getLangArray()[strtolower($lang)] : "";
    }
}


