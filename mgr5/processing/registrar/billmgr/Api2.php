<?php
namespace Billmgr;

/**
 * @see http://doc.ispsystem.ru/index.php/%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BC%D0%BE%D0%B4%D1%83%D0%BB%D0%B5%D0%B9_%D1%80%D0%B5%D0%B3%D0%B8%D1%81%D1%82%D1%80%D0%B0%D1%82%D0%BE%D1%80%D0%BE%D0%B2#.D0.A4.D1.83.D0.BD.D0.BA.D1.86.D0.B8.D0.B8_BILLmanager
 */
use SimpleXMLElement;

class Api2 extends Api {


    public static function escapeShell( $string ){
        return "'" . str_replace( "'", "'\\''", $string) . "'";
    }

    /**
     * @param $method
     * @param array $params
     * @param string $out
     * @return SimpleXMLElement
     */
    public static function sendRequest($method, $params = array(), $out= "xml" ){
        $params_line = array();

        foreach ($params as $key => $value){
            $params_line[] ="$key=" . self::escapeShell( $value );
        }
        $command = \Config::$MGRCTLPATH . " -o $out -m billmgr " . $method . " " . implode( " ", $params_line );
        \logger::dump("CallBillmgrApi", $command, \logger::LEVEL_ISP_REQUEST);

        $trys = 30;
        do{
            $apiLocked = self::tryLock();
            if(!$apiLocked){
                sleep(1);
            }
        }while( !$apiLocked && $trys-- > 0 );

        $result = shell_exec( $command );

        if($apiLocked){
            self::unLock();
        }

        \logger::dump("Result", $result, \logger::LEVEL_ISP_RESULT);

        return new SimpleXMLElement($result);
    }
}