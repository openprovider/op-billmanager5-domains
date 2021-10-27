<?php
namespace Billmgr\Responses;
use Billmgr;

class DomainStatuses extends Billmgr\Response{

    private $statuses = array();


    public function __construct( $statuses ) {
        $this->setStatuses( $statuses );
        parent::__construct(true);
    }

    /**
     * @return array
     */
    public function getStatuses() {
        return $this->statuses;
    }

    /**
     * @param array $statuses
     */
    public function setStatuses($statuses) {
        $this->statuses = $statuses;
    }


    protected function makeXMLParams() {
        $xml = $this->getEmptyXML();

        foreach ($this->getStatuses() as $key => $values ){
            $child = $xml->addChild($key);
            foreach ( $values AS $value ){
                $child->addChild("status", $value);
            }
        }

        return $xml;
    }
}