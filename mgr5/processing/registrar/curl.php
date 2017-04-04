<?php

trait curl{

    private $curl_connection_timeout = 60;
    private $curl_timeout = 60;


    protected function SendGet($url,$onlyhandler=false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curl_connection_timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);

        if($onlyhandler)
        {
            return $ch;
        }

        \logger::write("Sending to " . $url, \logger::LEVEL_REMOTE_REQUEST );
        $res = curl_exec($ch);
        \logger::dump("Resived", $res, \logger::LEVEL_REMOTE_RESULT );
        curl_close($ch);

        return $res;
    }

    protected function SendPost($url,$query,$onlyhandler=false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curl_connection_timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);

        if($onlyhandler)
        {
            return $ch;
        }

        \logger::dump("Sending to " . $url, $query,  \logger::LEVEL_REMOTE_REQUEST );
        $res = curl_exec($ch);
        \logger::dump("Resived", $res, \logger::LEVEL_REMOTE_RESULT );
        curl_close($ch);

        return $res;
    }
}