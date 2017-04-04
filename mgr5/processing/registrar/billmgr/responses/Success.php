<?php
namespace Billmgr\Responses;
use Billmgr;

class Success extends Billmgr\Response{
    
    public function __construct(){
        parent::__construct(true);
    }
}