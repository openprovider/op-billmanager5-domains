#!/usr/bin/php
<?php

require_once __DIR__ . "/../processing/registrar/autoload.php";
\logger::$filename = __DIR__ . "/../var/domainauthcode.log";
\logger::$loglevel = \logger::LEVEL_INFO;
\logger::$rotate = true;

$xml = new SimpleXMLElement(\Billmgr\Request::readStdIn());
$userInfo = Billmgr\Api::getUserInfo($_SERVER["AUTH_USER"]);
$accountInfo = Billmgr\Api::getAccountInfo( (string)$userInfo->account );


if( $xml["func"] == "domain.authcode" ){
    \logger::dump("INPUT",  $xml->asXML(), \logger::LEVEL_INFO);
    \logger::dump("SERVER",  $_SERVER, \logger::LEVEL_DEBUG);

    if(isset($_SERVER["PARAM_sok"]) &&  $_SERVER["PARAM_sok"] == "ok" && $_SERVER["PARAM_clicked_button"]=="ok") {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<doc><ok/></doc>");
    } else {
        $elements = array();
        if (!empty($_SERVER["QUERY_STRING"])) {
            parse_str($_SERVER["QUERY_STRING"], $urlparams);
            $elements = explode(",", $urlparams["elid"]);
        }

        if($elements[0] == $_SERVER["PARAM_elid"]){
            if (empty($elements)) {
                $xml = makeError($xml, "Param 'elid' cannot be empty!");
            } else {
                $domains = array();
                foreach ($elements as $elem) {
                    $domainInfo = Billmgr\Api::getDomainInfo(trim($elem));

                    if ( ((string)$domainInfo->account == (string)$accountInfo->id && (string)$domainInfo->account!="")  || $_SERVER["AUTH_LEVEL"] == 29) {
                        $domains[] = $domainInfo;
                    }
                }

                if (empty($domains)) {
                    $xml = makeError($xml, "Param 'elid' cannot be empty!");
                } else {
                    $dList = array();
                    foreach ($domains as $domain) {
                        $moduleInfo = Billmgr\Api::getModuleInfo((string)$domain->processingmodule);

                        $defMessage = false;

                        if( $defMessage!== false ) {
                            addInfo($xml, (string)$domain->id, (string)$domain->domain, $defMessage);
                            $dList[] = (string)$domain->domain . ": " . $defMessage;
                        } else {

                            $command = __DIR__ . "/../processing/" . ((string)$moduleInfo->module) . " --command getauthcode --item " . ((string)$domain->id);
                            \logger::dump("Command", $command, \logger::LEVEL_INFO);
                            $processing_result = shell_exec($command);
                            \logger::dump("Result", $processing_result, \logger::LEVEL_INFO);
                            if ($processing_result == "") {
                                addInfo($xml, (string)$domain->id, (string)$domain->domain, "Generating authcode not supported");
                            } else {
                                try {
                                    $xmlResult = new \SimpleXMLElement($processing_result);

                                    if (!isset($xmlResult->authcode) || (string)$xmlResult->authcode == "") {
                                        if (isset($xmlResult->error) && isset($xmlResult->error->msg)) {
                                            addInfo($xml, (string)$domain->id, (string)$domain->domain, (string)$xmlResult->error->msg);
                                            $dList[] = (string)$domain->domain . ": " . (string)$xmlResult->error->msg;
                                        } else {
                                            addInfo($xml, (string)$domain->id, (string)$domain->domain, "Internal error on process");
                                            $dList[] = (string)$domain->domain . ": Internal error on process";
                                        }
                                    } else {

                                        addInfo($xml, (string)$domain->id, (string)$domain->domain, (string)$xmlResult->authcode);
                                        $dList[] = (string)$domain->domain . ": " . (string)$xmlResult->authcode;
                                    }
                                } catch (Exception $ex) {

                                    addInfo($xml, (string)$domain->id, (string)$domain->domain, "Internal error on process");
                                    $dList[] = (string)$domain->domain . ": Internal error on process";
                                }
                            }
                        }
                    }

                    if ($xml->messages instanceof SimpleXMLElement) {
                        $msg = $xml->messages->addChild("msg", htmlspecialchars( implode("\n", $dList) ));
                        $msg->addAttribute("name", "domains");
                    }
                }
            }
        }
    }

    \logger::dump("OUTPUT",  $xml->asXML(), \logger::LEVEL_INFO);
}
/**
 * @param \SimpleXMLElement $xml
 * @param $id
 * @param $domain
 * @param $authinfo
 */
function addInfo($xml, $id, $domain, $authinfo ){
    $list = $xml->xpath("/doc/list[@name='authcodelist']");
    if(!empty($list) && $list[0] instanceof \SimpleXMLElement) {
        $list = $list[0];
    } else {
        $list = $xml->addChild("list");
        $list->addAttribute("name", "authcodelist");
    }
    $elem = $list->addChild("elem");
    $elem->addChild("id", htmlspecialchars($id));
    $elem->addChild("domain", htmlspecialchars($domain));
    $elem->addChild("authinfo", htmlspecialchars($authinfo));
}
function makeError($xml, $message){
    foreach ($xml as $node) {
        $name = $node->getName();
        if ($name != "tparams" && $name != "elid") {
            unset($xml->$name);
        }
    }
    $error = $xml->addChild("error");
    $error->addAttribute( "type", "plugin" );
    $error->addAttribute( "object", "" );
    $error->addAttribute( "lang", "ru" );
    $error->addChild("msg", htmlspecialchars($message));

    return $xml;
}

echo $xml->asXML();