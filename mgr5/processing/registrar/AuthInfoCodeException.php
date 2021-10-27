<?php

use \Billmgr\Database;

class AuthInfoCodeException extends \TransferException
{
    public function __construct($message = "", $domainName = null) {
        parent::__construct($message);
        $this->setSubject("Трансфер домена {$domainName} не выполнен");
        $this->setBody("Здравствуйте,\n\n\n".
            "Трансфер доменного имени {$domainName} не выполнен, т.к. введенный вами код авторизации (AuthInfo-код) неверный. ".
            "Заказ отменен, средства возвращены на баланс.\n\n\n".
            "Пожалуйста, обратитесь к текущему регистратору за разъяснением причины проблемы. ".
            "После получения корректного кода авторизации (AuthInfo-код) создайте заказ на трансфер повторно."
        );
    }
}