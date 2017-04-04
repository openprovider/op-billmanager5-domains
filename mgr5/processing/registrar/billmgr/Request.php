<?php
namespace Billmgr;

class Request{

    private static $instance;
    public static function readStdIn(){
        $xml = "";
        $stdin = fopen("php://stdin", "r");
        stream_set_blocking($stdin, false);
        if( ($buffer = fgets($stdin, 1000)) !== false) {
            $xml .= $buffer;
            while (!feof($stdin)) {
                $buffer = fgets($stdin, 1000);
                $xml .= $buffer;
            }
        }
        fclose($stdin);
        return $xml;
    }
    public static function init( $argv ){
        $request = new Request();

        for($i=1; $i<count($argv); $i++){
            if( strpos( $argv[$i], "--" ) === 0 ){
                if( $argv[$i] == "--param"){
                    if(isset($argv[$i+2]) && $argv[$i+2] == "--value") {
                        $request->setParam($argv[$i + 1], $argv[$i + 3]);
                        $i+=3;
                    } else {
                        $request->setParam($argv[$i + 1], true);
                        $i+=1;
                    }
                } else {
                    $key = preg_replace( "/^--/", "", $argv[$i]);
                    $setter = "set" . ucfirst($key);
                    if(is_callable(array($request, $setter))){
                        $request->$setter($argv[$i+1]);
                    } else {
                        $request->setParam($key, $argv[$i+1]);
                    }
                    $i +=1;
                }
            }
        }

        $request->getStdin();

        self::$instance = $request;
    }

    /**
     * @return Request
     */
    public static function getInstance(){
        return self::$instance;
    }


    private $command;
    private $item;
    private $module;
    private $params=array();
    private $runningoperation = null;
    private $tld;
    private $searchstring;
    private $stdin = null;

    /**
     * @return mixed
     */
    public function getSearchstring()
    {
        return $this->searchstring;
    }

    /**
     * @param mixed $searchstring
     */
    public function setSearchstring($searchstring)
    {
        $this->searchstring = $searchstring;
    }

    /**
     * @return mixed
     */
    public function getStdin()
    {
        if( $this->stdin == null ){
            $this->setStdin( self::readStdIn() );
            \logger::dump("StdIn:", $this->stdin, \logger::LEVEL_DEBUG);
        }
        return $this->stdin;
    }

    /**
     * @param mixed $stdin
     */
    public function setStdin($stdin)
    {
        $this->stdin = $stdin;
    }


    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param mixed $command
     */
    public function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * @return mixed
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * @param mixed $item
     */
    public function setItem($item)
    {
        $this->item = $item;
    }

    /**
     * @return mixed
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param mixed $module
     */
    public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * @return mixed
     */
    public function getParam( $key )
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
    }

    /**
     * @return mixed
     */
    public function getParams() {
        return array_keys( $this->params );
    }


    /**
     * @return mixed
     */
    public function getRunningoperation()
    {
        return $this->runningoperation;
    }

    /**
     * @param mixed $runningoperation
     */
    public function setRunningoperation($runningoperation)
    {
        $this->runningoperation = $runningoperation;
    }

    /**
     * @return mixed
     */
    public function getTld()
    {
        return $this->tld;
    }

    /**
     * @param mixed $tld
     */
    public function setTld($tld)
    {
        $this->tld = $tld;
    }

}