<?php

namespace Billmgr;

class RegistrarProcessQueue
{
    const REG_PROCESSES_QUEUE_TABLE = "registrar_proc_query";
    const REG_PROCESSES2PARAMS_TABLE = "registrar_proc2params";


    public static function escape($val) {
        return Database::getInstance()->mysqli->real_escape_string($val);
    }

    public static function addToQuery($moduleName, $nextstart, $rowParams = array() , $taskID=null) {
        $params = [];

        $retrys = 0;
        $haveReg_process = false;
        for ($i = 1; $i < count($rowParams); ++$i) {
            if( strpos( $rowParams[$i], "--" ) === 0 ){
                if($rowParams[$i] == "--retryId" ) {
                    $lastRetry = Database::getInstance()->escape($rowParams[$i+1]);
                    $qres = Database::getInstance()->query("SELECT `retrys` FROM " . self::REG_PROCESSES_QUEUE_TABLE . " WHERE `id` = '$lastRetry'");
                    if($qres && $row = $qres->fetch_assoc()) {
                        $retrys = (int) $row['retrys'] + 1;
                    }
                }
                if($rowParams[$i] == "--reg_process" ){
                    $haveReg_process = true;
                }
                $params[] = [$rowParams[$i], $rowParams[$i+1]];
                $i +=1;
            }  else {
                $params[] = [$rowParams[$i], ''];
            }
        }

        if(!$haveReg_process){
            $params[] = ["--reg_process" , "true"];
        }

        if($taskID !== null){
            $params[] = ["--taskID" , $taskID];
        }

        Database::getInstance()->query("INSERT INTO `" . self::REG_PROCESSES_QUEUE_TABLE . "` (`module`,`nextstart`,`status`, `retrys` )" . " VALUES ( '" .
                self::escape($moduleName) . "', '" . self::escape($nextstart) . "', 'ACTIVE', '$retrys')");
        if (!empty($rowParams)) {
            $result = mysqli_fetch_assoc(Database::getInstance()->query("SELECT `id` FROM `" . self::REG_PROCESSES_QUEUE_TABLE . "` WHERE `id` = LAST_INSERT_ID() LIMIT 1;"));
            $id = $result['id'];

            $query = "INSERT INTO `" . self::REG_PROCESSES2PARAMS_TABLE . "` (`process`,`param`,`value`)" . " VALUES ";
            foreach ($params as $v) {
                $query .= " ( '" . self::escape($id) . "', '" . self::escape($v[0]) .  "', '" . self::escape($v[1]) . "'),";
            }
            $query = rtrim($query, ',');
            Database::getInstance()->query($query);
        }

    }


    /**
     * @param null $before
     * @return \RegistrarProcess[] array
     */
    public static function getQueue($before = null) {
        if(isset($before)) {
            $before = "'" . self::escape($before) . "'";
        } else {
            $before = 'NOW()';
        }
        $table = self::REG_PROCESSES_QUEUE_TABLE;
        $tableParams = self::REG_PROCESSES2PARAMS_TABLE;
        $query = "SELECT `$table`.`id` AS id, `$table`.`module` AS module, `$table`.`retrys` AS retrys, `$tableParams`.`param` AS param, `$tableParams`.`value` AS value " .
            "FROM `$table` LEFT JOIN `$tableParams` ON `$tableParams`.process = `$table`.id WHERE " .
            "`$table`.`nextstart` <= $before AND `$table`.`status` = 'ACTIVE'";
        $result = Database::getInstance()->query($query);

        $array = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if(!isset($array[$row['id']])) {
                $array[$row['id']] = new \RegistrarProcess($row['id'], $row['module'], (int)$row['retrys']);
                //$array[$row['id']]->setTaskID($row["taskID"]);
            }
            $array[$row['id']]->addParam($row['param']);
            $array[$row['id']]->addParam($row['value']);
            /*
            if($row['param'] == '--item'){
                $array[$row['id']]->setItem($row['value']);
            }
            if($row['param'] == '--command'){
                $array[$row['id']]->setCommand($row['value']);
            }
            */
        }

        $keys = array_keys($array);
        foreach ($keys as &$key) {
            $key = "'" . self::escape($key) . "'";
        }
        $keys = implode($keys,',');

        return $array;
    }

    public static function setProcessesStatus($ids, $status) {
        $table =  self::REG_PROCESSES_QUEUE_TABLE;
        foreach ($ids as &$id) {
            $id = "'" . self::escape($id) . "'";
        }
        $status = self::escape($status);

        if( !empty($ids) ) {
            $ids = implode(',', $ids);
            Database::getInstance()->query("UPDATE `$table` SET `status` = '$status' WHERE `id` IN ($ids)");
        }
    }

}