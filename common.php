<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Lukas Smith <smith@dybnet.de>                                   |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id$
//
// Base class for DB implementations.
//

require_once("MDB.php");

define("MDB_TYPE_TEXT",0);
define("MDB_TYPE_BOOLEAN",1);
define("MDB_TYPE_INTEGER",2);
define("MDB_TYPE_DECIMAL",3);
define("MDB_TYPE_FLOAT",4);
define("MDB_TYPE_DATE",5);
define("MDB_TYPE_TIME",6);
define("MDB_TYPE_TIMESTAMP",7);
define("MDB_TYPE_CLOB",8);
define("MDB_TYPE_BLOB",9);

$registered_transactions_shutdown = 0;

function shutdownTransactions($databases_array = '')
{
    // BC support for Metabase
    if($databases_array == '')
    {
        global $databases;
        $databases_array = $databases;
    }
    
    for(reset($databases_array), $i = 0, $j = count($databases_array);
        $i < $j;
        next($databases_array), ++$i)
    {
        $database = key($databases_array);
        if ($databases_array[$database]->in_transaction
            && !MDB::is_Error($databases_array[$database]->rollbackTransaction($database)))
        {
            $databases_array[$database]->autoCommitTransactions($database,1);
        }
    }
}

function defaultDebugOutput($database, $message)
{
    if(is_object) {
        $database->debug_output .= "$database $message".$database->log_line_break;
    // BC support for Metabase
    } else {
        global $databases;
        $databases[$database]->debug_output .= "$database $message".$databases[$database]->log_line_break;
    }
}

/*
 * MDB_common is a base class for DB implementations, and must be
 * inherited by all such.
 */

class MDB_common extends PEAR
{
    /* PUBLIC DATA */

    var $database = 0;
    var $host = "";
    var $user = "";
    var $password = "";
    var $options = array();
    var $supported = array();
    var $persistent = 1;
    var $database_name = "";
    var $warning = "";
    var $affected_rows = -1;
    var $auto_commit = 1;
    var $prepared_queries = array();
    var $decimal_places = 2;
    var $first_selected_row = 0;
    var $selected_row_limit = 0;
    var $lob_buffer_length = 8000;
    var $escape_quotes = "";
    var $log_line_break = "\n";

    /* PRIVATE DATA */
    
    var $lobs = array();
    var $clobs = array();
    var $blobs = array();
    var $last_error = "";
    var $in_transaction = 0;
    var $debug = "";
    var $debug_output = "";
    var $pass_debug_handle = 0;
    var $fetchmodes = array();
    var $error_handler = "";

    // new for PEAR
    var $last_query = "";
    /* PRIVATE METHODS */
    
    /**
     * Quotes a string so it can be safely used in a query. It will quote
     * the text so it can safely be used within a query.
     *
     * @param string $text the input string to quote
     *
     */
    function quote(&$text)
    {
        if (strcmp($this->escape_quotes, "'")) {
            $text = str_replace($this->escape_quotes, $this->escape_quotes.$this->escape_quotes, $text);
        }
        $text = str_replace("'", $this->escape_quotes."'", $text);
    }

    /* PUBLIC METHODS */
    
    function close()
    {
    }

    // renamed for PEAR
    // used to be: CloseSetup
    function disconnect()
    {
        if ($this->in_transaction
        && !MDB::isError($this->rollbackTransaction())
        && !MDB::isError($this->autoCommitTransactions(1))) {
            $this->in_transaction = 0;
        }
        $this->close();
    }

    function debug($message)
    {
        if (strcmp($function = $this->debug, "")) {
            if ($this->pass_debug_handle) {
                $function($this->database, $message);
            } else {
                $function($message);
            }
        }
    }

    function debugOutput()
    {
        return($this->debug_output);
    }

    function setDatabase($name)
    {
        $previous_database_name = $this->database_name;
        $this->database_name = $name;
        return($previous_database_name);
    }

    function registerTransactionShutdown($auto_commit)
    {
        global $registered_transactions_shutdown;

        if (($this->in_transaction = !$auto_commit)
            && !$registered_transactions_shutdown)
        {
            register_shutdown_function("shutdownTransactions");
            $registered_transactions_shutdown = 1;
        }
        return(1);
    }

    function captureDebugOutput($capture)
    {
        $this->pass_debug_handle = $capture;
        $this->debug = ($capture ? "defaultDebugOutput" : "");
    }

    function setError($scope, $message)
    {
        $this->last_error=$message;
        $this->debug($scope.": ".$message);
        if (strcmp($function=$this->error_handler,"")) {
            $error=array(
                "Scope"=>$scope,
                "Message"=>$message
            );
            $function($this,$error);
        }
        return(0);
    }
    
    function createDatabase($database)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Create database: database creation is not supported");
    }

    function dropDatabase($database)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Drop database: database dropping is not supported");
    }

    function getField(&$field, $field_name, &$query)
    {
        if (!strcmp($field_name, "")) {
            //return($this->setError("Get field", "it was not specified a valid field name (\"$field_name\")"));
            return $this->raiseError(DB_ERROR_NOSUCHFIELD, "", "", "Get field: it was not specified a valid field name (\"$field_name\")");
        }
        switch($field["type"]) {
            case "integer":
                $query = $this->getIntegerDeclaration($field_name, $field);
                break;
            case "text":
                $query = $this->getTextDeclaration($field_name, $field);
                break;
            case "clob":
                $query = $this->getCLOBDeclaration($field_name, $field);
                break;
            case "blob":
                $query = $this->getBLOBDeclaration($field_name, $field);
                break;
            case "boolean":
                $query = $this->getBooleanDeclaration($field_name, $field);
                break;
            case "date":
                $query = $this->getDateDeclaration($field_name, $field);
                break;
            case "timestamp":
                $query = $this->getTimestampDeclaration($field_name, $field);
                break;
            case "time":
                $query = $this->getTimeDeclaration($field_name, $field);
                break;
            case "float":
                $query = $this->getFloatDeclaration($field_name, $field);
                break;
            case "decimal":
                $query = $this->getDecimalDeclaration($field_name, $field);
                break;
            default:
                //return($this->setError("Get field", "type \"".$field["type"]."\" is not yet supported"));
                return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Get field: type \"".$field["type"]."\" is not yet supported");
                break;
        }
        return(1);
    }

    function getFieldList(&$fields, &$query_fields)
    {
        for($query_fields = "", reset($fields), $field_number = 0;
            $field_number < count($fields);
            $field_number++,next($fields))
        {
            if ($field_number>0) {
                $query_fields.= ", ";
            }
            $field_name = key($fields);
            if (!$this->getField($fields[$field_name], $field_name, $query)) {
                return(0);
            }
            $query_fields .= $query;
        }
        return(1);
    }

    function createTable($name, &$fields)
    {
        if (!isset($name) || !strcmp($name, "")) {
            return $this->raiseError(DB_ERROR_CANNOT_CREATE, "", "", "no valid table name specified");
        }
        if (count($fields) == 0) {
            return $this->raiseError(DB_ERROR_CANNOT_CREATE, "", "", 'no fields specified for table "'.$name.'"');
        }
        $query_fields = "";
        if (!$this->getFieldList($fields, $query_fields)) {
            return(0);
        }
        return($this->query("CREATE TABLE $name ($query_fields)"));
    }

    function dropTable($name)
    {
        return($this->Query("DROP TABLE $name"));
    }

    function alterTable($name, &$changes, $check)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Alter table: database table alterations are not supported");
    }

    function query($query)
    {
        $this->debug("Query: $query");
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Query: database queries are not implemented");
    }

    function replace($table, &$fields)
    {
        if (!$this->supported["Replace"]) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Replace: replace query is not supported");
        }
        $count = count($fields);
        for($keys = 0, $condition = $update = $insert = $values = "", reset($fields), $field = 0;
            $field < $count;
            next($fields), $field++)
        {
            $name = key($fields);
            if ($field>0) {
                $update.= ", ";
                $insert.= ", ";
                $values.= ", ";
            }
            $update.= $name;
            $insert.= $name;
            if (isset($fields[$name]["Null"])
            && $fields[$name]["Null"]) {
                $value = "NULL";
            } else {
                if (!isset($fields[$name]["Value"])) {
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'no value for field "'.$name.'" specified');
                }
                switch(isset($fields[$name]["Type"]) ? $fields[$name]["Type"] : "text")    {
                    case "text":
                        $value = $this->getTextFieldValue($fields[$name]["Value"]);
                        break;
                    case "boolean":
                        $value = $this->getBooleanFieldValue($fields[$name]["Value"]);
                        break;
                    case "integer":
                        $value = strval($fields[$name]["Value"]);
                        break;
                    case "decimal":
                        $value = $this->getDecimalFieldValue($fields[$name]["Value"]);
                        break;
                    case "float":
                        $value = $this->getFloatFieldValue($fields[$name]["Value"]);
                        break;
                    case "date":
                        $value = $this->getDateFieldValue($fields[$name]["Value"]);
                        break;
                    case "time":
                        $value = $this->getTimeFieldValue($fields[$name]["Value"]);
                        break;
                    case "timestamp":
                        $value = $this->getTimestampFieldValue($fields[$name]["Value"]);
                        break;
                    default:
                        return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                            'no supported type for field "'.$name.'" specified');
                }
            }
            $update .= "=".$value;
            $values.= $value;
            if (isset($fields[$name]["Key"]) && $fields[$name]["Key"]) {
                if ($value == "NULL") {
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'key values may not be NULL');
                }
                $condition.= ($keys ? " AND " : " WHERE ").$name."=".$value;
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                'not specified which fields are keys');
        }
        if (!($in_transaction = $this->in_transaction)
            && MDB::isError($result = $this->autoCommitTransactions(0)))
        {
            return $result;
        }
        if (!MDB::isError($success = $this->queryField("SELECT COUNT(*) FROM $table$condition", $affected_rows, "integer"))) {
            switch($affected_rows) {
                case 0:
                    $success = $this->query("INSERT INTO $table ($insert) VALUES ($values)");
                    $affected_rows = 1;
                    break;
                case 1:
                    $success = $this->query("UPDATE $table SET $update$condition");
                    $affected_rows = $this->affected_rows*2;
                    break;
                default:
                    $success = $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                                   'replace keys are not unique');
                    break;
            }
        }

        if (!$in_transaction) {
            if (!MDB::isError($success)) {
                if (($success = (!MDB::isError($this->commitTransaction())
                    && !MDB::isError($this->autoCommitTransactions(1))))
                    && isset($this->supported["AffectedRows"]))
                {
                    $this->affected_rows = $affected_rows;
                }
            } else {
                $this->rollbackTransaction();
                $this->autoCommitTransactions(1);
            }
        }
        return($success);
    }

    function prepareQuery($query)
    {
        $this->debug("PrepareQuery: $query");
        $positions = array();
        for($position = 0;
            $position<strlen($query) && gettype($question = strpos($query, "?", $position)) == "integer";)
        {
            if (gettype($quote = strpos($query, "'", $position)) == "integer"
                && $quote<$question)
            {
                if (gettype($end_quote = strpos($query, "'", $quote+1))!= "integer") {
                    return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                        'Prepare query: query with an unterminated text string specified');
                }
                switch($this->escape_quotes) {
                    case "":
                    case "'":
                        $position = $end_quote+1;
                        break;
                    default:
                        if ($end_quote == $quote+1) {
                            $position = $end_quote+1;
                        }
                        else {
                            if ($query[$end_quote-1] == $this->escape_quotes) {
                                $position = $end_quote;
                            } else {
                                $position = $end_quote+1;
                            }
                        }
                        break;
                }
            } else {
                $positions[] = $question;
                $position = $question+1;
            }
        }
        $this->prepared_queries[] = array(
            "Query" =>$query,
            "Positions" =>$positions,
            "Values" =>array(),
            "Types" =>array()
        );
        $prepared_query = count($this->prepared_queries);
        if ($this->selected_row_limit>0) {
            $this->prepared_queries[$prepared_query-1]["First"] = $this->first_selected_row;
            $this->prepared_queries[$prepared_query-1]["Limit"] = $this->selected_row_limit;
        }
        return($prepared_query);
    }

    function validatePreparedQuery($prepared_query)
    {
        if ($prepared_query<1 || $prepared_query>count($this->prepared_queries)) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Validate prepared query: invalid prepared query');
        }
        if (gettype($this->prepared_queries[$prepared_query-1])!= "array") {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Validate prepared query: prepared query was already freed');
        }
        return(1);
    }

    function freePreparedQuery($prepared_query)
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $this->prepared_queries[$prepared_query-1] = "";
        return(1);
    }

    function executePreparedQuery($prepared_query, $query)
    {
        return($this->Query($query));
    }

    function executeQuery($prepared_query)
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query-1;
        for($this->clobs[$prepared_query] = $this->blobs[$prepared_query] = array(), $success = 1, $query = "", $last_position = $position = 0;
            $position<count($this->prepared_queries[$index]["Positions"]);
            $position++)
        {
            if (!isset($this->prepared_queries[$index]["Values"][$position])) {
                return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Execute query: it was not defined query argument '.($position+1));
            }
            $current_position = $this->prepared_queries[$index]["Positions"][$position];
            $query .= substr($this->prepared_queries[$index]["Query"], $last_position, $current_position-$last_position);
            $value = $this->prepared_queries[$index]["Values"][$position];
            if ($this->prepared_queries[$index]["IsNULL"][$position]) {
                $query .= $value;
            } else {
                switch($this->prepared_queries[$index]["Types"][$position]) {
                    case "clob":
                        if (MDB::isError($success = $this->getCLOBFieldValue($prepared_query, $position+1, $value, $this->clobs[$prepared_query][$position+1]))) {
                            unset($this->clobs[$prepared_query][$position+1]);
                            break;
                        }
                        $query .= $this->clobs[$prepared_query][$position+1];
                        break;
                    case "blob":
                        if (MDB::isError($success = $this->getBLOBFieldValue($prepared_query, $position+1, $value, $this->blobs[$prepared_query][$position+1]))) {
                            unset($this->blobs[$prepared_query][$position+1]);
                            break;
                        }
                        $query .= $this->blobs[$prepared_query][$position+1];
                        break;
                    default:
                        $query .= $value;
                        break;
                }
            }
            $last_position = $current_position+1;
        }
        if (!MDB::isError($success)) {
            $query.= substr($this->prepared_queries[$index]["Query"], $last_position);
            if ($this->selected_row_limit>0) {
                $this->prepared_queries[$index]["First"] = $this->first_selected_row;
                $this->prepared_queries[$index]["Limit"] = $this->selected_row_limit;
            }
            if (isset($this->prepared_queries[$index]["Limit"])
                && $this->prepared_queries[$index]["Limit"]>0)
            {
                $this->first_selected_row = $this->prepared_queries[$index]["First"];
                $this->selected_row_limit = $this->prepared_queries[$index]["Limit"];
            } else {
                $this->first_selected_row = $this->selected_row_limit = 0;
            }
            $success = $this->executePreparedQuery($prepared_query, $query);
        }
        for(reset($this->clobs[$prepared_query]), $clob = 0;
            $clob<count($this->clobs[$prepared_query]);
            $clob++,next($this->clobs[$prepared_query]))
        {
            $this->freeCLOBValue($prepared_query,key($this->clobs[$prepared_query]), $this->clobs[$prepared_query][Key($this->clobs[$prepared_query])], $success);
        }
        unset($this->clobs[$prepared_query]);
        for(reset($this->blobs[$prepared_query]), $blob = 0;
        $blob<count($this->blobs[$prepared_query]);
        $blob++,next($this->blobs[$prepared_query])) {
            $this->freeBLOBValue($prepared_query,key($this->blobs[$prepared_query]), $this->blobs[$prepared_query][key($this->blobs[$prepared_query])], $success);
        }
        unset($this->blobs[$prepared_query]);
        return($success);
    }

    function querySet($prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query-1;
        if ($parameter<1
            || $parameter>count($this->prepared_queries[$index]["Positions"]))
        {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Query set: it was not specified a valid argument number');
        }
        $this->prepared_queries[$index]["Values"][$parameter-1] = $value;
        $this->prepared_queries[$index]["Types"][$parameter-1] = $type;
        $this->prepared_queries[$index]["Fields"][$parameter-1] = $field;
        $this->prepared_queries[$index]["IsNULL"][$parameter-1] = $is_null;
        return(1);
    }
    
    // new Metabase function for the PEAR get*()
    // needs testing
    // the array elements must use keys that correspond to the number of the position of the parameter
    // single dimensional array: querySet with type text for all values of the array
    // multi dimensional array :
    // 0:    type
    // 1:    value
    // 2:    optional data
    function querySetArray($prepared_query, $array)
    {
        if (is_array($array[0])) {
            for($i = 0, $j = count($array); $i<$j; ++$i) {
                switch($array[$i][0]) {
                    case "null":
                        // maybe it would be cleaner to use $array[$i][2] instead of $array[$i][1] here
                        // but it might not be as nice when defining the array itself
                        $success = $this->QuerySet($prepared_query, $i+1, $array[$i][1], "NULL",1, "");
                        break;
                    case "text":
                        $success = $this->QuerySet($prepared_query, $i+1, "text", $this->GetTextFieldValue($array[$i][1]));
                        break;
                    case "clob":
                        $success = $this->QuerySet($prepared_query, $i+1, "clob", $array[$i][1],0, $array[$i][2]);
                        break;
                    case "blob":
                        $success = $this->QuerySet($prepared_query, $i+1, "blob", $array[$i][1],0, $array[$i][2]);
                        break;
                    case "integer":
                        $success = $this->QuerySet($prepared_query, $i+1, "integer", $this->GetIntegerFieldValue($array[$i][1]));
                        break;
                    case "boolean":
                        $success = $this->QuerySet($prepared_query, $i+1, "boolean", $this->GetBooleanFieldValue($array[$i][1]));
                        break;
                    case "date":
                        $success = $this->QuerySet($prepared_query, $i+1, "date", $this->GetDateFieldValue($array[$i][1]));
                        break;
                    case "timestamp":
                        $success = $this->QuerySet($prepared_query, $i+1, "timestamp", $this->GetTimestampFieldValue($array[$i][1]));
                        break;
                    case "time":
                        $success = $this->QuerySet($prepared_query, $i+1, "time", $this->GetTimeFieldValue($array[$i][1]));
                        break;
                    case "float":
                        $success = $this->QuerySet($prepared_query, $i+1, "float", $this->GetFloatFieldValue($array[$i][1]));
                        break;
                    case "decimal":
                        $success = $this->QuerySet($prepared_query, $i+1, "decimal", $this->GetDecimalFieldValue($array[$i][1]));
                        break;
                    default:
                        $success = $this->QuerySet($prepared_query, $i+1, "text", $this->GetTextFieldValue($array[$i][1]));                    
                        break;
                }
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        else
        {
            for($i = 0, $j = count($array); $i<$j; ++$i) {
                $success = $this->QuerySet($prepared_query, $i+1, "text", $this->GetTextFieldValue($array[$i]));
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        return (1);
    }

    function querySetNull($prepared_query, $parameter, $type)
    {
        return($this->QuerySet($prepared_query, $parameter, $type, "NULL", 1, ""));
    }

    function querySetText($prepared_query, $parameter, $value)
    {
        return($this->QuerySet($prepared_query, $parameter, "text", $this->GetTextFieldValue($value)));
    }

    function querySetCLob($prepared_query, $parameter, $value, $field)
    {
        return($this->querySet($prepared_query, $parameter, "clob", $value, 0, $field));
    }

    function querySetBLob($prepared_query, $parameter, $value, $field)
    {
        return($this->querySet($prepared_query, $parameter, "blob", $value, 0, $field));
    }

    function querySetInteger($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "integer", $this->GetIntegerFieldValue($value)));
    }

    function querySetBoolean($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "boolean", $this->GetBooleanFieldValue($value)));
    }

    function querySetDate($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "date", $this->GetDateFieldValue($value)));
    }

    function querySetTimestamp($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "timestamp", $this->GetTimestampFieldValue($value)));
    }

    function querySetTime($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "time", $this->GetTimeFieldValue($value)));
    }

    function querySetFloat($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "float", $this->GetFloatFieldValue($value)));
    }

    function querySetDecimal($prepared_query, $parameter, $value)
    {
        return($this->querySet($prepared_query, $parameter, "decimal", $this->GetDecimalFieldValue($value)));
    }

    function affectedRows()
    {
        if ($this->affected_rows == -1) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA);
        }
        $affected_rows = $this->affected_rows;
        return($affected_rows);
    }

    function endOfResult($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'End of result: end of result method not implemented');
        //return(-1);
    }

    function fetch($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Fetch: fetch result method not implemented');
    }

    function fetchLobResult($result, $row, $field)
    {
        $lob = count($this->lobs)+1;
        $this->lobs[$lob] = array(
            "Result" =>$result,
            "Row" =>$row,
            "Field" =>$field,
            "Position" =>0
        );
        $character_lob = array(
            "Database" =>$this->database,
            "Error" =>"",
            "Type" =>"resultlob",
            "ResultLOB" =>$lob
        );
        if (!createLob($character_lob, $clob)) {
            return $this->raiseError(DB_ERROR, "", "", 
                'Fetch LOB result: '. $character_lob["Error"]);
        }
        return($clob);
    }

    function retrieveLob($lob)
    {
        if (!isset($this->lobs[$lob])) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                'Fetch LOB result: it was not specified a valid lob');
        }
        if (!isset($this->lobs[$lob]["Value"])) {
            $this->lobs[$lob]["Value"] = $this->fetch($this->lobs[$lob]["Result"], $this->lobs[$lob]["Row"], $this->lobs[$lob]["Field"]);
        }
        return(1);
    }

    function endOfResultLob($lob)
    {
        $result = $this->retrieveLob($lob);
        if (MDB::isError($result)) {
            return $result;
        }
        return($this->lobs[$lob]["Position"] >= strlen($this->lobs[$lob]["Value"]));
    }

    function readResultLob($lob, &$data, $length)
    {
        $result = $this->retrieveLob($lob);
        if (MDB::isError($result)) {
            return $result;
        }
        $length = min($length,strlen($this->lobs[$lob]["Value"])-$this->lobs[$lob]["Position"]);
        $data = substr($this->lobs[$lob]["Value"], $this->lobs[$lob]["Position"], $length);
        $this->lobs[$lob]["Position"] += $length;
        return($length);
    }

    function destroyResultLob($lob)
    {
        if (isset($this->lobs[$lob])) {
            $this->lobs[$lob] = "";
        }
    }

    function fetchClobResult($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'fetch clob result method is not implemented');
    }

    function fetchBlobResult($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'fetch blob result method is not implemented');
    }

    function resultIsNull($result, $row, $field)
    {
        $value = $this->fetch($result, $row, $field);
        return(!isset($value));
    }

    function baseConvertResult(&$value, $type)
    {
        switch($type) {
            case MDB_TYPE_TEXT:
                return(1);
            case MDB_TYPE_INTEGER:
                $value = intval($value);
                return(1);
            case MDB_TYPE_BOOLEAN:
                $value = (strcmp($value, "Y") ? 0 : 1);
                return(1);
            case MDB_TYPE_DECIMAL:
                return(1);
            case MDB_TYPE_FLOAT:
                $value = doubleval($value);
                return(1);
            case MDB_TYPE_DATE:
            case MDB_TYPE_TIME:
            case MDB_TYPE_TIMESTAMP:
                return(1);
            case MDB_TYPE_CLOB:
            case MDB_TYPE_BLOB:
                $value = "";
                return $this->raiseError(DB_ERROR_INVALID, "", "", 
                    'BaseConvertResult: attempt to convert result value to an unsupported type '.$type);
            default:
                $value = "";
                return $this->raiseError(DB_ERROR_INVALID, "", "", 
                    'BaseConvertResult: attempt to convert result value to an unknown type '.$type);
        }
    }

    function convertResult(&$value, $type)
    {
        return($this->BaseConvertResult($value, $type));
    }

    function convertResultRow($result, &$row)
    {
        if (isset($this->result_types[$result]))
        {
            $columns = $this->numCols($result);
            if (MDB::isError($columns)) {
                return $columns;
            }
            for($column = 0; $column<$columns; $column++) {
                if (!isset($row[$column])) {
                    continue;
                }
                switch($type = $this->result_types[$result][$column]) {
                    case MDB_TYPE_TEXT:
                        break;
                    case MDB_TYPE_INTEGER:
                        $row[$column] = intval($row[$column]);
                        break;
                    default:
                        if (!$this->ConvertResult($row[$column], $type)) {
                            return(0);
                        }
                        break;
                }
            }
        }
        return(1);
    }

    // renamed for PEAR
    // used to be: NumberOfColumns
    function numRows($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Num Rows: number of rows method not implemented");
    }

    function freeResult($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Free Result: free result method not implemented");
    }

    function error()
    {
        return($this->last_error);
    }

    function setErrorHandler($function)
    {
        $last_function = $this->error_handler;
        $this->error_handler = $function;
        return($last_function);
    }

    function getIntegerDeclaration($name, &$field)
    {
        // How should warnings be handled?
        if (isset($field["unsigned"])) {
            $this->warning = "unsigned integer field \"$name\" is being declared as signed integer";
        }
        return("$name INT".(isset($field["default"]) ? " DEFAULT ".$field["default"] : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTextDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getCLOBDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBLOBDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBooleanDeclaration($name, &$field)
    {
        return("$name CHAR (1)".(isset($field["default"]) ? " DEFAULT ".$this->getBooleanFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDateDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("YYYY-MM-DD").")".(isset($field["default"]) ? " DEFAULT ".$this->getDateFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimestampDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("YYYY-MM-DD HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->getTimestampFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimeDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->getTimeFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getFloatDeclaration($name, &$field)
    {
        return("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->getFloatFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDecimalDeclaration($name, &$field)
    {
        return("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->getDecimalFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getIntegerFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    function getTextFieldValue($value)
    {
        $this->quote($value);
        return("'$value'");
    }
    
    function getCLOBFieldValue($prepared_query, $parameter, $clob, &$value)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get CLOB field value: prepared queries with values of type "clob" are not yet supported');
    }

    function freeCLOBValue($prepared_query, $clob, &$value, $success)
    {
    }

    function getBLOBFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get BLOB field value: prepared queries with values of type "blob" are not yet supported');
    }

    function freeBLOBValue($prepared_query, $blob, &$value, $success)
    {
    }

    function getBooleanFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : ($value ? "'Y'" : "'N'"));
    }

    function getDateFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    function getTimestampFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    function getTimeFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    function getFloatFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    function getDecimalFieldValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    function getFieldValue($type, $value)
    {
        switch($type) {
            case "integer":
                return($this->GetIntegerFieldValue($value));
            case "text":
                return($this->GetTextFieldValue($value));
            case "boolean":
                return($this->GetBooleanFieldValue($value));
            case "date":
                return($this->GetDateFieldValue($value));
            case "timestamp":
                return($this->GetTimestampFieldValue($value));
            case "time":
                return($this->GetTimeFieldValue($value));
            case "float":
                return($this->GetFloatFieldValue($value));
            case "decimal":
                return($this->GetDecimalFieldValue($value));
        }
        return("");
    }

    function support($feature)
    {
        return(isset($this->supported[$feature]));
    }

    function createSequence($name, $start)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Create Sequence: sequence creation not supported');
    }

    function dropSequence($name)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Drop Sequence: sequence dropping not supported');
    }
    
    // renamed for PEAR
    // used to be: getSequencenextValue
    function nextId($name)
    {
         return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Next Sequence: getting next sequence value not supported');
    }
    
    // renamed for PEAR
    // used to be: getSequenceCurrentValue
    function currId($name)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Current Sequence: getting current sequence value not supported');
    }

    function autoCommitTransactions()
    {
        $this->debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Auto-commit transactions: transactions are not supported');
    }

    function commitTransaction()
    {
        $this->debug("Commit Transaction");
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Commit transaction: commiting transactions are not supported');
    }

    function rollbackTransaction()
    {
        $this->debug("Rollback Transaction");
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Rollback transaction: rolling back transactions are not supported');
    }

    function createIndex($table, $name, $definition)
    {
        $query = "CREATE";
        if (isset($definition["unique"])) {
            $query .= " UNIQUE";
        }
        $query .= " INDEX $name ON $table (";
        for($field = 0,reset($definition["FIELDS"]); $field<count($definition["FIELDS"]); $field++,next($definition["FIELDS"])) {
            if ($field>0) {
                $query.= ", ";
            }
            $field_name = Key($definition["FIELDS"]);
            $query.= $field_name;
            if ($this->Support("IndexSorting") && isset($definition["FIELDS"][$field_name]["sorting"])) {
                switch($definition["FIELDS"][$field_name]["sorting"]) {
                    case "ascending":
                        $query.= " ASC";
                        break;
                    case "descending":
                        $query.= " DESC";
                        break;
                }
            }
        }
        $query.= ")";
        return($this->Query($query));
    }

    function dropIndex($table, $name)
    {
        return($this->Query("DROP INDEX $name"));
    }

    function setup()
    {
        return("");
    }

    function setSelectedRowRange($first, $limit)
    {
        if (!isset($this->supported["SelectRowRanges"])) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                'Set selected row range: selecting row ranges is not supported by this driver');
        }
        if (gettype($first)!= "integer" || $first<0) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set selected row range: it was not specified a valid first selected range row');
        }
        if (gettype($limit)!= "integer" || $limit<1) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set selected row range: it was not specified a valid selected range row limit');
        }
        $this->first_selected_row = $first;
        $this->selected_row_limit = $limit;
        return(1);
    }

    function getColumnNames($result, &$columns)
    {
        $columns = array();
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get column names: obtaining result column names is not implemented');
    }
    
    // renamed for PEAR
    // used to be: NumberOfColumns
    function numCols($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Number of columns: obtaining the number of result columns is not implemented');
    }

    function setResultTypes($result, &$types)
    {
        if (isset($this->result_types[$result])) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Set result types: attempted to redefine the types of the columns of a result set');
        }
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        if ($columns<count($types)) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set result types: it were specified more result types ('.count($types).') than result columns ('.$columns.')');
        }
        $valid_types = array(
            "text" =>      MDB_TYPE_TEXT,
            "boolean" =>   MDB_TYPE_BOOLEAN,
            "integer" =>   MDB_TYPE_INTEGER,
            "decimal" =>   MDB_TYPE_DECIMAL,
            "float" =>     MDB_TYPE_FLOAT,
            "date" =>      MDB_TYPE_DATE,
            "time" =>      MDB_TYPE_TIME,
            "timestamp" => MDB_TYPE_TIMESTAMP,
            "clob" =>      MDB_TYPE_CLOB,
            "blob" =>      MDB_TYPE_BLOB
        );
        for($column = 0; $column<count($types); $column++) {
            if (!isset($valid_types[$types[$column]])) {
                return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                    'Set result types: '.$types[$column].' is not a supported column type');
            }
            $this->result_types[$result][$column] = $valid_types[$types[$column]];
        }
        for(; $column<$columns; $column++) {
            $this->result_types[$result][$column] = MDB_TYPE_TEXT;
        }
        return(1);
    }

    function baseFetchArray($result, &$array, $row)
    {
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        for($array = array(), $column = 0; $column<$columns; $column++) {
            if (!$this->resultIsNull($result, $row, $column)) {
                $array[$column] = $this->fetch($result, $row, $column);
            }
        }
        $result = $this->convertResultRow($result, $array);
        if (!MDB::isError($result)) {
            return DB_OK;
        } else {
            return $result;
        }
    }
    
    // renamed for PEAR
    // used to be fetchResultArray
    // added $fetchmode
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $row = null)
    {
        return ($this->baseFetchArray($result, $array, $row));
    }
    
    // renamed for PEAR
    // used to be fetchResultField
    // $fetchmode added
    // new uses fetchInto
    function fetchField($result, &$value, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        if ($this->endOfResult($result)) {
            $success = $this->setError("Fetch field", "result set is empty");
        } else {
            if ($this->resultIsNull($result, 0, 0)) {
                unset($value);
            } else {
                $this->fetchInto($result, $value, $fetchmode, 0);
                $value = $value[0];
            }
            $success = 1;
        }
        $this->freeResult($result);
        return ($success);
    }

    // renamed for PEAR
    // used to be fetchResultRow
    // $fetchmode added
    function fetchRow($result, &$row, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        if ($this->endOfResult($result)) {
            $success = $this->setError("Fetch field", "result set is empty");
        } else {
            $success = $this->fetchInto($result, $row, $fetchmode, 0);
        }
        $this->freeResult($result);
        return ($success);
    }

    // renamed for PEAR
    // used to be fetchResultColumn
    // $fetchmode added
    // new uses fetchInto
    function fetchColumn($result, &$column, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        $temp = array();
        for($success = 1, $column = array(), $row = 0;!$this->endOfResult($result); $row++) {
            if ($this->ResultIsNull($result, 0, 0)) {
                continue;
            }
            if (!($success = $this->fetchInto($result, $temp, $fetchmode, $row))) {
                break;
            }
            $column[$row] = $temp[0];
        }
        $this->freeResult($result);
        return ($success);
    }

    // renamed for PEAR
    // used to be fetchResultAll
    // $fetchmode, $rekey, $force_array, $group added
    // this is basically a slightly modified version of fetchAll()
    // the only difference is that the first dimension in the 2 dimensional result set is pop-off each row
    // this is especially interesting if you need to fetch a large result set and
    // then jump around in the result set based on some primary key
    // if the same key is pop-off for multiple rows then the second overwrites the first
    function fetchAll($result, &$all, $fetchmode = DB_FETCHMODE_DEFAULT, $rekey = false, $force_array = false, $group = false)
    {
        if (!$result) {
            return ($this->setError("Fetch field","it was not specified a valid result set"));
        }
        if($rekey) {
            $cols = $this->numCols($result);
            if (MDB::isError($cols))
                return $cols;
            if ($cols < 2) {
                return $this->raiseError(DB_ERROR_TRUNCATED);
            }
        }
        $row = 0;
        $all = array();
        while (DB_OK === $this->fetchInto($result, $array, $fetchmode, null)) {
            if ($rekey) {
                if ($fetchmode == DB_FETCHMODE_ASSOC) {
                    reset($array);
                    $key = current($array);
                    unset($array[key($array)]);
                } else {
                    $key = array_shift($array);
                }
                if (!$force_array && sizeof($array) == 1) {
                    $array = $array[0];
                }
                if ($group) {
                    $all[$key][$row] = $array;
                } else {
                    $all[$key] = $array;
                }
            } else {
                $all[$row] = $array;
            }
            $row++;
        }
        $this->freeResult($result);
        return (1);
    }
    
    function queryField($query, &$field, $type = "text")
    {
        $result = $this->Query($query);
        if (MDB::isError($result)) {
            return $result;
        }
        if (strcmp($type, "text")) {
            $types = array($type);
            if (MDB::isError($success = $this->setResultTypes($result, $types))) {
                $this->freeResult($result);
                return $success;
            }
        }
        return ($this->fetchField($result, $field));
    }

    function queryRow($query, &$row, $types = "")
    {
        $result = $this->Query($query);
        if (MDB::isError($result)) {
            return $result;
        }
        if (gettype($types) == "array") {
            if (MDB::isError($success = $this->setResultTypes($result, $types))) {
                $this->freeResult($result);
                return $success;
            }
        }
        return ($this->fetchRow($result, $row));
    }

    function queryColumn($query, &$column, $type = "text")
    {
        $result = $this->Query($query);
        if (MDB::isError($result)) {
            return $result;
        }
        if (strcmp($type, "text")) {
            $types = array($type);
            if (MDB::isError($success = $this->setResultTypes($result, $types))) {
                $this->freeResult($result);
                return $success;
            }
        }
        return ($this->fetchColumn($result, $column));
    }

    function queryAll($query, &$all, $types = "")
    {
        if (MDB::isError($result = $this->query($query))) {
            return $result;
        }
        if (gettype($types) == "array") {
            if (MDB::isError($success = $this->setResultTypes($result, $types))) {
                $this->freeResult($result);
                return $success;
            }
        }
        return ($this->fetchAll($result, $all));
    }

    // ********************
    // new methods for PEAR
    // ********************
    
    function limitQuery($query, $from, $count)
    {
        $result = $this->SetSelectedRowRange($from, $count);
        if (MDB::isError($result))
            return $result;
        return $this->query($query);
    }
    
    function quoteString($string)
    {
        $string = $this->quote($string);
        if ($string{0} == "'") {
            return substr($string, 1, -1);
        }
        return $string;
    }

    // porting incomplete
    function toString()
    {
        $info = get_class($this);
        $info .=  ": (phptype = " . $this->phptype .
                  ", dbsyntax = " . $this->dbsyntax .
                  ")";

        if ($this->connection) {
            $info .= " [connected]";
        }

        return $info;
    }

    function errorCode($nativecode)
    {
        if (isset($this->errorcode_map[$nativecode])) {
            return $this->errorcode_map[$nativecode];
        }

        //php_error(E_WARNING, get_class($this)."::errorCode: no mapping for $nativecode");
        // Fall back to DB_ERROR if there was no mapping.

        return DB_ERROR;
    }

    function errorMessage($dbcode)
    {
        return MDB::errorMessage($this->errorcode_map[$dbcode]);
    }
    
    function &raiseError($code = DB_ERROR, $mode = null, $options = null,
                         $userinfo = null, $nativecode = null)
    {
        // The error is yet a DB error object
        if (is_object($code)) {
            return PEAR::raiseError($code, null, null, null, null, null, true);
        }

        if ($userinfo === null) {
            $userinfo = $this->last_query;
        }

        if ($nativecode) {
            $userinfo .= " [nativecode = $nativecode]";
        }

        return PEAR::raiseError(null, $code, $mode, $options, $userinfo,
                                  'MDB_Error', true);
    }
    
    function prepare($query)
    {
        return $this->PrepareQuery($query);
    }
    
    function execute($prepared_query, $data = false)
    {
        $this->querySetArray($prepared_query, $data);

        return $this->executeQuery($prepared_query);
    }

    function executeMultiple($prepared_query, &$data )
    {
        for($i = 0; $i < sizeof( $data ); $i++) {
            $result = $this->execute($prepared_query, $data[$i]);
            //if (MDB::isError($result)) {
            if (!$result) {
                return $result;
            }
        }
        return DB_OK;
    }

    function &getOne($query, $params = array())
    {
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepare($query);
            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params);
            $result = $this->execute($prepared_query, $params);
        } else {
            $result = $this->query($query);
        }

        if (MDB::isError($result)) {
            return $result;
        }

        $err = $this->fetchField($result, $value, DB_FETCHMODE_ORDERED);
        if ($err !== DB_OK) {
            return $err;
        }

        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result))
                return $result;
        }

        return $value;
    }

    function &getRow($query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        $fetchmode = (empty($fetchmode)) ? DB_FETCHMODE_DEFAULT : $fetchmode;
        settype($params, 'array');
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepare($query);
            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params);
            $result = $this->execute($prepared_query, $params);
        } else {
            $result = $this->query($query);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        $err = $this->fetchRow($result, $row, $fetchmode);
        
        if ($err !== DB_OK) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result))
                return $result;
        }

        return $row;
    }

    function &getCol($query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        $fetchmode = (empty($fetchmode)) ? DB_FETCHMODE_DEFAULT : $fetchmode;
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepare($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params);
            $result = $this->execute($prepared_query, $params);
        } else {
            $result = $this->query($query);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchColumn($result, $col, $fetchmode);
        
        if ($err !== DB_OK) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result))
                return $result;
        }

        return $col;
    }

    function &getAll($query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        $fetchmode = (empty($fetchmode)) ? DB_FETCHMODE_DEFAULT : $fetchmode;
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepare($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params);
            $result = $this->execute($prepared_query, $params);
        } else {
            $result = $this->query($query);
        }

        if (MDB::isError($result)) {
            return $result;
        }

        $err = $this->fetchAll($result, $all, $fetchmode);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result))
                return $result;
        }

        return $all;
    }

    function &getAssoc($query, $force_array = false, $params = array(),
        $fetchmode = DB_FETCHMODE_ORDERED, $group = false)
    {
        $fetchmode = (empty($fetchmode)) ? DB_FETCHMODE_DEFAULT : $fetchmode;
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepare($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params);
            $result = $this->execute($prepared_query, $params);
        } else {
            $result = $this->query($query);
        }

        if (MDB::isError($result)) {
            return $result;
        }

        $err = $this->fetchAll($result, $all, $fetchmode, true, $force_array, $group);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result))
                return $result;
        }

        return $all;
    }
    
    function tableInfo($result, $mode = null)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }
};

?>