<?php
namespace Billmgr;

class Cache{

    const PROCESSING_CACHE_TABLE = "processingcache";
    const PROCESSING_CACHE_ACTUALUNTIL = "1 HOUR";

    static private $instance = null;

    static public function init( $processingName ){
        if( !(self::$instance instanceof Cache) ){
            self::$instance = new Cache( $processingName );
            Database::getInstance()->query("CREATE TABLE IF NOT EXISTS `" . self::PROCESSING_CACHE_TABLE . "` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `processingmodule` VARCHAR(255) NOT NULL,
  `actualuntil` DATETIME NOT NULL,
  `key` VARCHAR(255) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB");
        }
    }

    /**
     * @return Cache
     * @throws \Exception
     */
    static public function getInstance(){
        if( !(self::$instance instanceof Cache) ){
            throw new \Exception("Cache not initiated, required to call Cache::init(int) first!");
        }

        return self::$instance;
    }


    private $processingId;
    private $loaded = array();


    public function __construct( $processingName ){
        $this->setProcessingName( $processingName );
    }


    public function getValue( $paramName ){
        if( !isset($this->loaded[ $this->getProcessingName() ]) ){
            $loadedResult = Database::getInstance()->query("SELECT * FROM `" . self::PROCESSING_CACHE_TABLE . "` WHERE `processingmodule`='" .
                Database::getInstance()->mysqli->escape_string( $this->getProcessingName() ) . "' AND `actualuntil`>NOW()");
            while( $row = mysqli_fetch_assoc($loadedResult) ){
                $this->loaded[ $this->getProcessingName() ][$row["key"]] = $row["value"];
            }
        }

        return isset($this->loaded[ $this->getProcessingName() ][ $paramName ]) ? $this->loaded[ $this->getProcessingName() ][ $paramName ] : null;
    }

    public function setValue( $paramName, $paramValue, $actualUntil = null ){
        Database::getInstance()->query("UPDATE  `" . self::PROCESSING_CACHE_TABLE . "`  SET `value`='" .
            Database::getInstance()->mysqli->escape_string($paramValue) . "', `actualuntil`=" .
            ($actualUntil != null ? "'" . Database::getInstance()->mysqli->escape_string(date("Y-m-d H:i:s", $actualUntil)) . "'" : "NOW() + INTERVAL " . self::PROCESSING_CACHE_ACTUALUNTIL ) ." WHERE `processingmodule`='" .
            Database::getInstance()->mysqli->escape_string($this->getProcessingName()) . "' AND `key`='" .
            Database::getInstance()->mysqli->escape_string($paramName) . "'");

        if(Database::getInstance()->mysqli->affected_rows == 0){
            Database::getInstance()->query("INSERT INTO  `" . self::PROCESSING_CACHE_TABLE . "`  (`processingmodule`,`actualuntil`,`key`,`value`) VALUES ('" .
                Database::getInstance()->mysqli->escape_string($this->getProcessingName()) . "'," .
                ($actualUntil != null ? "'" . Database::getInstance()->mysqli->escape_string(date("Y-m-d H:i:s", $actualUntil)) . "'" : "NOW() + INTERVAL " . self::PROCESSING_CACHE_ACTUALUNTIL )
                . ",'" .
                Database::getInstance()->mysqli->escape_string($paramName) . "', '" .
                Database::getInstance()->mysqli->escape_string($paramValue) . "')");
        }

        $this->loaded[ $this->getProcessingName() ][ $paramName ] = $paramValue;
    }

    /**
     * @return mixed
     */
    public function getProcessingName(){
        return $this->processingId;
    }

    /**
     * @param mixed $processingName
     */
    public function setProcessingName($processingName){
        $this->processingId = $processingName;
    }

}