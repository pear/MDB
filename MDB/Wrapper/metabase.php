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
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
 * Wrapper that makes MDB behave like Metabase
 *
 * @package MDB
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */

define("METABASE_TYPE_TEXT",      MDB_TYPE_TEXT);
define("METABASE_TYPE_BOOLEAN",   MDB_TYPE_BOOLEAN);
define("METABASE_TYPE_INTEGER",   MDB_TYPE_INTEGER);
define("METABASE_TYPE_DECIMAL",   MDB_TYPE_DECIMAL);
define("METABASE_TYPE_FLOAT",     MDB_TYPE_FLOAT);
define("METABASE_TYPE_DATE",      MDB_TYPE_DATE);
define("METABASE_TYPE_TIME",      MDB_TYPE_TIME);
define("METABASE_TYPE_TIMESTAMP", MDB_TYPE_TIMESTAMP);
define("METABASE_TYPE_CLOB",      MDB_TYPE_CLOB);
define("METABASE_TYPE_BLOB",      MDB_TYPE_BLOB);

$metabase_registered_transactions_shutdown=0;

$metabase_databases=array();

Function MetabaseParseConnectionArguments($connection,&$arguments)
{
}

Function MetabaseLoadClass($include,$include_path,$type)
{

}

Function MetabaseSetupInterface(&$arguments,&$db)
{

}

Function MetabaseSetupDatabaseObject($arguments,&$db)
{
}

Function MetabaseCloseSetup($database)
{
}

Function MetabaseNow()
{
}

Function MetabaseToday()
{
}

Function MetabaseTime()
{
}

Function MetabaseShutdownTransactions()
{
}

Function MetabaseDefaultDebugOutput($database,$message)
{
}

class MDB_PEAR_PROXY
{
    var $MDB_object;

    /* PUBLIC DATA */

    var $database=0;
    var $host="";
    var $user="";
    var $password="";
    var $options=array();
    var $supported=array();
    var $persistent=1;
    var $database_name="";
    var $warning="";
    var $affected_rows=-1;
    var $auto_commit=1;
    var $prepared_queries=array();
    var $decimal_places=2;
    var $first_selected_row=0;
    var $selected_row_limit=0;
    var $lob_buffer_length=8000;
    var $escape_quotes="";
    var $log_line_break="\n";

    /* PRIVATE DATA */

    var $lobs=array();
    var $clobs=array();
    var $blobs=array();
    var $last_error="";
    var $in_transaction=0;
    var $debug="";
    var $debug_output="";
    var $pass_debug_handle=0;
    var $result_types=array();
    var $error_handler="";
    var $manager;
    var $include_path="";
    var $manager_included_constant="";
    var $manager_include="";
    var $manager_sub_included_constant="";
    var $manager_sub_include="";
    var $manager_class_name="";

    /* PRIVATE METHODS */

    function MDB_PEAR_PROXY($MDB_object)
    {
        $this->MDB_object = $MDB_object;
        $this->MDB_object->sequence_prefix = '_seq_';
    }

    Function EscapeText(&$text)
    {
    }

    /* PUBLIC METHODS */

    Function Close()
    {
    }

    Function CloseSetup()
    {
    }

    Function Debug($message)
    {
    }

    Function DebugOutput()
    {
    }

    Function SetDatabase($name)
    {
    }

    Function RegisterTransactionShutdown($auto_commit)
    {
    }

    Function CaptureDebugOutput($capture)
    {
    }

    Function SetError($scope,$message)
    {
    }

    Function LoadExtension($scope,$extension,$included_constant,$include)
    {
    }

    Function LoadManager($scope)
    {
    }

    Function CreateDatabase($database)
    {
    }

    Function DropDatabase($database)
    {
    }

    Function CreateTable($name,&$fields)
    {
    }

    Function DropTable($name)
    {
    }

    Function AlterTable($name,&$changes,$check)
    {
    }

    Function ListTables(&$tables)
    {
    }

    Function ListTableFields($table,&$fields)
    {
    }

    Function GetTableFieldDefinition($table,$field,&$definition)
    {
    }

    Function ListTableIndexes($table,&$indexes)
    {
    }

    Function GetTableIndexDefinition($table,$index,&$definition)
    {
    }

    Function ListSequences(&$sequences)
    {
    }

    Function GetSequenceDefinition($sequence,&$definition)
    {
    }

    Function CreateIndex($table,$name,&$definition)
    {
    }

    Function DropIndex($table,$name)
    {
    }

    Function CreateSequence($name,$start)
    {
    }

    Function DropSequence($name)
    {
    }

    Function GetSequenceNextValue($name,&$value)
    {
    }

    Function GetSequenceCurrentValue($name,&$value)
    {
    }

    Function Query($query)
    {
    }

    Function Replace($table,&$fields)
    {
    }

    Function PrepareQuery($query)
    {
    }

    Function ValidatePreparedQuery($prepared_query)
    {
    }

    Function FreePreparedQuery($prepared_query)
    {
    }

    Function ExecutePreparedQuery($prepared_query,$query)
    {
    }

    Function ExecuteQuery($prepared_query)
    {
    }

    Function QuerySet($prepared_query,$parameter,$type,$value,$is_null=0,$field="")
    {
    }

    Function QuerySetNull($prepared_query,$parameter,$type)
    {
    }

    Function QuerySetText($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetCLOB($prepared_query,$parameter,$value,$field)
    {
    }

    Function QuerySetBLOB($prepared_query,$parameter,$value,$field)
    {
    }

    Function QuerySetInteger($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetBoolean($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetDate($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetTimestamp($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetTime($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetFloat($prepared_query,$parameter,$value)
    {
    }

    Function QuerySetDecimal($prepared_query,$parameter,$value)
    {
    }

    Function AffectedRows(&$affected_rows)
    {
    }

    Function EndOfResult($result)
    {
    }

    Function FetchResult($result,$row,$field)
    {
    }

    Function FetchLOBResult($result,$row,$field)
    {
    }

    Function RetrieveLOB($lob)
    {
    }

    Function EndOfResultLOB($lob)
    {
    }

    Function ReadResultLOB($lob,&$data,$length)
    {
    }

    Function DestroyResultLOB($lob)
    {
    }

    Function FetchCLOBResult($result,$row,$field)
    {
    }

    Function FetchBLOBResult($result,$row,$field)
    {
    }

    Function ResultIsNull($result,$row,$field)
    {
    }

    Function BaseConvertResult(&$value,$type)
    {
    }

    Function ConvertResult(&$value,$type)
    {
    }

    Function ConvertResultRow($result,&$row)
    {
    }

    Function FetchDateResult($result,$row,$field)
    {
    }

    Function FetchTimestampResult($result,$row,$field)
    {
    }

    Function FetchTimeResult($result,$row,$field)
    {
    }

    Function FetchBooleanResult($result,$row,$field)
    {
    }

    Function FetchFloatResult($result,$row,$field)
    {
    }

    Function FetchDecimalResult($result,$row,$field)
    {
    }

    Function NumberOfRows($result)
    {
    }

    Function FreeResult($result)
    {
    }

    Function Error()
    {
    }

    Function SetErrorHandler($function)
    {
    }

    Function GetIntegerFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetTextFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetCLOBFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetBLOBFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetBooleanFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetDateFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetTimestampFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetTimeFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetFloatFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetDecimalFieldTypeDeclaration($name,&$field)
    {
    }

    Function GetIntegerFieldValue($value)
    {
    }

    Function GetTextFieldValue($value)
    {
    }
    
    Function GetCLOBFieldValue($prepared_query,$parameter,$clob,&$value)
    {
    }

    Function FreeCLOBValue($prepared_query,$clob,&$value,$success)
    {
    }

    Function GetBLOBFieldValue($prepared_query,$parameter,$blob,&$value)
    {
    }

    Function FreeBLOBValue($prepared_query,$blob,&$value,$success)
    {
    }

    Function GetBooleanFieldValue($value)
    {

    }

    Function GetDateFieldValue($value)
    {

    }

    Function GetTimestampFieldValue($value)
    {

    }

    Function GetTimeFieldValue($value)
    {

    }

    Function GetFloatFieldValue($value)
    {

    }

    Function GetDecimalFieldValue($value)
    {

    }

    Function GetFieldValue($type,$value)
    {

    }

    Function Support($feature)
    {

    }

    Function AutoCommitTransactions()
    {

    }

    Function CommitTransaction()
    {

    }

    Function RollbackTransaction()
    {

    }

    Function Setup()
    {

    }

    Function SetSelectedRowRange($first,$limit)
    {

    }

    Function GetColumnNames($result,&$columns)
    {

    }

    Function NumberOfColumns($result)
    {

    }

    Function SetResultTypes($result,&$types)
    {

    }

    Function FetchResultField($result,&$value)
    {

    }

    Function FetchResultArray($result,&$array,$row)
    {

    }

    Function FetchResultRow($result,&$row)
    {

    }

    Function FetchResultColumn($result,&$column)
    {

    }

    Function FetchResultAll($result,&$all)
    {

    }

    Function QueryField($query,&$field,$type="text")
    {

    }

    Function QueryRow($query,&$row,$types="")
    {

    }

    Function QueryColumn($query,&$column,$type="text")
    {

    }

    Function QueryAll($query,&$all,$types="")
    {

    }
}
?>
