<?php
/*
 * Metabase_wrapper.php
 *
 * @(#) $Header$
 *
 */
require_once("MDB.php");

$metabases_databases = array();
$metabases_lobs = array();

function MetabaseSetupDatabase($arguments, &$database)
{
    global $metabase_databases;
    $database = count($metabase_databases)+1;
    
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
        $database = 0;
        $error = $result->getMessage.":".$result->getCode();
    } else {
        $metabase_databases[$database] = $db;
        $metabase_databases[$database]->database = $database;
    }
    return($error);
}

function MetabaseSetupDatabaseObject($arguments, &$db)
{
    global $metabase_databases;

    $error = MetabaseSetupDatabase($arguments, &$database);
    $db = $metabase_databases[$database];
    return($error);
}

function MetabaseCloseSetup($database)
{
    global $metabase_databases;
    
    $metabase_databases[$database]->disconnect();
    unset($metabase_databases[$database]);
}

function MetabaseQuery($database, $query)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->query($query);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQueryField($database, $query, &$field, $type = "text")
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->queryField($query, $field, $type);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryRow($database, $query, &$row, $types = "")
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->queryRow($query, $row, $types);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryColumn($database, $query, &$column, $type = "text")
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->queryColumn($query, $column, $type);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryAll($database, $query, &$all, $types = "")
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->queryAll($query, $all, $types);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseReplace($database, $table, &$fields)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->replace($table, $fields);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabasePrepareQuery($database, $query)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->prepareQuery($query);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFreePreparedQuery($database, $prepared_query)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->freePreparedQuery($prepared_query);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseExecuteQuery($database, $prepared_query)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->executeQuery($prepared_query);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQuerySet($database, $prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySet($prepared_query, $parameter, $type, $value, $is_null, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetNull($database, $prepared_query, $parameter, $type)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetNull($prepared_query, $parameter, $type);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetText($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetText($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetCLob($database, $prepared_query, $parameter, $value, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetCLob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBLob($database, $prepared_query, $parameter, $value, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetBLob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetInteger($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetInteger($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBoolean($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetBoolean($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDate($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetDate($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTimestamp($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetTimestamp($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTime($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetTime($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetFloat($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetFloat($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDecimal($database, $prepared_query, $parameter, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->querySetDecimal($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseAffectedRows($database, &$affected_rows)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->affectedRows();
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $affected_rows = $result;
        return(1);
    }
}

function MetabaseFetchResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchClobResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchClobResult($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchBlobResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchBlobResult($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseDestroyResultLob($database, $lob)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->destroyResultLob($lob);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfResultLob($database, $lob)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->endOfResultLob($lob);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseReadResultLob($database, $lob, &$data, $length)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->readResultLob($lob, $data, $length);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseResultIsNull($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->resultIsNull($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchDateResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_DATE);
        return($result);
    }
}

function MetabaseFetchTimestampResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_TIMESTAMP);
        return($result);
    }
}

function MetabaseFetchTimeResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_TIME);
        return($result);
    }
}

function MetabaseFetchBooleanResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_BOOLEAN);
        return($result);
    }
}

function MetabaseFetchFloatResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_FLOAT);
        return($result);
    }
}

function MetabaseFetchDecimalResult($database, $result, $row, $field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $metabase_databases[$database]->convertResult($result, MDB_TYPE_DECIMAL);
        return($result);
    }
}

function MetabaseFetchResultField($database, $result, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchField($result, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultArray($database, $result, &$array, $row)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchInto($result, $array, "NULL", $row);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultRow($database, $result, &$row)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchRow($result, $row);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultColumn($database, $result, &$column)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchColumn($result, $column);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultAll($database, $result, &$all)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->fetchAll($result, $all);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseNumberOfRows($database, $result)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->numRows($result);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseNumberOfColumns($database, $result)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->numCols($result);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetColumnNames($database, $result, &$column_names)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getColumnNames($result, $column_names);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetResultTypes($database, $result, &$types)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->setResultTypes($result, $types);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFreeResult($database, $result)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->freeResult($result);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseError($database)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->error();
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetErrorHandler($database, $function)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->setErrorHandler($function);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateDatabase($database, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->createDatabase($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropDatabase($database, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->dropDatabase($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetDatabase($database, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->setDatabase($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetIntegerFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getIntegerDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTextFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTextDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetClobFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getClobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetBlobFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getBlobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetBooleanFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getBooleanDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetDateFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getDateDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTimestampFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTimestampDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTimeFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTimeDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetFloatFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getFloatDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetDecimalFieldTypeDeclaration($database, $name, &$field)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getDecimalDeclaration($name, $field);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTextFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTextFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetBooleanFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getBooleanFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDateFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getDateFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimestampFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTimestampFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimeFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getTimeFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetFloatFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getFloatFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDecimalFieldValue($database, $value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->getDecimalFieldValue($value);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseSupport($database, $feature)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->support($feature);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseCreateTable($database, $name, &$fields)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->createTable($name, $fields);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropTable($database, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->dropTable($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseAlterTable($database, $name, &$changes, $check = 0)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->alterTable($name, $changes, $check);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateSequence($database, $name, $start)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->createSequence($name, $start);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropSequence($database, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->dropSequence($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetSequenceNextValue($database, $name, &$value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->nextId($name, false);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $value = $result;
        return(1);
    }
}

function MetabaseGetSequenceCurrentValue($database, $name, &$value)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->currId($name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        $value = $result;
        return(1);
    }
}

function MetabaseAutoCommitTransactions($database, $auto_commit)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->autoCommitTransactions($auto_commit);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCommitTransaction($database)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->commitTransaction();
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseRollbackTransaction($database)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->rollbackTransaction();
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateIndex($database, $table, $name, $definition)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->createIndex($table, $name, $definition);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropIndex($database, $table, $name)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->dropIndex($table, $name);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
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
    global $metabase_databases;
    $result = $metabase_databases[$database]->setSelectedRowRange($first, $limit);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfResult($database, $result)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->endOfResult($result);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseCaptureDebugOutput($database, $capture)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->captureDebugOutput($capture);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDebugOutput($database)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->debugOutput();
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDebug($database, $message)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->debug($message);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseShutdownTransactions()
{
    shutdownTransactions();
}

function MetabaseDefaultDebugOutput($database, $message)
{
    global $metabase_databases;
    $result = $metabase_databases[$database]->defaultDebugOutput($metabase_databases[$database], $message);
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateLob(&$arguments, &$lob)
{
    global $metabase_databases;
    $args = $arguments;
    $args["Database"] = $metabase_databases[$arguments["Database"]];
    $result = createLob(&$args, &$lob);
    $args["Database"] = $arguments["Database"];
    $arguments = $args;
    if (MDB::isError($result)) {
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDestroyLob($lob)
{
    $result = destroyLob($lob);
    if (MDB::isError($result)) {
        global $metabase_databases;
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfLob($lob)
{
    $result = endOfLob($lob);
    if (MDB::isError($result)) {
        global $metabase_databases;
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseReadLob($lob, &$data, $length)
{
    $result = readLob($lob, &$data, $length);
    if (MDB::isError($result)) {
        global $metabase_databases;
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseLobError($lob)
{
    $result = lobError($lob);
    if (MDB::isError($result)) {
        global $metabase_databases;
        $metabase_databases[$database]->setError($result->getMessage(), $result->getCode());
        return(0);
    } else {
        return($result);
    }
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
        global $metabase_databases;
        $this->MDB_manager_object = new MDB_manager;
        $this->database = count($metabase_databases)+1;
        $this->MDB_manager_object->fail_on_invalid_names = $this->fail_on_invalid_names;
        $this->MDB_manager_object->error = $this->error;
        $this->MDB_manager_object->warnings = $this->warnings;
        $this->MDB_manager_object->database_definition = $this->database_definition;
    }
    
    function SetupDatabase(&$arguments)
    {
        global $metabase_databases;
        $database = count($metabase_databases)+1;
        
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];
        
        $options["includedconstant"] = $arguments["IncludedConstant"];
        if (isset($arguments["Persistent"])) {
            $options["persistent"] = true;
        }
        if (isset($arguments["IncludePath"])) {
             $options["includepath"] = $arguments["IncludePath"];
        }
        
        if (isset($arguments["Debug"])) {
             $options["debug"] = $arguments["Debug"];
        }
        $options["decimalplaces"] = $arguments["DecimalPlaces"];
        $options["LOBbufferlength"] = $arguments["LOBBufferLength"];
        $options["loglinebreak"] = $arguments["LogLineBreak"];
        $options["options"] = $arguments["Options"];

        $db = MDB::connect($dsninfo, $options);

        if (MDB::isError($db) || !is_object($db)) {
            $database = 0;
            $error = $result->getMessage.":".$result->getCode();
        } else {
            $metabase_databases[$database] = $db;
            $metabase_databases[$database]->database = $database;
        }
        
        return($this->MDB_manager_object->setupDatabase($dsninfo, $options));
    }

    function CloseSetup()
    {
        return($this->MDB_manager_object->close());
    }

    function GetField(&$field, $field_name, $declaration, &$query)
    {
        return($this->MDB_manager_object->getField($field, $field_name, $declaration, $query));
    }

    function GetFieldList($fields, $declaration, &$query_fields)
    {
        return($this->MDB_manager_object->getFieldList($fields, $declaration, $query_fields));
    }

    function GetFields($table, &$fields)
    {
        return($this->MDB_manager_object->getFields($table, $fields));
    }

    function CreateTable($table_name, $table)
    {
        return($this->MDB_manager_object->createTable($table_name, $table));
    }

    function DropTable($table_name)
    {
        return($this->MDB_manager_object->dropTable($table_name));
    }

    function CreateSequence($sequence_name, $sequence, $created_on_table)
    {
        return($this->MDB_manager_object->createSequence($sequence_name, $sequence, $created_on_table));
    }

    function DropSequence($sequence_name)
    {
        return($this->MDB_manager_object->dropSequence($sequence_name));
    }

    function CreateDatabase()
    {
        return($this->MDB_manager_object->createDatabase());
    }

    function AddDefinitionChange(&$changes, $definition, $item, $change)
    {
        return($this->MDB_manager_object->addDefinitionChange($changes, $definition, $item, $change));
    }

    function CompareDefinitions(&$previous_definition, &$changes)
    {
        return($this->MDB_manager_object->compareDefinitions($previous_definition, $changes));
    }

    function AlterDatabase(&$previous_definition, &$changes)
    {
        return($this->MDB_manager_object->alterDatabase($previous_definition, $changes));
    }

    function EscapeSpecialCharacters($string)
    {
        return($this->MDB_manager_object->escapeSpecialCharacters($string));
    }

    function DumpSequence($sequence_name, $output, $eol, $dump_definition)
    {
        return($this->MDB_manager_object->dumpSequence($sequence_name, $output, $eol, $dump_definition));
    }

    function DumpDatabase($arguments)
    {
        return($this->MDB_manager_object->dumpDatabase($arguments));
    }

    function ParseDatabaseDefinitionFile($input_file, &$database_definition, &$variables, $fail_on_invalid_names = 1)
    {
        return($this->MDB_manager_object->parseDatabaseDefinitionFile($input_file, $database_definition, $variables, $fail_on_invalid_names));
    }

    function DumpDatabaseChanges(&$changes)
    {
        return($this->MDB_manager_object->dumpDatabaseChanges($changes));
    }

    function UpdateDatabase($current_schema_file, $previous_schema_file, &$arguments, &$variables)
    {
        global $metabase_databases;
        $database = count($metabase_databases)+1;
        
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];
        
        $options["includedconstant"] = $arguments["IncludedConstant"];
        if (isset($arguments["Persistent"])) {
            $options["persistent"] = true;
        }
        if (isset($arguments["IncludePath"])) {
             $options["includepath"] = $arguments["IncludePath"];
        }
        
        if (isset($arguments["Debug"])) {
             $options["debug"] = $arguments["Debug"];
        }
        $options["decimalplaces"] = $arguments["DecimalPlaces"];
        $options["LOBbufferlength"] = $arguments["LOBBufferLength"];
        $options["loglinebreak"] = $arguments["LogLineBreak"];
        $options["options"] = $arguments["Options"];

        $db = MDB::connect($dsninfo, $options);

        if (MDB::isError($db) || !is_object($db)) {
            $database = 0;
            $error = $result->getMessage.":".$result->getCode();
        } else {
            $metabase_databases[$database] = $db;
            $metabase_databases[$database]->database = $database;
        }
        return($this->MDB_manager_object->updateDatabase($current_schema_file, $previous_schema_file, $dsninfo, $variables, $options));
    }

    function DumpDatabaseContents($schema_file, &$setup_arguments, &$dump_arguments, &$variables)
    {
        return($this->MDB_manager_object->dumpDatabaseContents($schema_file, $setup_arguments, $dump_arguments, $variables));
    }
};
?>