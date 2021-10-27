<?php

class Database
{
    public $error_list=array();
    public $die_on_error=false;
    public $mysqli;
    private $db;
    protected  function report()
    {
        //echo $this->errMsg;
    }
    public function __construct($hostname,$username,$password,$port,$database, $socket = null)
    {
        $this->db=array();
        $this->db['hostname']=$hostname;
        $this->db['username']=$username;
        $this->db['password']=$password;
        $this->db['port']=$port;
        $this->db['database']=$database;
        $this->db['socket']=$socket;
        $this->mysqli=mysqli_init();
        $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $isConnected = $socket != null ?
            $this->mysqli->real_connect(null, $this->db['username'], $this->db['password'], null,null,$socket):
            $this->mysqli->real_connect($this->db['hostname'], $this->db['username'], $this->db['password'], null,$this->db['port'],null,  MYSQLI_CLIENT_SSL);

        if (!$isConnected)
        {
            $this->errMsg = "Connect error to MySQL server!\r\n".$this->mysqli->error;
            $this->report();
            if($this->die_on_error) {
                return;
                //exit('MySQL Connection Error');
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
            return;
            //exit('MySQL Connection Error');
        }
        $this->mysqli->query("SET NAMES 'utf8'");
    }
    function __destruct()
    {
        $this->mysqli->close();
    }

    public function reconnect(){
        \logger::write("Trying to reconnect to MySQL server",\logger::LEVEL_SQL_REQUEST);
        $this->mysqli->close();
        $this->mysqli=mysqli_init();
        $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        $isConnected = $this->db['socket'] != null ?
            $this->mysqli->real_connect(null, $this->db['username'], $this->db['password'], null,null,$this->db['socket']):
            $this->mysqli->real_connect($this->db['hostname'], $this->db['username'], $this->db['password'], null,$this->db['port'],null,  MYSQLI_CLIENT_SSL);


        if (!$isConnected) {
            \logger::dump("SQLerror", "Connect error to MySQL server! ".$this->mysqli->error, \logger::LEVEL_ERROR);
        }
        if ( $this->mysqli->select_db( $this->db['database']) === false ) {
            \logger::dump("SQLerror", "Database not found!", \logger::LEVEL_ERROR);
        }
        $this->mysqli->query("SET NAMES 'utf8'");
    }

    public function query($query)
    {
        $trys = 0;
        $reconnected = false;
        do {
            $reconnected = false;
            $trys++;
            \logger::dump("SQLquery", $query, \logger::LEVEL_SQL_REQUEST);
            $result = $this->mysqli->query($query);
            if($this->mysqli->error != ""){
                \logger::dump("SQLerror", $this->mysqli->error, \logger::LEVEL_ERROR);
                if($this->mysqli->error == "MySQL server has gone away" || $this->mysqli->error == "Connection refused" ){
                    $reconnected = true;
                    $this->reconnect();
                }
            }
        }while($trys < 10 &&
         (
             $this->mysqli->error == "Deadlock found when trying to get lock; try restarting transaction" ||
             $this->mysqli->error == "MySQL server has gone away" ||
             $reconnected
         )
        );

        return $result;
    }

    public function escape( $string ){
        return $this->mysqli->real_escape_string( $string );
    }
}