<?php


namespace Billmgr;


class CustomProlongUnavailableException extends ProlongUnavailableException {

    public function __construct($customMessage, $code, $domanename) {
        parent::__construct(null, $code, $domanename);

        $this->setBody("Здравствуйте,\n\n\n" . $customMessage);
    }
}