<?php

namespace Billmgr\Tlds;
use Domain;

interface Tldcheck {
    /**
     * @param \SimpleXMLElement $contact
     * @throws \Exception
     * @return bool
     */
    public function check($contact);
}