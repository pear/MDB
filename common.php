<?php
/*
 * common.php
 *
 * @(#) $Header$
 *
*/

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

function shutdownTransactions()
{
    global $databases;

    for(reset($databases), $i = 0;
        $i < count($databases);
        next($databases), $i++)
    {
        $database = key($databases);
        if ($databases[$database]->in_transaction
        && $databases[$database]->rollbackTransaction($database)) {
            $databases[$database]->autoCommitTransactions($database,1);
        }
    }
}

function defaultDebugOutput($database, $message)
{
    global $databases;

    $databases[$database]->debug_output .= "$database $message".$databases[$database]->log_line_break;
}

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
    
    // renamed for PEAR
    // used to be: EscapeText
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
        && $this->rollbackTransaction()
        && $this->autoCommitTransactions(1)) {
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
                $query = $this->getIntegerFieldTypeDeclaration($field_name, $field);
                break;
            case "text":
                $query = $this->getTextFieldTypeDeclaration($field_name, $field);
                break;
            case "clob":
                $query = $this->getCLOBFieldTypeDeclaration($field_name, $field);
                break;
            case "blob":
                $query = $this->getBLOBFieldTypeDeclaration($field_name, $field);
                break;
            case "boolean":
                $query = $this->getBooleanFieldTypeDeclaration($field_name, $field);
                break;
            case "date":
                $query = $this->getDateFieldTypeDeclaration($field_name, $field);
                break;
            case "timestamp":
                $query = $this->getTimestampFieldTypeDeclaration($field_name, $field);
                break;
            case "time":
                $query = $this->getTimeFieldTypeDeclaration($field_name, $field);
                break;
            case "float":
                $query = $this->getFloatFieldTypeDeclaration($field_name, $field);
                break;
            case "decimal":
                $query = $this->getDecimalFieldTypeDeclaration($field_name, $field);
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
            return("it was not specified a valid table name");
        }
        if (count($fields) == 0) {
            return("it were not specified any fields for table \"$name\"");
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
        return($this->setError("Alter table", "database table alterations are not supported"));
    }

    function query($query)
    {
        $this->debug("Query: $query");
        return($this->setError("Query", "database queries are not implemented"));
    }

    function replace($table, &$fields)
    {
        if (!$this->supported["Replace"]) {
            return($this->setError("Replace", "replace query is not supported"));
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
                    return($this->setError("Replace", "it was not specified a value for the $name field"));
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
                        return($this->setError("Replace", "it was not specified a supported type for the $name field"));
                }
            }
            $update .= "=".$value;
            $values.= $value;
            if (isset($fields[$name]["Key"]) && $fields[$name]["Key"]) {
                if ($value == "NULL") {
                    return($this->setError("Replace", "key values may not be NULL"));
                }
                $condition.= ($keys ? " AND " : " WHERE ").$name."=".$value;
                $keys++;
            }
        }
        if ($keys == 0) {
            return($this->setError("Replace", "it were not specified which fields are keys"));
        }
        if (!($in_transaction = $this->in_transaction)
            && !$this->autoCommitTransactions(0))
        {
            return(0);
        }
        if (($success = $this->queryField("SELECT COUNT(*) FROM $table$condition", $affected_rows, "integer"))) {
            switch($affected_rows) {
                case 0:
                    $success = $this->query("INSERT INTO $table ($insert) VALUES($values)");
                    $affected_rows = 1;
                    break;
                case 1:
                    $success = $this->query("UPDATE $table SET $update$condition");
                    $affected_rows = $this->affected_rows*2;
                    break;
                default:
                    $success = $this->setError("Replace", "replace keys are not unique");
                    break;
            }
        }
        if (!$in_transaction) {
            if ($success) {
                if (($success = ($this->CommitTransaction()
                    && $this->AutoCommitTransactions(1)))
                    && isset($this->supported["AffectedRows"]))
                {
                    $this->affected_rows = $affected_rows;
                }
            } else {
                $this->RollbackTransaction();
                $this->AutoCommitTransactions(1);
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
                    return($this->setError("Prepare query", "it was specified a query with an unterminated text string"));
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
            return($this->setError("Validate prepared query", "invalid prepared query"));
        }
        if (gettype($this->prepared_queries[$prepared_query-1])!= "array") {
            return($this->setError("Validate prepared query", "prepared query was already freed"));
        }
        return(1);
    }

    function freePreparedQuery($prepared_query)
    {
        if (!$this->validatePreparedQuery($prepared_query)) {
            return(0);
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
        if (!$this->ValidatePreparedQuery($prepared_query)) {
            return(0);
        }
        $index = $prepared_query-1;
        for($this->clobs[$prepared_query] = $this->blobs[$prepared_query] = array(), $success = 1, $query = "", $last_position = $position = 0;
            $position<count($this->prepared_queries[$index]["Positions"]);
            $position++)
        {
            if (!isset($this->prepared_queries[$index]["Values"][$position])) {
                return($this->setError("Execute query", "it was not defined query argument ".($position+1)));
            }
            $current_position = $this->prepared_queries[$index]["Positions"][$position];
            $query .= substr($this->prepared_queries[$index]["Query"], $last_position, $current_position-$last_position);
            $value = $this->prepared_queries[$index]["Values"][$position];
            if ($this->prepared_queries[$index]["IsNULL"][$position]) {
                $query .= $value;
            } else {
                switch($this->prepared_queries[$index]["Types"][$position]) {
                    case "clob":
                        if (!($success = $this->getCLOBFieldValue($prepared_query, $position+1, $value, $this->clobs[$prepared_query][$position+1]))) {
                            unset($this->clobs[$prepared_query][$position+1]);
                            break;
                        }
                        $query .= $this->clobs[$prepared_query][$position+1];
                        break;
                    case "blob":
                        if (!($success = $this->getBLOBFieldValue($prepared_query, $position+1, $value, $this->blobs[$prepared_query][$position+1]))) {
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
        if ($success) {
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
        if (!$this->ValidatePreparedQuery($prepared_query)) {
            return(0);
        }
        $index = $prepared_query-1;
        if ($parameter<1
            || $parameter>count($this->prepared_queries[$index]["Positions"]))
        {
            return($this->setError("Query set", "it was not specified a valid argument number"));
        }
        $this->prepared_queries[$index]["Values"][$parameter-1] = $value;
        $this->prepared_queries[$index]["Types"][$parameter-1] = $type;
        $this->prepared_queries[$index]["Fields"][$parameter-1] = $field;
        $this->prepared_queries[$index]["IsNULL"][$parameter-1] = $is_null;
        return(1);
    }
    
    // new Metabase function for the PEAR get*()
    // needs testing
    // the array elements must use kyes that correspond to the number of the position of the parameter
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
                if (!$success) {
                    return (0);
                }
            }
        }
        else
        {
            for($i = 0, $j = count($array); $i<$j; ++$i) {
                $success = $this->QuerySet($prepared_query, $i+1, "text", $this->GetTextFieldValue($array[$i]));
                if (!$success) {
                    return (0);
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
        $this->setError("End of result", "end of result method not implemented");
        return(-1);
    }

    function fetchResult($result, $row, $field)
    {
        $this->warning = "fetch result method not implemented";
        return("");
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
            return($this->setError("Fetch LOB result", $character_lob["Error"]));
        }
        return($clob);
    }

    function retrieveLob($lob)
    {
        if (!isset($this->lobs[$lob])) {
            return($this->setError("Fetch LOB result", "it was not specified a valid lob"));
        }
        if (!isset($this->lobs[$lob]["Value"])) {
            $this->lobs[$lob]["Value"] = $this->fetchResult($this->lobs[$lob]["Result"], $this->lobs[$lob]["Row"], $this->lobs[$lob]["Field"]);
        }
        return(1);
    }

    function endOfResultLob($lob)
    {
        if (!$this->retrieveLob($lob)) {
            return(0);
        }
        return($this->lobs[$lob]["Position"] >= strlen($this->lobs[$lob]["Value"]));
    }

    function readResultLob($lob, &$data, $length)
    {
        if (!$this->RetrieveLob($lob)) {
            return(-1);
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
        return($this->setError("Fetch CLOB result", "fetch clob result method is not implemented"));
    }

    function fetchBlobResult($result, $row, $field)
    {
        return($this->setError("Fetch BLOB result", "fetch blob result method is not implemented"));
    }

    function resultIsNull($result, $row, $field)
    {
        $value = $this->fetchResult($result, $row, $field);
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
                return($this->setError("BaseConvertResult", "attempt to convert result value to an unsupported type $type"));
            default:
                $value = "";
                return($this->setError("BaseConvertResult", "attempt to convert result value to an unknown type $type"));
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
            if (($columns = $this->numCols($result)) == -1) {
                return(0);
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
        $this->warning = "number of rows method not implemented";
        return(0);
    }

    function freeResult($result)
    {
        $this->warning = "free result method not implemented";
        return(0);
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

    function getIntegerFieldTypeDeclaration($name, &$field)
    {
        if (isset($field["unsigned"])) {
            $this->warning = "unsigned integer field \"$name\" is being declared as signed integer";
        }
        return("$name INT".(isset($field["default"]) ? " DEFAULT ".$field["default"] : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTextFieldTypeDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->GetTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getCLOBFieldTypeDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->GetTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBLOBFieldTypeDeclaration($name, &$field)
    {
        return((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->GetTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBooleanFieldTypeDeclaration($name, &$field)
    {
        return("$name CHAR (1)".(isset($field["default"]) ? " DEFAULT ".$this->GetBooleanFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDateFieldTypeDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("YYYY-MM-DD").")".(isset($field["default"]) ? " DEFAULT ".$this->GetDateFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimestampFieldTypeDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("YYYY-MM-DD HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->GetTimestampFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimeFieldTypeDeclaration($name, &$field)
    {
        return("$name CHAR (".strlen("HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->GetTimeFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getFloatFieldTypeDeclaration($name, &$field)
    {
        return("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->GetFloatFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDecimalFieldTypeDeclaration($name, &$field)
    {
        return("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->GetDecimalFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
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
        return($this->setError("Get CLOB field value", "prepared queries with values of type \"clob\" are not yet supported"));
    }

    function freeCLOBValue($prepared_query, $clob, &$value, $success)
    {
    }

    function getBLOBFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        return($this->setError("Get BLOB field value", "prepared queries with values of type \"blob\" are not yet supported"));
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
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }

    function dropSequence($name)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }
    
    // renamed for PEAR
    // used to be: getSequencenextValue
    function nextId($name)
    {
         return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }
    
    // renamed for PEAR
    // used to be: getSequenceCurrentValue
    function currId($name)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }

    function autoCommitTransactions()
    {
        $this->Debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        return($this->setError("Auto-commit transactions", "transactions are not supported"));
    }

    function commitTransaction()
    {
         $this->Debug("Commit Transaction");
        return($this->setError("Commit transaction", "commiting transactions are not supported"));
    }

    function rollbackTransaction()
    {
         $this->Debug("Rollback Transaction");
        return($this->setError("Rollback transaction", "rolling back transactions are not supported"));
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
            return($this->setError("Set selected row range", "selecting row ranges is not supported by this driver"));
        }
        if (gettype($first)!= "integer" || $first<0) {
            return($this->setError("Set selected row range", "it was not specified a valid first selected range row"));
        }
        if (gettype($limit)!= "integer" || $limit<1) {
            return($this->setError("Set selected row range", "it was not specified a valid selected range row limit"));
        }
        $this->first_selected_row = $first;
        $this->selected_row_limit = $limit;
        return(1);
    }

    function getColumnNames($result, &$columns)
    {
        $columns = array();
        return($this->setError("Get column names", "obtaining result column names is not implemented"));
    }
    
    // renamed for PEAR
    // used to be: NumberOfColumns
    function numCols($result)
    {
        $this->setError("Number of columns", "obtaining the number of result columns is not implemented");
        return(-1);
    }

    function SetResultTypes($result, &$types)
    {
        if (isset($this->result_types[$result])) {
            return($this->setError("Set result types", "attempted to redefine the types of the columns of a result set"));
        }
        if (($columns = $this->numCols($result)) == -1) {
            return(0);
        }
        if ($columns<count($types)) {
            return($this->setError("Set result types", "it were specified more result types (".count($types).") than result columns ($columns)"));
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
                return($this->setError("Set result types", $types[$column]." is not a supported column type"));
            }
            $this->result_types[$result][$column] = $valid_types[$types[$column]];
        }
        for(; $column<$columns; $column++) {
            $this->result_types[$result][$column] = MDB_TYPE_TEXT;
        }
        return(1);
    }

    function baseFetchResultArray($result, &$array, $row)
    {
        if (($columns = $this->numCols($result)) == -1) {
            return null;
        }
        for($array = array(), $column = 0; $column<$columns; $column++) {
            if (!$this->resultIsNull($result, $row, $column)) {
                $array[$column] = $this->fetchResult($result, $row, $column);
            }
        }
        if ($this->ConvertResultRow($result, $array)) {
            return DB_OK;
        } else {
            return $this->raiseError();
        }
    }
    
    // renamed for PEAR
    // used to be fetchResultArray
    // added $fetchmode
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $row = 0)
    {
        return ($this->BaseFetchResultArray($result, &$array, $row));
    }
    
    // $fetchmode added
    // new uses fetchInto
    function fetchResultField($result, &$value, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        if ($this->EndOfResult($result)) {
            $success = $this->setError("Fetch field", "result set is empty");
        } else {
            if ($this->ResultIsNull($result, 0, 0)) {
                unset($value);
            } else {
                $this->fetchInto($result, $value, $fetchmode, 0);
                $value = $value[0];
            }
            $success = 1;
        }
        $this->FreeResult($result);
        return ($success);
    }

    // $fetchmode added
    function fetchResultRow($result, &$row, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        if ($this->EndOfResult($result)) {
            $success = $this->setError("Fetch field", "result set is empty");
        } else {
            $success = $this->fetchInto($result, $row, $fetchmode, 0);
        }
        $this->FreeResult($result);
        return ($success);
    }

    // $fetchmode added
    // new uses fetchInto
    function fetchResultColumn($result, &$column, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (!$result) {
            return ($this->setError("Fetch field", "it was not specified a valid result set"));
        }
        $temp = array();
        for($success = 1, $column = array(), $row = 0;!$this->EndOfResult($result); $row++) {
            if ($this->ResultIsNull($result, 0, 0)) {
                continue;
            }
            if (!($success = $this->fetchInto($result, $temp, $fetchmode, $row))) {
                break;
            }
            $column[$row] = $temp[0];
        }
        $this->FreeResult($result);
        return ($success);
    }

    // $fetchmode added
    // this is basically a slightly modified version of fetchResultAll()
    // the only difference is that the first dimension in the 2 dimensional result set is pop-off each row
    // this is especially interesting if you need to fetch a large result set and
    // then jump aroun din the result set based on some primary key
    // if the same key is pop-off for multiple rows then the second overwrites the first
    // fetchResultAll new: 0.002962
    // fetchResultAll old: 0.002920
    function fetchResultAll($result, &$all, $fetchmode = DB_FETCHMODE_DEFAULT, $rekey = false, $force_array = false, $group = false)
    {
        if (!$result) {
            return ($this->setError("Fetch field","it was not specified a valid result set"));
        }
        $cols = $this->numCols($result);
        if ($cols < 2) {
            return $this->raiseError(DB_ERROR_TRUNCATED);
        }
        for($all = array(), $row = 0; !$this->endOfResult($result); $row++) {
            if(!($success=$this->fetchInto($result, $array, $fetchmode, $row))) {
                break;
            }
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
                    $all[$key][] = $array;
                } else {
                    $all[$key] = $array;
                }
            } else {
                $all[] = $array;
            }
        }
        $this->FreeResult($result);
        return ($success);
    }
    
    function queryField($query, &$field, $type = "text")
    {
        if (!($result = $this->Query($query))) {
            return (0);
        }
        if (strcmp($type, "text")) {
            $types = array($type);
            if (!($success = $this->SetResultTypes($result, $types))) {
                $this->FreeResult($result);
                return (0);
            }
        }
        return ($this->fetchResultField($result, $field));
    }

    function queryRow($query, &$row, $types = "")
    {
        if (!($result = $this->Query($query))) {
            return (0);
        }
        if (gettype($types) == "array") {
            if (!($success = $this->SetResultTypes($result, $types))) {
                $this->FreeResult($result);
                return (0);
            }
        }
        return ($this->fetchResultRow($result, $row));
    }

    function queryColumn($query, &$column, $type = "text")
    {
        if (!($result = $this->Query($query))) {
            return (0);
        }
        if (strcmp($type, "text")) {
            $types = array($type);
            if (!($success = $this->SetResultTypes($result, $types))) {
                $this->FreeResult($result);
                return (0);
            }
        }
        return ($this->fetchResultColumn($result, $column));
    }

    function queryAll($query, &$all, $types = "")
    {
        if (!($result = $this->query($query))) {
            return (0);
        }
        if (gettype($types) == "array") {
            if (!($success = $this->SetResultTypes($result, $types))) {
                $this->FreeResult($result);
                return (0);
            }
        }
        return ($this->fetchResultAll($result, $all));
    }

    // ********************
    // new methods for PEAR
    // ********************
    
    function limitQuery($query, $from, $count)
    {
        $this->SetSelectedRowRange($from, $count);
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
            //if (MDB::isError($res)) {
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

        $err = $this->fetchResultField($result, &$value,DB_FETCHMODE_ORDERED);
        if ($err !== DB_OK) {
            return $err;
        }

        if (isset($prepared_query)) {
            $this->FreePreparedQuery($prepared_query);
        }

        return $value;
    }

    function &getRow($query, $params = array(),    $fetchmode = DB_FETCHMODE_DEFAULT)
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
        $err = $this->fetchResultRow($result, &$row, $fetchmode);
        
        if ($err !== DB_OK) {
            return $err;
        }
        if (isset($prepared_query)) {
            $this->FreePreparedQuery($prepared_query);
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
        
        $err = $this->fetchResultColumn($result, &$col, $fetchmode);
        
        if ($err !== DB_OK) {
            return $err;
        }
        if (isset($prepared_query)) {
            $this->FreePreparedQuery($prepared_query);
        }

        return $col;
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

        $err = $this->fetchResultAll($result, &$all, $fetchmode, $rekey = true, $force_array, $group);
        
        if ($err !== DB_OK) {
            return $err;
        }        
        if (isset($prepared_query)) {
            $this->FreePreparedQuery($prepared_query);
        }

        return $all;
    }
    
    function tableInfo($result, $mode = null)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }
};

?>