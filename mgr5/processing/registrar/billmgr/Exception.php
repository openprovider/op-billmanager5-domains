<?php


namespace Billmgr;


class Exception extends \Exception {
    private $withTask = true;

    public function __construct($message = "", $code = 0, $withTask = true, $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->setWithTask( $withTask );
    }

    /**
     * @return bool
     */
    public function isWithTask() {
        return $this->withTask;
    }

    /**
     * @param bool $withTask
     */
    public function setWithTask($withTask) {
        $this->withTask = $withTask;
    }

}