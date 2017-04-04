<?php

class Module
{
    public static function run( $argv )
    {
        \logger::$oldfiles_dir = "logs";
        \logger::$use_gzcompress = true;
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
            }

            else {
                throw new Exception("Method '" . $methodname . "' not found");
            }
        } catch(\ClientException $ex){
            if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                Billmgr\Api::setManualRunningOperation(Billmgr\Request::getInstance()->getRunningoperation());
            }
            $moduleInfo = Billmgr\Api::getModuleInfo($registrar->getModuleId());
            $itemInfo = Billmgr\Api::getDomainInfo(Billmgr\Request::getInstance()->getItem());

            if (!isset($itemInfo->account) || $itemInfo->account == "" || trim($ex->getMessage()) == "") {
                $moduleInfo = Billmgr\Api::getModuleInfo($registrar->getModuleId());
                if ($moduleInfo instanceof \SimpleXMLElement &&
                    $moduleInfo->department != ""
                ) {
                    Billmgr\Api::createTask($moduleInfo->department,
                        "ClientException" .
                        "Command: " . Billmgr\Request::getInstance()->getCommand() . "\n" .
                        (Billmgr\Request::getInstance()->getItem() != "" ? "Item: " . Billmgr\Request::getInstance()->getItem() . "\n" : "") .
                        "Module: #" . $moduleInfo->id . " " . $moduleInfo->name . "\n" .
                        "Code: " . $ex->getCode() . "\n" .
                        "Reason: " . $ex->getMessage() . "\n" .
                        "Trace: " . $ex->getTraceAsString() . "\n\n" .
                        "LogFile: " . logger::$filename
                    );
                }
            } else {
                $priceInfo = Billmgr\Api::getPriceInfo(  (string)$itemInfo->pricelist );
                Billmgr\Api::createClientTicket(
                    $itemInfo->account,
                    Config::$NOTICE_USER,
                    Billmgr\Request::getInstance()->getItem(),
                    $moduleInfo->department,
                    $ex->getSubject() == null ? "В процессе обработки операции произошла ошибка" : $ex->getSubject(),
                    $ex->getBody() == null ?
                        "В процессе обработки операции по вашему заказу #" . Billmgr\Request::getInstance()->getItem() . " произошла следующая ошибка: \n\n" . $ex->getMessage() . " \n\nПожалуйста, исправьте ошибку и сообщите нам ответом на данный запрос для перезапуска операции."
                        : $ex->getBody(),
                    isset($priceInfo->project) && (string)$priceInfo->project != ""? (string)$priceInfo->project : 1
                );
            }
            \logger::write($ex->getMessage(), \logger::LEVEL_ERROR);
        } catch (\Exception $ex){
            if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                Billmgr\Api::setManualRunningOperation(Billmgr\Request::getInstance()->getRunningoperation());
            }
            if (Billmgr\Request::getInstance()->getCommand() == "transfer") {

            } else {
                $moduleInfo = Billmgr\Api::getModuleInfo($registrar->getModuleId());
                if ($moduleInfo instanceof \SimpleXMLElement &&
                    $moduleInfo->department != ""
                ) {
                    $taskCreated = false;
                    if (Billmgr\Request::getInstance()->getRunningoperation() != null) {
                        Billmgr\Api::runningOperationError(
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
                        Billmgr\Api::createTask($moduleInfo->department,
                            "Command: " . Billmgr\Request::getInstance()->getCommand() . "\n" .
                            (Billmgr\Request::getInstance()->getItem() != "" ? "Item: " . Billmgr\Request::getInstance()->getItem() . "\n" : "") .
                            "Module: #" . $moduleInfo->id . " " . $moduleInfo->name . "\n" .
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