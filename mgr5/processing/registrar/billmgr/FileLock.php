<?php


namespace Billmgr;


class FileLock {

    private $directory = "";
    private $name = "";
    private $handler = null;


    public function __construct( $name, $directory = null ) {
        if( $directory == null){
            $directory = __DIR__ . "/lock/";
            if(!file_exists(__DIR__ . "/lock") ){
                mkdir(__DIR__ . "/lock");
            }
        }
        $this->setName( $name );
        $this->setDirectory($directory);
    }


    /**
     * @param bool $blocking
     * @return bool
     * @throws \Exception
     */
    public function lock($blocking = true){
        $flags = LOCK_EX;

        if( !$blocking ){
            $flags = LOCK_EX|LOCK_NB;
        }


        return flock($this->getFileHandler(),$flags);
    }

    public function unlock(){
        if( is_resource( $this->handler) ){
            fclose($this->handler);
            $this->handler = null;
        }
    }


    /**
     * @return resource
     * @throws \Exception
     */
    protected function getFileHandler(){
        if( $this->handler == null ){
            $this->handler = fopen($this->getFileName(), 'w');
            if( !is_resource($this->handler) ){
                $lastError =  error_get_last();
                throw new \Exception("Open lock file '" . $this->getFileName() . "' failed: " . $lastError["message"], 500);
            }
        }

        return $this->handler;
    }

    /**
     * @return string
     */
    protected function getFileName(){
        return $this->getDirectory() . $this->getName() . ".lock";
    }


    /**
     * @return string
     */
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * @param string $directory
     */
    public function setDirectory($directory) {
        $directory = trim($directory);
        if( substr($directory,-1) != DIRECTORY_SEPARATOR ){
            $directory .= DIRECTORY_SEPARATOR;
        }
        $this->directory = $directory;
    }



    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

}