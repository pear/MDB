
<?
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Manuel Lemos <mlemos@acm.org>                               |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id$
//
// Metabase test suite config script.
//

	$driver_arguments["Type"]="mysql";
	$driver_arguments["DontCaptureDebug"]=1;
	$driver_arguments["Persistent"]=0;
	$driver_arguments["LogLineBreak"]="\n";
	$driver_arguments["Options"]=array(
	);

	switch($driver_arguments["Type"])
	{
		case "ibase":
			$driver_arguments["Host"]="";
			$driver_arguments["Options"]=array(
				"DBAUser"=>"sysdba",
				"DBAPassword"=>"masterkey",
				"DatabasePath"=>"/opt/interbase/",
				"DatabaseExtension"=>".gdb"
			);
			$database_variables["create"]="0";
			break;
		case "ifx":
			$driver_arguments["Host"]="demo_on";
			$driver_arguments["User"]="webuser";
			$driver_arguments["Password"]="webuser_password";
			$driver_arguments["Options"]=array(
				"DBAUser"=>"informix",
				"DBAPassword"=>"informix_pasword",
				"Use8ByteIntegers"=>1,
				"Logging"=>"Unbuffered"
			);
			break;
		case "msql":
			break;
		case "mssql":
			$driver_arguments["User"]="sa";
			$driver_arguments["Password"]="";
			$driver_arguments["Options"]=array(
				"DatabaseDevice"=>"DEFAULT",
				"DatabaseSize"=>"10"
			);
			break;
		case "mysql":
			$driver_arguments["User"]="metapear";
			$driver_arguments["Password"]="funky";
			$driver_arguments["Options"]["UseTransactions"]=0;
			break;
		case "oci":
			$driver_arguments["User"]="drivertest";
			$driver_arguments["Password"]="drivertest";
			$driver_arguments["Options"]=array(
				"SID"=>"dboracle",
				"HOME"=>"/home/oracle/u01",
				"DBAUser"=>"SYS",
				"DBAPassword"=>"change_on_install"
			);
			break;
		case "odbc":
			$driver_arguments["User"]="webuser";
			$driver_arguments["Password"]="webuser_password";
			$driver_arguments["Options"]=array(
				"DBADSN"=>"dbadsn",
				"DBAUser"=>"dbauser",
				"DBAPassword"=>"dbapassword",
				"UseDefaultValues"=>0,
				"UseDecimalScale"=>0,
				"UseTransactions"=>0
			);
			$database_variables["create"]="0";
			$database_variables["name"]="userdsn";
			break;
		case "pgsql":
			$driver_arguments["User"]="metapear";
			$driver_arguments["Password"]="funky";
			$driver_arguments["Options"]["UseTransactions"]=1;
			break;
	}
?>
