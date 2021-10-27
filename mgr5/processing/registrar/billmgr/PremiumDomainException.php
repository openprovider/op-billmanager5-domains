<?php


namespace Billmgr;


use Domain;

class PremiumDomainException extends RegistrationUnavailableException {

    /**
     * PremiumDomainException constructor.
     * @param Domain $domain
     */
    public function __construct( $domain ) {
        parent::__construct();

        $this->setSubject("Регистрация доменного имени " . mb_strtoupper($domain->getName(), "utf-8") . " не выполнена");
        $this->setBody("Здравствуйте,\n\n\nРегистрация доменного имени " . mb_strtoupper($domain->getName(), "utf-8") . " не выполнена: домен является премиальным и стоимость его регистрации может отличаться от общей для ." . mb_strtoupper($domain->getTLD(), "utf-8") . ". Если вы заинтересованы в его приобритении, пожалуйста, сообщите нам об этом в рамках данного запроса для уточнения условий регистрации.");
    }

}