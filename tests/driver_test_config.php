<?php
/*
 * driver_test_configuration.php
 *
 * @(#) $Header$
 *
 */

 $driver_arguments["Type"]="mysql";
 $driver_arguments["DontCaptureDebug"]=1;
 $driver_arguments["Persistent"]=0;
 $driver_arguments["Options"]=array();

 switch($driver_arguments["Type"]) {
    case "mysql":
        // modify to your needs
        $driver_arguments["User"]="metapear";
        $driver_arguments["Password"]="funky";
        $driver_arguments["Options"]["UseTransactions"]=0;
    break;
 }
?>