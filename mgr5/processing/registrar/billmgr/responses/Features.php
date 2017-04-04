<?php
namespace Billmgr\Responses;
use Billmgr;
class Features extends Billmgr\Response{

    private $features = array();
    private $authparams = array();


    public function __construct( $features, $authparams )
    {
        $this->setFeatures($features);
        $this->setAuthparams($authparams);
        parent::__construct(true);
    }

    /**
     * @return \SimpleXMLElement
     */
    protected function makeXMLParams(){
        $xml = $this->getEmptyXML();

        $xml->addChild("itemtypes")->addChild("itemtype")->addAttribute("name", "domain");
        $params = $xml->addChild("params");

        $authparams = $this->getAuthparams();
        foreach ($authparams as $paramKey => $isCrypted){
            $param = $params->addChild("param");
            $param->addAttribute("name", $paramKey);
            if($isCrypted){
                $param->addAttribute("crypted", "yes");
            }
        }

        $features = $this->getFeatures();
        $fts = $xml->addChild("features");
        foreach ($features as $feature){
            $fts->addChild("feature")->addAttribute("name",$feature);
        }

        return $xml;
    }


    /**
     * @return array
     */
    public function getFeatures()
    {
        return $this->features;
    }

    /**
     * @param array $features
     */
    public function setFeatures($features)
    {
        $this->features = $features;
    }

    /**
     * @return array
     */
    public function getAuthparams()
    {
        return $this->authparams;
    }

    /**
     * @param array $authparams
     */
    public function setAuthparams($authparams)
    {
        $this->authparams = $authparams;
    }




}