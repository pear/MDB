<?php
/*
 * Metabase_wrapper.php
 *
 * @(#) $Header$
 *
 */
require_once("MDB.php");

$databases = array();

function MetabaseSetupDatabase($arguments, &$database)
{
    global $databases;
    $database=count($metabase_databases)+1;
    $dsninfo["phptype"] = $arguments["Type"];
    $dsninfo["username"] = $arguments["User"];
    $dsninfo["password"] = $arguments["Password"];
    $dsninfo["hostspec"] = $arguments["Host"];
    
    $options["includedconstant"] = $arguments["IncludedConstant"];
    $options["includepath"] = $arguments["IncludePath"];
    $options["debug"] = $arguments["Debug"];
    $options["decimalplaces"] = $arguments["DecimalPlaces"];
    $options["LOBbufferlength"] = $arguments["LOBBufferLength"];
    $options["loglinebreak"] = $arguments["LogLineBreak"];
    $options["options"] = $arguments["Options"];

    $db = MDB::connect($dsninfo, $options);
    
    if (MDB::isError($db) || !is_object($db)) {
        $database=0;
        $error = $db;
    } else {
        $databases[$database] = $db;
        $databases[$database]->database=$database;
    }
    return($error);
}

function MetabaseSetupDatabaseObject($arguments, &$db)
{
    global $databases;
    $error = MetabaseSetupDatabase($arguments, &$database);
    $db = $databases[$database];
    return($error);
}

function MetabaseCloseSetup($database)
{
    global $databases;

    $databases[$database]->disconnect();
    $databases[$database] = "";
}

function MetabaseQuery($database, $query)
{
    global $databases;

    return($databases[$database]->query($query));    
}

function MetabaseQueryField($database, $query, &$field, $type = "text")
{
    global $databases;

    return($databases[$database]->queryField($query, $field, $type));
}

function MetabaseQueryRow($database, $query, &$row, $types = "")
{
    global $databases;

    return($databases[$database]->queryRow($query, $row, $types));
}

function MetabaseQueryColumn($database, $query, &$column, $type = "text")
{
    global $databases;

    return($databases[$database]->queryColumn($query, $column, $type));
}

function MetabaseQueryAll($database, $query, &$all, $types = "")
{
    global $databases;

    return($databases[$database]->queryAll($query, $all, $types));
}

function MetabaseReplace($database, $table, &$fields)
{
    global $databases;

    return($databases[$database]->replace($table, $fields));
}

function MetabasePrepareQuery($database, $query)
{
    global $databases;

    return($databases[$database]->prepareQuery($query));
}

function MetabaseFreePreparedQuery($database, $prepared_query)
{
    global $databases;

    return($databases[$database]->freePreparedQuery($prepared_query));
}

function MetabaseExecuteQuery($database, $prepared_query)
{
    global $databases;

    return($databases[$database]->executeQuery($prepared_query));
}

function MetabaseQuerySet($database, $prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
{
    global $databases;

    return($databases[$database]->querySet($prepared_query, $parameter, $type, $value, $is_null, $field));
}

function MetabaseQuerySetNull($database, $prepared_query, $parameter, $type)
{
    global $databases;

    return($databases[$database]->querySetNull($prepared_query, $parameter, $type));
}

function MetabaseQuerySetText($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetText($prepared_query, $parameter, $value));
}

function MetabaseQuerySetCLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;

    return($databases[$database]->querySetCLob($prepared_query, $parameter, $value, $field));
}

function MetabaseQuerySetBLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;

    return($databases[$database]->querySetBLob($prepared_query, $parameter, $value, $field));
}

function MetabaseQuerySetInteger($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetInteger($prepared_query, $parameter, $value));
}

function MetabaseQuerySetBoolean($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetBoolean($prepared_query, $parameter, $value));
}

function MetabaseQuerySetDate($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetDate($prepared_query, $parameter, $value));
}

function MetabaseQuerySetTimestamp($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetTimestamp($prepared_query, $parameter, $value));
}

function MetabaseQuerySetTime($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetTime($prepared_query, $parameter, $value));
}

function MetabaseQuerySetFloat($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetFloat($prepared_query, $parameter, $value));
}

function MetabaseQuerySetDecimal($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetDecimal($prepared_query, $parameter, $value));
}

function MetabaseAffectedRows($database, &$affected_rows)
{
    global $databases;
    
    $affected_rows  =  $databases[$database]->affectedRows();
    if (MDB::isError($affected_rows)) {
        return($databases[$database]->setError("Affected rows",
            "there was no previous valid query to determine the number of affected rows")
        );
    } else {
        return(1);
    }
}

function MetabaseFetchResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetch($result, $row, $field));
}

function MetabaseFetchClobResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetchClobResult($result, $row, $field));
}

function MetabaseFetchBlobResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetchBlobResult($result, $row, $field));
}

function MetabaseDestroyResultLob($database, $lob)
{
    global $databases;

    return($databases[$database]->destroyResultLob($lob));
}

function MetabaseEndOfResultLob($database, $lob)
{
    global $databases;

    return($databases[$database]->endOfResultLob($lob));
}

function MetabaseReadResultLob($database, $lob, &$data, $length)
{
    global $databases;

    return($databases[$database]->readResultLob($lob, $data, $length));
}

function MetabaseResultIsNull($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->resultIsNull($result, $row, $field));
}

function MetabaseFetchDateResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_DATE);
    return($value);
}

function MetabaseFetchTimestampResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->convertResult($value, MDB_TYPE_TIMESTAMP);
    return($value);
}

function MetabaseFetchTimeResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_TIME);
    return($value);
}

function MetabaseFetchBooleanResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_BOOLEAN);
    return($value);
}

function MetabaseFetchFloatResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_FLOAT);
    return($value);
}

function MetabaseFetchDecimalResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetch($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_DECIMAL);
    return($value);
}

function MetabaseFetchResultField($database, $result, &$field)
{
    global $databases;

    return($databases[$database]->fetchField($result, $field));
}

function MetabaseFetchResultArray($database, $result, &$array, $row)
{
    global $databases;

    return($databases[$database]->fetchInto($result, $array, "NULL", $row));
}

function MetabaseFetchResultRow($database, $result, &$row)
{
    global $databases;

    return($databases[$database]->fetchRow($result, $row));
}

function MetabaseFetchResultColumn($database, $result, &$column)
{
    global $databases;

    return($databases[$database]->fetchColumn($result, $column));
}

function MetabaseFetchResultAll($database, $result, &$all)
{
    global $databases;

    return($databases[$database]->fetchAll($result, $all));
}

function MetabaseNumberOfRows($database, $result)
{
    global $databases;

    return($databases[$database]->numRows($result));
}

function MetabaseNumberOfColumns($database, $result)
{
    global $databases;

    return($databases[$database]->numCols($result));
}

function MetabaseGetColumnNames($database, $result, &$column_names)
{
    global $databases;

    return($databases[$database]->getColumnNames($result, $column_names));
}

function MetabaseSetResultTypes($database, $result, &$types)
{
    global $databases;

    return($databases[$database]->setResultTypes($result, $types));
}

function MetabaseFreeResult($database, $result)
{
    global $databases;

    return($databases[$database]->freeResult($result));
}

function MetabaseError($database)
{
    global $databases;

    return($databases[$database]->error());
}

function MetabaseSetErrorHandler($database, $function)
{
    global $databases;

    return($databases[$database]->setErrorHandler($function));
}

function MetabaseCreateDatabase($database, $name)
{
    global $databases;
    $result  =  $databases[$database]->createDatabase($name);
    if (MDB::isError($result)) {
        switch ($result->getCode()) {
            default:
                $this->setError("Create database", $result->getMessage());
                break;
        }
    } else {
        return(1);
    }
}

function MetabaseDropDatabase($database, $name)
{
    global $databases;
    $result  =  $databases[$database]->dropDatabase($name);
    if (MDB::isError($result)) {
        switch ($result->getCode()) {
            default:
                $this->setError("Drop database", $result->getMessage());
                break;
        }
    } else {
        return(1);
    }
}

function MetabaseSetDatabase($database, $name)
{
    global $databases;

    return($databases[$database]->setDatabase($name));
}

function MetabaseGetIntegerFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getIntegerDeclaration($name, $field));
}

function MetabaseGetTextFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTextDeclaration($name, $field));
}

function MetabaseGetClobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getClobDeclaration($name, $field));
}

function MetabaseGetBlobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getBlobDeclaration($name, $field));
}

function MetabaseGetBooleanFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getBooleanDeclaration($name, $field));
}

function MetabaseGetDateFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getDateDeclaration($name, $field));
}

function MetabaseGetTimestampFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTimestampDeclaration($name, $field));
}

function MetabaseGetTimeFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTimeDeclaration($name, $field));
}

function MetabaseGetFloatFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getFloatDeclaration($name, $field));
}

function MetabaseGetDecimalFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getDecimalDeclaration($name, $field));
}

function MetabaseGetTextFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTextFieldValue($value));
}

function MetabaseGetBooleanFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getBooleanFieldValue($value));
}

function MetabaseGetDateFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getDateFieldValue($value));
}

function MetabaseGetTimestampFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTimestampFieldValue($value));
}

function MetabaseGetTimeFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTimeFieldValue($value));
}

function MetabaseGetFloatFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getFloatFieldValue($value));
}

function MetabaseGetDecimalFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getDecimalFieldValue($value));
}

function MetabaseSupport($database, $feature)
{
    global $databases;

    return($databases[$database]->support($feature));
}

function MetabaseCreateTable($database, $name, &$fields)
{
    global $databases;

    return($databases[$database]->createTable($name, $fields));
}

function MetabaseDropTable($database, $name)
{
    global $databases;

    return($databases[$database]->dropTable($name));
}

function MetabaseAlterTable($database, $name, &$changes, $check = 0)
{
    global $databases;

    return($databases[$database]->alterTable($name, $changes, $check));
}

function MetabaseCreateSequence($database, $name, $start)
{
    global $databases;

    $value = $databases[$database]->createSequence($name, $start);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Create sequence","sequence creation is not supported"));
    } else {
        return(1);
    }
}

function MetabaseDropSequence($database, $name)
{
    global $databases;

    $value = $databases[$database]->dropSequence($name);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Drop sequence","sequence dropping is not supported"));
    } else {
        return(1);
    }
}

function MetabaseGetSequenceNextValue($database, $name, &$value)
{
    global $databases;

    $value = $databases[$database]->nextId($name,false);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Get sequence next value","getting sequence next value is not supported"));
    } else {
        return(1);
    }
}

function MetabaseGetSequenceCurrentValue($database, $name, &$value)
{
    global $databases;

    $value = $databases[$database]->currId($name);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Get sequence current value","getting sequence current value is not supported"));
    } else {
        return(1);
    }
}

function MetabaseAutoCommitTransactions($database, $auto_commit)
{
    global $databases;

    return($databases[$database]->autoCommitTransactions($auto_commit));
}

function MetabaseCommitTransaction($database)
{
    global $databases;

    return($databases[$database]->commitTransaction());
}

function MetabaseRollbackTransaction($database)
{
    global $databases;

    return($databases[$database]->rollbackTransaction());
}

function MetabaseCreateIndex($database, $table, $name, $definition)
{
    global $databases;

    return($databases[$database]->createIndex($table, $name, $definition));
}

function MetabaseDropIndex($database, $table, $name)
{
    global $databases;

    return($databases[$database]->dropIndex($table, $name));
}

function MetabaseNow()
{
    return(strftime("%Y-%m-%d %H:%M:%S"));
}

function MetabaseToday()
{
    return(strftime("%Y-%m-%d"));
}

function MetabaseTime()
{
    return(strftime("%H:%M:%S"));
}

function MetabaseSetSelectedRowRange($database, $first, $limit)
{
    global $databases;

    return($databases[$database]->setSelectedRowRange($first, $limit));
}

function MetabaseEndOfResult($database, $result)
{
    global $databases;

    return($databases[$database]->endOfResult($result));
}

function MetabaseCaptureDebugOutput($database, $capture)
{
    global $databases;

    $databases[$database]->captureDebugOutput($capture);
}

function MetabaseDebugOutput($database)
{
    global $databases;

    return($databases[$database]->debugOutput());
}

function MetabaseDebug($database, $message)
{
    global $databases;

    return($databases[$database]->debug($message));
}

function MetabaseShutdownTransactions()
{
    shutdownTransactions();
}

function MetabaseDefaultDebugOutput($database, $message)
{
    global $databases;
    defaultDebugOutput($databases[$database], $message);
}

function MetabaseCreateLob(&$arguments, &$lob)
{
    return(createLob(&$arguments, &$lob));
}

function MetabaseCreateLobError(&$arguments, &$lob)
{
    return(createLobError(&$arguments, &$lob));
}

function MetabaseDestroyLob($lob)
{
    destroyLob($lob);
}

function MetabaseEndOfLob($lob)
{
    return(endOfLob($lob));
}

function MetabaseReadLob($lob, &$data, $length)
{
    return(readLob($lob, &$data, $length));
}

function MetabaseLobError($lob)
{
    return(lobError($lob));
}

class Metabase_manager_class
{
    var $MDB_manager_object;
    
    var $fail_on_invalid_names = 1;
    var $error = "";
    var $warnings = array();
    var $database = 0;
    var $database_definition = array(
        "name" => "",
        "create" => 0,
        "TABLES" => array()
    );
    
    function Metabase_manager_class()
    {
        global $databases;
        $this->MDB_manager_object = new MDB_manager;
        $this->database = count($databases)+1;
        $this->MDB_manager_object->fail_on_invalid_names = $this->fail_on_invalid_names;
        $this->MDB_manager_object->error = $this->error;
        $this->MDB_manager_object->warnings = $this->warnings;
        $this->MDB_manager_object->database_definition = $this->database_definition;
    }
    
    function SetupDatabase(&$arguments)
    {
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];
        if (isset($arguments["Persistent"])) {
            $options["persistent"] = true;
        }
        if (isset($arguments["IncludePath"])) {
             $options["includepath"] = $arguments["IncludePath"];
        }
        
        if (isset($arguments["Debug"])) {
             $options["debug"] = $arguments["Debug"];
        }
        
        return($this->MDB_manager_object->setupDatabase($dsninfo, $options));
    }

    function CloseSetup()
    {
        return($this->MDB_manager_object->closeSetup());
    }

    function GetField(&$field,$field_name,$declaration,&$query)
    {
        return($this->MDB_manager_object->getField(&$field,$field_name,$declaration,&$query));
    }

    function GetFieldList($fields,$declaration,&$query_fields)
    {
        return($this->MDB_manager_object->getFieldList($fields,$declaration,&$query_fields));
    }

    function GetFields($table,&$fields)
    {
        return($this->MDB_manager_object->getFields($table,&$fields));
    }

    function CreateTable($table_name,$table)
    {
        return($this->MDB_manager_object->createTable($table_name,$table));
    }

    function DropTable($table_name)
    {
        return($this->MDB_manager_object->dropTable($table_name));
    }

    function CreateSequence($sequence_name,$sequence,$created_on_table)
    {
        return($this->MDB_manager_object->createSequence($sequence_name,$sequence,$created_on_table));
    }

    function DropSequence($sequence_name)
    {
        return($this->MDB_manager_object->dropSequence($sequence_name));
    }

    function CreateDatabase()
    {
        return($this->MDB_manager_object->createDatabase());
    }

    function AddDefinitionChange(&$changes,$definition,$item,$change)
    {
        return($this->MDB_manager_object->addDefinitionChange(&$changes,$definition,$item,$change));
    }

    function CompareDefinitions(&$previous_definition,&$changes)
    {
        return($this->MDB_manager_object->compareDefinitions(&$previous_definition,&$changes));
    }

    function AlterDatabase(&$previous_definition,&$changes)
    {
        return($this->MDB_manager_object->alterDatabase(&$previous_definition,&$changes));
    }

    function EscapeSpecialCharacters($string)
    {
        return($this->MDB_manager_object->escapeSpecialCharacters($string));
    }

    function DumpSequence($sequence_name,$output,$eol,$dump_definition)
    {
        return($this->MDB_manager_object->dumpSequence($sequence_name,$output,$eol,$dump_definition));
    }

    function DumpDatabase($arguments)
    {
        return($this->MDB_manager_object->dumpDatabase($arguments));
    }

    function ParseDatabaseDefinitionFile($input_file,&$database_definition,&$variables,$fail_on_invalid_names=1)
    {
        return($this->MDB_manager_object->parseDatabaseDefinitionFile($input_file,&$database_definition,&$variables,$fail_on_invalid_names=1));
    }

    function DumpDatabaseChanges(&$changes)
    {
        return($this->MDB_manager_object->dumpDatabaseChanges(&$changes));
    }

    function UpdateDatabase($current_schema_file,$previous_schema_file,&$arguments,&$variables)
    {
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];
        if (isset($arguments["Persistent"])) {
            $options["persistent"] = true;
        }
        if (isset($arguments["IncludePath"])) {
             $options["includepath"] = $arguments["IncludePath"];
        }
        
        if (isset($arguments["Debug"])) {
             $options["debug"] = $arguments["Debug"];
        }
        
        return($this->MDB_manager_object->updateDatabase($current_schema_file,$previous_schema_file,&$dsninfo,&$variables,$options));
    }

    function DumpDatabaseContents($schema_file,&$setup_arguments,&$dump_arguments,&$variables)
    {
        return($this->MDB_manager_object->dumpDatabaseContents($schema_file,&$setup_arguments,&$dump_arguments,&$variables));
    }
};

?>