<?
/* vim: set expandtab tabstop=4 shiftwidth=4: */
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
// +----------------------------------------------------------------------+
//
// $Id$
//
// Metabase test suite.
//

    require("manager.php");
    require("metabase_wrapper.php");

Function Output($message)
{
    echo $message,"\n";
}

Function Dump($output)
{
    echo $output;
}

    $input_file=($argc<2 ? "test.schema" : $argv[1]);
    $variables=array(
        "create"=>"1"
    );
    $arguments=array(
        "Type"=>"mysql",
        "User"=>"metapear",
        "Password"=>"funky",
        "Debug"=>"Output"
    );
    $manager=new metabase_manager_class;
    $manager->debug="Output";
    $success=$manager->UpdateDatabase($input_file,$input_file.".before",$arguments,$variables);
    if($success)
    {
        echo $manager->DumpDatabase(array(
            "Output"=>"Dump",
            "EndOfLine"=>"\n"
        ));
    }
    else
        echo "Error: ".$manager->error."\n";
    if(count($manager->warnings)>0)
        echo "WARNING:\n",implode($manager->warnings,"!\n"),"\n";
    if($manager->database)
        echo MetabaseDebugOutput($manager->database);

?>