<?php
//  common.php
//  CityDistance
//
//  Created by Valeriy Chevtaev on 5/7/12.
//  Copyright (c) 2012 myltik@gmail.com. All rights reserved.

   
global $DEBUG;

//
// Aux
   
function cd_runner($app_name, $run_function)
{
    $ret = 0;
    date_default_timezone_set("Europe/Moscow");
    
    cd_echo ("-------------------------------------------------------------");
    cd_echo ("Running $app_name on " . cd_date_str());
    cd_echo ("");
    
    try
    {
        call_user_func($run_function);
        cd_echo ("");
        cd_echo ("SUCCESS");
    }
    catch (Exception $e)
    {
        cd_echo ("");
        cd_echo ("EXCEPTION: " . $e->getTraceAsString());
        $ret = 1;
    }
    
    cd_echo ("");
    cd_echo ("Finish $app_name on " . cd_date_str());
    cd_echo ("");
    
    return $ret;
}

function cd_echo($s)
{
    echo "$s\n";
}

function cd_date_str()
{
    return date("D M j G:i:s T Y");
}


