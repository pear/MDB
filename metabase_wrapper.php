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
// | Author: Lukas Smith <smith@dybnet.de>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//
// Metabase Wrapper for MDB.
//

require_once("MDB.php");

$metabase_databases = &$databases;
$metabases_lobs = &$lobs;

function MetabaseSetupDatabase($arguments, &$database)
{
    $dsninfo["phptype"] = $arguments["Type"];
    $dsninfo["username"] = $arguments["User"];
    $dsninfo["password"] = $arguments["Password"];
    $dsninfo["hostspec"] = $arguments["Host"];

    if(isset($arguments["IncludedConstant"])) {
        $options["includedconstant"] = $arguments["IncludedConstant"];
    }
    if(isset($arguments["IncludePath"])) {
        $options["includepath"] = $arguments["IncludePath"];
    }
    if(isset($arguments["Debug"])) {
        $options["debug"] = $arguments["Debug"];
    }
    if(isset($arguments["DecimalPlaces"])) {
        $options["decimal_places"] = $arguments["DecimalPlaces"];
    }
    if(isset($arguments["LOBBufferLength"])) {
        $options["LOBbufferlength"] = $arguments["LOBBufferLength"];
    }
    if(isset($arguments["LogLineBreak"])) {
        $options["loglinebreak"] = $arguments["LogLineBreak"];
    }
    if(is_array($arguments["Options"])) {
       $options = array_merge($options, $arguments["Options"]);
    }
    $options["seqname_format"] = "_sequence_%s";
    $db = MDB::connect($dsninfo, $options);
    
    if (MDB::isError($db) || !is_object($db)) {
        $database = 0;
        $error = $result->getMessage;
    } else
    {
        $database = $db->database;
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
    unset($databases[$database]);
}

function MetabaseQuery($database, $query)
{
    global $databases;
    $result = $databases[$database]->query($query);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQueryField($database, $query, &$field, $type = "text")
{
    global $databases;
    $result = $databases[$database]->queryOne($query, $field, $type);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryRow($database, $query, &$row, $types = "")
{
    global $databases;
    $result = $databases[$database]->queryRow($query, $row, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryColumn($database, $query, &$column, $type = "text")
{
    global $databases;
    $result = $databases[$database]->queryCol($query, $column, $type);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQueryAll($database, $query, &$all, $types = "")
{
    global $databases;
    $result = $databases[$database]->queryAll($query, $all, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseReplace($database, $table, &$fields)
{
    global $databases;
    $result = $databases[$database]->replace($table, $fields);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabasePrepareQuery($database, $query)
{
    global $databases;
    $result = $databases[$database]->prepareQuery($query);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFreePreparedQuery($database, $prepared_query)
{
    global $databases;
    $result = $databases[$database]->freePreparedQuery($prepared_query);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseExecuteQuery($database, $prepared_query)
{
    global $databases;
    $result = $databases[$database]->executeQuery($prepared_query);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQuerySet($database, $prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
{
    global $databases;
    $result = $databases[$database]->querySet($prepared_query, $parameter, $type, $value, $is_null, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetNull($database, $prepared_query, $parameter, $type)
{
    global $databases;
    $result = $databases[$database]->querySetNull($prepared_query, $parameter, $type);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetText($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetText($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetCLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;
    $result = $databases[$database]->querySetCLob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBLob($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;
    $result = $databases[$database]->querySetBLob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetInteger($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetInteger($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBoolean($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetBoolean($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDate($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetDate($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTimestamp($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetTimestamp($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTime($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetTime($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetFloat($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetFloat($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDecimal($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->querySetDecimal($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseAffectedRows($database, &$affected_rows)
{
    global $databases;
    $result = $databases[$database]->affectedRows();
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $affected_rows = $result;
        return(1);
    }
}

function MetabaseFetchResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchClobResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchClobResult($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchBlobResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchBlobResult($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseDestroyResultLob($database, $lob)
{
    global $databases;
    $result = $databases[$database]->destroyResultLob($lob);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfResultLob($database, $lob)
{
    global $databases;
    $result = $databases[$database]->endOfResultLob($lob);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseReadResultLob($database, $lob, &$data, $length)
{
    global $databases;
    $result = $databases[$database]->readResultLob($lob, $data, $length);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseResultIsNull($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->resultIsNull($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchDateResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_DATE);
        return($result);
    }
}

function MetabaseFetchTimestampResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_TIMESTAMP);
        return($result);
    }
}

function MetabaseFetchTimeResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_TIME);
        return($result);
    }
}

function MetabaseFetchBooleanResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_BOOLEAN);
        return($result);
    }
}

function MetabaseFetchFloatResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_FLOAT);
        return($result);
    }
}

function MetabaseFetchDecimalResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetch($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $databases[$database]->convertResult($result, MDB_TYPE_DECIMAL);
        return($result);
    }
}

function MetabaseFetchResultField($database, $result, &$field)
{
    global $databases;
    $result = $databases[$database]->fetchOne($result, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultArray($database, $result, &$array, $row)
{
    global $databases;
    $result = $databases[$database]->fetchInto($result, $array, "NULL", $row);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultRow($database, $result, &$row)
{
    global $databases;
    $result = $databases[$database]->fetchRow($result, $row);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultColumn($database, $result, &$column)
{
    global $databases;
    $result = $databases[$database]->fetchCol($result, $column);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFetchResultAll($database, $result, &$all)
{
    global $databases;
    $result = $databases[$database]->fetchAll($result, $all);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseNumberOfRows($database, $result)
{
    global $databases;
    $result = $databases[$database]->numRows($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseNumberOfColumns($database, $result)
{
    global $databases;
    $result = $databases[$database]->numCols($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetColumnNames($database, $result, &$column_names)
{
    global $databases;
    $result = $databases[$database]->getColumnNames($result, $column_names);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetResultTypes($database, $result, &$types)
{
    global $databases;
    $result = $databases[$database]->setResultTypes($result, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseFreeResult($database, $result)
{
    global $databases;
    $result = $databases[$database]->freeResult($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseError($database)
{
    global $databases;
    $result = $databases[$database]->error();
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetErrorHandler($database, $function)
{
    global $databases;
    $result = $databases[$database]->setErrorHandler($function);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateDatabase($database, $name)
{
    global $databases;
    $result = $databases[$database]->createDatabase($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropDatabase($database, $name)
{
    global $databases;
    $result = $databases[$database]->dropDatabase($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseSetDatabase($database, $name)
{
    global $databases;
    $result = $databases[$database]->setDatabase($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetIntegerFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getIntegerDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTextFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getTextDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetClobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getClobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetBlobFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getBlobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetBooleanFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getBooleanDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetDateFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getDateDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTimestampFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getTimestampDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTimeFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getTimeDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetFloatFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getFloatDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetDecimalFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getDecimalDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTextFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTextFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetBooleanFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getBooleanFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDateFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getDateFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimestampFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTimestampFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimeFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTimeFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetFloatFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getFloatFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDecimalFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getDecimalFieldValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseSupport($database, $feature)
{
    global $databases;
    $result = $databases[$database]->support($feature);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseCreateTable($database, $name, &$fields)
{
    global $databases;
    $result = $databases[$database]->createTable($name, $fields);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropTable($database, $name)
{
    global $databases;
    $result = $databases[$database]->dropTable($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseAlterTable($database, $name, &$changes, $check = 0)
{
    global $databases;
    $result = $databases[$database]->alterTable($name, $changes, $check);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseListTables($database, &$tables)
{
    global $databases;
    $result = $databases[$database]->listTables($tables);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $tables = $result;
        return(1);
    }
}

function MetabaseListTableFields($database, $table, &$fields)
{
    global $databases;
    $result = $databases[$database]->listTableFields($table,$fields);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $fields = $result;
        return(1);
    }
}

function MetabaseGetTableFieldDefinition($database, $table, $field, &$definition)
{
    global $databases;
    $result = $databases[$database]->getTableFieldDefinition($table, $field, $definition);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $definition = $result;
        return(1);
    }
}

function MetabaseCreateSequence($database, $name, $start)
{
    global $databases;
    $result = $databases[$database]->createSequence($name, $start);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropSequence($database, $name)
{
    global $databases;
    $result = $databases[$database]->dropSequence($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetSequenceNextValue($database, $name, &$value)
{
    global $databases;
    $result = $databases[$database]->nextId($name, $value, FALSE);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetSequenceCurrentValue($database, $name, &$value)
{
    global $databases;
    $result = $databases[$database]->currId($name, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseListSequences($database, &$sequences)
{
    global $databases;
    $result = $databases[$database]->listSequences($sequence);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        $sequences = $result;
        return(1);
    }
}

function MetabaseAutoCommitTransactions($database, $auto_commit)
{
    global $databases;
    $result = $databases[$database]->autoCommit($auto_commit);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCommitTransaction($database)
{
    global $databases;
    $result = $databases[$database]->commit();
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseRollbackTransaction($database)
{
    global $databases;
    $result = $databases[$database]->rollback();
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateIndex($database, $table, $name, $definition)
{
    global $databases;
    $result = $databases[$database]->createIndex($table, $name, $definition);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDropIndex($database, $table, $name)
{
    global $databases;
    $result = $databases[$database]->dropIndex($table, $name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
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
    global $databases;
    $result = $databases[$database]->setSelectedRowRange($first, $limit);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfResult($database, $result)
{
    global $databases;
    $result = $databases[$database]->endOfResult($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
       return($result);
    }
}

function MetabaseCaptureDebugOutput($database, $capture)
{
    global $databases;
    $result = $databases[$database]->captureDebugOutput($capture);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDebugOutput($database)
{
    global $databases;
    $result = $databases[$database]->debugOutput();
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDebug($database, $message)
{
    global $databases;
    $result = $databases[$database]->debug($message);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
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
    global $databases;
    $result = $databases[$database]->defaultDebugOutput($databases[$database], $message);
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateLOB(&$arguments, &$lob)
{
    global $databases;
    $args = $arguments;
    $args["Database"] = $databases[$arguments["Database"]];
    $result = createLob(&$args, &$lob);
    $args["Database"] = $arguments["Database"];
    $arguments = $args;
    if (MDB::isError($result)) {
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseDestroyLOB($lob)
{
    $result = destroyLob($lob);
    if (MDB::isError($result)) {
        global $databases;
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfLOB($lob)
{
    $result = endOfLob($lob);
    if (MDB::isError($result)) {
        global $databases;
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseReadLOB($lob, &$data, $length)
{
    $result = readLob($lob, &$data, $length);
    if (MDB::isError($result)) {
        global $databases;
        $databases[$database]->setError('', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseLOBError($lob)
{
    $result = lobError($lob);
    if (MDB::isError($result)) {
        global $databases;
        $databases[$database]->setError('', $result->getMessage());
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
        global $databases;
        $database = count($databases)+1;
        
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];
        
        $options["includedconstant"] = $arguments["IncludedConstant"];
        if (isset($arguments["Persistent"])) {
            $options["persistent"] = TRUE;
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
            $databases[$database] = $db;
            $databases[$database]->database = $database;
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
        $dsninfo["phptype"] = $arguments["Type"];
        $dsninfo["username"] = $arguments["User"];
        $dsninfo["password"] = $arguments["Password"];
        $dsninfo["hostspec"] = $arguments["Host"];

        if(isset($arguments["IncludedConstant"])) {
            $options["includedconstant"] = $arguments["IncludedConstant"];
        }
        if(isset($arguments["IncludePath"])) {
            $options["includepath"] = $arguments["IncludePath"];
        }
        if(isset($arguments["Debug"])) {
            $options["debug"] = $arguments["Debug"];
        }
        if(isset($arguments["DecimalPlaces"])) {
            $options["decimalplaces"] = $arguments["DecimalPlaces"];
        }
        if(isset($arguments["LOBBufferLength"])) {
            $options["LOBbufferlength"] = $arguments["LOBBufferLength"];
        }
        if(isset($arguments["LogLineBreak"])) {
            $options["loglinebreak"] = $arguments["LogLineBreak"];
        }
        if(is_array($arguments["Options"])) {
            $options = array_merge($options, $arguments["Options"]);
        }
        $options["seqname_format"] = "_sequence_%s";
        $db = MDB::connect($dsninfo, $options);

        if (MDB::isError($db) || !is_object($db)) {
            $error = $result->getMessage.":".$result->getCode();
        }
        return($this->MDB_manager_object->updateDatabase($current_schema_file, $previous_schema_file, $dsninfo, $variables, $options));
    }

    function DumpDatabaseContents($schema_file, &$setup_arguments, &$dump_arguments, &$variables)
    {
        return($this->MDB_manager_object->dumpDatabaseContents($schema_file, $setup_arguments, $dump_arguments, $variables));
    }
};
?>
