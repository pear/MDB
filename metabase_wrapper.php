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

/**
* Wrapper that makes MDB behave like Metabase
*
* @package MDB
* @author  Lukas Smith <smith@dybnet.de>
*/

require_once(dirname(__FILE__).'/MDB.php');

$metabase_databases = &$databases;
$metabases_lobs = &$lobs;

function MetabaseSetupDatabase($arguments, &$database)
{
    _convertArguments($arguments, $dsninfo, $options);
    $db = MDB::connect($dsninfo, $options);

    if (MDB::isError($db) || !is_object($db)) {
        $database = 0;
        return $db->getMessage();
    }
    $database = $db->database;
    return ('');
}

function MetabaseSetupDatabaseObject($arguments, &$db)
{
    _convertArguments($arguments, $dsninfo, $options);
    $db = MDB::connect($dsninfo, $options);

    if (MDB::isError($db) || !is_object($db)) {
        return $db->getMessage();
    }
    return ('');
}

function _convertArguments($arguments, &$dsninfo, &$options)
{
    $dsninfo['phptype'] = $arguments['Type'];
    $dsninfo['username'] = $arguments['User'];
    $dsninfo['password'] = $arguments['Password'];
    $dsninfo['hostspec'] = $arguments['Host'];

    if (isset($arguments["IncludedConstant"])) {
        $options["includedconstant"] = $arguments["IncludedConstant"];
    }
    if (isset($arguments["Persistent"])) {
        $options["persistent"] = TRUE;
    }
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

    if(is_array($arguments['options'])) {
       $options = array_merge($options, $arguments['options']);
    }
    $options['seqname_format'] = '_sequence_%s';
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
        $databases[$database]->setError('Query', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQueryField($database, $query, &$field, $type = 'text')
{
    global $databases;
    $result = $databases[$database]->queryOne($query, $type);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QueryField', $result->getMessage());
        return(0);
    } else {
        $field = $result;
        return(1);
    }
}

function MetabaseQueryRow($database, $query, &$row, $types = '')
{
    global $databases;
    $result = $databases[$database]->queryRow($query, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QueryRow', $result->getMessage());
        return(0);
    } else {
        $row = $result;
        return(1);
    }
}

function MetabaseQueryColumn($database, $query, &$column, $type = 'text')
{
    global $databases;
    $result = $databases[$database]->queryCol($query, $type, DB_FETCHMODE_ORDERED);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QueryColumn', $result->getMessage());
        return(0);
    } else {
        $column = $result;
        return(1);
    }
}

function MetabaseQueryAll($database, $query, &$all, $types = '')
{
    global $databases;
    $result = $databases[$database]->queryAll($query, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QueryAll', $result->getMessage());
        return(0);
    } else {
        $all = $result;
        return(1);
    }
}

function MetabaseReplace($database, $table, &$fields)
{
    global $databases;
    $result = $databases[$database]->replace($table, $fields);
    if (MDB::isError($result)) {
        $databases[$database]->setError('Replace', $result->getMessage());
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
        $databases[$database]->setError('PrepareQuery', $result->getMessage());
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
        $databases[$database]->setError('FreePreparedQuery', $result->getMessage());
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
        $databases[$database]->setError('ExecuteQuery', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseQuerySet($database, $prepared_query, $parameter, $type, $value, $is_null = 0, $field = '')
{
    global $databases;
    $result = $databases[$database]->setParam($prepared_query, $parameter, $type, $value, $is_null, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySet', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetNull($database, $prepared_query, $parameter, $type)
{
    global $databases;
    $result = $databases[$database]->setParamNull($prepared_query, $parameter, $type);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetNull', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetText($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamText($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetText', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetCLOB($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;
    $result = $databases[$database]->setParamClob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetCLOB', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBLOB($database, $prepared_query, $parameter, $value, $field)
{
    global $databases;
    $result = $databases[$database]->setParamBlob($prepared_query, $parameter, $value, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetBLOB', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetInteger($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamInteger($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetInteger', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetBoolean($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamBoolean($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetBoolean', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDate($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamDate($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetDate(', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTimestamp($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamTimestamp($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetTimestamp', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetTime($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamTime($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetTime', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetFloat($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamFloat($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetFloat', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseQuerySetDecimal($database, $prepared_query, $parameter, $value)
{
    global $databases;
    $result = $databases[$database]->setParamDecimal($prepared_query, $parameter, $value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('QuerySetDecimal', $result->getMessage());
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
        $databases[$database]->setError('AffectedRows', $result->getMessage());
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
        $databases[$database]->setError('FetchResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchCLOBResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchClob($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchCLOBResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchBLOBResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchBlob($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchBLOBResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseDestroyResultLOB($database, $lob)
{
    global $databases;
    $result = $databases[$database]->destroyResultLob($lob);
    if (MDB::isError($result)) {
        $databases[$database]->setError('DestroyResultLOB', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseEndOfResultLOB($database, $lob)
{
    global $databases;
    $result = $databases[$database]->endOfResultLob($lob);
    if (MDB::isError($result)) {
        $databases[$database]->setError('EndOfResultLOB', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseReadResultLOB($database, $lob, &$data, $length)
{
    global $databases;
    $result = $databases[$database]->readResultLob($lob, $data, $length);
    if (MDB::isError($result)) {
        $databases[$database]->setError('ReadResultLOB', $result->getMessage());
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
        $databases[$database]->setError('ResultIsNull', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchDateResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchDate($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchDateResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchTimestampResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchTimestamp($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchTimestampResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchTimeResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchTime($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchTimeResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchBooleanResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchBoolean($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchBooleanResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchFloatResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchFloat($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchFloatResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchDecimalResult($database, $result, $row, $field)
{
    global $databases;
    $result = $databases[$database]->fetchDecimal($result, $row, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchDecimalResult', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseFetchResultField($database, $result, &$field)
{
    global $databases;
    $result = $databases[$database]->fetchOne($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchResultField', $result->getMessage());
        return(0);
    } else {
        $field = $result;
        return(1);
    }
}

function MetabaseFetchResultArray($database, $result, &$array, $row)
{
    global $databases;
    $result = $databases[$database]->fetchInto($result, 'NULL', $row);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchResultArray', $result->getMessage());
        return(0);
    } else {
        $array = $result;
        return(1);
    }
}

function MetabaseFetchResultRow($database, $result, &$row)
{
    global $databases;
    $result = $databases[$database]->fetchRow($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchResultRow', $result->getMessage());
        return(0);
    } else {
        $row = $result;
        return(1);
    }
}

function MetabaseFetchResultColumn($database, $result, &$column)
{
    global $databases;
    $result = $databases[$database]->fetchCol($result, DB_FETCHMODE_ORDERED);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchResultColumn', $result->getMessage());
        return(0);
    } else {
        $column = $result;
        return(1);
    }
}

function MetabaseFetchResultAll($database, $result, &$all)
{
    global $databases;
    $result = $databases[$database]->fetchAll($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('FetchResultAll', $result->getMessage());
        return(0);
    } else {
        $all = $result;
        return(1);
    }
}

function MetabaseNumberOfRows($database, $result)
{
    global $databases;
    $result = $databases[$database]->numRows($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('NumberOfRows', $result->getMessage());
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
        $databases[$database]->setError('NumberOfColumns', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetColumnNames($database, $result, &$column_names)
{
    global $databases;
    $result = $databases[$database]->getColumnNames($result);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetColumnNames', $result->getMessage());
        return(0);
    } else {
        $column_names = $result;
        return(1);
    }
}

function MetabaseSetResultTypes($database, $result, &$types)
{
    global $databases;
    $result = $databases[$database]->setResultTypes($result, $types);
    if (MDB::isError($result)) {
        $databases[$database]->setError('SetResultTypes', $result->getMessage());
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
        $databases[$database]->setError('FreeResult', $result->getMessage());
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
        $databases[$database]->setError('Error', $result->getMessage());
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
        $databases[$database]->setError('SetErrorHandler', $result->getMessage());
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
        $databases[$database]->setError('CreateDatabase', $result->getMessage());
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
        $databases[$database]->setError('DropDatabase', $result->getMessage());
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
        $databases[$database]->setError('SetDatabase', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetIntegerFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getIntegerDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetIntegerFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetTextFieldTypeDeclaration', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetCLOBFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getClobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetCLOBFieldTypeDeclaration', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetBLOBFieldTypeDeclaration($database, $name, &$field)
{
    global $databases;
    $result = $databases[$database]->getBlobDeclaration($name, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetBLOBFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetBooleanFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetDateFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetTimestampFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetTimeFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetFloatFieldTypeDeclaration', $result->getMessage());
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
        $databases[$database]->setError('GetDecimalFieldTypeDeclaration', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetTextFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTextValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetTextFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetBooleanFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getBooleanValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetBooleanFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDateFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getDateValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetDateFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimestampFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTimestampValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetTimestampFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetTimeFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getTimeValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetTimeFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetFloatFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getFloatValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetFloatFieldValue', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseGetDecimalFieldValue($database, $value)
{
    global $databases;
    $result = $databases[$database]->getDecimalValue($value);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetDecimalFieldValue', $result->getMessage());
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
        $databases[$database]->setError('Support', $result->getMessage());
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
        $databases[$database]->setError('CreateTable', $result->getMessage());
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
        $databases[$database]->setError('DropTable', $result->getMessage());
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
        $databases[$database]->setError('AlterTable', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseListTables($database, &$tables)
{
    global $databases;
    $result = $databases[$database]->listTables();
    if (MDB::isError($result)) {
        $databases[$database]->setError('ListTables', $result->getMessage());
        return(0);
    } else {
        $tables = $result;
        return(1);
    }
}

function MetabaseListTableFields($database, $table, &$fields)
{
    global $databases;
    $result = $databases[$database]->listTableFields($table);
    if (MDB::isError($result)) {
        $databases[$database]->setError('ListTableFields', $result->getMessage());
        return(0);
    } else {
        $fields = $result;
        return(1);
    }
}

function MetabaseGetTableFieldDefinition($database, $table, $field, &$definition)
{
    global $databases;
    $result = $databases[$database]->getTableFieldDefinition($table, $field);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetTableFieldDefinition', $result->getMessage());
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
        $databases[$database]->setError('CreateSequence', $result->getMessage());
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
        $databases[$database]->setError('DropSequence', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseGetSequenceNextValue($database, $name, &$value)
{
    global $databases;
    $result = $databases[$database]->nextId($name, FALSE);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetSequenceNextValue', $result->getMessage());
        return(0);
    } else {
        $value = $result;
        return(1);
    }
}

function MetabaseGetSequenceCurrentValue($database, $name, &$value)
{
    global $databases;
    $result = $databases[$database]->currId($name);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetSequenceCurrentValue', $result->getMessage());
        return(0);
    } else {
        $value = $result;
        return(1);
    }
}

function MetabaseListSequences($database, &$sequences)
{
    global $databases;
    $result = $databases[$database]->listSequences();
    if (MDB::isError($result)) {
        $databases[$database]->setError('ListSequences', $result->getMessage());
        return(0);
    } else {
        $sequences = $result;
        return(1);
    }
}

function MetabaseGetSequenceDefinition($database, $sequence, &$definition)
{
    global $databases;
    $result = $databases[$database]->getSequenceDefinition($sequence);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetSequenceDefinition', $result->getMessage());
        return(0);
    } else {
        $definition = $result;
        return(1);
    }
}

function MetabaseAutoCommitTransactions($database, $auto_commit)
{
    global $databases;
    $result = $databases[$database]->autoCommit($auto_commit);
    if (MDB::isError($result)) {
        $databases[$database]->setError('AutoCommitTransactions', $result->getMessage());
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
        $databases[$database]->setError('CommitTransaction', $result->getMessage());
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
        $databases[$database]->setError('RollbackTransaction', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

function MetabaseCreateIndex($database, $table, $name, $definition)
{
    global $databases;
    $result = $databases[$database]->createIndex($table, $name, $definition);
    if (MDB::isError($result)) {
        $databases[$database]->setError('CreateIndex', $result->getMessage());
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
        $databases[$database]->setError('DropIndex', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseListTableIndex($database, $table, &$index)
{
    global $databases;
    $result = $databases[$database]->listTableIndex($table);
    if (MDB::isError($result)) {
        $databases[$database]->setError('ListTableIndex', $result->getMessage());
        return(0);
    } else {
        $index = $result;
        return(1);
    }
}

function MetabaseGetTableIndexDefinition($database, $table, $index, &$definition)
{
    global $databases;
    $result = $databases[$database]->getTableFieldDefinition($table, $index);
    if (MDB::isError($result)) {
        $databases[$database]->setError('GetTableIndexDefinition', $result->getMessage());
        return(0);
    } else {
        $definition = $result;
        return(1);
    }
}

function MetabaseNow()
{
    return(mdbNow());
}

function MetabaseToday()
{
    return(mdbToday());
}

function MetabaseTime()
{
    return(mdbTime());
}

function MetabaseSetSelectedRowRange($database, $first, $limit)
{
    global $databases;
    $result = $databases[$database]->setSelectedRowRange($first, $limit);
    if (MDB::isError($result)) {
        $databases[$database]->setError('SetSelectedRowRange', $result->getMessage());
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
        $databases[$database]->setError('EndOfResult', $result->getMessage());
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
        $databases[$database]->setError('CaptureDebugOutput', $result->getMessage());
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
        $databases[$database]->setError('DebugOutput', $result->getMessage());
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
        $databases[$database]->setError('Debug', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseShutdownTransactions()
{
    _shutdownTransactions();
}

function MetabaseDefaultDebugOutput($database, $message)
{
    global $databases;
    $result = $databases[$database]->defaultDebugOutput($databases[$database], $message);
    if (MDB::isError($result)) {
        $databases[$database]->setError('DefaultDebugOutput', $result->getMessage());
        return(0);
    } else {
        return(1);
    }
}

function MetabaseCreateLOB(&$arguments, &$lob)
{
    global $databases;
    $args = $arguments;
    $args = array_change_key_case($arguments, 0);
    $args['database'] = $databases[$arguments['Database']];
    $result = createLob($args, $lob);
    $arguments = $args;
    $arguments['Database'] = $args['database']->database;
    if (MDB::isError($result)) {
        $databases[$database]->setError('CreateLOB', $result->getMessage());
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
        $databases[$database]->setError('DestroyLOB', $result->getMessage());
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
        $databases[$database]->setError('EndOfLOB', $result->getMessage());
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
        $databases[$database]->setError('ReadLOB', $result->getMessage());
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
        $databases[$database]->setError('LOBError', $result->getMessage());
        return(0);
    } else {
        return($result);
    }
}

class Metabase_manager_class
{
    var $MDB_manager_object;

    var $fail_on_invalid_names = 1;
    var $error = '';
    var $warnings = array();
    var $database = 0;
    var $database_definition = array(
        'name' => '',
        'create' => 0,
        'TABLES' => array()
    );

    function Metabase_manager_class()
    {
        $this->MDB_manager_object = new MDB_manager;
        $this->MDB_manager_object->fail_on_invalid_names = &$this->fail_on_invalid_names;
        $this->MDB_manager_object->error = &$this->error;
        $this->MDB_manager_object->warnings = &$this->warnings;
        $this->MDB_manager_object->database_definition = &$this->database_definition;
    }

    function SetupDatabase(&$arguments)
    {
        _convertArguments($arguments, $dsninfo, $options);

        $result = $this->MDB_manager_object->connect($dsninfo, $options);
        if (MDB::isError($result)) {
            return $result->getMessage();
        }
        $this->database = $this->MDB_manager_object->database->database;
        return (1);
    }

    function CloseSetup()
    {
        $result = $this->MDB_manager_object->_close();
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function GetField(&$field, $field_name, $declaration, &$query)
    {
        if($declaration) {
            $result = $this->MDB_manager_object->database->getFieldDeclaration($field, $field_name, $declaration);
        } else {
            $result = $field_name;
        }
        if (MDB::isError($result)) {
            return(0);
        } else {
            $query = $result;
            return(1);
        }
    }

    function GetFieldList($fields, $declaration, &$query_fields)
    {
        if($declaration) {
            $result = $this->MDB_manager_object->database->getFieldDeclarationList($fields);
        } else {
            for(reset($fields), $i = 0;
                $field_number < count($fields);
                $i++, next($fields))
            {
                if ($i > 0) {
                    $query_fields .= ', ';
                }
                $result .= key($fields);
            }
        }
        if (MDB::isError($result)) {
            return(0);
        } else {
            $query_fields = $result;
            return(1);
        }
    }

    function GetFields($table, &$fields)
    {
        $result = $this->MDB_manager_object->database->getFieldDeclarationList($this->database_definition['TABLES'][$table]['FIELDS']);
        if (MDB::isError($result)) {
            return(0);
        } else {
            $fields = $result;
            return(1);
        }
    }

    function CreateTable($table_name, $table)
    {
        $result = $this->MDB_manager_object->_createTable($table_name, $table);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function _dropTable($table_name)
    {
        $result = $this->MDB_manager_object->_dropTable($table_name);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function CreateSequence($sequence_name, $sequence, $created_on_table)
    {
        $result = $this->MDB_manager_object->createSequence($sequence_name, $sequence, $created_on_table);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function DropSequence($sequence_name)
    {
        $result = $this->MDB_manager_object->_dropSequence($sequence_name);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function CreateDatabase()
    {
        $result = $this->MDB_manager_object->_createDatabase();
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function AddDefinitionChange(&$changes, $definition, $item, $change)
    {
        $result = $this->MDB_manager_object->_addDefinitionChange($changes, $definition, $item, $change);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function CompareDefinitions(&$previous_definition, &$changes)
    {
        $result = $this->MDB_manager_object->_compareDefinitions($previous_definition);
        if (MDB::isError($result)) {
            return(0);
        } else {
            $changes = $result;
            return(1);
        }
    }

    function AlterDatabase(&$previous_definition, &$changes)
    {
        $result = $this->MDB_manager_object->_alterDatabase($previous_definition, $changes);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function EscapeSpecialCharacters($string)
    {
        $result = $this->MDB_manager_object->_escapeSpecialCharacters($string);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return($result);
        }
    }

    function DumpSequence($sequence_name, $output, $eol, $dump_definition)
    {
        $result = $this->MDB_manager_object->_dumpSequence($sequence_name, $output, $eol, $dump_definition);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function DumpDatabase($arguments)
    {
        $result = $this->MDB_manager_object->dumpDatabase($arguments);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function ParseDatabaseDefinitionFile($input_file, &$database_definition, &$variables, $fail_on_invalid_names = 1)
    {
        $result = $this->MDB_manager_object->_parseDatabaseDefinitionFile($input_file, $variables, $fail_on_invalid_names);
        if (MDB::isError($result)) {
            return(0);
        } else {
            $database_definition = $result;
            return(1);
        }
    }

    function DumpDatabaseChanges(&$changes)
    {
        $result = $this->MDB_manager_object->_dumpDatabaseChanges($changes);
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }

    function UpdateDatabase($current_schema_file, $previous_schema_file, &$arguments, &$variables)
    {
        _convertArguments($arguments, $dsninfo, $options);

        $result = $this->MDB_manager_object->updateDatabase($current_schema_file, $previous_schema_file, $dsninfo, $variables, $options);
        if (MDB::isError($result)) {
            return $result->getMessage();
        }
        $this->database = $this->MDB_manager_object->database->database;
        return (1);
    }

    function DumpDatabaseContents($schema_file, &$setup_arguments, &$dump_arguments, &$variables)
    {
        $result = $this->MDB_manager_object->_dumpDatabaseContents($schema_file, $setup_arguments, $dump_arguments, $variables);
        if (MDB::isError($result)) {
            return(0);
        } else {
            $database_definition = $result;
            return($result);
        }
    }

    function GetDefinitionFromDatabase()
    {
        $result = $this->MDB_manager_object->getDefinitionFromDatabase();
        if (MDB::isError($result)) {
            return(0);
        } else {
            return(1);
        }
    }
};
?>
