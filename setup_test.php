<?php
/*
 * setup_test.php
 *
 * @(#) $Header$
 *
 */

    require("parser.php");
    require("manager.php");
    require("metabase_wrapper.php");
    require("xml_parser.php");

function output($message)
{
    echo $message,"\n";
}

function dump($output)
{
    echo $output;
}

    $input_file = ($argc<2 ? "test.schema" : $argv[1]);
    $variables = array(
        "create" => "1"
    );
    $arguments = array(
        "Type" => "mysql",
        "User" => "root",
        "Debug" => "Output"
    );
    $manager = new MDB_manager;
    $manager->debug = "Output";
    $success=$manager->updateDatabase($input_file,$input_file.".before",$arguments,$variables);
    if($success) {
        echo $manager->dumpDatabase(array(
            "Output"=>"Dump",
            "EndOfLine"=>"\n"
        ));
    } else {
        echo "Error: ".$manager->error."\n";
    }
    if(count($manager->warnings) > 0) {
        echo "WARNING:\n",implode($manager->warnings,"!\n"),"\n";
    }
    if($manager->database) {
        echo metabaseDebugOutput($manager->database);
    }

?>
