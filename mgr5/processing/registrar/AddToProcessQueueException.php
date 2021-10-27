<?php


class AddToProcessQueueException extends Exception
{
    private $nextstart;

    /**
     * AddToProcessQueueException constructor.
     * @param $nextstart
     */
    public function __construct($nextstart) {
        parent::__construct();
        $this->nextstart = $nextstart;
    }

    /**
     * @return mixed
     */
    public function getNextstart() {
        return $this->nextstart;
    }

    /**
     * @param mixed $nextstart
     */
    public function setNextstart($nextstart) {
        $this->nextstart = $nextstart;
    }

}