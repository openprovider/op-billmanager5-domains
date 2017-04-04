<?php
namespace Billmgr\Responses;
use Billmgr;
class ContactVerify extends Billmgr\Response{

    private $fileIds = array();


    public function __construct( $fileIds = array() )
    {
        $this->setFileIds($fileIds);
        parent::__construct(true);
    }

    /**
     * @return array
     */
    public function getFileIds()
    {
        return $this->fileIds;
    }

    /**
     * @param array $fileIds
     */
    public function setFileIds($fileIds)
    {
        $this->fileIds = $fileIds;
    }

    /**
     * @return \SimpleXMLElement
     */
    protected function makeXMLParams(){
        $xml = $this->getEmptyXML();
        $response = $xml->addChild("response");


        $ids = $this->getFileIds();
        foreach ($ids as $id){
            $fileResult = $response->addChild("file", "ok");
            $fileResult->addAttribute( 'id', $id );
        }

        return $xml;
    }



}