<?php
/*
 * driver_test.php
 *
 * @(#) $Header$
 *
 * This is a script intended to be used by Metabase DBMS driver class
 * developers or other users to verify if the implementation of a given
 * driver works in conformance with the documented behavior of the driver
 * class functions.
 *
 * Driver classes that are not compliant may lead to bugs in the Metabase
 * applications that use such drivers.  Make sure that new or updated
 * drivers pass all tests performed by this script before releasing the
 * driver classes to Metabase users.  In the future this script will be
 * updated to perform conformance tests.
 *
 * To use this script, edit the driver_test_configuration.php script and
 * adjust any database setup values that may be needed to use the driver
 * class being tested in your environment.  Read Metabase documentation
 * about the MetabaseSetupDatabase section to learn more about these
 * database setup arguments.
 *
 */

// BC hack to define PATH_SEPARATOR for version of PHP prior 4.3
if(!defined('PATH_SEPARATOR')) {
	if(defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == "\\") {
		define('PATH_SEPARATOR', ';');
	} else {
		define('PATH_SEPARATOR', ':');
	}
}
ini_set('include_path', '..'.PATH_SEPARATOR.ini_get('include_path'));

require_once 'MDB.php';
MDB::loadFile('metabase_wrapper');
MDB::loadFile('Manager');

Function VerifyFetchedValues($database,$result,$row,&$data,&$value,&$field)
{
	return(strcmp($value=MetabaseFetchResult($database,$result,$row,"user_name"),$data[$field="user_name"])
	|| strcmp($value=MetabaseFetchResult($database,$result,$row,"user_password"),$data[$field="user_password"])
	|| strcmp($value=MetabaseFetchBooleanResult($database,$result,$row,"subscribed"),$data[$field="subscribed"])
	|| strcmp($value=MetabaseFetchResult($database,$result,$row,"user_id"),$data[$field="user_id"])
	|| ($value=MetabaseFetchDecimalResult($database,$result,$row,"quota"))!=$data[$field="quota"]
	|| strcmp($value=MetabaseFetchFloatResult($database,$result,$row,"weight"),$data[$field="weight"])
	|| strcmp($value=MetabaseFetchDateResult($database,$result,$row,"access_date"),$data[$field="access_date"])
	|| strcmp($value=MetabaseFetchTimeResult($database,$result,$row,"access_time"),$data[$field="access_time"])
	|| strcmp($value=MetabaseFetchTimestampResult($database,$result,$row,"approved"),$data[$field="approved"]));
}

Function InsertTestValues($database,$prepared_query,&$data)
{
	MetabaseQuerySetText($database,$prepared_query,1,$data["user_name"]);
	MetabaseQuerySetText($database,$prepared_query,2,$data["user_password"]);
	MetabaseQuerySetBoolean($database,$prepared_query,3,$data["subscribed"]);
	MetabaseQuerySetInteger($database,$prepared_query,4,$data["user_id"]);
	MetabaseQuerySetDecimal($database,$prepared_query,5,$data["quota"]);
	MetabaseQuerySetFloat($database,$prepared_query,6,$data["weight"]);
	MetabaseQuerySetDate($database,$prepared_query,7,$data["access_date"]);
	MetabaseQuerySetTime($database,$prepared_query,8,$data["access_time"]);
	MetabaseQuerySetTimestamp($database,$prepared_query,9,$data["approved"]);
	return(MetabaseExecuteQuery($database,$prepared_query));
}

	$driver_arguments=array(
	);

	$database_variables=array(
		"create"=>"1",
		"name"=>"driver_test"
	);

	if(file_exists("driver_test_config.php"))
		include("driver_test_config.php");

	$eol=(IsSet($driver_arguments["LogLineBreak"]) ? $driver_arguments["LogLineBreak"] : "\n");

	$default_tests=array(
		"storage"=>1,
		"bulkfetch"=>1,
		"preparedqueries"=>1,
		"metadata"=>1,
		"nulls"=>1,
		"escapesequences"=>1,
		"ranges"=>1,
		"sequences"=>1,
		"affectedrows"=>1,
		"transactions"=>1,
		"replace"=>1,
		"lobstorage"=>1,
		"lobfiles"=>1,
		"lobnulls"=>1
	);
	if($argc<=1)
		$tests=$default_tests;
	else
	{
		for($tests=array(),$argument=1;$argument<$argc;$argument++)
		{
			if(!IsSet($default_tests[$argv[$argument]]))
			{
				echo "Usage: ",$argv[0];
				for(Reset($default_tests);Key($default_tests);Next($default_tests))
					echo " [",Key($default_tests),"]";
				echo $eol;
				exit;
			}
			$tests[$argv[$argument]]=$default_tests[$argv[$argument]];
		}
	}

	set_time_limit(0);
	$input_file="driver_test.schema";
	$manager=new metabase_manager_class;
	$success=$manager->UpdateDatabase($input_file,$input_file.".before",$driver_arguments,$database_variables);
	$debug_output="";
	if(count($manager->warnings)>0)
		$debug_output.="WARNING:$eol".implode($manager->warnings,"!$eol").$eol;
	if($manager->database
	&& IsSet($driver_arguments["CaptureDebug"]))
		$debug_output.=MetabaseDebugOutput($manager->database);
	$passed=$failed=0;
	if($success)
	{
		if(!strcmp($error=MetabaseSetupDatabase($driver_arguments,$database),""))
		{
			if(IsSet($driver_arguments["CaptureDebug"]))
				MetabaseCaptureDebugOutput($database,1);
			MetabaseSetDatabase($database,$database_variables["name"]);

			if(IsSet($tests["storage"])
			&& $success)
			{
				$test="storage";
				echo "Testing typed field storage and retrieval ... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM users"))
					$success=0;
				else
				{
					$row=1234;
					$data=array();
					$data["user_name"]="user_$row";
					$data["user_password"]="somepassword";
					$data["subscribed"]=$row % 2;
					$data["user_id"]=$row;
					$data["quota"]=strval($row/100);
					$data["weight"]=sqrt($row);
					$data["access_date"]=MetabaseToday();
					$data["access_time"]=MetabaseTime();
					$data["approved"]=MetabaseNow();
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
					{
						if(!InsertTestValues($database,$prepared_query,$data))
						{
							$success=0;
						}
						MetabaseFreePreparedQuery($database,$prepared_query);
						if($success)
						{
							if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users")))
								$success=0;
							else
							{
								if(VerifyFetchedValues($database,$result,0,$data,$value,$field))
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: the value retrieved for field \"$field\" ($value) doesn't match what was stored (".$data[$field].")$eol";
								}
								else
								{
									if(!MetabaseEndOfResult($database,$result))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query result did not seem to have reached the end of result as expected$eol";
									}
								}
								MetabaseFreeResult($database,$result);
							}
						}
						if($success
						&& $pass)
						{
							$passed++;
							echo "OK.$eol";
						}
					}
					else
						$success=0;
				}
			}

			if(IsSet($tests["bulkfetch"])
			&& $success)
			{
				$test="bulkfetch";
				echo "Testing query result data bulk fetching... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM users"))
					$success=0;
				else
				{
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
					{
						$data=array();
						for($total_rows=5,$row=0;$row<$total_rows;$row++)
						{
							$data[$row]["user_name"]="user_$row";
							$data[$row]["user_password"]="somepassword";
							$data[$row]["subscribed"]=$row % 2;
							$data[$row]["user_id"]=$row;
							$data[$row]["quota"]=sprintf("%.2f",strval(1+($row+1)/100));
							$data[$row]["weight"]=sqrt($row);
							$data[$row]["access_date"]=MetabaseToday();
							$data[$row]["access_time"]=MetabaseTime();
							$data[$row]["approved"]=MetabaseNow();
							if(!InsertTestValues($database,$prepared_query,$data[$row]))
							{
								$success=0;
								break;
							}
						}
						MetabaseFreePreparedQuery($database,$prepared_query);
						$types=array(
							"text",
							"text",
							"boolean",
							"integer",
							"decimal",
							"float",
							"date",
							"time",
							"timestamp"
						);
						if($success)
						{
							for($row=0;$row<$total_rows;$row++)
							{
								for(Reset($data[$row]),$column=0;$column<count($data[$row]);Next($data[$row]),$column++)
								{
									$field=Key($data[$row]);
									$type=$types[$column];
									if(!($success=MetabaseQueryField($database,"SELECT $field FROM users WHERE user_id=$row",$value,$type)))
										break 2;
									if(strcmp(strval($data[$row][$field]),strval($value)))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query field \"$field\" of type $type for row $row was returned in \"$value\" unlike \"".$data[$row][$field]."\" as expected$eol";
										break 2;
									}
								}
							}
						}
						if($success
						&& $pass)
						{
							for($fields="",Reset($data[0]),$column=0;$column<count($data[0]);Next($data[0]),$column++)
							{
								if($column>0)
									$fields.=",";
								$fields.=Key($data[0]);
							}
							for($row=0;$row<$total_rows;$row++)
							{
								if(!($success=MetabaseQueryRow($database,"SELECT $fields FROM users WHERE user_id=$row",$value,$types)))
									break;
								for(Reset($data[$row]),$column=0;$column<count($data[$row]);Next($data[$row]),$column++)
								{
									$field=Key($data[$row]);
									if(strcmp(strval($data[$row][$field]),strval($value[$column])))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query row field \"$field\" of for row $row was returned in \"".$value[$column]."\" unlike \"".$data[$row][$field]."\" as expected$eol";
										break 2;
									}
								}
							}
						}
						if($success
						&& $pass)
						{
							for(Reset($data[0]),$column=0;$column<count($data[0]);Next($data[0]),$column++)
							{
								$field=Key($data[0]);
								$type=$types[$column];
								if(!($success=MetabaseQueryColumn($database,"SELECT $field,user_id FROM users ORDER BY 2",$value,$type)))
									break;
								for($row=0;$row<$total_rows;$row++)
								{
									if(strcmp(strval($data[$row][$field]),strval($value[$row])))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query column field \"$field\" of type $type for row $row was returned in \"".$value[$row]."\" unlike \"".$data[$row][$field]."\" as expected$eol";
										break 2;
									}
								}
							}
						}
						if($success
						&& $pass)
						{
							for($fields="",Reset($data[0]),$column=0;$column<count($data[0]);Next($data[0]),$column++)
							{
								if($column>0)
									$fields.=",";
								$fields.=Key($data[0]);
							}
							if(($success=MetabaseQueryAll($database,"SELECT $fields FROM users ORDER BY user_id",$value,$types)))
							{
								for($row=0;$row<$total_rows;$row++)
								{
									for(Reset($data[$row]),$column=0;$column<count($data[$row]);Next($data[$row]),$column++)
									{
										$field=Key($data[$row]);
										if(strcmp(strval($data[$row][$field]),strval($value[$row][$column])))
										{
											$pass=0;
											echo "FAILED!$eol";
											$failed++;
											echo "Test $test: the query all field \"$field\" of for row $row was returned in \"".$value[$row][$column]."\" unlike \"".$data[$row][$field]."\" as expected$eol";
											break 2;
										}
									}
								}
							}
						}
						if($success
						&& $pass)
						{
							$passed++;
							echo "OK.$eol";
						}
					}
					else
						$success=0;
				}
			}

			if(IsSet($tests["preparedqueries"])
			&& $success)
			{
				$test="preparedqueries";
				echo "Testing prepared queries ... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM users"))
					$success=0;
				else
				{
					$question_value=MetabaseGetTextFieldValue($database,"Does this work?");
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,user_id) VALUES (?,$question_value,1)")))
					{
						MetabaseQuerySetText($database,$prepared_query,1,"Sure!");
						if(!MetabaseExecuteQuery($database,$prepared_query))
						{
							$sucess=$pass=0;
							echo "FAILED!$eol";
							echo "Test $test: could not execute prepared query with a text value with a question mark. Error: ".MetabaseError($database).$eol;
							echo "Testing prepared queries ... ";
							flush();
						}
						MetabaseFreePreparedQuery($database,$prepared_query);
					}
					else
					{
						$sucess=$pass=0;
						echo "FAILED!$eol";
						echo "Test $test: could not execute prepared query with a text value with a question mark. Error: ".MetabaseError($database).$eol;
						echo "Testing prepared queries ... ";
						flush();
					}
					$question_value=MetabaseGetTextFieldValue($database,"Wouldn't it be great if this worked too?");
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,user_id) VALUES (?,$question_value,2)")))
					{
						MetabaseQuerySetText($database,$prepared_query,1,"Sure!");
						if(!MetabaseExecuteQuery($database,$prepared_query))
						{
							$sucess=$pass=0;
							echo "FAILED!$eol";
							echo "Test $test: could not execute prepared query with a text value with a quote character before a question mark. Error: ".MetabaseError($database).$eol;
						}
						MetabaseFreePreparedQuery($database,$prepared_query);
					}
					else
					{
						$sucess=$pass=0;
						echo "FAILED!$eol";
						echo "Test $test: could not execute prepared query with a text value with a quote character before a question mark. Error: ".MetabaseError($database).$eol;
					}
					if($success
					&& $pass)
					{
						$passed++;
						echo "OK.$eol";
					}
					else
						$failed++;
				}
			}

			if(IsSet($tests["metadata"])
			&& $success)
			{
				$test="metadata";
				echo "Testing retrieval of result metadata... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM users"))
					$success=0;
				else
				{
					$row=1234;
					$data=array();
					$data["user_name"]="user_$row";
					$data["user_password"]="somepassword";
					$data["subscribed"]=$row % 2;
					$data["user_id"]=$row;
					$data["quota"]=strval($row/100);
					$data["weight"]=sqrt($row);
					$data["access_date"]=MetabaseToday();
					$data["access_time"]=MetabaseTime();
					$data["approved"]=MetabaseNow();
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
					{
						if(!InsertTestValues($database,$prepared_query,$data))
						{
							$success=0;
						}
						MetabaseFreePreparedQuery($database,$prepared_query);
						if($success)
						{
							if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users")))
								$success=0;
							else
							{
								$fields=array(
									"user_name",
									"user_password",
									"subscribed",
									"user_id",
									"quota",
									"weight",
									"access_date",
									"access_time",
									"approved"
								);
								if(($columns=MetabaseNumberOfColumns($database,$result))==count($fields))
								{
									if(($success=MetabaseGetColumnNames($database,$result,$column_names)))
									{
										if($columns==count($column_names))
										{
											for($column=0;$column<$columns;$column++)
											{
												if($column_names[$fields[$column]]!=$column)
												{
													$pass=0;
													echo "FAILED!$eol";
													$failed++;
													echo "Test $test: the query result column \"".$fields[$column]."\" was returned in position ".$column_names[$fields[$column]]." unlike $column as expected$eol";
												}
											}
										}
										else
										{
											$pass=0;
											echo "FAILED!$eol";
											$failed++;
											echo "Test $test: the query result returned a number of ".count($column_names)." column names unlike $columns as expected$eol";
										}
									}
								}
								else
								{
									if($columns==-1)
										$success=0;
									else
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query result returned a number of $columns columns unlike ".count($fields)." as expected$eol";
									}
								}
								MetabaseFreeResult($database,$result);
							}
						}
						if($success
						&& $pass)
						{
							$passed++;
							echo "OK.$eol";
						}
					}
					else
						$success=0;
				}
			}

			if(IsSet($tests["nulls"])
			&& $success)
			{
				$test="nulls";
				echo "Testing storage and retrieval of NULL values... ";
				flush();
				$pass=1;
				$test_values=array(
					"test",
					"NULL",
					"null",
					""
				);
				for($test_value=0;$success && $test_value<=count($test_values);$test_value++)
				{
					$is_null=($test_value==count($test_values));
					if($is_null)
						$value="NULL";
					else
						$value=MetabaseGetTextFieldValue($database,$test_values[$test_value]);
					if(MetabaseQuery($database,"DELETE FROM users")
					&& MetabaseQuery($database,"INSERT INTO users (user_name,user_password,user_id) VALUES ($value,$value,0)")
					&& ($result=MetabaseQuery($database,"SELECT user_name,user_password FROM users")))
					{
						if(MetabaseEndOfResult($database,$result))
						{
							if($pass)
							{
								$pass=0;
								$failed++;
								echo "FAILED!$eol";
							}
							echo "Test $test: the query result seems to have reached the end of result earlier than expected$eol";
						}
						else
						{
							if(MetabaseResultIsNull($database,$result,0,0)!=$is_null)
							{
								if($pass)
								{
									$pass=0;
									$failed++;
									echo "FAILED!$eol";
								}
								if($is_null)
									echo "Test $test: a query result column is not NULL unlike what was expected$eol";
								else
									echo "Test $test: a query result column is NULL although it was expected to be \"".$test_values[$test_value]."\"$eol";
							}
							else
							{
								if(MetabaseResultIsNull($database,$result,0,1)!=$is_null)
								{
									if($pass)
									{
										$pass=0;
										$failed++;
										echo "FAILED!$eol";
									}
									if($is_null)
										echo "Test $test: a query result column is not NULL unlike what was expected$eol";
									else
										echo "Test $test: a query result column is NULL although it was expected to be \"".$test_values[$test_value]."\"$eol";
								}
								else
								{
									if(!MetabaseEndOfResult($database,$result))
									{
										if($pass)
										{
											$pass=0;
											$failed++;
											echo "FAILED!$eol";
										}
										echo "Test $test: the query result did not seem to have reached the end of result as expected after testing only if columns are NULLs$eol";
									}
								}
							}
						}
						MetabaseFreeResult($database,$result);
					}
					else
					{
						$success=0;
						break;
					}
				}
				if($success
				&& $pass)
				{
					$passed++;
					echo "OK.$eol";
				}
			}

			if(IsSet($tests["escapesequences"])
			&& $success)
			{
				$test="escapesequences";
				echo "Testing escaping text values with special characters... ";
				flush();
				$pass=1;
				$test_strings=array(
					"'",
					"\"",
					"\\",
					"%",
					"_",
					"''",
					"\"\"",
					"\\\\",
					"\\'\\'",
					"\\\"\\\""
				);
				for($string=0;$string<count($test_strings);$string++)
				{
					$value=MetabaseGetTextFieldValue($database,$test_strings[$string]);
					if(!MetabaseQuery($database,"DELETE FROM users")
					|| !MetabaseQuery($database,"INSERT INTO users (user_name,user_password,user_id) VALUES ($value,$value,0)")
					|| !($result=MetabaseQuery($database,"SELECT user_name,user_password FROM users")))
						$success=0;
					else
					{
						if(MetabaseEndOfResult($database,$result))
						{
							$pass=0;
							echo "FAILED!$eol";
							$failed++;
							echo "Test $test: the query result seems to have reached the end of result earlier than expected$eol";
						}
						else
						{
							$field="user_name";
							if(strcmp($value=MetabaseFetchResult($database,$result,0,$field),$test_strings[$string]))
							{
								$pass=0;
								echo "FAILED!$eol";
								$failed++;
								echo "Test $test: the value retrieved for field \"$field\" (\"$value\") doesn't match what was stored (".$test_strings[$string].")$eol";
							}
							else
							{
								$field="user_password";
								if(strcmp($value=MetabaseFetchResult($database,$result,0,$field),$test_strings[$string]))
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: the value retrieved for field \"$field\" (\"$value\") doesn't match what was stored (".$test_string[$string].")$eol";
								}
								else
								{
									if(!MetabaseEndOfResult($database,$result))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the query result did not seem to have reached the end of result as expected after testing only if text fields values are correctly escaped$eol";
									}
								}
							}
						}
						MetabaseFreeResult($database,$result);
					}
					if(!$success
					|| !$pass)
						break;
				}
				if($success
				&& $pass)
				{
					$passed++;
					echo "OK.$eol";
				}
			}

			if(IsSet($tests["ranges"])
			&& $success)
			{
				if(MetabaseSupport($database,"SelectRowRanges"))
				{
					$test="ranges";
					echo "Testing paged queries... ";
					flush();
					$pass=1;
					if(!MetabaseQuery($database,"DELETE FROM users"))
						$success=0;
					else
					{
						if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
						{
							$data=array();
							for($total_rows=5,$row=0;$row<$total_rows;$row++)
							{
								$data[$row]["user_name"]="user_$row";
								$data[$row]["user_password"]="somepassword";
								$data[$row]["subscribed"]=$row % 2;
								$data[$row]["user_id"]=$row;
								$data[$row]["quota"]=sprintf("%.2f",strval($row/100));
								$data[$row]["weight"]=sqrt($row);
								$data[$row]["access_date"]=MetabaseToday();
								$data[$row]["access_time"]=MetabaseTime();
								$data[$row]["approved"]=MetabaseNow();
								if(!InsertTestValues($database,$prepared_query,$data[$row]))
								{
									$success=0;
									break;
								}
							}
							MetabaseFreePreparedQuery($database,$prepared_query);
							if($success)
							{
								for($rows=2,$start_row=0;$pass && $start_row<$total_rows;$start_row+=$rows)
								{
									MetabaseSetSelectedRowRange($database,$start_row,$rows);
									if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users ORDER BY user_id")))
									{
										$success=0;
										break;
									}
									else
									{
										for($row=0;$row<$rows && $row+$start_row<$total_rows;$row++)
										{
											if(VerifyFetchedValues($database,$result,$row,$data[$row+$start_row],$value,$field))
											{
												$pass=0;
												echo "FAILED!$eol";
												$failed++;
												echo "Test $test: the value retrieved from row ".($row+$start_row)." for field \"$field\" ($value) doesn't match what was stored (".$data[$row+$start_row][$field].")$eol";
												break;
											}
										}
										if($pass)
										{
											if(!MetabaseEndOfResult($database,$result))
											{
												$pass=0;
												echo "FAILED!$eol";
												$failed++;
												echo "Test $test: the query result did not seem to have reached the end of result as expected starting row $start_row after fetching upto row $row$eol";
											}
										}
										MetabaseFreeResult($database,$result);
									}
								}
							}
							if($success)
							{
								for($rows=2,$start_row=0;$pass && $start_row<$total_rows;$start_row+=$rows)
								{
									MetabaseSetSelectedRowRange($database,$start_row,$rows);
									if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users ORDER BY user_id")))
									{
										$success=0;
										break;
									}
									else
									{
										if(($result_rows=MetabaseNumberOfRows($database,$result))>$rows)
										{
											$pass=0;
											echo "FAILED!$eol";
											$failed++;
											echo "Test $test: expected a result of no more than $rows but the returned number of rows is $result_rows";
										}
										else
										{
											for($row=0;$row<$result_rows;$row++)
											{
												if(MetabaseEndOfResult($database,$result))
												{
													$pass=0;
													echo "FAILED!$eol";
													$failed++;
													echo "Test $test: the query result seem to have reached the end of result at row $row that is before $result_rows as expected$eol";
													break;
												}
												if(VerifyFetchedValues($database,$result,$row,$data[$row+$start_row],$value,$field))
												{
													$pass=0;
													echo "FAILED!$eol";
													$failed++;
													echo "Test $test: the value retrieved from row ".($row+$start_row)." for field \"$field\" ($value) doesn't match what was stored (".$data[$row+$start_row][$field].")$eol";
													break;
												}
											}
										}
										if($pass)
										{
											if(!MetabaseEndOfResult($database,$result))
											{
												$pass=0;
												echo "FAILED!$eol";
												$failed++;
												echo "Test $test: the query result did not seem to have reached the end of result as expected$eol";
											}
										}
										MetabaseFreeResult($database,$result);
									}
								}
							}
							if($success
							&& $pass)
							{
								$passed++;
								echo "OK.$eol";
							}
						}
						else
							$success=0;
					}
				}
				else
					echo "Selecting rows ranges is not supported.$eol";
			}

			if(IsSet($tests["sequences"])
			&& $success)
			{
				if(MetabaseSupport($database,"Sequences"))
				{
					$test="sequences";
					echo "Testing sequences... ";
					flush();
					$pass=1;
					for($start_value=1;$success && $pass && $start_value<4;$start_value++)
					{
						$sequence_name="test_sequence_$start_value";
						MetabaseDropSequence($database,$sequence_name);
						if(($success=MetabaseCreateSequence($database,$sequence_name,$start_value)))
						{
							for($sequence_value=$start_value;$sequence_value<$start_value+4;$sequence_value++)
							{
								if(!($success=MetabaseGetSequenceNextValue($database,$sequence_name,$value)))
									break;
								if($value!=$sequence_value)
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: the returned sequence value is $value and not $sequence_value as expected with sequence start value with $start_value$eol";
									break;
								}
							}
							if(!$success)
								$error=MetabaseError($database);
							if(!MetabaseDropSequence($database,$sequence_name))
							{
								if(!$success)
									$error.=" - ";
								$error.=MetabaseError($database);
								$success=0;
							}
						}
					}
					if($success
					&& $pass)
					{
						$passed++;
						echo "OK.$eol";
					}
				}
				else
					echo "Sequences are not supported.$eol";
			}

			if(IsSet($tests["replace"])
			&& $success)
			{
				if(MetabaseSupport($database,"Replace"))
				{
					$test="sequences";
					echo "Testing the replace query... ";
					flush();
					$pass=1;
					if(!MetabaseQuery($database,"DELETE FROM users"))
						$success=0;
					else
					{
						$row=1234;
						$data=array();
						$data["user_name"]="user_$row";
						$data["user_password"]="somepassword";
						$data["subscribed"]=$row % 2;
						$data["user_id"]=$row;
						$data["quota"]=strval($row/100);
						$data["weight"]=sqrt($row);
						$data["access_date"]=MetabaseToday();
						$data["access_time"]=MetabaseTime();
						$data["approved"]=MetabaseNow();
						$fields=array(
							"user_name"=>array(
								"Value"=>"user_$row",
								"Type"=>"text"
							),
							"user_password"=>array(
								"Value"=>$data["user_password"],
								"Type"=>"text"
							),
							"subscribed"=>array(
								"Value"=>$data["subscribed"],
								"Type"=>"boolean"
							),
							"user_id"=>array(
								"Value"=>$data["user_id"],
								"Type"=>"integer",
								"Key"=>1
							),
							"quota"=>array(
								"Value"=>$data["quota"],
								"Type"=>"decimal"
							),
							"weight"=>array(
								"Value"=>$data["weight"],
								"Type"=>"float"
							),
							"access_date"=>array(
								"Value"=>$data["access_date"],
								"Type"=>"date"
							),
							"access_time"=>array(
								"Value"=>$data["access_time"],
								"Type"=>"time"
							),
							"approved"=>array(
								"Value"=>$data["approved"],
								"Type"=>"timestamp"
							)
						);
						$support_affected_rows=MetabaseSupport($database,"AffectedRows");
						if(($success=MetabaseReplace($database,"users",$fields))
						&& (!$support_affected_rows
						|| ($success=MetabaseAffectedRows($database,$affected_rows))))
						{
							if($support_affected_rows
							&& $affected_rows!=1)
							{
								$pass=0;
								echo "FAILED!$eol";
								$failed++;
								echo "Test $test: replacing a row in an empty table returned $affected_rows unlike 1 as expected$eol";
							}
							else
							{
								if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users")))
									$success=0;
								else
								{
									$verify=VerifyFetchedValues($database,$result,0,$data,$value,$field);
									MetabaseFreeResult($database,$result);
									if($verify)
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: the value retrieved for field \"$field\" ($value) doesn't match what was inserted (".$data[$field].")$eol";
									}
									else
									{
										$row=4321;
										$fields["user_name"]["Value"]=$data["user_name"]="user_$row";
										$fields["user_password"]["Value"]=$data["user_password"]="somepassword";
										$fields["subscribed"]["Value"]=$data["subscribed"]=$row % 2;
										$fields["quota"]["Value"]=$data["quota"]=strval($row/100);
										$fields["weight"]["Value"]=$data["weight"]=sqrt($row);
										$fields["access_date"]["Value"]=$data["access_date"]=MetabaseToday();
										$fields["access_time"]["Value"]=$data["access_time"]=MetabaseTime();
										$fields["approved"]["Value"]=$data["approved"]=MetabaseNow();
										if(($success=MetabaseReplace($database,"users",$fields))
										&& (!$support_affected_rows
										|| ($success=MetabaseAffectedRows($database,$affected_rows))))
										{
											if(!$support_affected_rows
											&& $affected_rows!=2)
											{
												$pass=0;
												echo "FAILED!$eol";
												$failed++;
												echo "Test $test: replacing a row in an table with a single row returned $affected_rows unlike 2 as expected$eol";
											}
											if(!($result=MetabaseQuery($database,"SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users")))
												$success=0;
											else
											{
												if(VerifyFetchedValues($database,$result,0,$data,$value,$field))
												{
													$pass=0;
													echo "FAILED!$eol";
													$failed++;
													echo "Test $test: the value retrieved for field \"$field\" ($value) doesn't match what was replaced (".$data[$field].")$eol";
												}
												else
												{
													if(!MetabaseEndOfResult($database,$result))
													{
														$pass=0;
														echo "FAILED!$eol";
														$failed++;
														echo "Test $test: the query result did not seem to have reached the end of result as expected$eol";
													}
												}
												MetabaseFreeResult($database,$result);
											}
										}
									}
								}
							}
						}
					}
					if($success
					&& $pass)
					{
						$passed++;
						echo "OK.$eol";
					}
				}
				else
					echo "Replace query is not supported.$eol";
			}

			if(IsSet($tests["affectedrows"])
			&& $success)
			{
				if(MetabaseSupport($database,"AffectedRows"))
				{
					$test="affectedrows";
					echo "Testing query affected rows... ";
					flush();
					$pass=1;
					if(!MetabaseQuery($database,"DELETE FROM users"))
						$success=0;
					else
					{
						if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
						{
							$data=array();
							$inserted_rows=7;
							for($row=0;$row<$inserted_rows;$row++)
							{
								$data["user_name"]="user_$row";
								$data["user_password"]="somepassword";
								$data["subscribed"]=$row % 2;
								$data["user_id"]=$row;
								$data["quota"]=strval($row/100);
								$data["weight"]=sqrt($row);
								$data["access_date"]=MetabaseToday();
								$data["access_time"]=MetabaseTime();
								$data["approved"]=MetabaseNow();
								if(!InsertTestValues($database,$prepared_query,$data)
								|| !MetabaseAffectedRows($database,$affected_rows))
								{
									$success=0;
									break;
								}
								if($affected_rows!=1)
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: inserting the row $row return $affected_rows affected row count instead of 1 as expected$eol";
									break;
								}
							}
							MetabaseFreePreparedQuery($database,$prepared_query);
						}
						else
							$success=0;
						if($success
						&& $pass
						&& ($prepared_query=MetabasePrepareQuery($database,"UPDATE users SET user_password=? WHERE user_id<?")))
						{
							for($row=0;$row<$inserted_rows;$row++)
							{
								MetabaseQuerySetText($database,$prepared_query,1,"another_password_$row");
								MetabaseQuerySetInteger($database,$prepared_query,2,$row);
								if(!MetabaseExecuteQuery($database,$prepared_query)
								|| !MetabaseAffectedRows($database,$affected_rows))
								{
									$success=0;
									break;
								}
								if($affected_rows!=$row)
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: updating the $row rows returned $affected_rows affected row count$eol";
									break;
								}
							}
							MetabaseFreePreparedQuery($database,$prepared_query);
						}
						else
							$success=0;
						if($success
						&& $pass
						&& ($prepared_query=MetabasePrepareQuery($database,"DELETE FROM users WHERE user_id>=?")))
						{
							for($row=$inserted_rows;$inserted_rows;$inserted_rows=$row)
							{
								MetabaseQuerySetInteger($database,$prepared_query,1,$row=intval($inserted_rows/2));
								if(!MetabaseExecuteQuery($database,$prepared_query)
								|| !MetabaseAffectedRows($database,$affected_rows))
								{
									$success=0;
									break;
								}
								if($affected_rows!=$inserted_rows-$row)
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: deleting ".($inserted_rows-$row)." rows returned $affected_rows affected row count$eol";
									break;
								}
							}
							MetabaseFreePreparedQuery($database,$prepared_query);
						}
						else
							$success=0;
						if($success
						&& $pass)
						{
							$passed++;
							echo "OK.$eol";
						}
					}
				}
				else
					echo "Query AffectedRows fetching is not supported.$eol";
			}

			if(IsSet($tests["transactions"])
			&& $success)
			{
				if(MetabaseSupport($database,"Transactions"))
				{
					$test="transactions";
					echo "Testing transactions... ";
					flush();
					$pass=1;
					if(!MetabaseQuery($database,"DELETE FROM users")
					|| !MetabaseAutoCommitTransactions($database,0))
						$success=0;
					else
					{
						if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO users (user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved) VALUES (?,?,?,?,?,?,?,?,?)")))
						{
							$data=array();
							$row=0;
							$data["user_name"]="user_$row";
							$data["user_password"]="somepassword";
							$data["subscribed"]=$row % 2;
							$data["user_id"]=$row;
							$data["quota"]=strval($row/100);
							$data["weight"]=sqrt($row);
							$data["access_date"]=MetabaseToday();
							$data["access_time"]=MetabaseTime();
							$data["approved"]=MetabaseNow();
							if(!InsertTestValues($database,$prepared_query,$data)
							|| !MetabaseRollbackTransaction($database)
							|| !($result=MetabaseQuery($database,"SELECT * FROM users")))
							{
								$success=0;
							}
							else
							{
								if(!MetabaseEndOfResult($database,$result))
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: transaction rollback did not revert the row that was inserted$eol";
								}
								MetabaseFreeResult($database,$result);
							}
							if($success
							&& $pass)
							{
								if(!InsertTestValues($database,$prepared_query,$data)
								|| !MetabaseCommitTransaction($database)
								|| !($result=MetabaseQuery($database,"SELECT * FROM users")))
									$success=0;
								else
								{
									if(MetabaseEndOfResult($database,$result))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: transaction commit did not make permanent the row that was inserted$eol";
									}
									MetabaseFreeResult($database,$result);
								}
							}
							if($success
							&& $pass)
							{
								if(!($result=MetabaseQuery($database,"DELETE FROM users")))
									$success=0;
							}
							if(!$success)
							{
								$error=MetabaseError($database);
								MetabaseRollbackTransaction($database);
							}
							if(!MetabaseAutoCommitTransactions($database,1))
							{
								if(strcmp($error,""))
									$error.=" and then could not end the transactions";
								$success=0;
							}
							MetabaseFreePreparedQuery($database,$prepared_query);
							if($success
							&& $pass)
							{
								if(!($result=MetabaseQuery($database,"SELECT * FROM users")))
									$success=0;
								else
								{
									if(!MetabaseEndOfResult($database,$result))
									{
										$pass=0;
										echo "FAILED!$eol";
										$failed++;
										echo "Test $test: transaction end with implicit commit when re-enabling auto-commit did not make permanent the rows that were deleted$eol";
									}
									MetabaseFreeResult($database,$result);
								}
							}
						}
						else
							$success=0;
						if($success
						&& $pass)
						{
							$passed++;
							echo "OK.$eol";
						}
					}
				}
				else
					echo "Transactions are not supported.$eol";
			}

			$support_lobs=MetabaseSupport($database,"LOBs");
			if((IsSet($tests["lobstorage"])
			|| IsSet($tests["lobfiles"])
			|| IsSet($tests["lobnulls"]))
			&& $success)
			{
				if($support_lobs)
				{
					$input_file="lob_test.schema";
					if(!($success=$manager->UpdateDatabase($input_file,$input_file.".before",$driver_arguments,$database_variables)))
						$error=$manager->error;
					$debug_output="";
					if(count($manager->warnings)>0)
						$debug_output.="WARNING:$eol".implode($manager->warnings,"!$eol").$eol;
					if($manager->database
					&& IsSet($driver_arguments["CaptureDebug"]))
						$debug_output.=MetabaseDebugOutput($manager->database);
				}
				else
					echo "LOBs are not supported.$eol";
			}

			if($support_lobs
			&& IsSet($tests["lobstorage"])
			&& $success)
			{
				$test="lobstorage";
				echo "Testing lob storage... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM files"))
					$success=0;
				else
				{
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO files (document,picture) VALUES (?,?)")))
					{
						$character_lob=array(
							"Database"=>$database,
							"Error"=>"",
							"Data"=>""
						);
						for($code=32;$code<=127;$code++)
							$character_lob["Data"].=chr($code);
						$binary_lob=array(
							"Database"=>$database,
							"Error"=>"",
							"Data"=>""
						);
						for($code=0;$code<=255;$code++)
							$binary_lob["Data"].=chr($code);
						if(($success=MetabaseCreateLOB($character_lob,$clob)))
						{
							if(($success=MetabaseCreateLOB($binary_lob,$blob)))
							{
								MetabaseQuerySetCLOB($database,$prepared_query,1,$clob,"document");
								MetabaseQuerySetBLOB($database,$prepared_query,2,$blob,"picture");
								$success=MetabaseExecuteQuery($database,$prepared_query);
								MetabaseDestroyLOB($blob);
							}
							else
								$error=$binary_lob["Error"];
							MetabaseDestroyLOB($clob);
						}
						else
							$error=$character_lob["Error"];
						MetabaseFreePreparedQuery($database,$prepared_query);
						if(!$success
						|| !($result=MetabaseQuery($database,"SELECT document,picture FROM files")))
							$success=0;
						else
						{
							if(MetabaseEndOfResult($database,$result))
							{
								$pass=0;
								echo "FAILED!$eol";
								$failed++;
								echo "Test $test: the query result seem to have reached the end of result too soon.$eol";
							}
							else
							{
								$clob=MetabaseFetchCLOBResult($database,$result,0,"document");
								if($clob)
								{
									for($value="";!MetabaseEndOfLOB($clob);)
									{
										if(MetabaseReadLOB($clob,$data,8000)<0)
										{
											$error=MetabaseLOBError($clob);
											$success=0;
											break;
										}
										$value.=$data;
									}
									MetabaseDestroyLOB($clob);
									if($success)
									{
										if(strcmp($value,$character_lob["Data"]))
										{
											$pass=0;
											echo "FAILED!$eol";
											$failed++;
											echo "Test $test: retrieved character LOB value (\"".$value."\") is different from what was stored (\"".$character_lob["Data"]."\")$eol";
										}
										else
										{
											$blob=MetabaseFetchBLOBResult($database,$result,0,"picture");
											if($blob)
											{
												for($value="";!MetabaseEndOfLOB($blob);)
												{
													if(MetabaseReadLOB($blob,$data,8000)<0)
													{
														$error=MetabaseLOBError($blob);
														$success=0;
														break;
													}
													$value.=$data;
												}
												MetabaseDestroyLOB($blob);
												if($success)
												{
													if(strcmp($value,$binary_lob["Data"]))
													{
														$pass=0;
														echo "FAILED!$eol";
														$failed++;
														echo "Test $test: retrieved binary LOB value (\"".$value."\") is different from what was stored (\"".$binary_lob["Data"]."\")$eol";
													}
												}
											}
											else
												$success=0;
										}
									}
								}
								else
									$success=0;
							}
							MetabaseFreeResult($database,$result);
						}
					}
					else
						$success=0;
				}
				if($success
				&& $pass)
				{
					$passed++;
					echo "OK.$eol";
				}
			}

			if($support_lobs
			&& IsSet($tests["lobfiles"])
			&& $success)
			{
				$test="lobfiles";
				echo "Testing lob storage from and to files... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM files"))
					$success=0;
				else
				{
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO files (document,picture) VALUES (?,?)")))
					{
						$character_data_file="character_data";
						if(($file=fopen($character_data_file,"wb")))
						{
							for($character_data="",$code=32;$code<=127;$code++)
								$character_data.=chr($code);
							$character_lob=array(
								"Type"=>"inputfile",
								"Database"=>$database,
								"Error"=>"",
								"FileName"=>$character_data_file,
								"BufferLength"=>32
							);
							$success=(fwrite($file,$character_data,strlen($character_data))==strlen($character_data));
							fclose($file);
							if($success)
							{
								$binary_data_file="binary_data";
								if(($file=fopen($binary_data_file,"wb")))
								{
									for($binary_data="",$code=0;$code<=255;$code++)
										$binary_data.=chr($code);
									$binary_lob=array(
										"Type"=>"inputfile",
										"Database"=>$database,
										"Error"=>"",
										"FileName"=>$binary_data_file,
										"BufferLength"=>32
									);
									$success=(fwrite($file,$binary_data,strlen($binary_data))==strlen($binary_data));
									fclose($file);
								}
								else
									$success=0;
							}
						}
						else
							$sucess=0;
						if($success)
						{
							if(($success=MetabaseCreateLOB($character_lob,$clob)))
							{
								if(($success=MetabaseCreateLOB($binary_lob,$blob)))
								{
									MetabaseQuerySetCLOB($database,$prepared_query,1,$clob,"document");
									MetabaseQuerySetBLOB($database,$prepared_query,2,$blob,"picture");
									$success=MetabaseExecuteQuery($database,$prepared_query);
									MetabaseDestroyLOB($blob);
								}
								else
									$error=$binary_lob["Error"];
								MetabaseDestroyLOB($clob);
							}
							else
								$error=$character_lob["Error"];
							MetabaseFreePreparedQuery($database,$prepared_query);
							if(!$success
							|| !($result=MetabaseQuery($database,"SELECT document,picture FROM files")))
								$success=0;
							else
							{
								if(MetabaseEndOfResult($database,$result))
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: the query result seem to have reached the end of result too soon.$eol";
								}
								else
								{
									$character_lob=array(
										"Type"=>"outputfile",
										"Database"=>$database,
										"Result"=>$result,
										"Row"=>0,
										"Field"=>"document",
										"Binary"=>0,
										"Error"=>"",
										"FileName"=>$character_data_file,
										"BufferLength"=>32
									);
									if(($success=MetabaseCreateLOB($character_lob,$clob)))
									{
										if(MetabaseReadLOB($clob,$data,0)<0)
										{
											$error=MetabaseLOBError($clob);
											$success=0;
										}
										MetabaseDestroyLOB($clob);
										if($success)
										{
											if(($file=fopen($character_data_file,"rb")))
											{
												if(GetType($value=fread($file,filesize($character_data_file)))!="string")
												{
													$success=0;
													$error="could not read from the character lob data file";
												}
												fclose($file);
											}
											else
											{
												$success=0;
												$error="could not reopen the character lob data file";
											}
										}
										if($success)
										{
											if(strcmp($value,$character_data))
											{
												$pass=0;
												echo "FAILED!$eol";
												$failed++;
												echo "Test $test: retrieved character LOB value (\"".$value."\") is different from what was stored (\"".$character_data."\")$eol";
											}
											else
											{
												$binary_lob=array(
													"Type"=>"outputfile",
													"Database"=>$database,
													"Result"=>$result,
													"Row"=>0,
													"Field"=>"picture",
													"Binary"=>1,
													"Error"=>"",
													"FileName"=>$binary_data_file,
													"BufferLength"=>32
												);
												if(($success=MetabaseCreateLOB($binary_lob,$blob)))
												{
													if(MetabaseReadLOB($blob,$data,0)<0)
													{
														$error=MetabaseLOBError($clob);
														$success=0;
													}
													MetabaseDestroyLOB($blob);
													if($success)
													{
														if(($file=fopen($binary_data_file,"rb")))
														{
															if(GetType($value=fread($file,filesize($binary_data_file)))!="string")
															{
																$success=0;
																$error="could not read from the binary lob data file";
															}
															fclose($file);
														}
														else
														{
															$success=0;
															$error="could not reopen the binary lob data file";
														}
													}
													if($success)
													{
														if(strcmp($value,$binary_data))
														{
															$pass=0;
															echo "FAILED!$eol";
															$failed++;
															echo "Test $test: retrieved binary LOB value (\"".$value."\") is different from what was stored (\"".$binary_data."\")$eol";
														}
													}
												}
												else
													$success=0;
											}
										}
									}
									else
										$error=$character_lob["Error"];
								}
								MetabaseFreeResult($database,$result);
							}
						}
						else
							$success=0;
					}
				}
				if($success
				&& $pass)
				{
					$passed++;
					echo "OK.$eol";
				}
			}

			if($support_lobs
			&& IsSet($tests["lobnulls"])
			&& $success)
			{
				$test="lobnulls";
				echo "Testing lob nulls... ";
				flush();
				$pass=1;
				if(!MetabaseQuery($database,"DELETE FROM files"))
					$success=0;
				else
				{
					if(($prepared_query=MetabasePrepareQuery($database,"INSERT INTO files (document,picture) VALUES (?,?)")))
					{
						MetabaseQuerySetNULL($database,$prepared_query,1,"clob");
						MetabaseQuerySetNULL($database,$prepared_query,2,"blob");
						$success=MetabaseExecuteQuery($database,$prepared_query);
						MetabaseFreePreparedQuery($database,$prepared_query);
						if(!$success
						|| !($result=MetabaseQuery($database,"SELECT document,picture FROM files")))
							$success=0;
						else
						{
							if(MetabaseEndOfResult($database,$result))
							{
								$pass=0;
								echo "FAILED!$eol";
								$failed++;
								echo "Test $test: the query result seem to have reached the end of result too soon.$eol";
							}
							else
							{
								if(!MetabaseResultIsNull($database,$result,0,$field="document")
								|| !MetabaseResultIsNull($database,$result,0,$field="picture"))
								{
									$pass=0;
									echo "FAILED!$eol";
									$failed++;
									echo "Test $test: a query result large object column is not NULL unlike what was expected$eol";
								}
							}
							MetabaseFreeResult($database,$result);
						}
					}
					else
						$success=0;
				}
				if($success
				&& $pass)
				{
					$passed++;
					echo "OK.$eol";
				}
			}

			if(!$success
			&& !strcmp($error,""))
				$error=MetabaseError($database);
			if(IsSet($driver_arguments["CaptureDebug"]))
				$debug_output.=MetabaseDebugOutput($database);
			MetabaseCloseSetup($database);
		}
	}
	else
		$error=$manager->error;
	if(strcmp($error,""))
		echo "Error: $error$eol";
	else
	{
		echo ($failed==0 ? "Passed all the $passed tests that were performed!$eol" : ($passed==1 ? "Passed one test" : "$passed tests passed").", ".($failed==1 ? "Failed one test" : "$failed tests failed")."!$eol");
	}
	if(IsSet($driver_arguments["CaptureDebug"]))
		echo $debug_output;
	echo "Exiting.$eol"; flush();
?>
