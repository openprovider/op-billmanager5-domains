<?php
namespace Modules;

use Billmgr\ProlongUnavailableException;
use Domain;

/**
 * Class Registrar
 * Notes:
 *  - create_contact for non RU / РФ / etc zones should be translit() all strings
 *
 * @package Modules
 */
abstract class Registrar {
    use \curl;
    protected $auth_info = array();

    const RU_TLDs = array("xn--p1ai", "рф", "ru", "su");

    function __construct() {

    }

    protected function translit($string) {
        $converter = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',
            'г' => 'g',   'д' => 'd',   'е' => 'e',
            'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
            'и' => 'i',   'й' => 'y',   'к' => 'k',
            'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',
            'с' => 's',   'т' => 't',   'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'ts',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ы' => 'y',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'Ts',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ы' => 'Y',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',

            'ь' => '', 'Ь'=> '', 'ъ' => '', 'Ъ' => ''
        );
        return strtr($string, $converter);
    }


    protected function mb_ucwords($str) {
        $str = mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
        return $str;
    }

    /**
     * @param Domain $domain
     * @param string $whois
     * @return array
     */
    protected function getDomainWhoisNss($domain, $whois="" ){
        $nsList = array();

        if( $whois == "" ) {
            $whois = $domain->getWhois()["whois"];
        }

        $nsRowLabel = "Name Server";
        switch ($domain->getTLD()){
            case "su":
            case "рф":
            case "ru":
                $nsRowLabel = "nserver";
                break;
        }

        preg_match_all("/^\s*" . $nsRowLabel . "\s*:\s*(.*)/m", $whois, $matches );

        if( !empty($matches[1]) ){
            foreach ($matches[1] as $ns ){
                $nsParts = preg_split("/\s/", trim($ns));
                if( count($nsParts) > 1 ){
                    $nsList[] = array(
                        "ns" => mb_strtolower($nsParts[0], "utf-8"),
                        "ip" => mb_strtolower($nsParts[1], "utf-8")
                    );
                } else {
                    $nsList[] = array(
                        "ns" => mb_strtolower($nsParts[0], "utf-8")
                    );
                }
            }
        }

        return $nsList;
    }


    /**
     * @param \Domain $domain
     * @param null|string $expiresDate 2000-01-30
     * @throws ProlongUnavailableException
     */
    protected function checkProlongUnavailable($domain , $expiresDate = null ){
        if (in_array($domain->getTLD(), self::RU_TLDs)){
            if (!isset($expiresDate)) {
                $domainInfo = $this->info_domain($domain);
                if (isset($domainInfo["result"]) && $domainInfo["result"] == "success"
                    && isset($domainInfo["expire"])) {
                    $expiresDate = date("Y-m-d", $domainInfo["expire"]);
                } else {
                    \logger::write($domainInfo, \logger::LEVEL_ERROR);
                    throw new \Exception("Fail to get domain info");
                }
            }

            if (time() <= strtotime("-4 month", strtotime($expiresDate))) {
                \logger::dump("expiration date", $expiresDate, \logger::LEVEL_DEBUG);
                throw new ProlongUnavailableException($expiresDate, 503, $domain->getName());
            }
        }
    }
    /**
     * @return boolean
     */
    abstract public function test();

    /**
     * @param Domain $domain
     * @param $nss
     * @param $contact
     * @param int $period
     *
     * @return array
     */
    abstract public function reg_domain( $domain, $nss, $contact, $period=1 );

    /**
     * @param Domain $domain
     * @param int $period
     *
     * @return array
     */
    abstract public function renew_domain( $domain, $period=1 );

    /**
     * @param Domain $domain
     * @param $nss
     * @param $contact
     * @param $period
     * @param array $params
     *
     * @return array
     */
    abstract public function transfer_domain( $domain, $nss, $contact, $period, $params=array() );

    /**
     * @param \BillmanagerDomain $domain
     * @param array $nss
     *
     * @return array
     */
    abstract public function update_ns( $domain, $nss = array() );

    /**
     * @param Domain $domain
     *
     * @return array{"result", "status" ("ACTIVE", "NOT_DELEGATE", "P_REGISTER", "P_RENEW", "P_TRANSFER"), "expire", "nss"}
     */
    abstract public function info_domain( $domain );
}
