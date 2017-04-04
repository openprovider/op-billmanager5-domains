<?php

class logger{

    const LEVEL_OFF = 0;
    const LEVEL_ERROR = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_INFO = 171;
    const LEVEL_DEBUG = 508;

    /*
     * LogLevel for ISP functions
     */
    const LEVEL_ISP_OFF = -25;
    const LEVEL_ISP_REQUEST = 8;
    const LEVEL_ISP_RESULT = 16;
    const LEVEL_ISP_ALL = 24;

    /*
     * LogLevel for REMOTE functions
     */
    const LEVEL_REMOTE_OFF = -97;
    const LEVEL_REMOTE_REQUEST = 32;
    const LEVEL_REMOTE_RESULT = 64;
    const LEVEL_REMOTE_ALL = 96;


    /*
     * LogLevel for SQL functions
     */
    const LEVEL_SQL_OFF = -385;
    const LEVEL_SQL_REQUEST = 128;
    const LEVEL_SQL_RESULT = 256;
    const LEVEL_SQL_ALL = 384;

    public static $filename=  "script.log";          // Имя файла для логов
    public static $maxsize=10;                  // Максимальный размер файла до запуска ротации
    public static $maxlogfiles=10;              // Максимальное колличество файлов логов в ротацие
    public static $rotate=true;                 // Использовать или нет ротация
    public static $oldfiles_template=".{D}.gz"; // Шаблон названия логов в ротацие, {N} заменяется на номер
    public static $errors=array();              // Ошибки логера
    public static $use_gzcompress = false;
    public static $oldfiles_dir = "";
    public static $loglevel = 3;


    private static $logfile=null;
    private static $curold=null;
    private static $rand=null;

    public static function getRand(){
        if( self::$rand == null) {
            self::$rand = "RND|" . str_pad(rand(1, 99999), 5, "0");
        }

        return self::$rand;
    }

    private static function OpenLogFile()
    {
        logger::$logfile = fopen(logger::$filename,"a");
    }

    private static function WriteToLog($message)
    {
        if(logger::$logfile==null)
            logger::OpenLogFile();

        // echo $message;
        fwrite(logger::$logfile,$message);
    }

    private static function getLogLevelName( $level ){
        $name = "UNDEF";
        switch ($level){
            case self::LEVEL_ERROR:
                $name = "ERROR";
                break;
            
            case self::LEVEL_WARNING:
                $name = "WARNING";
                break;
            
            case self::LEVEL_INFO:
                $name = "INFO";
                break;

            case self::LEVEL_DEBUG:
                $name = "DEBUG";
                break;


            case self::LEVEL_ISP_REQUEST:
                $name = "ISP_REQUEST";
                break;
            case self::LEVEL_ISP_RESULT:
                $name = "ISP_RESULT";
                break;


            case self::LEVEL_REMOTE_REQUEST :
                $name = "REMOTE_REQUEST";
                break;
            case self::LEVEL_REMOTE_RESULT:
                $name = "REMOTE_RESULT";
                break;


            case self::LEVEL_SQL_REQUEST :
                $name = "SQL_REQUEST";
                break;
            case self::LEVEL_SQL_RESULT:
                $name = "SQL_RESULT";
                break;
        }
        
        return $name;
    }

    public static function isLoggable( $logLevel ){
        if( ($logLevel & 7) != 0 ){
            return  (self::$loglevel & 7) >= ($logLevel & 7);
        }

        return (self::$loglevel | $logLevel) == self::$loglevel;
    }
    
    /**
     * Записать в лог сообщение
     *
     * @param string $message текст сообщения для записи в лог
     * @param int $level уровень оповещения
     */
    public static function write($message, $level = null)
    {
        if(self::$loglevel != self::LEVEL_OFF) {
            if ($level == null) {
                $level = self::$loglevel;
            }
            if (self::isLoggable( $level )) {
                logger::WriteToLog("\n[" . date("Y-m-d H:i:s") . "]: " . self::getRand() . " " . self::getLogLevelName($level) . " " . $message);
            }
        }
    }

    /**
     * Записать в лог JSON представление переменной
     *
     * @param string $name наименование переменной
     * @param string|object $object значение
     */
    public static function dump($name, $object, $level = null)
    {
        logger::write($name." ". (is_string($object) ? $object : json_encode($object)), $level);
    }

    /**
     * Завершение сеанса логирования, закрывается файл, производится ротация по необходимости, меняются права на файлы
     */
    public static function close()
    {
        fclose(logger::$logfile);

        if(logger::$rotate)
            logger::rotation();

        logger::SetPermissions();
    }

    private static function rotation()
    {
        if(filesize(logger::$filename) > logger::$maxsize * 1024*1024) {

            if(self::$use_gzcompress){
                rename(logger::$filename, logger::$filename . "_compressing");
                if(self::gzCompressFile( logger::$filename . "_compressing", 9 )){
                    unlink(logger::$filename . "_compressing");
                }
            } else {
                rename(logger::$filename, logger::GetOldName());
            }
        }
    }

    private static function gzCompressFile($source, $level = 9){
        $dest = logger::GetOldName();
        $mode = 'wb' . $level;
        $error = false;
        if ($fp_out = gzopen($dest, $mode)) {
            if ($fp_in = fopen($source,'rb')) {
                while (!feof($fp_in))
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                fclose($fp_in);
            } else {
                $error = true;
            }
            gzclose($fp_out);
        } else {
            $error = true;
        }
        if ($error)
            return false;
        else
            return true;
    }

    private static function SetPermissions()
    {
        if(file_exists(logger::$filename))
            chmod(logger::$filename,0660);
        if(file_exists(logger::GetOldName()))
            chmod(logger::GetOldName(),0660);
    }


    private static function GetOldName()
    {
        if(logger::$curold !== null)
            return logger::$curold;

        if(strpos(logger::$oldfiles_template,"{D}")!==false){

            $tmp = str_replace("{D}",date("Y-m-d_H:i:s"),logger::$oldfiles_template);


            if(self::$oldfiles_dir != ""){
                if(!is_dir(dirname(logger::$filename) ."/" . self::$oldfiles_dir))
                    mkdir( dirname(logger::$filename) ."/" . self::$oldfiles_dir);
                $new_name = dirname(logger::$filename) ."/" . self::$oldfiles_dir . "/" . basename(logger::$filename) . $tmp;
            } else {
                $new_name = logger::$filename . $tmp;
            }

            if(!file_exists($new_name)){
                logger::$curold = $new_name;
                return logger::$curold;
            }
            logger::$oldfiles_template = $new_name . "{N}";
        } else {
            if (strpos(logger::$oldfiles_template, "{N}") === false) {
                logger::$curold = logger::$filename . logger::$oldfiles_template;
                return logger::$curold;
            }
        }

        $tmp = str_replace("{N}","",logger::$oldfiles_template);

        if(!file_exists(logger::$filename . $tmp)) {
            logger::$curold = logger::$filename . $tmp;
            return logger::$curold;
        }

        $i=1;
        while($i < logger::$maxlogfiles &&  file_exists(logger::$filename . str_replace("{N}",$i,logger::$oldfiles_template)))
            $i++;

        if(file_exists(logger::$filename . str_replace("{N}",$i,logger::$oldfiles_template)))
            logger::$errors[]="Logs rotation pool is full";

        logger::$curold = logger::$filename . str_replace("{N}",$i,logger::$oldfiles_template);
        return logger::$curold;
    }
}