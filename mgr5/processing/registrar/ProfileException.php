<?php

class ProfileException extends Exception{
    private $profileErrorList = array();
    private $item = null;

    public function addProfileError($profileId, $profileParamName, $profileParamValue ){
        $this->profileErrorList[$profileId][] = array(
            "key" => $profileParamName,
            "value" => $profileParamValue
        );
    }

    public function getProfileErrorList(){
        return $this->profileErrorList;
    }

    /**
     * @return null
     */
    public function getItem() {
        return $this->item;
    }

    /**
     * @param null $item
     */
    public function setItem($item) {
        $this->item = $item;
    }

    /**
     * @param array $profileErrorList
     */
    public function setProfileErrorList($profileErrorList) {
        $this->profileErrorList = $profileErrorList;
    }

}