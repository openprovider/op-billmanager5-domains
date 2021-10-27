<?php
namespace Billmgr\Responses;
use Billmgr;

class Balance extends Billmgr\Response{

    private $currency = "";
    private $balance = "";
    private $credit = "";
    private $extensions = array();


    public function __construct( $balanceResult ) {
        $this->setBalance( $balanceResult["amount"] );
        $this->setCurrency( $balanceResult["currency"] );
        $this->setCredit( $balanceResult["credit"] );
        $this->setExtensions( $balanceResult["extensions"] );
        parent::__construct(true);
    }

    /**
     * @return string
     */
    public function getCredit() {
        return $this->credit;
    }

    /**
     * @param string $credit
     */
    public function setCredit($credit) {
        $this->credit = $credit;
    }

    /**
     * @return string
     */
    public function getCurrency() {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    /**
     * @return string
     */
    public function getBalance() {
        return $this->balance;
    }

    /**
     * @param string $balance
     */
    public function setBalance($balance) {
        $this->balance = $balance;
    }

    /**
     * @return array
     */
    public function getExtensions() {
        return $this->extensions;
    }

    /**
     * @param array $extensions
     */
    public function setExtensions($extensions) {
        $this->extensions = $extensions;
    }


    protected function makeXMLParams() {
        $xml = $this->getEmptyXML();

        $xml->addChild("balance", $this->getBalance());
        $xml->addChild("currency", $this->getCurrency());
        $xml->addChild("credit", $this->getCredit());
        $extensions = $xml->addChild("extensions");

        foreach ($this->getExtensions() as $extensionInfo ){
            $extension = $extensions->addChild("extension");
            foreach ($extensionInfo as $key => $value ){
                $extension->addChild($key, $value);
            }
        }

        return $xml;
    }
}