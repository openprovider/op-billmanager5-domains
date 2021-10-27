<?php

class TemporaryException extends Exception{
    private $withTask = true;
    private $retryTime = null;

    public function __construct($message = "", $code = 0, $withTask = true, Exception $previous = null) {
        $this->setWithTask( $withTask );
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return bool
     */
    public function isWithTask() {
        return $this->withTask;
    }

    /**
     * @param bool $withTask
     * @return TemporaryException
     */
    public function setWithTask($withTask) {
        $this->withTask = $withTask;
        return $this;
    }

    /**
     * @return null|int
     */
    public function getRetryTime() {
        return $this->retryTime;
    }

    /**
     * @param null $retryTime
     */
    public function setRetryTime($retryTime) {
        $this->retryTime = $retryTime;

        return $this;
    }


}