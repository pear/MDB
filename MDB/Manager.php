<?
/*
 * manager.php
 */

class MDB_manager
{
	var $fail_on_invalid_names=1;
	var $error="";
	var $warnings=array();
	var $database=0;
	var $database_definition=array(
		"name"=>"",
		"create"=>0,
		"TABLES"=>array()
	);

	function setupDatabase(&$arguments)
	{
		global $databases;
		if(IsSet($arguments["Debug"])) {
			$this->debug=$arguments["Debug"];
		}
		if(strcmp($error=setupDatabase($arguments,$this->database),"")) {
			return($error);
		}
		if(!IsSet($arguments["Debug"])) {
			$databases[$this->database]->captureDebugOutput(1);
		}
		return("");
	}

	function closeSetup()
	{
		if($this->database!=0) {
			global $databases;
			$databases[$this->database]->closeSetup();
		}
	}

	function gtField(&$field,$field_name,$declaration,&$query)
	{
		global $databases;
		if(!strcmp($field_name,"")) {
			return("it was not specified a valid field name (\"$field_name\")");
		}
		switch($field["type"]) {
			case "integer":
				if($declaration) {
					$query=$databases[$this->database]->getIntegerFieldTypeDeclaration($field_name,$field);
				} else {
					$query=$field_name;
				}
				break;
			case "text":
				if($declaration)
					$query=$databases[$this->database]->getTextFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "clob":
				if($declaration)
					$query=$databases[$this->database]->getClobFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "blob":
				if($declaration)
					$query=$databases[$this->database]->getBlobFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "boolean":
				if($declaration)
					$query=$databases[$this->database]->getBooleanFieldTypeDeclaration(field_name,$field);
				else
					$query=$field_name;
				break;
			case "date":
				if($declaration)
					$query=$databases[$this->database]->getDateFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "timestamp":
				if($declaration)
					$query=$databases[$this->database]->getTimestampFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "time":
				if($declaration)
					$query=$databases[$this->database]->getTimeFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "float":
				if($declaration)
					$query=$databases[$this->database]->getFloatFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			case "decimal":
				if($declaration)
					$query=$databases[$this->database]->getDecimalFieldTypeDeclaration($field_name,$field);
				else
					$query=$field_name;
				break;
			default:
				return("type \"".$field["type"]."\" is not yet supported");
		}
		return("");
	}

	function getFieldList($fields,$declaration,&$query_fields)
	{
		for($query_fields="",reset($fields),$field_number=0;
			$field_number<count($fields);
			$field_number++,next($fields))
		{
			if($field_number>0) {
				$query_fields.=",";
			}
			$field_name=Key($fields);
			if(strcmp($error=$this->GetField($fields[$field_name],$field_name,$declaration,$query),"")) {
				return($error);
			}
			$query_fields.=$query;
		}
		return("");
	}

	function getFields($table,&$fields)
	{
		return($this->getFieldList($this->database_definition["TABLES"][$table]["FIELDS"],0,$fields));
	}

	function createTable($table_name,$table)
	{
		global $databases;
		$databases[$this->database]->debug("Create table: ".$table_name);
		if(!$databases[$this->database]->createTable($table_name,$table["FIELDS"])) {
			return($databases[$this->database]->error());
		}
		$success=1;
		$error="";
		if(IsSet($table["initialization"]))	{
			$instructions=$table["initialization"];
			for(reset($instructions),$instruction=0;
				$success && $instruction<count($instructions);
				$instruction++,next($instructions))
			{
				switch($instructions[$instruction]["type"]) {
					case "insert":
						$fields=$instructions[$instruction]["FIELDS"];
						for($query_fields=$query_values="",reset($fields),$field_number=0;
							$field_number<count($fields);
							$field_number++,next($fields))
						{
							if($field_number>0) {
								$query_fields.=",";
								$query_values.=",";
							}
							$field_name=key($fields);
							$field=$table["FIELDS"][$field_name];
							if(strcmp($error=$this->GetField($field,$field_name,0,$query),"")) {
								return($error);
							}
							$query_fields.=$query;
							$query_values.="?";
						}
						if(($success=($prepared_query=$databases[$this->database]->
							prepareQuery("INSERT INTO $table_name ($query_fields) VALUES ($query_values)"))))
						{
							for($lobs=array(),reset($fields),$field_number=0;
								$field_number<count($fields);
								$field_number++,next($fields))
							{
								$field_name=key($fields);
								$field=$table["FIELDS"][$field_name];
								if(strcmp($error=$this->GetField($field,$field_name,0,$query),"")) {
									return($error);
								}
								switch($field["type"]) {
									case "integer":
										$success=$databases[$this->database]->querySetInteger($prepared_query,$field_number+1,intval($fields[$field_name]));
										break;
									case "text":
										$success=$databases[$this->database]->querySetText($prepared_query,$field_number+1,$fields[$field_name]);
										break;
									case "clob":
										$lob_definition=array(
											"Database"=>$this->database,
											"Error"=>"",
											"Data"=>$fields[$field_name]
										);
										$lob=count($lobs);
										if(!($success=createLOB($lob_definition,$lobs[$lob])))
										{
											$error=$lob_definition["Error"];
											break;
										}
										$success=$databases[$this->database]->querySetCLOB($prepared_query,$field_number+1,$lobs[$lob],$field_name);
										break;
									case "blob":
										$lob_definition=array(
											"Database"=>$this->database,
											"Error"=>"",
											"Data"=>$fields[$field_name]
										);
										$lob=count($lobs);
										if(!($success=createLOB($lob_definition,$lobs[$lob])))
										{
											$error=$lob_definition["Error"];
											break;
										}
										$success=$databases[$this->database]->querySetBLOB($prepared_query,$field_number+1,$lobs[$lob],$field_name);
										break;
									case "boolean":
										$success=$databases[$this->database]->querySetBoolean($prepared_query,$field_number+1,intval($fields[$field_name]));
										break;
									case "date":
										$success=$databases[$this->database]->querySetDate($prepared_query,$field_number+1,$fields[$field_name]);
										break;
									case "timestamp":
										$success=$databases[$this->database]->querySetTimestamp($prepared_query,$field_number+1,$fields[$field_name]);
										break;
									case "time":
										$success=$databases[$this->database]->querySetTime($prepared_query,$field_number+1,$fields[$field_name]);
										break;
									case "float":
										$success=$databases[$this->database]->querySetFloat($prepared_query,$field_number+1,doubleval($fields[$field_name]));
										break;
									case "decimal":
										$success=$databases[$this->database]->querySetDecimal($prepared_query,$field_number+1,$fields[$field_name]);
										break;
									default:
										$error="type \"".$field["type"]."\" is not yet supported";
										$success=0;
										break;
								}
								if(!$success
								&& $error=="")
								{
									$error=$databases[$this->database]->error();
									break;
								}
							}
							if($success
							&& !($success=$databases[$this->database]->executeQuery($prepared_query)))
								$error=$databases[$this->database]->error();
							for($lob=0;$lob<count($lobs);$lob++)
								destroyLOB($lobs[$lob]);
							$databases[$this->database]->freePreparedQuery($prepared_query);
						}
						else
							$error=$databases[$this->database]->error();
						break;
				}
			}
		}
		if($success
		&& IsSet($table["INDEXES"]))
		{
			if(!$databases[$this->database]->support("Indexes"))
				return("indexes are not supported");
			$indexes=$table["INDEXES"];
			for($index=0,reset($indexes);
				$index<count($indexes);
				next($indexes),$index++)
			{
				if(!$databases[$this->database]->createIndex($table_name,key($indexes),$indexes[key($indexes)]))
				{
					$error=$databases[$this->database]->error();
					$success=0;
					break;
				}
			}
		}
		if(!$success)
		{
			if(!$databases[$this->database]->dropTable($table_name))
				$error="could not initialize the table \"$table_name\" ($error) and then could not drop the table (".$databases[$this->database]->error().")"; 
		}
		return($error);
	}

	function DropTable($table_name)
	{
		global $databases;
		return($databases[$this->database]->dropTable($table_name) ? "" : $databases[$this->database]->error());
	}

	function CreateSequence($sequence_name,$sequence,$created_on_table)
	{
		if(!$databases[$this->database]->support("Sequences"))
			return("sequences are not supported");
		global $databases;
		$databases[$this->database]->debug("Create sequence: ".$sequence_name);
		if(!IsSet($sequence_name)
		|| !strcmp($sequence_name,""))
			return("it was not specified a valid sequence name");
		$start=$sequence["start"];
		if(IsSet($sequence["on"])
		&& !$created_on_table)
		{
			$table=$sequence["on"]["table"];
			$field=$sequence["on"]["field"];
			if($databases[$this->database]->support("Summaryfunctions"))
				$field="MAX($field)";
			if(!($result=$databases[$this->database]->query("SELECT $field FROM $table")))
				return($databases[$this->database]->error());
			if(($rows=$databases[$this->database]->numberOfRows($result)))
			{
				for($row=0;$row<$rows;$row++)
				{
					if(!$databases[$this->database]->resultIsNull($result,$row,0)
					&& ($value=$databases[$this->database]->fetchResult($result,$row,0)+1)>$start)
						$start=$value;
				}
			}
			$databases[$this->database]->freeResult($result);
		}
		if(!$databases[$this->database]->createSequence($sequence_name,$start))
			return($databases[$this->database]->error());
		return("");
	}

	function DropSequence($sequence_name)
	{
		global $databases;
		return($databases[$this->database]->dropSequence($sequence_name) ? "" : $databases[$this->database]->error());
	}

	function CreateDatabase()
	{
		if(!IsSet($this->database_definition["name"])
		|| !strcmp($this->database_definition["name"],""))
			return("it was not specified a valid database name");
		global $databases;
		$create=(IsSet($this->database_definition["create"]) && $this->database_definition["create"]);
		if($create)
		{
			$databases[$this->database]->debug("Create database: ".$this->database_definition["name"]);
			if(!$databases[$this->database]->createDatabase($this->database_definition["name"]))
			{
				$error=$databases[$this->database]->error();
				$databases[$this->database]->debug("Create database error: ".$error);
				return($error);
			}
		}
		$previous_database_name=$databases[$this->database]->setDatabase($this->database_definition["name"]);
		if(($support_transactions=$databases[$this->database]->support("Transactions"))
		&& !$databases[$this->database]->autoCommitTransactions(0))
			return($databases[$this->database]->error());
		$created_objects=0;
		for($error="",Reset($this->database_definition["TABLES"]),$table=0;$table<count($this->database_definition["TABLES"]);Next($this->database_definition["TABLES"]),$table++)
		{
			$table_name=Key($this->database_definition["TABLES"]);
			if(strcmp($error=$this->CreateTable($table_name,$this->database_definition["TABLES"][$table_name]),""))
				break;
			$created_objects++;
		}
		if(!strcmp($error,"")
		&& IsSet($this->database_definition["SEQUENCES"]))
		{
			for($error="",Reset($this->database_definition["SEQUENCES"]),$sequence=0;$sequence<count($this->database_definition["SEQUENCES"]);Next($this->database_definition["SEQUENCES"]),$sequence++)
			{
				$sequence_name=Key($this->database_definition["SEQUENCES"]);
				if(strcmp($error=$this->CreateSequence($sequence_name,$this->database_definition["SEQUENCES"][$sequence_name],1),""))
					break;
				$created_objects++;
			}
		}
		if(strcmp($error,""))
		{
			if($created_objects)
			{
				if($support_transactions)
				{
					if(!$databases[$this->database]->rollbackTransaction())
						$error="Could not rollback the partially created database alterations: Rollback error: ".$databases[$this->database]->error()." Creation error: $error";
				}
				else
					$error="the database was only partially created: $error";
			}
		}
		else
		{
			if($support_transactions)
			{
				if(!$databases[$this->database]->autoCommitTransactions(1))
					$error="Could not end transaction after successfully created the database: ".$databases[$this->database]->error();
			}
		}
		$databases[$this->database]->setDatabase($previous_database_name);
		if(strcmp($error,"")
		&& $create
		&& !$databases[$this->database]->dropDatabase($this->database_definition["name"]))
			$error="Could not drop the created database after unsuccessful creation attempt: ".$databases[$this->database]->error()." Creation error: ".$error;
		return($error);
	}

	function AddDefinitionChange(&$changes,$definition,$item,$change)
	{
		if(!IsSet($changes[$definition][$item]))
			$changes[$definition][$item]=array();
		for($change_number=0,Reset($change);$change_number<count($change);Next($change),$change_number++)
		{
			$name=Key($change);
			if(!strcmp(GetType($change[$name]),"array"))
			{
				if(!IsSet($changes[$definition][$item][$name]))
					$changes[$definition][$item][$name]=array();
				$change_parts=$change[$name];
				for($change_part=0,Reset($change_parts);$change_part<count($change_parts);Next($change_parts),$change_part++)
					$changes[$definition][$item][$name][Key($change_parts)]=$change_parts[Key($change_parts)];
			} 
			else
				$changes[$definition][$item][Key($change)]=$change[Key($change)];
		}
	}

	function CompareDefinitions(&$previous_definition,&$changes)
	{
		global $databases;
		$changes=array();
		for($defined_tables=array(),Reset($this->database_definition["TABLES"]),$table=0;$table<count($this->database_definition["TABLES"]);Next($this->database_definition["TABLES"]),$table++)
		{
			$table_name=Key($this->database_definition["TABLES"]);
			$was_table_name=$this->database_definition["TABLES"][$table_name]["was"];
			if(IsSet($previous_definition["TABLES"][$table_name])
			&& IsSet($previous_definition["TABLES"][$table_name]["was"])
			&& !strcmp($previous_definition["TABLES"][$table_name]["was"],$was_table_name))
				$was_table_name=$table_name;
			if(IsSet($previous_definition["TABLES"][$was_table_name]))
			{
				if(strcmp($was_table_name,$table_name))
				{
					$this->AddDefinitionChange($changes,"TABLES",$was_table_name,array("name"=>$table_name));
					$databases[$this->database]->debug("Renamed table '$was_table_name' to '$table_name'");
				}
				if(IsSet($defined_tables[$was_table_name]))
					return("the table '$was_table_name' was specified as base of more than of table of the database");
				$defined_tables[$was_table_name]=1;

				$fields=$this->database_definition["TABLES"][$table_name]["FIELDS"];
				$previous_fields=$previous_definition["TABLES"][$was_table_name]["FIELDS"];
				for($defined_fields=array(),Reset($fields),$field=0;$field<count($fields);Next($fields),$field++)
				{
					$field_name=Key($fields);
					$was_field_name=$fields[$field_name]["was"];
					if(IsSet($previous_fields[$field_name])
					&& IsSet($previous_fields[$field_name]["was"])
					&& !strcmp($previous_fields[$field_name]["was"],$was_field_name))
						$was_field_name=$field_name;
					if(IsSet($previous_fields[$was_field_name]))
					{
						if(strcmp($was_field_name,$field_name))
						{
							$field_declaration=$fields[$field_name];
							if(strcmp($error=$this->GetField($field_declaration,$field_name,1,$query),""))
								return($error);
							$this->AddDefinitionChange($changes,"TABLES",$was_table_name,array("RenamedFields"=>array($was_field_name=>array(
								"name"=>$field_name,
								"Declaration"=>$query
							))));
							$databases[$this->database]->debug("Renamed field '$was_field_name' to '$field_name' in table '$table_name'");
						}
						if(IsSet($defined_fields[$was_field_name]))
							return("the field '$was_field_name' was specified as base of more than one field of table '$table_name'");
						$defined_fields[$was_field_name]=1;
						$change=array();
						if(!strcmp($fields[$field_name]["type"],$previous_fields[$was_field_name]["type"]))
						{
							switch($fields[$field_name]["type"])
							{
								case "integer":
									$previous_unsigned=IsSet($previous_fields[$was_field_name]["unsigned"]);
									$unsigned=IsSet($fields[$field_name]["unsigned"]);
									if(strcmp($previous_unsigned,$unsigned))
									{
										$change["unsigned"]=$unsigned;
										$databases[$this->database]->debug("Changed field '$field_name' type from '".($previous_unsigned ? "unsigned " : "").$previous_fields[$was_field_name]["type"]."' to '".($unsigned ? "unsigned " : "").$fields[$field_name]["type"]."' in table '$table_name'");
									}
									break;
								case "text":
								case "clob":
								case "blob":
									$previous_length=(IsSet($previous_fields[$was_field_name]["length"]) ? $previous_fields[$was_field_name]["length"] : 0);
									$length=(IsSet($fields[$field_name]["length"]) ? $fields[$field_name]["length"] : 0);
									if(strcmp($previous_length,$length))
									{
										$change["length"]=$length;
										$databases[$this->database]->debug("Changed field '$field_name' length from '".$previous_fields[$was_field_name]["type"].($previous_length==0 ? " no length" : "($previous_length)")."' to '".$fields[$field_name]["type"].($length==0 ? " no length" : "($length)")."' in table '$table_name'");
									}
									break;
								case "date":
								case "timestamp":
								case "time":
								case "boolean":
								case "float":
								case "decimal":
									break;
								default:
									return("type \"".$fields[$field_name]["type"]."\" is not yet supported");
							}

							$previous_notnull=IsSet($previous_fields[$was_field_name]["notnull"]);
							$notnull=IsSet($fields[$field_name]["notnull"]);
							if($previous_notnull!=$notnull)
							{
								$change["ChangedNotNull"]=1;
								if($notnull)
									$change["notnull"]=IsSet($fields[$field_name]["notnull"]);
								$databases[$this->database]->debug("Changed field '$field_name' notnull from $previous_notnull to $notnull in table '$table_name'");
							}

							$previous_default=IsSet($previous_fields[$was_field_name]["default"]);
							$default=IsSet($fields[$field_name]["default"]);
							if(strcmp($previous_default,$default))
							{
								$change["ChangedDefault"]=1;
								if($default)
									$change["default"]=$fields[$field_name]["default"];
								$databases[$this->database]->debug("Changed field '$field_name' default from ".($previous_default ? "'".$previous_fields[$was_field_name]["default"]."'" : "NULL")." TO ".($default ? "'".$fields[$field_name]["default"]."'" : "NULL")." IN TABLE '$table_name'");
							}
							else
							{
								if($default
								&& strcmp($previous_fields[$was_field_name]["default"],$fields[$field_name]["default"]))
								{
									$change["ChangedDefault"]=1;
									$change["default"]=$fields[$field_name]["default"];
									$databases[$this->database]->debug("Changed field '$field_name' default from '".$previous_fields[$was_field_name]["default"]."' to '".$fields[$field_name]["default"]."' in table '$table_name'");
								}
							}
						}
						else
						{
							$change["type"]=$fields[$field_name]["type"];
							$databases[$this->database]->debug("Changed field '$field_name' type from '".$previous_fields[$was_field_name]["type"]."' to '".$fields[$field_name]["type"]."' in table '$table_name'");
						}
						if(count($change))
						{
							$field_declaration=$fields[$field_name];
							if(strcmp($error=$this->GetField($field_declaration,$field_name,1,$query),""))
								return($error);
							$change["Declaration"]=$query;
							$change["Definition"]=$field_declaration;
							$this->AddDefinitionChange($changes,"TABLES",$was_table_name,array("ChangedFields"=>array($field_name=>$change)));
						}
					}
					else
					{
						if(strcmp($field_name,$was_field_name))
							return("it was specified a previous field name ('$was_field_name') for field '$field_name' of table '$table_name' that does not exist");
						$field_declaration=$fields[$field_name];
						if(strcmp($error=$this->GetField($field_declaration,$field_name,1,$query),""))
							return($error);
						$field_declaration["Declaration"]=$query;
						$this->AddDefinitionChange($changes,"TABLES",$table_name,array("AddedFields"=>array($field_name=>$field_declaration)));
						$databases[$this->database]->debug("Added field '$field_name' to table '$table_name'");
					}
				}
				for(Reset($previous_fields),$field=0;$field<count($previous_fields);Next($previous_fields),$field++)
				{
					$field_name=Key($previous_fields);
					if(!IsSet($defined_fields[$field_name]))
					{
						$this->AddDefinitionChange($changes,"TABLES",$table_name,array("RemovedFields"=>array($field_name=>array())));
						$databases[$this->database]->debug("Removed field '$field_name' from table '$table_name'");
					}
				}

				$indexes=(IsSet($this->database_definition["TABLES"][$table_name]["INDEXES"]) ? $this->database_definition["TABLES"][$table_name]["INDEXES"] : array());
				$previous_indexes=(IsSet($previous_definition["TABLES"][$was_table_name]["INDEXES"]) ? $previous_definition["TABLES"][$was_table_name]["INDEXES"] : array());
				for($defined_indexes=array(),Reset($indexes),$index=0;$index<count($indexes);Next($indexes),$index++)
				{
					$index_name=Key($indexes);
					$was_index_name=$indexes[$index_name]["was"];
					if(IsSet($previous_indexes[$index_name])
					&& IsSet($previous_indexes[$index_name]["was"])
					&& !strcmp($previous_indexes[$index_name]["was"],$was_index_name))
						$was_index_name=$index_name;
					if(IsSet($previous_indexes[$was_index_name]))
					{
						$change=array();

						if(strcmp($was_index_name,$index_name))
						{
							$change["name"]=$was_index_name;
							$databases[$this->database]->debug("Changed index '$was_index_name' name to '$index_name' in table '$table_name'");
						}
						if(IsSet($defined_indexes[$was_index_name]))
							return("the index '$was_index_name' was specified as base of more than one index of table '$table_name'");
						$defined_indexes[$was_index_name]=1;

						$previous_unique=IsSet($previous_indexes[$was_index_name]["unique"]);
						$unique=IsSet($indexes[$index_name]["unique"]);
						if($previous_unique!=$unique)
						{
							$change["ChangedUnique"]=1;
							if($unique)
								$change["unique"]=$unique;
							$databases[$this->database]->debug("Changed index '$index_name' unique from $previous_unique to $unique in table '$table_name'");
						}

						$fields=$indexes[$index_name]["FIELDS"];
						$previous_fields=$previous_indexes[$was_index_name]["FIELDS"];
						for($defined_fields=array(),Reset($fields),$field=0;$field<count($fields);Next($fields),$field++)
						{
							$field_name=Key($fields);
							if(IsSet($previous_fields[$field_name]))
							{
								$defined_fields[$field_name]=1;
								$sorting=(IsSet($fields[$field_name]["sorting"]) ? $fields[$field_name]["sorting"] : "");
								$previous_sorting=(IsSet($previous_fields[$field_name]["sorting"]) ? $previous_fields[$field_name]["sorting"] : "");
								if(strcmp($sorting,$previous_sorting))
								{
									$databases[$this->database]->debug("Changed index field '$field_name' sorting default from '$previous_sorting' to '$sorting' in table '$table_name'");
									$change["ChangedFields"]=1;
								}
							}
							else
							{
								$change["ChangedFields"]=1;
								$databases[$this->database]->debug("Added field '$field_name' to index '$index_name' of table '$table_name'");
							}
						}
						for(Reset($previous_fields),$field=0;$field<count($previous_fields);Next($previous_fields),$field++)
						{
							$field_name=Key($previous_fields);
							if(!IsSet($defined_fields[$field_name]))
							{
								$change["ChangedFields"]=1;
								$databases[$this->database]->debug("Removed field '$field_name' from index '$index_name' of table '$table_name'");
							}
						}

						if(count($change))
							$this->AddDefinitionChange($changes,"INDEXES",$table_name,array("ChangedIndexes"=>array($index_name=>$change)));

					}
					else
					{
						if(strcmp($index_name,$was_index_name))
							return("it was specified a previous index name ('$was_index_name') for index '$index_name' of table '$table_name' that does not exist");
						$this->AddDefinitionChange($changes,"INDEXES",$table_name,array("AddedIndexes"=>array($index_name=>$indexes[$index_name])));
						$databases[$this->database]->debug("Added index '$index_name' to table '$table_name'");
					}
				}
				for(Reset($previous_indexes),$index=0;$index<count($previous_indexes);Next($previous_indexes),$index++)
				{
					$index_name=Key($previous_indexes);
					if(!IsSet($defined_indexes[$index_name]))
					{
						$this->AddDefinitionChange($changes,"INDEXES",$table_name,array("RemovedIndexes"=>array($index_name=>1)));
						$databases[$this->database]->debug("Removed index '$index_name' from table '$table_name'");
					}
				}

			}
			else
			{
				if(strcmp($table_name,$was_table_name))
					return("it was specified a previous table name ('$was_table_name') for table '$table_name' that does not exist");
				$this->AddDefinitionChange($changes,"TABLES",$table_name,array("Add"=>1));
				$databases[$this->database]->debug("Added table '$table_name'");
			}
		}
		for(Reset($previous_definition["TABLES"]),$table=0;$table<count($previous_definition["TABLES"]);Next($previous_definition["TABLES"]),$table++)
		{
			$table_name=Key($previous_definition["TABLES"]);
			if(!IsSet($defined_tables[$table_name]))
			{
				$this->AddDefinitionChange($changes,"TABLES",$table_name,array("Remove"=>1));
				$databases[$this->database]->debug("Removed table '$table_name'");
			}
		}
		if(IsSet($this->database_definition["SEQUENCES"]))
		{
			for($defined_sequences=array(),Reset($this->database_definition["SEQUENCES"]),$sequence=0;$sequence<count($this->database_definition["SEQUENCES"]);Next($this->database_definition["SEQUENCES"]),$sequence++)
			{
				$sequence_name=Key($this->database_definition["SEQUENCES"]);
				$was_sequence_name=$this->database_definition["SEQUENCES"][$sequence_name]["was"];
				if(IsSet($previous_definition["SEQUENCES"][$sequence_name])
				&& IsSet($previous_definition["SEQUENCES"][$sequence_name]["was"])
				&& !strcmp($previous_definition["SEQUENCES"][$sequence_name]["was"],$was_sequence_name))
					$was_sequence_name=$sequence_name;
				if(IsSet($previous_definition["SEQUENCES"][$was_sequence_name]))
				{
					if(strcmp($was_sequence_name,$sequence_name))
					{
						$this->AddDefinitionChange($changes,"SEQUENCES",$was_sequence_name,array("name"=>$sequence_name));
						$databases[$this->database]->debug("Renamed sequence '$was_sequence_name' to '$sequence_name'");
					}
					if(IsSet($defined_sequences[$was_sequence_name]))
						return("the sequence '$was_sequence_name' was specified as base of more than of sequence of the database");
					$defined_sequences[$was_sequence_name]=1;
					$change=array();
					if(strcmp($this->database_definition["SEQUENCES"][$sequence_name]["start"],$previous_definition["SEQUENCES"][$was_sequence_name]["start"]))
					{
						$change["start"]=$this->database_definition["SEQUENCES"][$sequence_name]["start"];
						$databases[$this->database]->debug("Changed sequence '$sequence_name' start from '".$previous_definition["SEQUENCES"][$was_sequence_name]["start"]."' to '".$this->database_definition["SEQUENCES"][$sequence_name]["start"]."'");
					}
					if(strcmp($this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"],$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["table"])
					|| strcmp($this->database_definition["SEQUENCES"][$sequence_name]["on"]["field"],$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["field"]))
					{
						$change["on"]=$this->database_definition["SEQUENCES"][$sequence_name]["on"];
						$databases[$this->database]->debug("Changed sequence '$sequence_name' on table field from '".$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["table"].".".$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["field"]."' to '".$this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"].".".$this->database_definition["SEQUENCES"][$sequence_name]["on"]["field"]."'");
					}
					if(count($change))
						$this->AddDefinitionChange($changes,"SEQUENCES",$was_sequence_name,array("Change"=>array($sequence_name=>array($change))));
				}
				else
				{
					if(strcmp($sequence_name,$was_sequence_name))
						return("it was specified a previous sequence name ('$was_sequence_name') for sequence '$sequence_name' that does not exist");
					$this->AddDefinitionChange($changes,"SEQUENCES",$sequence_name,array("Add"=>1));
					$databases[$this->database]->debug("Added sequence '$sequence_name'");
				}
			}
		}
		if(IsSet($previous_definition["SEQUENCES"]))
		{
			for(Reset($previous_definition["SEQUENCES"]),$sequence=0;$sequence<count($previous_definition["SEQUENCES"]);Next($previous_definition["SEQUENCES"]),$sequence++)
			{
				$sequence_name=Key($previous_definition["SEQUENCES"]);
				if(!IsSet($defined_sequences[$sequence_name]))
				{
					$this->AddDefinitionChange($changes,"SEQUENCES",$sequence_name,array("Remove"=>1));
					$databases[$this->database]->debug("Removed sequence '$sequence_name'");
				}
			}
		}
		return("");
	}

	function AlterDatabase(&$previous_definition,&$changes)
	{
		global $databases;
		if(IsSet($changes["TABLES"]))
		{
			for($change=0,Reset($changes["TABLES"]);$change<count($changes["TABLES"]);Next($changes["TABLES"]),$change++)
			{
				$table_name=Key($changes["TABLES"]);
				if(IsSet($changes["TABLES"][$table_name]["Add"])
				|| IsSet($changes["TABLES"][$table_name]["Remove"]))
					continue;
				if(!$databases[$this->database]->alterTable($table_name,$changes["TABLES"][$table_name],1))
					return("database driver is not able to perform the requested alterations: ".$databases[$this->database]->error());
			}
		}
		if(IsSet($changes["SEQUENCES"]))
		{
			if(!$databases[$this->database]->support("Sequences"))
				return("sequences are not supported");
			for($change=0,Reset($changes["SEQUENCES"]);$change<count($changes["SEQUENCES"]);Next($changes["SEQUENCES"]),$change++)
			{
				$sequence_name=Key($changes["SEQUENCES"]);
				if(IsSet($changes["SEQUENCES"][$sequence_name]["Add"])
				|| IsSet($changes["SEQUENCES"][$sequence_name]["Remove"])
				|| IsSet($changes["SEQUENCES"][$sequence_name]["Change"]))
					continue;
				return("some sequences changes are not yet supported");
			}
		}
		if(IsSet($changes["INDEXES"]))
		{
			if(!$databases[$this->database]->support("Indexes"))
				return("indexes are not supported");
			for($change=0,Reset($changes["INDEXES"]);$change<count($changes["INDEXES"]);Next($changes["INDEXES"]),$change++)
			{
				$table_name=Key($changes["INDEXES"]);
				$table_changes=count($changes["INDEXES"][$table_name]);
				if(IsSet($changes["INDEXES"][$table_name]["AddedIndexes"]))
					$table_changes--;
				if(IsSet($changes["INDEXES"][$table_name]["RemovedIndexes"]))
					$table_changes--;
				if(IsSet($changes["INDEXES"][$table_name]["ChangedIndexes"]))
					$table_changes--;
				if($table_changes)
					return("index alteration not yet supported");
			}
		}
		$previous_database_name=$databases[$this->database]->setDatabase($this->database_definition["name"]);
		if(($support_transactions=$databases[$this->database]->support("Transactions"))
		&& !$databases[$this->database]->autoCommitTransactions(0))
			return($databases[$this->database]->error());
		$error="";
		$alterations=0;
		if(IsSet($changes["INDEXES"]))
		{
			for($change=0,Reset($changes["INDEXES"]);$change<count($changes["INDEXES"]);Next($changes["INDEXES"]),$change++)
			{
				$table_name=Key($changes["INDEXES"]);
				if(IsSet($changes["INDEXES"][$table_name]["RemovedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["RemovedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
					{
						if(!$databases[$this->database]->dropIndex($table_name,Key($indexes)))
						{
							$error=$databases[$this->database]->error();
							break;
						}
						$alterations++;
					}
				}
				if(!strcmp($error,"")
				&& IsSet($changes["INDEXES"][$table_name]["ChangedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["ChangedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
					{
						$name=Key($indexes);
						$was_name=(IsSet($indexes[$name]["name"]) ? $indexes[$name]["name"] : $name);
						if(!$databases[$this->database]->dropIndex($table_name,$was_name))
						{
							$error=$databases[$this->database]->error();
							break;
						}
						$alterations++;
					}
				}
				if(strcmp($error,""))
					break;
			}
		}
		if(!strcmp($error,"")
		&& IsSet($changes["TABLES"]))
		{
			for($change=0,Reset($changes["TABLES"]);$change<count($changes["TABLES"]);Next($changes["TABLES"]),$change++)
			{
				$table_name=Key($changes["TABLES"]);
				if(IsSet($changes["TABLES"][$table_name]["Remove"]))
				{
					if(!strcmp($error=$this->DropTable($table_name),""))
						$alterations++;
				}
				else
				{
					if(!IsSet($changes["TABLES"][$table_name]["Add"]))
					{
						if(!$databases[$this->database]->alterTable($table_name,$changes["TABLES"][$table_name],0))
							$error=$databases[$this->database]->error();
						else
							$alterations++;
					}
				}
				if(strcmp($error,""))
					break;
			}
			for($change=0,Reset($changes["TABLES"]);$change<count($changes["TABLES"]);Next($changes["TABLES"]),$change++)
			{
				$table_name=Key($changes["TABLES"]);
				if(IsSet($changes["TABLES"][$table_name]["Add"]))
				{
					if(!strcmp($error=$this->CreateTable($table_name,$this->database_definition["TABLES"][$table_name]),""))
						$alterations++;
				}
				if(strcmp($error,""))
					break;
			}
		}
		if(!strcmp($error,"")
		&& IsSet($changes["SEQUENCES"]))
		{
			for($change=0,Reset($changes["SEQUENCES"]);$change<count($changes["SEQUENCES"]);Next($changes["SEQUENCES"]),$change++)
			{
				$sequence_name=Key($changes["SEQUENCES"]);
				if(IsSet($changes["SEQUENCES"][$sequence_name]["Add"]))
				{
					$created_on_table=0;
					if(IsSet($this->database_definition["SEQUENCES"][$sequence_name]["on"]))
					{
						$table=$this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
						if(IsSet($changes["TABLES"])
						&& IsSet($changes["TABLES"][$table_name])
						&& IsSet($changes["TABLES"][$table_name]["Add"]))
							$created_on_table=1;
					}
					if(!strcmp($error=$this->CreateSequence($sequence_name,$this->database_definition["SEQUENCES"][$sequence_name],$create_on_table),""))
						$alterations++;
				}
				else
				{
					if(IsSet($changes["SEQUENCES"][$sequence_name]["Remove"]))
					{
						if(!strcmp($error=$this->DropSequence($sequence_name),""))
							$alterations++;
					}
					else
					{
						if(IsSet($changes["SEQUENCES"][$sequence_name]["Change"]))
						{
							$created_on_table=0;
							if(IsSet($this->database_definition["SEQUENCES"][$sequence_name]["on"]))
							{
								$table=$this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
								if(IsSet($changes["TABLES"])
								&& IsSet($changes["TABLES"][$table_name])
								&& IsSet($changes["TABLES"][$table_name]["Add"]))
									$created_on_table=1;
							}
							if(!strcmp($error=$this->DropSequence($this->database_definition["SEQUENCES"][$sequence_name]["was"]),"")
							&& !strcmp($error=$this->CreateSequence($sequence_name,$this->database_definition["SEQUENCES"][$sequence_name],$created_on_table),""))
								$alterations++;
						}
						else
							$error="changing sequences is not yet supported";
					}
				}
				if(strcmp($error,""))
					break;
			}
		}
		if(!strcmp($error,"")
		&& IsSet($changes["INDEXES"]))
		{
			for($change=0,Reset($changes["INDEXES"]);$change<count($changes["INDEXES"]);Next($changes["INDEXES"]),$change++)
			{
				$table_name=Key($changes["INDEXES"]);
				if(IsSet($changes["INDEXES"][$table_name]["ChangedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["ChangedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
					{
						if(!$databases[$this->database]->createIndex($table_name,Key($indexes),$this->database_definition["TABLES"][$table_name]["INDEXES"][Key($indexes)]))
						{
							$error=$databases[$this->database]->error();
							break;
						}
						$alterations++;
					}
				}
				if(!strcmp($error,"")
				&& IsSet($changes["INDEXES"][$table_name]["AddedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["AddedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
					{
						if(!$databases[$this->database]->createIndex($table_name,Key($indexes),$this->database_definition["TABLES"][$table_name]["INDEXES"][Key($indexes)]))
						{
							$error=$databases[$this->database]->error();
							break;
						}
						$alterations++;
					}
				}
				if(strcmp($error,""))
					break;
			}
		}
		if($alterations
		&& strcmp($error,""))
		{
			if($support_transactions)
			{
				if(!$databases[$this->database]->rollbackTransaction())
					$error="Could not rollback the partially implemented the requested database alterations: Rollback error: ".$databases[$this->database]->error()." Alterations error: $error";
			}
			else
				$error="the requested database alterations were only partially implemented: $error";
		}
		if($support_transactions)
		{
			if(!$databases[$this->database]->autoCommitTransactions(1))
				$this->warnings[]="Could not end transaction after successfully implemented the requested database alterations: ".$databases[$this->database]->error();
		}
		$databases[$this->database]->setDatabase($previous_database_name);
		return($error);
	}

	function EscapeSpecialCharacters($string)
	{
		if(GetType($string)!="string")
			$string=strval($string);
		for($escaped="",$character=0;$character<strlen($string);$character++)
		{
			switch($string[$character])
			{
				case "\"":
				case ">":
				case "<":
					$code=Ord($string[$character]);
					break;
				default:
					$code=Ord($string[$character]);
					if($code<32
					|| $code>127)
						break;
					$escaped.=$string[$character];
					continue 2;
			}
			$escaped.="&#$code;";
		}
		return($escaped);
	}

	function DumpSequence($sequence_name,$output,$eol,$dump_definition)
	{
		$sequence_definition=$this->database_definition["SEQUENCES"][$sequence_name];
		if($dump_definition)
			$start=$sequence_definition["start"];
		else
		{
			global $databases;
			if($databases[$this->database]->support("GetSequenceCurrentValue"))
			{
				if(!$databases[$this->database]->getSequenceCurrentValue($sequence_name,$start))
					return(0);
				$start++;
			}
			else
			{
				if(!$databases[$this->database]->getSequenceNextValue($sequence_name,$start))
					return(0);
				$this->warnings[]="database does not support getting current sequence value, the sequence value was incremented";
			}
		}
		$output("$eol <sequence>$eol  <name>$sequence_name</name>$eol  <start>$start</start>$eol");
		if(IsSet($sequence_definition["on"]))
			$output("  <on>$eol   <table>".$sequence_definition["on"]["table"]."</table>$eol   <field>".$sequence_definition["on"]["field"]."</field>$eol  </on>$eol");
		$output(" </sequence>$eol");
		return(1);
	}

	function DumpDatabase($arguments)
	{
		global $databases;
		if(!IsSet($arguments["Output"]))
			return("it was not specified a valid output function");
		$output=$arguments["Output"];
		$eol=(IsSet($arguments["EndOfLine"]) ? $arguments["EndOfLine"] : "\n");
		$dump_definition=IsSet($arguments["Definition"]);
		$sequences=array();
		if(IsSet($this->database_definition["SEQUENCES"]))
		{
			for($error="",Reset($this->database_definition["SEQUENCES"]),$sequence=0;$sequence<count($this->database_definition["SEQUENCES"]);Next($this->database_definition["SEQUENCES"]),$sequence++)
			{
				$sequence_name=Key($this->database_definition["SEQUENCES"]);
				if(IsSet($this->database_definition["SEQUENCES"][$sequence_name]["on"]))
					$table=$this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
				else
					$table="";
				$sequences[$table][]=$sequence_name;
			}
		}
		$previous_database_name=(strcmp($this->database_definition["name"],"") ? $databases[$this->database]->setDatabase($this->database_definition["name"]) : "");
		$output("<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>$eol");
		$output("<database>$eol$eol <name>".$this->database_definition["name"]."</name>$eol <create>".$this->database_definition["create"]."</create>$eol");
		for($error="",Reset($this->database_definition["TABLES"]),$table=0;$table<count($this->database_definition["TABLES"]);Next($this->database_definition["TABLES"]),$table++)
		{
			$table_name=Key($this->database_definition["TABLES"]);
			$output("$eol <table>$eol$eol  <name>$table_name</name>$eol");
			$output("$eol  <declaration>$eol");
			$fields=$this->database_definition["TABLES"][$table_name]["FIELDS"];
			for(Reset($fields),$field_number=0;$field_number<count($fields);$field_number++,Next($fields))
			{
				$field_name=Key($fields);
				$field=$fields[$field_name];
				$output("$eol   <field>$eol    <name>$field_name</name>$eol    <type>".$field["type"]."</type>$eol");
				switch($field["type"])
				{
					case "integer":
						if(IsSet($field["unsigned"]))
							$output("    <unsigned>1</unsigned>$eol");
						break;
					case "text":
					case "clob":
					case "blob":
						if(IsSet($field["length"]))
							$output("    <length>".$field["length"]."</length>$eol");
						break;
					case "boolean":
					case "date":
					case "timestamp":
					case "time":
					case "float":
					case "decimal":
						break;
					default:
						return("type \"".$field["type"]."\" is not yet supported");
				}
				if(IsSet($field["notnull"]))
					$output("    <notnull>1</notnull>$eol");
				if(IsSet($field["default"]))
					$output("    <default>".$this->EscapeSpecialCharacters($field["default"])."</default>$eol");
				$output("   </field>$eol");
			}

			if(IsSet($this->database_definition["TABLES"][$table_name]["INDEXES"]))
			{
				$indexes=$this->database_definition["TABLES"][$table_name]["INDEXES"];
				for(Reset($indexes),$index_number=0;$index_number<count($indexes);$index_number++,Next($indexes))
				{
					$index_name=Key($indexes);
					$index=$indexes[$index_name];
					$output("$eol   <index>$eol    <name>$index_name</name>$eol");
					if(IsSet($indexes[$index_name]["unique"]))
						$output("    <unique>1</unique>$eol");
					for(Reset($index["FIELDS"]),$field_number=0;$field_number<count($index["FIELDS"]);$field_number++,Next($index["FIELDS"]))
					{
						$field_name=Key($index["FIELDS"]);
						$field=$index["FIELDS"][$field_name];
						$output("    <field>$eol     <name>$field_name</name>$eol");
						if(IsSet($field["sorting"]))
							$output("     <sorting>".$field["sorting"]."</sorting>$eol");
						$output("    </field>$eol");
					}
					$output("   </index>$eol");
				}
			}

			$output("$eol  </declaration>$eol");
			if($dump_definition)
			{
				if(IsSet($this->database_definition["TABLES"][$table_name]["initialization"]))
				{
					$output("$eol  <initialization>$eol");
					$instructions=$this->database_definition["TABLES"][$table_name]["initialization"];
					for(Reset($instructions),$instruction=0;$instruction<count($instructions);$instruction++,Next($instructions))
					{
						switch($instructions[$instruction]["type"])
						{
							case "insert":
								$output("$eol   <insert>$eol");
								$fields=$instructions[$instruction]["FIELDS"];
								for(Reset($fields),$field_number=0;$field_number<count($fields);$field_number++,Next($fields))
								{
									$field_name=Key($fields);
									$output("$eol    <field>$eol     <name>$field_name</name>$eol     <value>".$this->EscapeSpecialCharacters($fields[$field_name])."</value>$eol    </field>$eol");
								}
								$output("$eol   </insert>$eol");
								break;
						}
					}
					$output("$eol  </initialization>$eol");
				}
			}
			else
			{
				if(strcmp($error=$this->GetFields($table_name,$query_fields),""))
					return($error);
				if(($support_summary_functions=$databases[$this->database]->support("Summaryfunctions")))
				{
					if(($result=$databases[$this->database]->query("SELECT COUNT(*) FROM $table_name"))==0)
						return($databases[$this->database]->error());
					$rows=$databases[$this->database]->fetchResult($result,0,0);
					$databases[$this->database]->freeResult($result);
				}
				if(($result=$databases[$this->database]->query("SELECT $query_fields FROM $table_name"))==0)
					return($databases[$this->database]->error());
				if(!$support_summary_functions)
					$rows=$databases[$this->database]->numberOfRows(result);
				if($rows>0)
				{
					$output("$eol  <initialization>$eol");
					for($row=0;$row<$rows;$row++)
					{
						$output("$eol   <insert>$eol");
						for(Reset($fields),$field_number=0;$field_number<count($fields);$field_number++,Next($fields))
						{
							$field_name=Key($fields);
							if(!$databases[$this->database]->resultIsNull($result,$row,$field_name))
							{
								$field=$fields[$field_name];
								$output("$eol    <field>$eol     <name>$field_name</name>$eol     <value>");
								switch($field["type"])
								{
									case "integer":
									case "text":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchResult($result,$row,$field_name)));
										break;
									case "clob":
										if(!($lob=$databases[$this->database]->fetchClobResult($result,$row,$field_name)))
											return($databases[$this->database]->error($this->database));
										while(!endOfLOB($lob))
										{
											if(readLOB($lob,$data,8000)<0)
												return(lobError($lob));
											$output($this->EscapeSpecialCharacters($data));
										}
										destroyLOB($lob);
										break;
									case "blob":
										if(!($lob=$databases[$this->database]->fetchBlobResult($result,$row,$field_name)))
											return($databases[$this->database]->error());
										while(!endOfLOB($lob))
										{
											if(readLOB($lob,$data,8000)<0)
												return(lobError($lob));
											$output(bin2hex($data));
										}
										destroyLOB($lob);
										break;
									case "float":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchFloatResult($result,$row,$field_name)));
										break;
									case "decimal":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchDecimalResult($result,$row,$field_name)));
										break;
									case "boolean":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchBooleanResult($result,$row,$field_name)));
										break;
									case "date":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchDateResult($result,$row,$field_name)));
										break;
									case "timestamp":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchTimestampResult($result,$row,$field_name)));
										break;
									case "time":
										$output($this->EscapeSpecialCharacters($databases[$this->database]->fetchTimeResult($result,$row,$field_name)));
										break;
									default:
										return("type \"".$field["type"]."\" is not yet supported");
								}
								$output("</value>$eol    </field>$eol");
							}
						}
						$output("$eol   </insert>$eol");
					}
					$output("$eol  </initialization>$eol");
				}
				$databases[$this->database]->freeResult($result);
			}
			$output("$eol </table>$eol");
			if(IsSet($sequences[$table_name]))
			{
				for($sequence=0;$sequence<count($sequences[$table_name]);$sequence++)
				{
					if(!$this->DumpSequence($sequences[$table_name][$sequence],$output,$eol,$dump_definition))
						return($databases[$this->database]->error());
				}
			}
		}
		if(IsSet($sequences[""]))
		{
			for($sequence=0;$sequence<count($sequences[""]);$sequence++)
			{
				if(!$this->DumpSequence($sequences[""][$sequence],$output,$eol,$dump_definition))
					return($databases[$this->database]->error());
			}
		}
		$output("$eol</database>$eol");
		if(strcmp($previous_database_name,""))
			$databases[$this->database]->setDatabase($previous_database_name);
		return($error);
	}

	function ParseDatabaseDefinitionFile($input_file,&$database_definition,&$variables,$fail_on_invalid_names=1)
	{
		if(!($file=fopen($input_file,"r")))
			return("Could not open input file \"$input_file\"");
		$parser=new MDB_parser;
		$parser->variables=$variables;
		$parser->fail_on_invalid_names=$fail_on_invalid_names;
		if(strcmp($error=$parser->ParseStream($file),""))
			$error.=" Line ".$parser->error_line." Column ".$parser->error_column." Byte index ".$parser->error_byte_index;
		else
			$database_definition=$parser->database;
		fclose($file);
		return($error);
	}

	function DumpDatabaseChanges(&$changes)
	{
		global $databases;
		if(IsSet($changes["TABLES"]))
		{
			for($change=0,Reset($changes["TABLES"]);$change<count($changes["TABLES"]);Next($changes["TABLES"]),$change++)
			{
				$table_name=Key($changes["TABLES"]);
				$databases[$this->database]->debug("$table_name:");
				if(IsSet($changes["tables"][$table_name]["Add"]))
					$databases[$this->database]->debug("\tAdded table '$table_name'");
				else
				{
					if(IsSet($changes["TABLES"][$table_name]["Remove"]))
						$databases[$this->database]->debug("\tRemoved table '$table_name'");
					else
					{
						if(IsSet($changes["TABLES"][$table_name]["name"]))
							$databases[$this->database]->debug("\tRenamed table '$table_name' to '".$changes["TABLES"][$table_name]["name"]."'");
						if(IsSet($changes["TABLES"][$table_name]["AddedFields"]))
						{
							$fields=$changes["TABLES"][$table_name]["AddedFields"];
							for($field=0,Reset($fields);$field<count($fields);$field++,Next($fields))
								$databases[$this->database]->debug("\tAdded field '".Key($fields)."'");
						}
						if(IsSet($changes["TABLES"][$table_name]["RemovedFields"]))
						{
							$fields=$changes["TABLES"][$table_name]["RemovedFields"];
							for($field=0,Reset($fields);$field<count($fields);$field++,Next($fields))
								$databases[$this->database]->debug("\tRemoved field '".Key($fields)."'");
						}
						if(IsSet($changes["TABLES"][$table_name]["RenamedFields"]))
						{
							$fields=$changes["TABLES"][$table_name]["RenamedFields"];
							for($field=0,Reset($fields);$field<count($fields);$field++,Next($fields))
								$databases[$this->database]->debug("\tRenamed field '".Key($fields)."' to '".$fields[Key($fields)]["name"]."'");
						}
						if(IsSet($changes["TABLES"][$table_name]["ChangedFields"]))
						{
							$fields=$changes["TABLES"][$table_name]["ChangedFields"];
							for($field=0,Reset($fields);$field<count($fields);$field++,Next($fields))
							{
								$field_name=Key($fields);
								if(IsSet($fields[$field_name]["type"]))
									$databases[$this->database]->debug("\tChanged field '$field_name' type to '".$fields[$field_name]["type"]."'");
								if(IsSet($fields[$field_name]["unsigned"]))
									$databases[$this->database]->debug("\tChanged field '$field_name' type to '".($fields[$field_name]["unsigned"] ? "" : "not ")."unsigned'");
								if(IsSet($fields[$field_name]["length"]))
									$databases[$this->database]->debug("\tChanged field '$field_name' length to '".($fields[$field_name]["length"]==0 ? "no length" : $fields[$field_name]["length"])."'");
								if(IsSet($fields[$field_name]["ChangedDefault"]))
									$databases[$this->database]->debug("\tChanged field '$field_name' default to ".(IsSet($fields[$field_name]["default"]) ? "'".$fields[$field_name]["default"]."'" : "NULL"));
								if(IsSet($fields[$field_name]["ChangedNotNull"]))
									$databases[$this->database]->debug("\tChanged field '$field_name' notnull to ".(IsSet($fields[$field_name]["notnull"]) ? "'1'" : "0"));
							}
						}
					}
				}
			}
		}
		if(IsSet($changes["SEQUENCES"]))
		{
			for($change=0,Reset($changes["SEQUENCES"]);$change<count($changes["SEQUENCES"]);Next($changes["SEQUENCES"]),$change++)
			{
				$sequence_name=Key($changes["SEQUENCES"]);
				$databases[$this->database]->debug("$sequence_name:");
				if(IsSet($changes["SEQUENCES"][$sequence_name]["Add"]))
					$databases[$this->database]->debug("\tAdded sequence '$sequence_name'");
				else
				{
					if(IsSet($changes["SEQUENCES"][$sequence_name]["Remove"]))
						$databases[$this->database]->debug("\tRemoved sequence '$sequence_name'");
					else
					{
						if(IsSet($changes["SEQUENCES"][$sequence_name]["name"]))
							$databases[$this->database]->debug("\tRenamed sequence '$sequence_name' to '".$changes["SEQUENCES"][$sequence_name]["name"]."'");
						if(IsSet($changes["SEQUENCES"][$sequence_name]["Change"]))
						{
							$sequences=$changes["SEQUENCES"][$sequence_name]["Change"];
							for($sequence=0,Reset($sequences);$sequence<count($sequences);$sequence++,Next($sequences))
							{
								$sequence_name=Key($sequences);
								if(IsSet($sequences[$sequence_name]["start"]))
									$databases[$this->database]->debug("\tChanged sequence '$sequence_name' start to '".$sequences[$sequence_name]["start"]."'");
							}
						}
					}
				}
			}
		}
		if(IsSet($changes["INDEXES"]))
		{
			for($change=0,Reset($changes["INDEXES"]);$change<count($changes["INDEXES"]);Next($changes["INDEXES"]),$change++)
			{
				$table_name=Key($changes["INDEXES"]);
				$databases[$this->database]->debug("$table_name:");
				if(IsSet($changes["INDEXES"][$table_name]["AddedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["AddedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
						$databases[$this->database]->debug("\tAdded index '".Key($indexes)."' of table '$table_name'");
				}
				if(IsSet($changes["INDEXES"][$table_name]["RemovedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["RemovedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
						$databases[$this->database]->debug("\tRemoved index '".Key($indexes)."' of table '$table_name'");
				}
				if(IsSet($changes["INDEXES"][$table_name]["ChangedIndexes"]))
				{
					$indexes=$changes["INDEXES"][$table_name]["ChangedIndexes"];
					for($index=0,Reset($indexes);$index<count($indexes);Next($indexes),$index++)
					{
						if(IsSet($indexes[Key($indexes)]["name"]))
							$databases[$this->database]->debug("\tRenamed index '".Key($indexes)."' to '".$indexes[Key($indexes)]["name"]."' on table '$table_name'");
						if(IsSet($indexes[Key($indexes)]["ChangedUnique"]))
							$databases[$this->database]->debug("\tChanged index '".Key($indexes)."' unique to '".IsSet($indexes[Key($indexes)]["unique"])."' on table '$table_name'");
						if(IsSet($indexes[Key($indexes)]["ChangedFields"]))
							$databases[$this->database]->sebug("\tChanged index '".Key($indexes)."' on table '$table_name'");
					}
				}
			}
		}
	}

	function UpdateDatabase($current_schema_file,$previous_schema_file,&$arguments,&$variables)
	{
		if(strcmp($error=$this->ParseDatabaseDefinitionFile($current_schema_file,$this->database_definition,$variables,$this->fail_on_invalid_names),""))
		{
			$this->error="Could not parse database schema file: $error";
			return(0);
		}
		if(strcmp($error=$this->SetupDatabase($arguments),""))
		{
			$this->error="Could not setup database: $error";
			return(0);
		}
		$copy=0;
		if(file_exists($previous_schema_file))
		{
			if(!strcmp($error=$this->ParseDatabaseDefinitionFile($previous_schema_file,$database_definition,$variables,0),"")
			&& !strcmp($error=$this->CompareDefinitions($database_definition,$changes),"")
			&& count($changes))
			{
				if(!strcmp($error=$this->AlterDatabase($database_definition,$changes),""))
				{
					$copy=1;
					$this->DumpDatabaseChanges($changes);
				}
			}
		}
		else
		{
			if(!strcmp($error=$this->CreateDatabase(),""))
				$copy=1;
		}
		if(strcmp($error,""))
		{
			$this->error="Could not install database: $error";
			return(0);
		}
		if($copy
		&& !copy($current_schema_file,$previous_schema_file))
		{
			$this->error="could not copy the new database definition file to the current file";
			return(0);
		}
		return(1);
	}

	function DumpDatabaseContents($schema_file,&$setup_arguments,&$dump_arguments,&$variables)
	{
		if(strcmp($error=$this->ParseDatabaseDefinitionFile($schema_file,$database_definition,$variables,$this->fail_on_invalid_names),""))
			return("Could not parse database schema file: $error");
		$this->database_definition=$database_definition;
		if(strcmp($error=$this->SetupDatabase($setup_arguments),""))
			return("Could not setup database: $error");
		return($this->DumpDatabase($dump_arguments));
	}
};

?>