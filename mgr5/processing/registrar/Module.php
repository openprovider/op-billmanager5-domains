<?php

use Billmgr\Api;
use Billmgr\DBApi;
use Billmgr\Request;

class Module
{
    public static function run( $argv )
    {
        if( \logger::$oldfiles_dir == "" ) {
            \logger::$oldfiles_dir = "logs";
        }
        \logger::$use_gzcompress = true;
        \logger::$maxsize = 100;
        Billmgr\Request::init($argv);
        Billmgr\Cache::init( Config::$REGNAME );

        logger::dump("INPUT_REQUEST", $argv, logger::LEVEL_INFO);
        $registrar = new Billmgr\Registrar();
        try
        {
            $methodname = Billmgr\Request::getInstance()->getCommand();
            if (is_callable(array($registrar, $methodname)))
            {
                /* @var Billmgr\Response $result */
                $result = $registrar->$methodname();

                if(Billmgr\Request::getInstance()->getParam("reg_process") !== null && $result->isSuccess() ){
                    if(Billmgr\Request::getInstance()->getParam("taskID") !== null ){
                        $taskID = Billmgr\Request::getInstance()->getParam("taskID");
                        Billmgr\Api::deleteTask($taskID);
                    }
                    if($methodname == "update_ns"){
                        Api::sendRequest("service.saveparam", array(
                            "elid" =>  Billmgr\Request::getInstance()->getItem(),
                            "name" => "ns_update_error",
                            "value" => "",
                            "crypted" => "off",
                            "sok" => "ok",
                        ));
                    }
                }
            }else {
                throw new Exception("Method '" . $methodname . "' not found");
            }
        }catch(\ProfileException $profileException){
            $itemInfo = Billmgr\DBApi::getDomainInfo(Billmgr\Request::getInstance()->getItem());
            if (Billmgr\Request::getInstance()->getRunningoperation() != null &&
                Billmgr\Request::getInstance()->getCommand() != "open"
            ) {
                Billmgr\Api::setManualRunningOperation(Billmgr\Request::getInstance()->getRunningoperation());
            }

            foreach ( $profileException->getProfileErrorList() as $profileId => $errorList ){
                $profileType = "owner";
                foreach ($itemInfo as $key => $value ){
                    if( strpos($key,"service_profile") === 0 && (string)$value == $profileId ){
                        $profileType = preg_replace("/^service_profile_/", "", (string)$key);
                    }
                }
                foreach ($errorList as $errorParams) {
                    Billmgr\Api::setProfileError(
                        Billmgr\Request::getInstance()->getItem(),
                        $profileType,
                        $errorParams["key"],
                        $errorParams["value"]
                    );
                }
            }

            if( Billmgr\Request::getInstance()->getCommand() == "transfer" && count(Billmgr\Database::getInstance()->getProfileWarnings((string)$itemInfo->service_profile_owner)) != 0 ){
                \Billmgr\Api::setAutoRunningOperation( Billmgr\Request::getInstance()->getRunningoperation());
            }

            $result = new Billmgr\Responses\Error($profileException);
        }catch(\TemporaryException $ex){
            \logger::write("TemporaryException " . $ex->getCode() . " " . $ex->getMessage(), \logger::LEVEL_ERROR);
            $taskID = Billmgr\Request::getInstance()->getParam("taskID") ;
            if( Billmgr\Request::getInstance()->getRunningoperation() != null ){
                \Billmgr\Api::setAutoRunningOperation( Billmgr\Request::getInstance()->getRunningoperation());
            }

            if( $ex->isWithTask() && $taskID == null ){
                $taskCreated = false;
                if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                    /*$moduleInfo = Billmgr\Api::getModuleInfo($registrar->getModuleId());
                    Billmgr\DBApi::runningOperationError(
                        Billmgr\Request::getInstance()->getRunningoperation(),
                        $moduleInfo,
                        $ex
                    );*/
                    try {
                        $elem = Billmgr\Api::createOpeartionTask(
                            Billmgr\Request::getInstance()->getRunningoperation(),
                            Billmgr\Request::getInstance()->getCommand(),
                            Billmgr\Request::getInstance()->getItem()
                        );

                        $taskID = isset($elem->xpath("//id")[0]) ? (string)$elem->xpath("//id")[0] : null;
                        $taskCreated = true;
                    } catch (Exception $t) {
                    };

                    Billmgr\Api::setAutoRunningOperation( Billmgr\Request::getInstance()->getRunningoperation() );
                }
                if( !$taskCreated ) {
                    $elem = Billmgr\Api::createTask(36757,
                        "TemporaryException
                    Command: " . Billmgr\Request::getInstance()->getCommand() . "\n" .
                        (Billmgr\Request::getInstance()->getItem() != "" ? "Item: " . Billmgr\Request::getInstance()->getItem() . "\n" : "") .
                        "Module: " . \Config::$REGNAME . "\n" .
                        "Code: " . $ex->getCode() . "\n" .
                        "Reason: " . $ex->getMessage() . "\n" .
                        "Trace: " . $ex->getTraceAsString() . "\n\n" .
                        "LogFile: " . logger::$filename
                    );
                    $taskID =  isset($elem->xpath("//param[@name='id']")[0]) ? (string)$elem->xpath("//id")[0] : null ;
                }

            }
            if( $ex->getRetryTime() != null ){
                \Billmgr\RegistrarProcessQueue::addToQuery(Config::$REGNAME, date("Y-m-d H:i:s", $ex->getRetryTime()), $argv , $taskID);
            }
            $result = new Billmgr\Responses\Error($ex);
        }catch(\ClientException $ex){
            if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                Billmgr\Api::setManualRunningOperation(Billmgr\Request::getInstance()->getRunningoperation());
            }

            $moduleInfo = Billmgr\DBApi::getModuleInfo($registrar->getModuleId());
            Billmgr\DBApi::runningOperationError(
                Billmgr\Request::getInstance()->getRunningoperation(),
                $moduleInfo,
                $ex
            );
            $itemInfo = Billmgr\DBApi::getDomainInfo(Billmgr\Request::getInstance()->getItem());

            if (!isset($itemInfo->account) || $itemInfo->account == "" || (trim($ex->getMessage()) == "" && trim($ex->getBody()) == "") ) {
                $moduleInfo = Billmgr\DBApi::getModuleInfo($registrar->getModuleId());

                if (!empty($moduleInfo) &&
                    $moduleInfo["department"] != ""
                ) {
                    Billmgr\Api::createTask($moduleInfo["department"],
                        "ClientException" .
                        "Command: " . Billmgr\Request::getInstance()->getCommand() . "\n" .
                        (Billmgr\Request::getInstance()->getItem() != "" ? "Item: " . Billmgr\Request::getInstance()->getItem() . "\n" : "") .
                        "Module: #" . $moduleInfo["id"] . " " . $moduleInfo["name"] . "\n" .
                        "Code: " . $ex->getCode() . "\n" .
                        "Reason: " . $ex->getMessage() . "\n" .
                        "Trace: " . $ex->getTraceAsString() . "\n\n" .
                        "LogFile: " . logger::$filename
                    );
                }
            } else {
                $priceInfo = Billmgr\DBApi::getPriceInfo(  (string)$itemInfo->pricelist );
                Billmgr\Api::createClientTicket(
                    $itemInfo->account,
                    Config::$NOTICE_USER,
                    Billmgr\Request::getInstance()->getItem(),
                    $moduleInfo["department"],
                    $ex->getSubject() == null ? "В процессе обработки операции произошла ошибка" : $ex->getSubject(),
                    $ex->getBody() == null ?
                        "В процессе обработки операции по вашему заказу #" . Billmgr\Request::getInstance()->getItem() . " произошла следующая ошибка: \n\n" . $ex->getMessage() . " \n\nПожалуйста, исправьте ошибку и сообщите нам ответом на данный запрос для перезапуска операции."
                        : $ex->getBody(),
                    isset($priceInfo['project']) && (string)$priceInfo['project'] != ""? (string)$priceInfo['project'] : 1
                );
            }
            \logger::write($ex->getMessage(), \logger::LEVEL_ERROR);
            $result = new Billmgr\Responses\Error($ex);
        } catch (\Exception $ex){

            if (Billmgr\Request::getInstance()->getRunningoperation() != null && $ex->getMessage() != "Bad reply" && $ex->getCode() != 503) {
                Billmgr\Api::setManualRunningOperation(Billmgr\Request::getInstance()->getRunningoperation());
            }

            $moduleInfo = Billmgr\DBApi::getModuleInfo($registrar->getModuleId());
            if (
                Billmgr\Request::getInstance()->getCommand() == "transfer" ||
                $ex instanceof \Billmgr\Exception && !$ex->isWithTask()
            ) {
                if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                    Billmgr\DBApi::runningOperationError(
                        Billmgr\Request::getInstance()->getRunningoperation(),
                        $moduleInfo,
                        $ex
                    );


                    Billmgr\Api::setAutoRunningOperation( Billmgr\Request::getInstance()->getRunningoperation() );
                }
            } else {

                if (!empty($moduleInfo) &&
                    $moduleInfo['department'] != ""
                ) {
                    $taskCreated = false;
                    if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                        Billmgr\DBApi::runningOperationError(
                            Billmgr\Request::getInstance()->getRunningoperation(),
                            $moduleInfo,
                            $ex
                        );
                        try {
                            Billmgr\Api::createOpeartionTask(
                                Billmgr\Request::getInstance()->getRunningoperation(),
                                Billmgr\Request::getInstance()->getCommand(),
                                Billmgr\Request::getInstance()->getItem()
                            );
                            $taskCreated = true;
                        } catch (Exception $t) {
                        };
                    }

                    if (!$taskCreated) {
                        Billmgr\Api::createTask($moduleInfo['department'],
                            "Command: " . Billmgr\Request::getInstance()->getCommand() . "\n" .
                            (Billmgr\Request::getInstance()->getItem() != "" ? "Item: " . Billmgr\Request::getInstance()->getItem() . "\n" : "") .
                            "Module: #" . $moduleInfo['id'] . " " . $moduleInfo['name'] . "\n" .
                            "Code: " . $ex->getCode() . "\n" .
                            "Reason: " . $ex->getMessage() . "\n" .
                            "Trace: " . $ex->getTraceAsString() . "\n\n" .
                            "LogFile: " . logger::$filename
                        );
                    }
                }
            }
            \logger::write($ex->getMessage(), \logger::LEVEL_ERROR);
            $result = new Billmgr\Responses\Error($ex);
        }
        logger::dump("OUTPUT_RESPONSE", $result->getXMLString(), logger::LEVEL_INFO);

        logger::close();
        echo $result->getXMLString();
    }
}