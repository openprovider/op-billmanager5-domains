<?php


namespace Billmgr;

use SimpleXMLElement;

require_once __DIR__ . "/CryptedParams.php";
require_once __DIR__ . "/Api.php";

class DBApi
{
    /**
     * @param $domain_id
     * @return \stdClass
     */
    public static function getDomainInfo( $domain_id ){
        $result = [];
        $id = Database::getInstance()->escape($domain_id);
        $domainQ = "SELECT id,pricelist,account,period,currency, status, createdate, expiredate,opendate, addonbool,
boolvalue,autosuspend, employeesuspend, abusesuspend, cost,costperiod,costdate,autoassign,specialstatus, scheduledclose, processingmodule
 FROM item WHERE id='$id' LIMIT 1";
        $qres = Database::getInstance()->query($domainQ);
        if($qres && ($row=$qres->fetch_assoc())) {
            $result = array_merge($result, $row);
        }
        $extendedF = array();
        $domainParamsQ = "SELECT intname, value FROM itemparam  WHERE item='$id'";
        $qres = Database::getInstance()->query($domainParamsQ);
        while ($qres && ($row = $qres->fetch_assoc())) {
            $result[$row["intname"]] = $row["value"];
            $extendedF[$row["intname"]] = $row["value"];
        }
        $result["extendedFields"] = $extendedF;

        $domainSPsQ = "SELECT service_profile, type FROM service_profile2item WHERE item = '$id'";
        $qres = Database::getInstance()->query($domainSPsQ);
        while ($qres && ($row = $qres->fetch_assoc())) {
            $result["service_profile_" . trim($row["type"])] = trim($row["service_profile"]);
        }

        $addonQ = "SELECT p.id, p.addontype, p.billtype, ifnull(i.addonlimit, p.addonlimit) AS addonlimit, 
p.compound, p.addonlimit AS pricelist_addonlimit, p.addonmin, ifnull(i.addonmaxtrial, p.addonmaxtrial) AS addonmaxtrial, 
IFNULL(i.addonlimittrial, p.addonlimittrial) AS addonlimittrial, p.scaletype AS scaletype, 
p.orderpolicy AS orderpolicy, p.roundtype AS roundtype, i.intvalue, i.fixedprices, p.measure,
 ifnull(i.addonbool, p.addonbool) AS addonbool, p.addonbool AS pricelist_addonbool, i.boolvalue,
  if (i.id IS NOT NULL, 1, 0) AS have_item, ifnull (pri.annually, pr.annually) AS period_cost,
   pr.setup,pr.annually AS pricelist_cost, ifnull(i.enumerationitem, p.enumerationitem) AS enumerationitem,
    p.enumerationitem as pricelist_addonenumerationitem, i.enumerationitem as enumerationitem_value,
     i.enumerationitem_addonenumerationitem as addonenumerationitem, i.id AS item, i.price,
     IFNULL(i.addonmax, p.addonmax) AS addonmax, p.addonstep, it.intname, p.enumeration,
     MAX(IF(i.pricelist = cp.id, cp.id, 0)) AS item_pricelist
     FROM pricelist p
     JOIN itemtype it ON it.id=p.itemtype
     LEFT JOIN pricelist pc ON pc.id = p.compound 
     LEFT JOIN pricelist cp ON cp.compound = p.id
     LEFT JOIN pricelistprice pp ON pp.pricelist=p.id and pp.currency='".Database::getInstance()->escape($result["currency"])."'
     LEFT JOIN price pr ON pr.id=pp.price
     LEFT JOIN item i ON i.parent='$id' AND i.pricelist=IFNULL(cp.id, p.id)
     LEFT JOIN price pri ON pri.id=i.price
     WHERE p.parent='".Database::getInstance()->escape($result["pricelist"])."' AND
     ((p.active = 'on' AND (p.compound IS NULL OR pc.active = 'on'))  
     OR (i.id IS NOT NULL AND (p.compound IS NOT NULL OR p.billtype=10 AND i.intvalue IS NOT NULL))) 
     AND p.compound IS NULL GROUP BY p.id ORDER BY IF(p.billtype = 5, 1, 0) ASC, IFNULL(p.orderpriority, it.orderpriority), p.id";
        $qres = Database::getInstance()->query($addonQ);
        while ($qres && ($row = $qres->fetch_assoc())) {
            $result["addon_" . trim($row["id"])] = trim($row["boolvalue"]);
        }

        $result["domain"] = (new \Domain($result["domain"]))->getName();

        $object = new \stdClass();
        foreach ($result as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    /**
     * @param $contact_id
     * @return array
     */
    public static function getContactInfo($contact_id)
    {
        $countries = Database::getInstance()->query("SELECT id, iso2 FROM country");
        $CC = [];
        while($countries && ($row = $countries->fetch_assoc())) {
            $CC[(string)$row["id"]] = $row["iso2"];
        }

        $q = "SELECT service_profile.id, service_profile.profiletype, service_profile.name, service_profile.account, service_profileparam.intname, service_profileparam.value FROM service_profile
                LEFT JOIN service_profileparam ON service_profileparam.service_profile = service_profile.id
                WHERE service_profile.id='" . Database::getInstance()->escape($contact_id) . "'";
        $qres = Database::getInstance()->query($q);

        $profile = [];
        while ($qres && ($row = $qres->fetch_assoc())) {
            foreach ($row as $k => $v) {
                if ($k != 'intname' && $k != 'value') {
                    $profile[$k] = $v;
                } elseif ($k == 'intname') {
                    $profile[$v] = $row["value"];
                }
            }
        }

        $out = array(
            "ctype" => $profile['profiletype'] == 2 ? "company" : "person",
            "profiletype" => (string)$profile['profiletype'],
        );

        $simpleReplaceFormat = array(
            "id", "name", "account", "company", "company_ru", "firstname", "firstname_ru", "middlename", "middlename_ru",
            "lastname", "lastname_ru", "email", "phone", "fax", "birthdate", "inn", "kpp", "la_state", "la_postcode",
            "la_city", "la_address", "pa_state", "pa_postcode", "pa_city", "pa_address", "mobile", "ogrn", "passport_org", "passport_date"
        );

        foreach ($simpleReplaceFormat as $paramname) {
            $out = self::formatContactProparty($out, $profile, $paramname);
        }
        $out["passport_series"] = (string)$profile['passport'];

        $out["iso2"] = $CC[(string)$profile["location_country"]];
        $out["iso2_postal"] = $CC[(string)$profile["postal_country"]];

        $qres = Database::getInstance()->query("SELECT * FROM service_profile2processingmodule WHERE service_profile='".Database::getInstance()->escape($out["id"])."'");

        while($qres && ($row = $qres->fetch_assoc())) {
            $out["external_id"][(string)$row['processingmodule']][] = array(
                "id" => (string)$row['externalid'],
                "type" => (string)$row['type'],
                "password" => isset($row['externalpassword']) ? (string)$row['externalpassword'] : null
            );
        }

        $out["raw"] = $profile;

        return $out;
    }

    private static function formatContactProparty($array, $profile, $proparty_name)
    {
        $profile_proparty_name = str_replace(array("_ru", "la_", "pa_"), array("_locale", "location_", "postal_"), $proparty_name);
        $array[$proparty_name] = (string)$profile[$profile_proparty_name];

        return $array;
    }

    /**
     * @param $filter
     * @return array
     */
    public static function getProcessingList($filter = [])
    {
        $additional = "";
        if (!empty($filter)) {
            $additional = " WHERE ";
            $implodes = [];
            foreach ($filter as $k => $v) {
                $implodes[] = " processingmodule." . Database::getInstance()->escape($k) . " = '" . Database::getInstance()->escape($v) . "' ";
            }
            $additional .= implode(" AND ", $implodes);
        }
        $query = "SELECT processingmodule.*, processingparam.intname, processingparam.value FROM processingmodule
LEFT JOIN processingparam ON processingparam.processingmodule = processingmodule.id

$additional";
        $qres = Database::getInstance()->query($query);
        $result = [];

        while ($qres && ($row = $qres->fetch_assoc())) {
            if (!isset($result[$row["id"]])) {
                $result[$row["id"]] = [];
            }
            foreach ($row as $key => $value) {
                if (trim($key) == 'intname') {
                    $result[$row["id"]][$value] = $row["value"];
                } else if ($key != 'value') {
                    $result[$row["id"]][$key] = $value;
                }
            }
        }

        $query = "SELECT processingmodule.id, processingcryptedparam.intname, processingcryptedparam.value FROM processingmodule
LEFT JOIN processingcryptedparam ON processingcryptedparam.processingmodule = processingmodule.id
$additional";
        $qres = Database::getInstance()->query($query);
        while ($qres && ($row = $qres->fetch_assoc())) {
            if (!isset($result[$row["id"]])) {
                $result[$row["id"]] = [];
            }
            $result[$row["id"]][$row["intname"]] = \CryptedParams::getInstance()->decrypt($row["value"]);
        }

        return $result;//
    }


    private static function getItem($table, $id)
    {
        $res = [];
        $query = "SELECT * FROM $table WHERE id=" . "'" . Database::getInstance()->escape($id) . "' LIMIT 1";
        $qres = Database::getInstance()->query($query);
        while ($qres && ($row = $qres->fetch_assoc())) {
            $res = $row;
        }
        return $res;
    }

    public static function getRunningOperation($operationId)
    {
        return static::getItem('runningoperation', $operationId);
    }

    /**
     * @param $itemtypeId
     * @return array
     */
    public static function getItemtype($itemtypeId)
    {
        return static::getItem('itemtype', $itemtypeId);
    }


    /**
     * @param $pricelistId
     * @return array
     */
    public static function getPriceInfo($pricelistId)
    {
        $q = "SELECT * FROM processingmodule2pricelist WHERE pricelist='" . Database::getInstance()->escape($pricelistId) . "'";
        $res = Database::getInstance()->query($q);
        $processingmodule = null;
        while ($res && ($row = $res->fetch_assoc())) {
            $processingmodule = $row["processingmodule"];
        }
        $answer = self::getItem('pricelist', $pricelistId);
        $answer["processingmodule"] = $processingmodule;
        return $answer;
    }

    /**
     * @param $pricelistId
     * @return array
     */
    public static function getPriceDetails($pricelistId)
    {
        $answer = [];
        $q = "SELECT p.id, p.addontype, IF(p.billtype != 4 AND p.manualname='off', it.name_ru, p.name_ru) AS name,
                IF(p.addontype=2 AND p.billtype!=1 AND COUNT(ps.id) > 0, 'on', NULL) AS has_scale, 
                CONCAT(IF(p.addontype=1 OR p.addontype=2, CONCAT(IFNULL(CONCAT(GROUP_CONCAT(CONCAT(if (ABS((pr.monthly) % 0.01) = 0,
                 SUBSTRING(pr.monthly, 1, LOCATE('.', pr.monthly) + 2), pr.monthly),''), ' ', IFNULL(c.symbol, c.iso) SEPARATOR ' / '), ' ', ''), ''), 
                 IF(p.scaletype != 2 AND p.billtype = 2 AND p.addontype=2, CONCAT(' (', p.addonstep, ' ',m.name_ru,')'), '')),
                  CONCAT(IFNULL(CONCAT(GROUP_CONCAT(CONCAT(if (ABS((p2eipr.monthly) % 0.01) = 0, 
                  SUBSTRING(p2eipr.monthly, 1, LOCATE('.', p2eipr.monthly) + 2), p2eipr.monthly),''), ' ', IFNULL(c.symbol, c.iso) SEPARATOR ' / '), ' ', ''), ''), ' (', ei.name_ru , ')')),
                  IF(p.billtype = 3, CONCAT(IF(pr.monthly IS NULL OR pr.stat IS NULL,'',' + '), 
                  IFNULL(CONCAT(GROUP_CONCAT(CONCAT(if (ABS((pr.stat) % 0.01) = 0, SUBSTRING(pr.stat, 1, LOCATE('.', pr.stat) + 2), pr.stat),''), ' ', 
                  IFNULL(c.symbol, c.iso) SEPARATOR ' / '), ' ', ''), '')), '')) AS price,
                   
                p.billtype, IFNULL(p.active, 'on') AS active, p.orderpriority AS orderpriority, p.manualprocessing, p.restrictclientchange, 
                IF(p.billtype = 4, 'on', NULL) AS is_compound, parent_itemtype.name_ru AS parent_itemtype, project.name AS project
                FROM pricelist p
                  JOIN itemtype it on it.id=p.itemtype 
                  JOIN pricelist parent ON p.parent = parent.id 
                  JOIN itemtype parent_itemtype ON parent_itemtype.id = parent.itemtype 
                  JOIN project ON parent.project = project.id
                   LEFT JOIN pricelistprice pp ON pp.pricelist=p.id 
                   LEFT JOIN measure m ON m.id=p.measure 
                   LEFT JOIN currency c ON c.id=pp.currency
                    LEFT JOIN price pr ON pr.id=pp.price
                     LEFT JOIN pricelist2enumerationitem p2ei ON p2ei.pricelist=p.id AND p2ei.enumerationitem = p.enumerationitem 
                LEFT JOIN pricelist2enumerationitemprice p2eip ON p2eip.pricelist2enumerationitem=p2ei.id 
                LEFT JOIN price p2eipr ON p2eipr.id=p2eip.price
                 LEFT JOIN pricelistscale ps ON ps.pricelist = p.id 
                 LEFT JOIN enumerationitem ei ON ei.id=p.enumerationitem
                  WHERE p.parent IN ('" . Database::getInstance()->escape($pricelistId) . "') AND p.compound IS NULL AND p.billtype!=10 GROUP BY p.id";
        $qres = Database::getInstance()->query($q);

        while ($qres && ($row = $qres->fetch_assoc())) {
            $answer[] = $row;
        }

        return $answer;
    }

    /**
     * @param $accountId
     * @return array
     */
    public static function getAccountInfo($accountId)
    {
        return self::getItem('account', $accountId);
    }

    /**
     * @param $userId
     * @return array
     */
    public static function getUserInfo($userId)
    {
        return self::getItem('user', $userId);
    }


    /**
     * @param $module_id
     * @return array
     */
    public static function getFraudGatewayInfo($module_id)
    {
        return self::getItem('fraud_gateway', $module_id);
    }

    /**
     * @param $module_id
     * @return array
     * @throws \Exception
     */
    public static function getModuleInfo($module_id)
    {
        if(trim($module_id) != null) {
            $list = self::getProcessingList(["id" => $module_id]);

            if (!isset($list[$module_id])) {
                throw new \Exception("Module $module_id not found");
            }
            return $list[$module_id];
        }
        return [];
    }


    /**
     * @param $domain_id
     * @return array
     */
    public static function getNSS($domain_id)
    {
        $i = 0;
        $nss = array();
        do {
            $q = "SELECT * FROM itemparam WHERE item='" . Database::getInstance()->escape($domain_id) . "' AND intname='ns$i' LIMIT 1";
            $qres = Database::getInstance()->query($q);
            if ($qres && ($row = $qres->fetch_assoc())) {
                if( trim($row["value"]) != "" ) {
                    $nss[] = self::string2Ns($row["value"]);
                }
            }
            $i++;
        } while ($qres && $qres->num_rows > 0);

        return $nss;
    }

    /**
     * @param $runningOperationId
     * @param $moduleInfo
     * @param \Exception $ex
     * @return SimpleXMLElement
     */
    public static function runningOperationError($runningOperationId, $moduleInfo, \Exception $ex)
    {
        $errorXml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><doc/>");

        /*$errorInfo = $errorXml->addChild("error");
        $errorInfo->addAttribute("date", htmlspecialchars( date("Y-m-d H:i:s") ));
        $errorInfo->addChild("backtrace", htmlspecialchars( $ex->getTraceAsString() ));
        $errorInfo->addChild("log", htmlspecialchars( "LogFile: " . \logger::$filename ));*/

        $module = $errorXml->addChild("processingmodule");
        $module->addAttribute("date", htmlspecialchars(date("Y-m-d H:i:s")));
        $module->addAttribute("id", htmlspecialchars((string)$moduleInfo["id"]));
        $module->addAttribute("name", htmlspecialchars((string)$moduleInfo["name"]));

        $pmError = $module->addChild("error");
        $pmError->addAttribute("date", htmlspecialchars(date("Y-m-d H:i:s")));
        $pmError->addChild("backtrace", htmlspecialchars($ex->getTraceAsString()));
        $pmError->addChild("log", htmlspecialchars("LogFile: " . \logger::$filename . " (" . \logger::getRand() . ")"));

        $param = $pmError->addChild("param", htmlspecialchars($ex->getMessage()));
        $param->addAttribute("name", "error");
        $param->addAttribute("type", "msg");

        //$errorXml->addChild("custommessage", htmlspecialchars( $ex->getMessage() ) );

        return Api::sendRequest("runningoperation.edit", array(
            "elid" => $runningOperationId,
            "errorxml" => (string)$errorXml->asXML(),
            "sok" => "ok",
        ));
    }


    public static function string2Ns($string)
    {
        $params = explode("/", $string);

        if (count($params) > 1) {
            $ns = array(
                "ns" => $params[0],
                "ip" => $params[1],
            );
        } else {
            $ns = array(
                "ns" => $params[0],
            );
        }
        $ns["hostparams"] = $params;

        return $ns;
    }

    public static function getProcessingmodule($id) {
        $q = "SELECT * FROM `processingmodule` WHERE id='". Database::getInstance()->escape($id)."'";
        $res = Database::getInstance()->query($q);

        return $res->fetch_assoc();
    }

    public static function profiledocSend($status) {
        $q = "SELECT dps.* , ip.value as domain , i.account , pr.module FROM `domain_profiledoc_send` dps
LEFT JOIN `itemparam` ip ON ip.item = dps.item AND ip.intname = 'domain' 
LEFT JOIN `item` i ON i.id = dps.item
LEFT JOIN `processingmodule` pr ON pr.id = dps.processingmodule
WHERE dps.verify = '" . Database::getInstance()->escape($status) . "'";
        $res = Database::getInstance()->query($q);
        $profiledocSend = array();
        while ($res && ($row = $res->fetch_assoc())) {
            $profiledocSend[] = $row;
        }
        return $profiledocSend;
    }

    public static function getprofiledocs($cid , $processingmodule ){
        $q = "SELECT DISTINCT dp.id,  dp.filename, dp.service_profile , dp.name , dps.processingmodule FROM `domain_profiledoc` dp 
LEFT JOIN `domain_profiledoc_send` dps ON dps.domain_profiledoc = dp.id
WHERE dps.verify = '0' AND dp.service_profile = '".Database::getInstance()->escape($cid)."' AND dps.processingmodule = '".Database::getInstance()->escape($processingmodule)."'";
        $res = Database::getInstance()->query($q);
        $profiledoc = array();
        while ($res && ($row = $res->fetch_assoc())) {
            $profiledoc[] = $row;
        }
        return $profiledoc;
    }

    public static function setDomainProfiledocSend($id , $status){
        $q = "UPDATE `domain_profiledoc_send` SET `verify` = '".Database::getInstance()->escape($status).
            "' WHERE `domain_profiledoc_send`.`id` = '" .Database::getInstance()->escape($id) . "'" ;
        $res = Database::getInstance()->query($q);
    }

    public static function getAllItems($contact ,$processiingModule , $itemtype){
        $q = "SELECT it.id , pro.module , ip.value as domain FROM `item` it  
LEFT JOIN `pricelist` AS pr ON pr.id = it.pricelist
LEFT JOIN `service_profile2item` AS spi ON spi.item = it.id
LEFT JOIN `processingmodule` pro ON it.processingmodule = pro.id
LEFT JOIN `itemparam` AS ip ON ip.item = it.id AND ip.intname = 'domain'
WHERE spi.service_profile = '".Database::getInstance()->escape($contact).
            "' AND it.processingmodule = '".Database::getInstance()->escape($processiingModule).
            "' AND pr.itemtype = '".Database::getInstance()->escape($itemtype)."' AND it.status = '2'";
        $out = array();
        $request = Database::getInstance()->query($q);
        return  $request;
    }

    public static function setVerifyStatus($service_profile , $processingmodule , $item , $docs , $status){
        if(!empty($docs)){
            $d = array();
            foreach ($docs as $doc){
                $d[] = "'" . Database::getInstance()->escape($doc) . "'";
            }
            $q = "UPDATE `domain_profiledoc_send` SET `verify` = '".Database::getInstance()->escape($status)."' WHERE `service_profile` = '".Database::getInstance()->escape($service_profile)
                ."' AND `processingmodule` = '".Database::getInstance()->escape($processingmodule)."' AND `item` = '".
                Database::getInstance()->escape($item)."' AND `domain_profiledoc` IN ( ".implode(', ' , $d)." ) ";
            Database::getInstance()->query($q);
        }
    }
}