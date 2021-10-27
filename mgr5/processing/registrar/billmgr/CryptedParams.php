<?php

require_once __DIR__ . "/../autoload.php";

use Billmgr\Api;
use Billmgr\FileCache;

class CryptedParams
{
    public $pubkey = '..';
    public $privkey = "";
    protected static $instance = null;

    /**
     * @return CryptedParams
     */
    public static function getInstance()
    {
        if (static::$instance == null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function __construct()
    {
        try {
            $paramlistCache = FileCache::getFileCache("paramlist");
            if ($paramlistCache->getValue() == null || $paramlistCache->getLastModifyTime() < strtotime("-1 minute")) {
                $config = Api::getConfig();
                try {
                    $paramlistCache->setValue($config->asXML(), false);
                } catch (\Exception $nothing) {
                }
            } else {
                $config = new \SimpleXMLElement($paramlistCache->getValue());
            }
        } catch (\Exception $nothing) {
            $config = Api::getConfig();
        }

        $path = (string)$config->xpath("/doc/elem/CryptKey")[0];// приватный ключ
        $fp = fopen(__DIR__ . "/../../../" . $path, "r");
        $priv_key = fread($fp, 8192);
        fclose($fp);
        $res = openssl_get_privatekey($priv_key);
        $this->privkey = $res;
    }

    public function encrypt($data)
    {
        if (openssl_public_encrypt($data, $encrypted, $this->pubkey)) {
            $data = base64_encode($encrypted);
        } else {
            throw new Exception('Unable to encrypt data. Perhaps it is bigger than the key size?');
        }

        return $data;
    }

    public function decrypt($data)
    {
        if (openssl_private_decrypt(base64_decode($data), $decrypted, $this->privkey)) {
            $data = $decrypted;
        } else {
            $data = '';
        }

        return $data;
    }
}