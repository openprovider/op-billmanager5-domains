<?php


namespace Billmgr;


class RestoreRequiredException extends ProlongUnavailableException {
    protected $not_available_for_prolong = "Здравствуйте,\n\n\nСрок преимущественного продления домена {DOMAIN} истек. Предварительно необходимо выполнить операцию восстановление, цена которой может отличаться. Если вы заинтересованы в востановлении домена, пожалуйста, сообщите нам об этом в рамках данного запроса для уточнения условий.";

    public function __construct($code, $domanename) {
        $this->not_available_for_prolong = str_replace("{DOMAIN}", $domanename, $this->not_available_for_prolong);
        parent::__construct(null, $code, $domanename);
    }

}