<?php
namespace Billmgr\Responses;
use Billmgr;

class Error extends Billmgr\Response{

    private $code;
    private $message;
    private $trace;
    private $description;
    private $langArray=null;

    public  function __construct(\Exception $ex)
    {
        $this->setCode($ex->getCode());
        $this->setMessage($ex->getMessage());
        $this->setTrace($ex->getTrace());
        if ($ex instanceof ExceptionWithLang){
            $this->langArray = $ex->getLangArray();
        }
        parent::__construct(false);
    }


    protected function makeXMLParams()
    {
        $xml = $this->getEmptyXML();
        $error = $xml->addChild("error");
        
        if( Billmgr\Request::getInstance() != null) {
            $this->setAttribute( $error, "type", Billmgr\Request::getInstance()->getCommand() );
            $this->setAttribute( $error, "object", Billmgr\Request::getInstance()->getItem() );
        }
        $this->setAttribute( $error, "code", $this->getCode() );



        if (isset($this->langArray)) {
            foreach ($this->langArray as $country => $msgEr) {
                $param = $error->addChild("param", (string)"v" . $country);
                $this->setAttribute($param, "name", (string)"value" . strtoupper($country));
                $this->setAttribute($param, "type", "msg");
                $this->setAttribute($param, "msg", (string)$msgEr);
            }
        }else{
            $this->setAttribute( $error, "lang", \Config::$LANG );
            if($this->getMessage() != ""){
                $error->addChild("msg", $this->getMessage());
            }
        }

        return $xml;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * @param mixed $trace
     */
    public function setTrace($trace)
    {
        $this->trace = $trace;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }
}