<?php
namespace Billmgr\Responses;
use Billmgr;
class UploadExt extends Billmgr\Response{

    private $exts = array();


    public function __construct( $exts = array('jpg','pdf','png','gif') )
    {
        $this->setExts($exts);
        parent::__construct(true);
    }

    /**
     * @return array
     */
    public function getExts()
    {
        return $this->exts;
    }

    /**
     * @param array $exts
     */
    public function setExts($exts)
    {
        $this->exts = $exts;
    }

    /**
     * @return \SimpleXMLElement
     */
    protected function makeXMLParams(){
        $xml = $this->getEmptyXML();

        $exts = $this->getExts();
        foreach ($exts as $ext){
            $xml->addChild("ext", htmlspecialchars($ext));
        }

        return $xml;
    }



}