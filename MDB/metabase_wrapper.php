<?
/*
 * metabase_wrapper.php
 */
require_once("MDB.php");

$databases=array();

Function MetabasesetupInterface(&$arguments,&$db)
{
	return(setupInterface(&$arguments,&$db));
}

Function MetabaseSetupDatabase($arguments,&$database)
{
	global $databases;

	$database=count($databases)+1;
	if(strcmp($error=setupInterface($arguments,$databases[$database]),""))
	{
		Unset($databases[$database]);
		$database=0;
	}
	else
		$databases[$database]->database=$database;
	return($error);
}

Function MetabaseSetupDatabaseObject($arguments,&$db)
{
	return(setupDatabaseObject($arguments,&$db));
}

Function MetabaseCloseSetup($database)
{
	global $databases;

	$databases[$database]->disconnect();
	$databases[$database]="";
}

Function MetabaseQuery($database,$query)
{
	global $databases;

	return($databases[$database]->Query($query));	
}

Function MetabaseQueryField($database,$query,&$field,$type="text")
{
	global $databases;

	return($databases[$database]->QueryField($query,$field,$type));
}

Function MetabaseQueryRow($database,$query,&$row,$types="")
{
	global $databases;

	return($databases[$database]->QueryRow($query,$row,$types));
}

Function MetabaseQueryColumn($database,$query,&$column,$type="text")
{
	global $databases;

	return($databases[$database]->QueryColumn($query,$column,$type));
}

Function MetabaseQueryAll($database,$query,&$all,$types="")
{
	global $databases;

	return($databases[$database]->QueryAll($query,$all,$types));
}

Function MetabaseReplace($database,$table,&$fields)
{
	global $databases;

	return($databases[$database]->Replace($table,$fields));
}

Function MetabasePrepareQuery($database,$query)
{
	global $databases;

	return($databases[$database]->PrepareQuery($query));
}

Function MetabaseFreePreparedQuery($database,$prepared_query)
{
	global $databases;

	return($databases[$database]->FreePreparedQuery($prepared_query));
}

Function MetabaseExecuteQuery($database,$prepared_query)
{
	global $databases;

	return($databases[$database]->ExecuteQuery($prepared_query));
}

Function MetabaseQuerySet($database,$prepared_query,$parameter,$type,$value,$is_null=0,$field="")
{
	global $databases;

	return($databases[$database]->QuerySet($prepared_query,$parameter,$type,$value,$is_null,$field));
}

Function MetabaseQuerySetNull($database,$prepared_query,$parameter,$type)
{
	global $databases;

	return($databases[$database]->QuerySetNull($prepared_query,$parameter,$type));
}

Function MetabaseQuerySetText($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetText($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetCLOB($database,$prepared_query,$parameter,$value,$field)
{
	global $databases;

	return($databases[$database]->QuerySetCLOB($prepared_query,$parameter,$value,$field));
}

Function MetabaseQuerySetBLOB($database,$prepared_query,$parameter,$value,$field)
{
	global $databases;

	return($databases[$database]->QuerySetBLOB($prepared_query,$parameter,$value,$field));
}

Function MetabaseQuerySetInteger($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetInteger($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetBoolean($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetBoolean($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetDate($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetDate($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetTimestamp($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetTimestamp($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetTime($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetTime($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetFloat($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetFloat($prepared_query,$parameter,$value));
}

Function MetabaseQuerySetDecimal($database,$prepared_query,$parameter,$value)
{
	global $databases;

	return($databases[$database]->QuerySetDecimal($prepared_query,$parameter,$value));
}

Function MetabaseAffectedRows($database,&$affected_rows)
{
	global $databases;
	
	$affected_rows = $databases[$database]->AffectedRows();
	if (DB::isError($affected_rows)) {
		return($databases[$database]->SetError("Affected rows","there was no previous valid query to determine the number of affected rows"));
	} else {
		return(1);
	}
}

Function MetabaseFetchResult($database,$result,$row,$field)
{
	global $databases;

	return($databases[$database]->FetchResult($result,$row,$field));
}

Function MetabaseFetchCLOBResult($database,$result,$row,$field)
{
	global $databases;

	return($databases[$database]->fetchClobResult($result, $row, $field));
}

Function MetabaseFetchBlobResult($database,$result,$row,$field)
{
	global $databases;

	return($databases[$database]->fetchBlobResult($result, $row, $field));
}

Function MetabaseDestroyResultLOB($database,$lob)
{
	global $databases;

	return($databases[$database]->destroyResultLOB($lob));
}

Function MetabaseEndOfResultLOB($database,$lob)
{
	global $databases;

	return($databases[$database]->endOfResultLob($lob));
}

Function MetabaseReadResultLOB($database,$lob,&$data,$length)
{
	global $databases;

	return($databases[$database]->readResultLob($lob, $data, $length));
}

Function MetabaseResultIsNull($database,$result,$row,$field)
{
	global $databases;

	return($databases[$database]->ResultIsNull($result,$row,$field));
}

Function MetabaseFetchDateResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value,MDB_TYPE_DATE);
    return($value);
}

Function MetabaseFetchTimestampResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->convertResult($value,MDB_TYPE_TIMESTAMP);
    return($value);
}

Function MetabaseFetchTimeResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value,MDB_TYPE_TIME);
    return($value);
}

Function MetabaseFetchBooleanResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value,MDB_TYPE_BOOLEAN);
    return($value);
}

Function MetabaseFetchFloatResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value,MDB_TYPE_FLOAT);
    return($value);
}

Function MetabaseFetchDecimalResult($database,$result,$row,$field)
{
	global $databases;
    $value = $databases[$database]->fetchResult($result, $row, $field);
    $databases[$database]->ConvertResult($value,MDB_TYPE_DECIMAL);
    return($value);
}

Function MetabaseFetchResultField($database,$result,&$field)
{
	global $databases;

	return($databases[$database]->FetchResultField($result,$field));
}

Function MetabaseFetchResultArray($database,$result,&$array,$row)
{
	global $databases;

	return($databases[$database]->fetchInto($result,$array,NULL,$row));
}

Function MetabaseFetchResultRow($database,$result,&$row)
{
	global $databases;

	return($databases[$database]->FetchResultRow($result,$row));
}

Function MetabaseFetchResultColumn($database,$result,&$column)
{
	global $databases;

	return($databases[$database]->FetchResultColumn($result,$column));
}

Function MetabaseFetchResultAll($database,$result,&$all)
{
	global $databases;

	return($databases[$database]->FetchResultAll($result,$all));
}

Function MetabaseNumberOfRows($database,$result)
{
	global $databases;

	return($databases[$database]->numRows($result));
}

Function MetabaseNumberOfColumns($database,$result)
{
	global $databases;

	return($databases[$database]->numCols($result));
}

Function MetabaseGetColumnNames($database,$result,&$column_names)
{
	global $databases;

	return($databases[$database]->GetColumnNames($result,$column_names));
}

Function MetabaseSetResultTypes($database,$result,&$types)
{
	global $databases;

	return($databases[$database]->SetResultTypes($result,$types));
}

Function MetabaseFreeResult($database,$result)
{
	global $databases;

	return($databases[$database]->FreeResult($result));
}

Function MetabaseError($database)
{
	global $databases;

	return($databases[$database]->Error());
}

Function MetabaseSetErrorHandler($database,$function)
{
	global $databases;

	return($databases[$database]->SetErrorHandler($function));
}

Function MetabaseCreateDatabase($database,$name)
{
	global $databases;

	return($databases[$database]->CreateDatabase($name));
}

Function MetabaseDropDatabase($database,$name)
{
	global $databases;

	return($databases[$database]->DropDatabase($name));
}

Function MetabaseSetDatabase($database,$name)
{
	global $databases;

	return($databases[$database]->SetDatabase($name));
}

Function MetabaseGetIntegerFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetIntegerFieldTypeDeclaration($name,$field));
}

Function MetabaseGetTextFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetTextFieldTypeDeclaration($name,$field));
}

Function MetabaseGetCLOBFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetCLOBFieldTypeDeclaration($name,$field));
}

Function MetabaseGetBLOBFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetBLOBFieldTypeDeclaration($name,$field));
}

Function MetabaseGetBooleanFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetBooleanFieldTypeDeclaration($name,$field));
}

Function MetabaseGetDateFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetDateFieldTypeDeclaration($name,$field));
}

Function MetabaseGetTimestampFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetTimestampFieldTypeDeclaration($name,$field));
}

Function MetabaseGetTimeFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetTimeFieldTypeDeclaration($name,$field));
}

Function MetabaseGetFloatFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetFloatFieldTypeDeclaration($name,$field));
}

Function MetabaseGetDecimalFieldTypeDeclaration($database,$name,&$field)
{
	global $databases;

	return($databases[$database]->GetDecimalFieldTypeDeclaration($name,$field));
}

Function MetabaseGetTextFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetTextFieldValue($value));
}

Function MetabaseGetBooleanFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetBooleanFieldValue($value));
}

Function MetabaseGetDateFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetDateFieldValue($value));
}

Function MetabaseGetTimestampFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetTimestampFieldValue($value));
}

Function MetabaseGetTimeFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetTimeFieldValue($value));
}

Function MetabaseGetFloatFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetFloatFieldValue($value));
}

Function MetabaseGetDecimalFieldValue($database,$value)
{
	global $databases;

	return($databases[$database]->GetDecimalFieldValue($value));
}

Function MetabaseSupport($database,$feature)
{
	global $databases;

	return($databases[$database]->Support($feature));
}

Function MetabaseCreateTable($database,$name,&$fields)
{
	global $databases;

	return($databases[$database]->CreateTable($name,$fields));
}

Function MetabaseDropTable($database,$name)
{
	global $databases;

	return($databases[$database]->DropTable($name));
}

Function MetabaseAlterTable($database,$name,&$changes,$check=0)
{
	global $databases;

	return($databases[$database]->AlterTable($name,$changes,$check));
}

Function MetabaseCreateSequence($database,$name,$start)
{
	global $databases;

	$value = $databases[$database]->CreateSequence($name,$start);
	if (DB::isError($value)) {
		return($databases[$database]->SetError("Create sequence","sequence creation is not supported"));
	} else {
		return(1);
	}
}

Function MetabaseDropSequence($database,$name)
{
	global $databases;

	$value = $databases[$database]->DropSequence($name);
	if (DB::isError($value)) {
		return($databases[$database]->SetError("Drop sequence","sequence dropping is not supported"));
	} else {
		return(1);
	}
}

Function MetabaseGetSequenceNextValue($database,$name,&$value)
{
	global $databases;

	$value = $databases[$database]->nextId($name,false);
	if (DB::isError($value)) {
		return($databases[$database]->SetError("Get sequence next value","getting sequence next value is not supported"));
	} else {
		return(1);
	}
}

Function MetabaseGetSequenceCurrentValue($database,$name,&$value)
{
	global $databases;

	$value = $databases[$database]->currId($name);
	if (DB::isError($value)) {
		return($databases[$database]->SetError("Get sequence current value","getting sequence current value is not supported"));
	} else {
		return(1);
	}
}

Function MetabaseAutoCommitTransactions($database,$auto_commit)
{
	global $databases;

	return($databases[$database]->AutoCommitTransactions($auto_commit));
}

Function MetabaseCommitTransaction($database)
{
	global $databases;

	return($databases[$database]->CommitTransaction());
}

Function MetabaseRollbackTransaction($database)
{
	global $databases;

	return($databases[$database]->RollbackTransaction());
}

Function MetabaseCreateIndex($database,$table,$name,$definition)
{
	global $databases;

	return($databases[$database]->CreateIndex($table,$name,$definition));
}

Function MetabaseDropIndex($database,$table,$name)
{
	global $databases;

	return($databases[$database]->DropIndex($table,$name));
}

Function MetabaseNow()
{
	return(strftime("%Y-%m-%d %H:%M:%S"));
}

Function MetabaseToday()
{
	return(strftime("%Y-%m-%d"));
}

Function MetabaseTime()
{
	return(strftime("%H:%M:%S"));
}

Function MetabaseSetSelectedRowRange($database,$first,$limit)
{
	global $databases;

	return($databases[$database]->SetSelectedRowRange($first,$limit));
}

Function MetabaseEndOfResult($database,$result)
{
	global $databases;

	return($databases[$database]->EndOfResult($result));
}

Function MetabaseCaptureDebugOutput($database,$capture)
{
	global $databases;

	$databases[$database]->CaptureDebugOutput($capture);
}

Function MetabaseDebugOutput($database)
{
	global $databases;

	return($databases[$database]->DebugOutput());
}

Function MetabaseDebug($database,$message)
{
	global $databases;

	return($databases[$database]->Debug($message));
}

function MetabaseShutdownTransactions()
{
	shutdownTransactions();
}

function MetabaseDefaultDebugOutput($database, $message)
{
	defaultDebugOutput($database, $message);
}

Function MetabaseCreateLOB(&$arguments,&$lob)
{
	return(createLob(&$arguments,&$lob));
}

Function MetabaseCreateLOBError(&$arguments, &$lob)
{
	return(createLobError(&$arguments, &$lob));
}

Function MetabaseDestroyLOB($lob)
{
	destroyLob($lob);
}

Function MetabaseEndOfLOB($lob)
{
	return(endOfLob($lob));
}

Function MetabaseReadLOB($lob, &$data, $length)
{
	return(readLob($lob, &$data, $length));
}

Function MetabaseLOBError($lob)
{
	return(lobError($lob));
}

?>