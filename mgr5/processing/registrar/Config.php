<?php

class Config{
    public static $REGNAME;
    public static $REPORTMAIL;
    public static $TELEGRAMKEY;
    public static $LANG = "ru";
    public static $MYSQL_SSL = true;
    public static $MGRCTLPATH;
    public static $NOTICE_USER;
}
Config::$MGRCTLPATH = __DIR__ . "/../../sbin/mgrctl";