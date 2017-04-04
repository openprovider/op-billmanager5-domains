<?php
namespace Billmgr;

class Database extends \Database{

    public static $instance = null;

    public static function getInstance(){
        if( self::$instance == null ){
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $config = Api::getConfig();

        $dbhost = (string) $config->xpath("/doc/elem/DBHost")[0];// Имя хоста БД
        $dbusername = (string) $config->xpath("/doc/elem/DBUser")[0]; // Пользователь БД
        $dbpass = (string) $config->xpath("/doc/elem/DBPassword")[0]; 					// Пароль к базе
        $dbname =  (string) $config->xpath("/doc/elem/DBName")[0];  // Имя базы


        parent::__construct( $dbhost=="localhost" ? "127.0.0.1" : $dbhost, $dbusername, $dbpass, 3306, $dbname );
    }

    /**
     * @param int $itemId
     * @return array
     */
    public function getItemExpense( $itemId ){
        $expRows = $this->query("SELECT * FROM expense WHERE item='" . $this->mysqli->real_escape_string( $itemId ) . "'");
        $expenses = array();
        
        while( $row = mysqli_fetch_assoc( $expRows ) ){
            $expenses[] = $row;
        }
        
        return $expenses;
    }

    /**
     * @deprecated 
     * 
     * @param int $itemId
     * @param array $params
     */
    public function updateItemParams($itemId, $params ){
        foreach ($params as $key => $value){
            $this->query("INSERT INTO itemparam (`id`,`item`,`intname`,`value`) VALUES ((SELECT MAX(id) + 1 FROM itemparam ipr),'" . $this->mysqli->real_escape_string($itemId) . "','" . $this->mysqli->real_escape_string($key) . "','" . $this->mysqli->real_escape_string($value) . "') ON DUPLICATE KEY UPDATE `value`='" . $this->mysqli->real_escape_string($value) . "'");
        }
    }

    
    public function getItemInfo( $id ){
        $itemInfo = mysqli_fetch_assoc($this->query("SELECT * FROM `item` WHERE `id`='" . $this->mysqli->real_escape_string( $id ) . "'"));

        $params = $this->query("SELECT * FROM itemparam WHERE item ='" . $this->mysqli->real_escape_string( $id ) . "'");
        while( $row = mysqli_fetch_assoc($params)){
            $itemInfo[$row["intname"]] = $row["value"];
        }

        return $itemInfo;
    }
    
    public function getDomainInfo( $domain_id ) {
        return $this->getItemInfo($domain_id);
    }
    
}