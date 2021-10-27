<?php

class InvalidProfileException extends \TransferException
{
    public function __construct($message = "", $domainName = null, $profileErrorList = null) {
        parent::__construct($message);
        if ($domainName !== null) {
            $this->setSubject("Трансфер домена {$domainName} не выполнен");
            $this->setBody("Здравствуйте,\n\n\n".
                "Трансфер доменного имени {$domainName} не выполнен т.к. введенные контактные данные не совпадают с данными ".
                "в реестре".$this->buildErrorsMessage($profileErrorList).". Заказ отменен, средства возвращены на баланс.\n\n\n".
                "Пожалуйста, исправьте существующий контакт или создайте новый, после чего сформируйте заказ на трансфер повторно."
            );
        }
    }

    /**
     * @param array|null $profileErrorList
     * @return string
     */
    private function buildErrorsMessage($profileErrorList) {
        if ($profileErrorList !== null) {
            $errorMessages = [];
            foreach (array_shift($profileErrorList) as $error) {
                $errorMessages[] = $error["value"]." (".$this->getFieldName($error["key"]).")";
            }
            return ": ".implode("; ", $errorMessages);
        }
        return "";
    }

    /**
     * @param string $fieldValue
     * @return string
     */
    private function getFieldName($fieldValue) {
        $xml = \Billmgr\Api::sendRequest("service_profile.edit", [], "devel");
        $messages = $xml->xpath("//messages[@lang='ru']/msg[@name='{$fieldValue}']");
        if (!isset($messages[0])) {
            $messages = $xml->xpath("//messages/msg[@name='{$fieldValue}']");
            if (!isset($messages[0])) {
                return $fieldValue;
            }
            return (string)$messages[0][0];
        }
        return (string)$messages[0][0];
    }
}