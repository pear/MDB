<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2002 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith                                         |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB is a merge of PEAR DB and Metabases that provides a unified DB   |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: Manuel Lemos <mlemos@acm.org>                                |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* Configuration file for the Metabase test suite
*
* @package MDB
* @author  Manuel Lemos <mlemos@acm.org>>
*/

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
