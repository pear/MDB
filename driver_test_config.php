<?
/*
 * driver_test_configuration.php
 *
 * @(#) $Header$
 *
 */

 $driver_arguments["Type"]="mysql";
 $driver_arguments["DontCaptureDebug"]=1;
 $driver_arguments["Persistent"]=0;
 $driver_arguments["Options"]=array(
 );

 switch($driver_arguments["Type"])
 {
  case "mysql":
   $driver_arguments["User"]="intern";
   $driver_arguments["Password"]="iqYB27km";
   $driver_arguments["Options"]["UseTransactions"]=1;
   break;
 }
?>
