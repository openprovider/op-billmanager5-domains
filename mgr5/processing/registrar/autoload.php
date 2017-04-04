<?php
function loadClasses( $name ){
    $params = explode("\\", $name );
    
    $path = __DIR__;
    for($i=0; $i< count($params)-1; $i++){
        $path .= "/" . strtolower( $params[$i] );
    }
    $fname = $path . "/" . $params[count($params)-1] . ".php";
    if(file_exists($fname)){
        require_once $fname;
    }
}

spl_autoload_register("loadClasses");