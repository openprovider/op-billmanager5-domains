<?php
namespace Billmgr;
require_once __DIR__ . "/FileCache.php";
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
        try {
            $paramlistCache = FileCache::getFileCache("paramlist");
            if ($paramlistCache->getValue() == null ) {
                $config = Api::getConfig();
                try {
                    $paramlistCache->setValue($config->asXML(), false);
                }catch (\Exception $nothing){}
            } else {
                $config = new \SimpleXMLElement($paramlistCache->getValue());
            }
        }catch (\Exception $nothing){
            $config = Api::getConfig();
        }

        $dbhost = (string)$config->xpath("/doc/elem/DBHost")[0];// Имя хоста БД
        $dbusername = (string)$config->xpath("/doc/elem/DBUser")[0]; // Пользователь БД
        $dbpass = (string)$config->xpath("/doc/elem/DBPassword")[0];                    // Пароль к базе
        $dbname = (string)$config->xpath("/doc/elem/DBName")[0];  // Имя базы
        $socket = (string)$config->xpath("/doc/elem/DBSocket")[0];  // Имя базы


        if( trim($socket) != "" ){
            parent::__construct( null, $dbusername, $dbpass, null, $dbname,  $socket );
        } else {
            \logger::write("DB connection using TCP!", \logger::LEVEL_WARNING);
            parent::__construct( $dbhost, $dbusername, $dbpass, 3306, $dbname );
        }
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
     * @param $itemId
     * @param int $day
     * @return array
     */
    public function getExpenseProlongForDay($itemId, $day){

        $expRows = $this->query("SELECT id FROM expense WHERE `item`='" . $this->mysqli->real_escape_string( $itemId ) . "' AND `operation`='prolong' AND DATEDIFF(CURRENT_DATE, realdate) <= '".$this->mysqli->real_escape_string($day) . "'");
        $expenses = array();

        while( $row = mysqli_fetch_assoc( $expRows ) ){
            $expenses[] = $row;
        }

        return $expenses;
    }
    public function getProfileWarnings( $profileId ){
        $warningQueryResult = $this->query("SELECT * FROM `service_profileparam` WHERE `service_profile`='" . $this->mysqli->real_escape_string($profileId) . "' AND `warning_message` IS NOT NULL");

        $warnings = array();
        while( $row = mysqli_fetch_assoc($warningQueryResult) ){
            $warnings[] =$row;
        }
        return $warnings;
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

    public function getAccountInfo( $id ){
        $itemInfo = mysqli_fetch_assoc($this->query("SELECT * FROM `account` WHERE `id`='" . $this->mysqli->real_escape_string( $id ) . "'"));

        return $itemInfo;
    }
    
    public function getDomainInfo( $domain_id ) {
        return $this->getItemInfo($domain_id);
    }

    public function getUserInfo( $userId ){

        $notFound = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<doc>
  <error type=\"value\" object=\"elid\" lang=\"ru\" code=\"1\">
    <param name=\"object\" type=\"msg\" msg=\"Идентификатор элемента\">elid</param>
    <param name=\"value\">" . htmlspecialchars($userId) . "</param>
    <param name=\"desc\" type=\"msg\">desk_empty</param>
    <stack>
      <action level=\"30\" user=\"root\">user.edit</action>
    </stack>
    <group>Поле '__object__' имеет недопустимое значение. __desc__</group>
    <msg>Поле 'Идентификатор элемента' имеет недопустимое значение. </msg>
  </error>
</doc>");

        $userId = trim($userId);
        if($userId == ""){
            return $notFound;
        }
        if( preg_match("/^[0-9]+$/", $userId) != 0 ) {
            $queryParam = "id";
        } else {
            $queryParam = "name";
        }

        $resultData = $this->query("SELECT * FROM `user` WHERE `$queryParam`='" . $this->escape($userId) ."'");

        if( $resultData->num_rows != 1 ){
            return $notFound;
        }

        return $this->makeObject( mysqli_fetch_assoc($resultData) );
    }


    public function getModuleInfo( $moduleID ){
        $moduleInfo = mysqli_fetch_assoc( $this->query("SELECT * FROM `processingmodule` WHERE `id`='" . $this->escape($moduleID) . "'") );

        if( !isset($moduleInfo["id"]) ){
            $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<doc>
  <error type=\"value\" object=\"elid\" lang=\"ru\" code=\"1\">
    <param name=\"object\" type=\"msg\" msg=\"Идентификатор элемента\">elid</param>
    <param name=\"value\">" . htmlspecialchars($moduleID) . "</param>
    <param name=\"desc\" type=\"msg\">desk_empty</param>
    <stack>
      <action level=\"30\" user=\"root\">processing.edit</action>
    </stack>
    <group>Поле '__object__' имеет недопустимое значение. __desc__</group>
    <msg>Поле 'Идентификатор элемента' имеет недопустимое значение. </msg>
  </error>
</doc>");
        } else {

            $params = $moduleInfo;
            $params["elid"] = $params["id"];


            $moduleInfo = $this->query("SELECT `intname`,`value` FROM `processingparam` WHERE `processingmodule`='" . $this->escape($moduleID) . "'");

            while( $row = mysqli_fetch_assoc($moduleInfo) ){
                if( !isset($params[$row["intname"]])){
                    $params[$row["intname"]] =  $row["value"];
                }
            }

            $xml = $this->makeObject($params);
        }

        return $xml;
    }



    /**
     * @param $row
     * @return \SimpleXMLElement
     */
    protected function makeObject( $row ){
        $object = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<doc/>");

        foreach ($row as $key => $value){
            $object->addChild($key, htmlspecialchars($value));
        }

        return $object;
    }

    public function getUserInfoArray( $id ){
        return mysqli_fetch_assoc($this->query("SELECT * FROM `user` WHERE `id`='" . $this->mysqli->real_escape_string( $id ) . "'"));
    }
}