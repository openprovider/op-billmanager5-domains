<?php


namespace Billmgr;


class FileCache {

    /**
     * @param $fname
     * @return FileCache
     */
    public static function getFileCache($fname ){
        $fname = str_replace(array("/","."), "_", $fname);
        if( !file_exists(__DIR__ . "/cache") ){
            mkdir(__DIR__ . "/cache");
        }
        return new FileCache( __DIR__ . "/cache/$fname.cache");
    }


    private $filename;
    private $value = null;

    public function __construct( $filename ) {
        if( !file_exists( $filename ) ){
            touch($filename);
        }
        $this->setFilename( $filename );
        $this->value = file_get_contents($this->getFilename());
    }


    public function getValue(){
        return $this->value;
    }

    public function setValue( $value, $blocking = false ){
        $lock_file = null;
        if( !flock($lock_file = fopen( $this->getFilename(), 'w'), $blocking ? (LOCK_EX) : (LOCK_EX | LOCK_NB)) ){
            throw new \Exception("Lock " . $this->getFilename() . " failed");
        }
        try {
            if( fwrite($lock_file, $value) !== false ) {
                $this->value = $value;
            } else {
                throw new \Exception("Write into " . $this->getFilename() . " failed" );
            }
        }finally{
            fclose($lock_file);
        }
    }

    /**
     * @return false|int
     */
    public function getLastModifyTime(){
        clearstatcache();
        return filemtime( $this->getFilename() );
    }

    /**
     * @return mixed
     */
    public function getFilename() {
        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    private function setFilename($filename) {
        $this->filename = $filename;
    }
}