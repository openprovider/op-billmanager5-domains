<?php


use Billmgr\Api;

class BillmanagerDomain extends Domain
{
    protected $extendedFields;
    protected $item;
    protected $processingmodule;
    protected $service_profiles = array();




    /**
     * @return mixed
     */
    public function getProcessingmodule() {
        return $this->processingmodule;
    }

    /**
     * @param mixed $processingmodule
     */
    public function setProcessingmodule($processingmodule) {
        $this->processingmodule = $processingmodule;
    }

    /**
     * @return mixed
     */
    public function getServiceProfiles() {
        return $this->service_profiles;
    }

    public function addServiceProfile($sp){
        $this->service_profiles[] =$sp;
    }

    /**
     * @param mixed $service_profiles
     */
    public function setServiceProfiles($service_profiles) {
        $this->service_profiles = $service_profiles;
    }

    /**
     * @return mixed
     */
    public function getItem() {
        return $this->item;
    }

    /**
     * @param mixed $item
     */
    public function setItem($item) {
        $this->item = $item;
    }


    /**
     * @return array
     */
    public function getExtendedFields()
    {
        return $this->extendedFields;
    }



    /**
     * @param array $extendedFields
     */
    public function setExtendedFields($extendedFields)
    {
        $this->extendedFields = $extendedFields;
    }

    public function  getExtraField($key){
        return isset($this->extendedFields[$key]) && trim($this->extendedFields[$key]) != "" ? $this->extendedFields[$key] : null;
    }

    public function setExtraField($item , $key, $value){
        $params = array();
        $params[$key] = $value;
        Api::setServiceParam( $item, $params );
    }
    public function setExtraFields($item , $params){
        Api::setServiceParam( $item, $params );
    }
}