<?php
/*
 * metabase_wrapper.php
 *
 * @(#) $Header$
 *
 */
require_once("MDB.php");

$databases = array();

function metabasesetupInterface(&$arguments, &$db)
{
    return(setupInterface(&$arguments, &$db));
}

function metabaseSetupDatabase($arguments, &$database)
{
    global $databases;

    $database = count($databases)+1;
    if (strcmp($error = setupInterface($arguments, $databases[$database]),"")) {
        unset($databases[$database]);
        $database = 0;
    } else {
        $databases[$database]->database = $database;
    }
    return($error);
}

function metabaseSetupDatabaseObject($arguments, &$db)
{
    return(setupDatabaseObject($arguments, &$db));
}

function metabaseCloseSetup($database)
{
    global $databases;

    $databases[$database]->disconnect();
    $databases[$database] = "";
}

function metabaseQuery($database, $query)
{
    global $databases;

    return($databases[$database]->query($query));    
}

function metabaseQueryField($database, $query, &$field, $type = "text")
{
    global $databases;

    return($databases[$database]->queryField($query, $field, $type));
}

function metabaseQueryRow($database, $query, &$row, $types = "")
{
    global $databases;

    return($databases[$database]->queryRow($query, $row, $types));
}

function metabaseQueryColumn($database, $query, &$column, $type = "text")
{
    global $databases;

    return($databases[$database]->queryColumn($query, $column, $type));
}

function metabaseQueryAll($database, $query, &$all, $types = "")
{
    global $databases;

    return($databases[$database]->queryAll($query, $all, $types));
}

function metabaseReplace($database, $table, &$fields)
{
    global $databases;

    return($databases[$database]->replace($table, $fields));
}

function metabasePrepareQuery($database, $query)
{
    global $databases;

    return($databases[$database]->prepareQuery($query));
}

function metabaseFreePreparedQuery($database, $prepared_query)
{
    global $databases;

    return($databases[$database]->freePreparedQuery($prepared_query));
}

function metabaseExecuteQuery($database, $prepared_query)
{
    global $databases;

    return($databases[$database]->executeQuery($prepared_query));
}

function metabaseQuerySet($database, $prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
{
    global $databases;

    return($databases[$database]->querySet($prepared_query, $parameter, $type, $value, $is_null, $field));
}

function metabaseQuerySetNull($database, $prepared_query, $parameter, $type)
{
    global $databases;

    return($databases[$database]->querySetNull($prepared_query, $parameter, $type));
}

function metabaseQuerySetText($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetText($prepared_query, $parameter, $value));
}

function metabaseQuerySetCLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;

    return($databases[$database]->querySetCLob($prepared_query, $parameter, $value, $field));
}

function metabaseQuerySetBLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;

    return($databases[$database]->querySetBLob($prepared_query, $parameter, $value, $field));
}

function metabaseQuerySetInteger($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetInteger($prepared_query, $parameter, $value));
}

function metabaseQuerySetBoolean($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetBoolean($prepared_query, $parameter, $value));
}

function metabaseQuerySetDate($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetDate($prepared_query, $parameter, $value));
}

function metabaseQuerySetTimestamp($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetTimestamp($prepared_query, $parameter, $value));
}

function metabaseQuerySetTime($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetTime($prepared_query, $parameter, $value));
}

function metabaseQuerySetFloat($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetFloat($prepared_query, $parameter, $value));
}

function metabaseQuerySetDecimal($database, $prepared_query, $parameter, $value)
{
    global $databases;

    return($databases[$database]->querySetDecimal($prepared_query, $parameter, $value));
}

function metabaseAffectedRows($database, &$affected_rows)
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

function metabaseFetchResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetchResult($result, $row, $field));
}

function metabaseFetchClobResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetchClobResult($result, $row, $field));
}

function metabaseFetchBlobResult($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->fetchBlobResult($result, $row, $field));
}

function metabaseDestroyResultLob($database, $lob)
{
    global $databases;

    return($databases[$database]->destroyResultLob($lob));
}

function metabaseEndOfResultLob($database, $lob)
{
    global $databases;

    return($databases[$database]->endOfResultLob($lob));
}

function metabaseReadResultLob($database, $lob, &$data, $length)
{
    global $databases;

    return($databases[$database]->readResultLob($lob, $data, $length));
}

function metabaseResultIsNull($database, $result, $row, $field)
{
    global $databases;

    return($databases[$database]->resultIsNull($result, $row, $field));
}

function metabaseFetchDateResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_DATE);
    return($value);
}

function metabaseFetchTimestampResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->convertResult($value, MDB_TYPE_TIMESTAMP);
    return($value);
}

function metabaseFetchTimeResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_TIME);
    return($value);
}

function metabaseFetchBooleanResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_BOOLEAN);
    return($value);
}

function metabaseFetchFloatResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_FLOAT);
    return($value);
}

function metabaseFetchDecimalResult($database, $result, $row, $field)
{
    global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value, MDB_TYPE_DECIMAL);
    return($value);
}

function metabaseFetchResultField($database, $result, &$field)
{
    global $databases;

    return($databases[$database]->fetchResultField($result, $field));
}

function metabaseFetchResultArray($database, $result, &$array, $row)
{
    global $databases;

    return($databases[$database]->fetchInto($result, $array, "NULL", $row));
}

function metabaseFetchResultRow($database, $result, &$row)
{
    global $databases;

    return($databases[$database]->fetchResultRow($result, $row));
}

function metabaseFetchResultColumn($database, $result, &$column)
{
    global $databases;

    return($databases[$database]->fetchResultColumn($result, $column));
}

function metabaseFetchResultAll($database, $result, &$all)
{
    global $databases;

    return($databases[$database]->fetchResultAll($result, $all));
}

function metabaseNumberOfRows($database, $result)
{
    global $databases;

    return($databases[$database]->numRows($result));
}

function metabaseNumberOfColumns($database, $result)
{
    global $databases;

    return($databases[$database]->numCols($result));
}

function metabaseGetColumnNames($database, $result, &$column_names)
{
    global $databases;

    return($databases[$database]->getColumnNames($result, $column_names));
}

function metabaseSetResultTypes($database, $result, &$types)
{
    global $databases;

    return($databases[$database]->setResultTypes($result, $types));
}

function metabaseFreeResult($database, $result)
{
    global $databases;

    return($databases[$database]->freeResult($result));
}

function metabaseError($database)
{
    global $databases;

    return($databases[$database]->error());
}

function metabaseSetErrorHandler($database, $function)
{
    global $databases;

    return($databases[$database]->setErrorHandler($function));
}

function metabaseCreateDatabase($database, $name)
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

function metabaseDropDatabase($database, $name)
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

function metabaseSetDatabase($database, $name)
{
    global $databases;

    return($databases[$database]->setDatabase($name));
}

function metabaseGetIntegerFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getIntegerFieldTypeDeclaration($name, $field));
}

function metabaseGetTextFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTextFieldTypeDeclaration($name, $field));
}

function metabaseGetClobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getClobFieldTypeDeclaration($name, $field));
}

function metabaseGetBlobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getBlobFieldTypeDeclaration($name, $field));
}

function metabaseGetBooleanFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getBooleanFieldTypeDeclaration($name, $field));
}

function metabaseGetDateFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getDateFieldTypeDeclaration($name, $field));
}

function metabaseGetTimestampFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTimestampFieldTypeDeclaration($name, $field));
}

function metabaseGetTimeFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getTimeFieldTypeDeclaration($name, $field));
}

function metabaseGetFloatFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getFloatFieldTypeDeclaration($name, $field));
}

function metabaseGetDecimalFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;

    return($databases[$database]->getDecimalFieldTypeDeclaration($name, $field));
}

function metabaseGetTextFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTextFieldValue($value));
}

function metabaseGetBooleanFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getBooleanFieldValue($value));
}

function metabaseGetDateFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getDateFieldValue($value));
}

function metabaseGetTimestampFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTimestampFieldValue($value));
}

function metabaseGetTimeFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getTimeFieldValue($value));
}

function metabaseGetFloatFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getFloatFieldValue($value));
}

function metabaseGetDecimalFieldValue($database, $value)
{
    global $databases;

    return($databases[$database]->getDecimalFieldValue($value));
}

function metabaseSupport($database, $feature)
{
    global $databases;

    return($databases[$database]->support($feature));
}

function metabaseCreateTable($database, $name, &$fields)
{
    global $databases;

    return($databases[$database]->createTable($name, $fields));
}

function metabaseDropTable($database, $name)
{
    global $databases;

    return($databases[$database]->dropTable($name));
}

function metabaseAlterTable($database, $name, &$changes, $check = 0)
{
    global $databases;

    return($databases[$database]->alterTable($name, $changes, $check));
}

function metabaseCreateSequence($database, $name, $start)
{
    global $databases;

    $value = $databases[$database]->createSequence($name, $start);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Create sequence","sequence creation is not supported"));
    } else {
        return(1);
    }
}

function metabaseDropSequence($database, $name)
{
    global $databases;

    $value = $databases[$database]->dropSequence($name);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Drop sequence","sequence dropping is not supported"));
    } else {
        return(1);
    }
}

function metabaseGetSequenceNextValue($database, $name, &$value)
{
    global $databases;

    $value = $databases[$database]->nextId($name,false);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Get sequence next value","getting sequence next value is not supported"));
    } else {
        return(1);
    }
}

function metabaseGetSequenceCurrentValue($database, $name, &$value)
{
    global $databases;

    $value = $databases[$database]->currId($name);
    if (MDB::isError($value)) {
        return($databases[$database]->setError("Get sequence current value","getting sequence current value is not supported"));
    } else {
        return(1);
    }
}

function metabaseAutoCommitTransactions($database, $auto_commit)
{
    global $databases;

    return($databases[$database]->autoCommitTransactions($auto_commit));
}

function metabaseCommitTransaction($database)
{
    global $databases;

    return($databases[$database]->commitTransaction());
}

function metabaseRollbackTransaction($database)
{
    global $databases;

    return($databases[$database]->rollbackTransaction());
}

function metabaseCreateIndex($database, $table, $name, $definition)
{
    global $databases;

    return($databases[$database]->createIndex($table, $name, $definition));
}

function metabaseDropIndex($database, $table, $name)
{
    global $databases;

    return($databases[$database]->dropIndex($table, $name));
}

function metabaseNow()
{
    return(strftime("%Y-%m-%d %H:%M:%S"));
}

function metabaseToday()
{
    return(strftime("%Y-%m-%d"));
}

function metabaseTime()
{
    return(strftime("%H:%M:%S"));
}

function metabaseSetSelectedRowRange($database, $first, $limit)
{
    global $databases;

    return($databases[$database]->setSelectedRowRange($first, $limit));
}

function metabaseEndOfResult($database, $result)
{
    global $databases;

    return($databases[$database]->endOfResult($result));
}

function metabaseCaptureDebugOutput($database, $capture)
{
    global $databases;

    $databases[$database]->captureDebugOutput($capture);
}

function metabaseDebugOutput($database)
{
    global $databases;

    return($databases[$database]->debugOutput());
}

function metabaseDebug($database, $message)
{
    global $databases;

    return($databases[$database]->debug($message));
}

function metabaseShutdownTransactions()
{
    shutdownTransactions();
}

function metabaseDefaultDebugOutput($database, $message)
{
    defaultDebugOutput($database, $message);
}

function metabaseCreateLob(&$arguments, &$lob)
{
    return(createLob(&$arguments, &$lob));
}

function metabaseCreateLobError(&$arguments, &$lob)
{
    return(createLobError(&$arguments, &$lob));
}

function metabaseDestroyLob($lob)
{
    destroyLob($lob);
}

function metabaseEndOfLob($lob)
{
    return(endOfLob($lob));
}

function metabaseReadLob($lob, &$data, $length)
{
    return(readLob($lob, &$data, $length));
}

function metabaseLobError($lob)
{
    return(lobError($lob));
}

?>