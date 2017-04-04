<?php
//  require_once dirname(__FILE__) . "/config.php";

class Database
{
    public $error_list=array();
    public $die_on_error=false;
    public $mysqli;
    private $db;
    protected  function report()
    {
        echo $this->errMsg;
    }
    public function __construct($hostname,$username,$password,$port,$database)
    {
        $this->db=array();
        $this->db['hostname']=$hostname;
        $this->db['username']=$username;
        $this->db['password']=$password;
        $this->db['port']=$port;
        $this->db['database']=$database;
        $this->mysqli=mysqli_init();
        $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        if (!$this->mysqli->real_connect($this->db['hostname'], $this->db['username'], $this->db['password'], null,$this->db['port'],null, Config::$MYSQL_SSL? MYSQLI_CLIENT_SSL:null))
        {
            $this->errMsg = "Connect error to MySQL server!\r\n".$this->mysqli->error;
            $this->report();
            if($this->die_on_error) {
                exit('MySQL Connection Error');
            }
            else {
                $this->error_list[] = $this->errMsg;
                return;
            }
        }
        if ( $this->mysqli->select_db( $this->db['database']) === false )
        {
            $this->errMsg = "Database not found!\r\n";
            $this->report();
            exit('MySQL Connection Error');
        }
        $this->mysqli->query("SET NAMES 'utf8'");
    }
    function __destruct()
    {
        $this->mysqli->close();
    }
    public function query($query)
    {
        $trys = 0;
        do {
            $trys++;
            \logger::dump("SQLquery", $query, \logger::LEVEL_SQL_REQUEST);
            $result = $this->mysqli->query($query);
            if($this->mysqli->error != ""){
                \logger::dump("SQLerror", $this->mysqli->error, \logger::LEVEL_ERROR);
            }
        }while($trys < 10 && $this->mysqli->error == "Deadlock found when trying to get lock; try restarting transaction");

        return $result;
    }
}