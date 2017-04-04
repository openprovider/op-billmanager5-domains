<?php
namespace Modules;


abstract class Registrar {
    use \curl;
    protected $auth_info = array();

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
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        );
        return strtr($string, $converter);
    }





    /**
     * @return boolean
     */
    abstract public function test();
    
    /**
     * @param \Domain $domain
     * @param $nss
     * @param $contact
     * @param int $period
     *
     * @return array
     */
    abstract public function reg_domain( $domain, $nss, $contact, $period=1 );

    /**
     * @param \Domain $domain
     * @param int $period
     *
     * @return array
     */
    abstract public function renew_domain( $domain, $period=1 );

    /**
     * @param \Domain $domain
     * @param $nss
     * @param $contact
     * @param $period
     * @param array $params
     *
     * @return array
     */
    abstract public function transfer_domain( $domain, $nss, $contact, $period, $params=array() );

    /**
     * @param \Domain $domain
     * @param array $nss
     *
     * @return array
     */
    abstract public function update_ns( $domain, $nss = array() );

    /**
     * @param \Domain $domain
     *
     * @return array{"result", "status" ("ACTIVE", "NOT_DELEGATE", "P_REGISTER", "P_RENEW", "P_TRANSFER"), "expire", "nss"}
     */
    abstract public function info_domain( $domain );
}
