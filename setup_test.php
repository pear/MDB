<?
/*
 * setup_test.php
 */

	require("parser.php");
	require("manager.php");
	require("metabase_wrapper.php");
	require("xml_parser.php");

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
		"User"=>"root",
		"Debug"=>"Output"
	);
	$manager=new MDB_manager;
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
