<?php


namespace Billmgr;


use Domain;

class DomainAlreadyRegistered extends RegistrationUnavailableException {

    /**
     * DomainAlreadyRegistered constructor.
     * @param Domain $domain
     */
    public function __construct($domain) {
        parent::__construct("Domain " . $domain->getName() . " already registered");

        $this->setSubject("Регистрация домена " . $domain->getName() . " не выполнена");
        $this->setBody("Здравствуйте,\r\n\r\nРегистрация домена " . $domain->getName() . " не выполнена: указанное доменное имя не является свободным.");
    }

}