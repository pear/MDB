<?php
/*
 * mysql.php
 *
 * @(#) $Header$
 *
 */

 if(!defined("MDB_MYSQL_INCLUDED")) {
    define("MDB_MYSQL_INCLUDED",1);

require_once "common.php";
 
class MDB_mysql extends MDB_common
{
    var $connection = 0;
    var $connected_host;
    var $connected_user;
    var $connected_password;
    var $connected_port;
    var $opened_persistent = "";
    var $decimal_factor = 1.0;
    var $highest_fetched_row = array();
    var $columns = array();
    var $fixed_float = 0;
    var $escape_quotes = "\\";
    // new for PEAR
    var $errorcode_map = array(
        1004 => DB_ERROR_CANNOT_CREATE,
        1005 => DB_ERROR_CANNOT_CREATE,
        1006 => DB_ERROR_CANNOT_CREATE,
        1007 => DB_ERROR_ALREADY_EXISTS,
        1008 => DB_ERROR_CANNOT_DROP,
        1046 => DB_ERROR_NODBSELECTED,
        1050 => DB_ERROR_ALREADY_EXISTS,
        1051 => DB_ERROR_NOSUCHTABLE,
        1054 => DB_ERROR_NOSUCHFIELD,
        1062 => DB_ERROR_ALREADY_EXISTS,
        1064 => DB_ERROR_SYNTAX,
        1100 => DB_ERROR_NOT_LOCKED,
        1136 => DB_ERROR_VALUE_COUNT_ON_ROW,
        1146 => DB_ERROR_NOSUCHTABLE,
    );
        
    function connect()
    {
        $port = (isset($this->options["Port"]) ? $this->options["Port"] : "");
        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_user, $this->user)
                && !strcmp($this->connected_password, $this->password)
                && !strcmp($this->connected_port, $port)
                && $this->opened_persistent == $this->persistent)
            {
                return (1);
            }
            mysql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }
        $this->fixed_float = 30;
        $function = ($this->persistent ? "mysql_pconnect" : "mysql_connect");
        if (!function_exists($function)) {
            return ($this->raiseError(DB_ERROR_UNSUPPORTED));
        }
        if (($this->connection = @$function($this->host.(!strcmp($port,"") ? "" : ":".$port), $this->user, $this->password)) <= 0) {
            return ($this->raiseError(DB_ERROR_CONNECT_FAILED));
        }
        if (isset($this->options["FixedFloat"])) {
            $this->fixed_float = $this->options["FixedFloat"];
        } else {
            if (($result = mysql_query("SELECT VERSION()", $this->connection))) {
                $version = explode(".",mysql_result($result,0,0));
                $major = intval($version[0]);
                $minor = intval($version[1]);
                $revision = intval($version[2]);
                if ($major > 3 || ($major == 3 && $minor >= 23 && ($minor > 23 || $revision >= 6))) {
                    $this->fixed_float = 0;
                }
                mysql_free_result($result);
            }
        }
        if (isset($this->supported["Transactions"]) && !$this->auto_commit)    {
            if (!mysql_query("SET AUTOCOMMIT = 0", $this->connection)) {
                mysql_Close($this->connection);
                $this->connection = 0;
                $this->affected_rows = -1;
                return (0);
            }
            $this->RegisterTransactionShutdown(0);
        }
        $this->connected_host = $this->host;
        $this->connected_user = $this->user;
        $this->connected_password = $this->password;
        $this->connected_port = $port;
        $this->opened_persistent = $this->persistent;
        return (1);
    }

    function close()
    {
        if ($this->connection != 0) {
            if (isset($this->supported["Transactions"]) && !$this->auto_commit) {
                $this->AutoCommitTransactions(1);
            }
            mysql_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }
    }

    function query($query)
    {
        $this->last_query = $query;
        $this->debug("Query: $query");
        
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (!strcmp($this->database_name, "")) {
            return $this->raiseError(DB_ERROR_NODBSELECTED);
        }
            //return ($this->setError("Query","it was not specified a valid database name to select"));
        if (!$this->connect()) {
            return (0);
        }
        if (($select = (substr(strtolower(ltrim($query)),0,strlen("select")) == "select")) && $limit > 0) {
            $query .= " LIMIT $first, $limit";
        }
        if (mysql_select_db($this->database_name, $this->connection)
            && ($result = mysql_query($query, $this->connection)))
        {
            if ($select) {
                $this->highest_fetched_row[$result] = -1;
            } else {
                $this->affected_rows = mysql_affected_rows($this->connection);
            }
        } else {
            return $this->raiseError(DB_ERROR);
        }
            //return ($this->setError("Query",mysql_error($this->connection)));
        return ($result);
    }

    function replace($table, &$fields)
    {
        $count = count($fields);
        for($keys = 0, $query = $values = "",reset($fields), $field = 0;
        $field<$count;
        next($fields), $field++) {
            $name = key($fields);
            if ($field>0) {
                $query .= ",";
                $values .= ",";
            }
            $query .= $name;
            if (isset($fields[$name]["Null"]) && $fields[$name]["Null"]) {
                $value = "NULL";
            } else {
                if (!isset($fields[$name]["Value"])) {
                    return ($this->setError("Replace","it was not specified a value for the $name field"));
                }
                switch(isset($fields[$name]["Type"]) ? $fields[$name]["Type"] : "text") {
                    case "text":
                        $value = $this->GetTextFieldValue($fields[$name]["Value"]);
                        break;
                    case "boolean":
                        $value = $this->GetBooleanFieldValue($fields[$name]["Value"]);
                        break;
                    case "integer":
                        $value = strval($fields[$name]["Value"]);
                        break;
                    case "decimal":
                        $value = $this->GetDecimalFieldValue($fields[$name]["Value"]);
                        break;
                    case "float":
                        $value = $this->GetFloatFieldValue($fields[$name]["Value"]);
                        break;
                    case "date":
                        $value = $this->GetDateFieldValue($fields[$name]["Value"]);
                        break;
                    case "time":
                        $value = $this->GetTimeFieldValue($fields[$name]["Value"]);
                        break;
                    case "timestamp":
                        $value = $this->GetTimestampFieldValue($fields[$name]["Value"]);
                        break;
                    default:
                        return ($this->setError("Replace","it was not specified a supported type for the $name field"));
                }
            }
            $values .= $value;
            if (isset($fields[$name]["Key"]) && $fields[$name]["Key"]) {
                if ($value == "NULL") {
                    return ($this->setError("Replace","key values may not be NULL"));
                }
                $keys++;
            }
        }
        if ($keys == 0) {
            return ($this->setError("Replace","it were not specified which fields are keys"));
        }
        return ($this->Query("REPLACE INTO $table ($query) VALUES($values)"));
    }

    function endOfResult($result)
    {
        if (!isset($this->highest_fetched_row[$result])) {
            $this->setError("End of result","attempted to check the end of an unknown result");
            return (-1);
        }
        return ($this->highest_fetched_row[$result] >= $this->numRows($result)-1);
    }

    function fetchResult($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return (mysql_result($result, $row, $field));
    }
    
    // renamed for PEAR
    // used to be : fetchResultArray
    // added $fetchmode
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $row = 0)
    {
        if (!mysql_data_seek($result, $row)) {
            return ($this->setError("Fetch result array", mysql_error($this->connection)));
        }
        if ($fetchmode & DB_FETCHMODE_ASSOC) {
            if (!$array = mysql_fetch_array($result, MYSQL_ASSOC)) {
                return ($this->setError("Fetch result array", mysql_error($this->connection)));
            }
        } else {
            if (!$array = mysql_fetch_row($result)) {
                return ($this->setError("Fetch result array", mysql_error($this->connection)));
            }
        }
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return ($this->ConvertResultRow($result, $array));
    }

    function fetchCLobResult($result, $row, $field)
    {
        return ($this->fetchLobResult($result, $row, $field));
    }

    function fetchBLobResult($result, $row, $field)
    {
        return ($this->fetchLobResult($result, $row, $field));
    }

    function convertResult(&$value, $type)
    {
        switch($type) {
            case MDB_TYPE_BOOLEAN:
                $value = (strcmp($value, "Y") ? 0 : 1);
                return (1);
            case MDB_TYPE_DECIMAL:
                $value = sprintf("%.".$this->decimal_places."f",doubleval($value)/$this->decimal_factor);
                return (1);
            case MDB_TYPE_FLOAT:
                $value = doubleval($value);
                return (1);
            case MDB_TYPE_DATE:
            case MDB_TYPE_TIME:
            case MDB_TYPE_TIMESTAMP:
                return (1);
            default:
                return ($this->BaseConvertResult($value, $type));
        }
    }

    function numRows($result)
    {
        return (mysql_num_rows($result));
    }

    function freeResult($result)
    {
        unset($this->highest_fetched_row[$result]);
        unset($this->columns[$result]);
        unset($this->result_types[$result]);
        return (mysql_free_result($result));
    }

    function createDatabase($name)
    {
        if (!$this->Connect()) {
            return (0);
        }
        if (!mysql_create_db($name, $this->connection)) {
            return ($this->setError("Create database",mysql_error($this->connection)));
        }
        return (1);
    }

    function dropDatabase($name)
    {
        if (!$this->Connect()) {
            return (0);
        }
        if (!mysql_drop_db($name, $this->connection)) {
            return ($this->setError("Drop database",mysql_error($this->connection)));
        }
        return (1);
    }

    function getClobFieldTypeDeclaration($name, &$field)
    {
        if (isset($field["length"])) {
            $length = $field["length"];
            if ($length<= 255) {
                $type = "TINYTEXT";
            } else {
                if ($length<= 65535) {
                    $type = "TEXT";
                } else {
                    if ($length<= 16777215) {
                        $type = "MEDIUMTEXT";
                    } else {
                        $type = "LONGTEXT";
                    }
                }
            }
        } else {
            $type = "LONGTEXT";
        }
        return ("$name $type".(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBLobFieldTypeDeclaration($name, &$field)
    {
        if (isset($field["length"])) {
            $length = $field["length"];
            if ($length<= 255) {
                $type = "TINYBLob";
            } else {
                if ($length<= 65535) {
                    $type = "BLob";
                } else {
                    if ($length<= 16777215) {
                        $type = "MEDIUMBLob";
                    } else {
                        $type = "LONGBLob";
                    }
                }
            }
        }
        else {
            $type = "LONGBLob";
        }
        return ("$name $type".(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getIntegerFieldTypeDeclaration($name, &$field)
    {
        return ("$name ".(isset($field["unsigned"]) ? "INT UNSIGNED" : "INT").(isset($field["default"]) ? " DEFAULT ".$field["default"] : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDateFieldTypeDeclaration($name, &$field)
    {
        return ($name." DATE".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimestampFieldTypeDeclaration($name, &$field)
    {
        return ($name." DATETIME".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimeFieldTypeDeclaration($name, &$field)
    {
        return ($name." TIME".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getFloatFieldTypeDeclaration($name, &$field)
    {
        if (isset($this->options["FixedFloat"])) {
            $this->fixed_float = $this->options["FixedFloat"];
        } else {
            if ($this->connection == 0) {
                $this->connect();
            }
        }
        return ("$name DOUBLE".($this->fixed_float ? "(".($this->fixed_float+2).",".$this->fixed_float.")" : "").(isset($field["default"]) ? " DEFAULT ".$this->getFloatFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDecimalFieldTypeDeclaration($name, &$field)
    {
        return ("$name BIGINT".(isset($field["default"]) ? " DEFAULT ".$this->getDecimalFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getClobFieldValue($prepared_query, $parameter, $clob, &$value)
    {
        for($value = "'";!endOfLob($clob);) {
            if (readLob($clob, $data, $this->lob_buffer_length)<0) {
                $value = "";
                return ($this->setError("Get Clob field value",LobError($clob)));
            }
            $this->quote($data);
            $value .= $data;
        }
        $value .= "'";
        return (1);            
    }

    function FreeClobValue($prepared_query, $clob, &$value, $success)
    {
        unset($value);
    }

    function getBLobFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        for($value = "'";!endOfLob($blob);)    {
            if (!readLob($blob, $data, $this->lob_buffer_length)) {
                $value = "";
                return ($this->setError("Get BLob field value",LobError($clob)));
            }
            $value .= AddSlashes($data);
        }
        $value .= "'";
        return (1);            
    }

    function FreeBLobValue($prepared_query, $blob, &$value, $success)
    {
        Unset($value);
    }

    function getFloatFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    function getDecimalFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : strval(round(doubleval($value)*$this->decimal_factor)));
    }

    function getColumnNames($result, &$column_names)
    {
        $result_value = intval($result);
        if (!isset($this->highest_fetched_row[$result_value])) {
            return ($this->setError("Get column names","it was specified an inexisting result set"));
        }
        if (!isset($this->columns[$result_value])) {
            $this->columns[$result_value] = array();
            $columns = mysql_num_fields($result);
            for($column = 0;$column<$columns;$column++) {
                $this->columns[$result_value][strtolower(mysql_field_name($result, $column))] = $column;
            }
        }
        $column_names = $this->columns[$result_value];
        return (1);
    }
    
    // renamed for PEAR
    // used to be: NumberOfColumns
    function numCols($result)
    {
        if (!isset($this->highest_fetched_row[intval($result)])) {
            $this->setError("Get column names","it was specified an inexisting result set");
            return (-1);
        }
        return (mysql_num_fields($result));
    }

    function createTable($name, &$fields)
    {
        if (!isset($name) || !strcmp($name, "")) {
            return ($this->setError("Create table","it was not specified a valid table name"));
        }
        if (count($fields) == 0) {
            return ($this->setError("Create table","it were not specified any fields for table \"$name\""));
        }
        $query_fields = "";
        if (!$this->getFieldList($fields, $query_fields)) {
            return (0);
        }
        if (isset($this->supported["Transactions"])) {
            $query_fields .= ", dummy_primary_key INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (dummy_primary_key)";
        }
        return ($this->Query("CREATE TABLE $name ($query_fields)".(isset($this->supported["Transactions"]) ? " TYPE = BDB" : "")));
    }

    function alterTable($name, &$changes, $check)
    {
        if ($check) {
            for($change = 0,reset($changes);
                $change < count($changes);
                next($changes), $change++)
            {
                switch(Key($changes)) {
                    case "AddedFields":
                    case "RemovedFields":
                    case "ChangedFields":
                    case "RenamedFields":
                    case "name":
                        break;
                    default:
                        return ($this->setError("Alter table","change type \"".Key($changes)."\" not yet supported"));
                }
            }
            return (1);
        } else {
            $query = (isset($changes["name"]) ? "RENAME AS ".$changes["name"] : "");
            if (isset($changes["AddedFields"]))    {
                $fields = $changes["AddedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $query .= "ADD ".$fields[key($fields)]["Declaration"];
                }
            }
            if (isset($changes["RemovedFields"])) {
                $fields = $changes["RemovedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $query .= "DROP ".Key($fields);
                }
            }
            $renamed_fields = array();
            if (isset($changes["RenamedFields"])) {
                $fields = $changes["RenamedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    $renamed_fields[$fields[key($fields)]["name"]] = key($fields);
                }
            }
            if (isset($changes["ChangedFields"])) {
                $fields = $changes["ChangedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    if (isset($renamed_fields[key($fields)])) {
                        $field_name = $renamed_fields[key($fields)];
                        unset($renamed_fields[key($fields)]);
                    } else {
                        $field_name = key($fields);
                    }
                    $query .= "CHANGE $field_name ".$fields[key($fields)]["Declaration"];
                }
            }
            if (count($renamed_fields))
            {
                for($field = 0,reset($renamed_fields);
                    $field<count($renamed_fields);
                    next($renamed_fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $old_field_name = $renamed_fields[Key($renamed_fields)];
                    $query .= "CHANGE $old_field_name ".$changes["RenamedFields"][$old_field_name]["Declaration"];
                }
            }
            return ($this->Query("ALTER TABLE $name $query"));
        }
    }

    function createSequence($name, $start)
    {
        $res = $this->Query("CREATE TABLE _sequence_$name
            (sequence INT DEFAULT 0 NOT NULL AUTO_INCREMENT, PRIMARY KEY (sequence))");
        if (DB::isError($res)) {
            return $res;
        }
        if ($start == 1 || $this->Query("INSERT INTO _sequence_$name (sequence) VALUES (".($start-1).")")) {
            return (1);
        }
        $error = $this->Error();
        if (!$this->Query("DROP TABLE _sequence_$name")) {
            $this->warning = "could not drop inconsistent sequence table";
        }
        return ($this->setError("Create sequence", $error));
    }

    function dropSequence($name)
    {
        return ($this->Query("DROP TABLE _sequence_$name"));
    }
    
    // renamed for PEAR
    // used to be: getSequenceNextValue
    // added $on_demand
    // on_demand was default true for PEAR but was unavailable in Metabase
    // so this needs to be handled
    function nextId($name, $on_demand = true)
    {
        if (!$this->Query("INSERT INTO _sequence_$name (sequence) VALUES (NULL)")) {
            return (0);
        }
        $value = intval(mysql_insert_id());
        if (!$this->Query("DELETE FROM _sequence_$name WHERE sequence<$value")) {
            $this->warning = "could delete previous sequence table values";
        }
        return ($value);
    }
    
    // renamed for PEAR
    // used to be: getSequenceCurrentValue
    function currId($name)
    {
        if (($result = $this->Query("SELECT MAX(sequence) FROM _sequence_$name")) == 0) {
            return (0);
        }
        $value = intval($this->fetchResult($result,0,0));
        $this->FreeResult($result);
        return ($value);
    }

    function createIndex($table, $name, $definition)
    {
        $query = "ALTER TABLE $table ADD ".(isset($definition["unique"]) ? "UNIQUE" : "INDEX")." $name (";
        for($field = 0,reset($definition["FIELDS"]);
            $field<count($definition["FIELDS"]);
            $field++,next($definition["FIELDS"]))
        {
            if ($field>0) {
                $query .= ",";
            }
            $query .= Key($definition["FIELDS"]);
        }
        $query .= ")";
        return ($this->Query($query));
    }

    function dropIndex($table, $name)
    {
        return ($this->Query("ALTER TABLE $table DROP INDEX $name"));
    }

    function autoCommitTransactions($auto_commit)
    {
        $this->debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        if (!isset($this->supported["Transactions"])) {
            return ($this->setError("Auto-commit transactions","transactions are not in use"));
        }
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return (1);
        }
        if ($this->connection) {
            if ($auto_commit) {
                if (!$this->Query("COMMIT") || !$this->Query("SET AUTOCOMMIT = 1")) {
                    return (0);
                }
            } else {
                if (!$this->Query("SET AUTOCOMMIT = 0")) {
                    return (0);
                }
            }
        }
        $this->auto_commit = $auto_commit;
        return ($this->RegisterTransactionShutdown($auto_commit));
    }

    function commitTransaction()
    {
         $this->debug("Commit Transaction");
        if (!isset($this->supported["Transactions"])) {
            return ($this->setError("Commit transaction",
                "transactions are not in use"));
        }
        if ($this->auto_commit) {
            return ($this->setError("Commit transaction",
                "transaction changes are being auto commited"));
        }
        return ($this->query("COMMIT"));
    }

    function rollbackTransaction()
    {
         $this->debug("Rollback Transaction");
        if (!isset($this->supported["Transactions"])) {
            return ($this->setError("Rollback transaction", "transactions are not in use"));
        }
        if ($this->auto_commit) {
            return ($this->setError("Rollback transaction",
                "transactions can not be rolled back when changes are auto commited"));
        }
        return ($this->query("ROLLBACK"));
    }

    function setup()
    {
        $this->supported["Sequences"] = 
        $this->supported["Indexes"] = 
        $this->supported["AffectedRows"] = 
        $this->supported["Summaryfunctions"] = 
        $this->supported["OrderByText"] = 
        $this->supported["GetSequenceCurrentValue"] = 
        $this->supported["SelectRowRanges"] = 
        $this->supported["LOBs"] = 
        $this->supported["Replace"] = 
            1;
        if (isset($this->options["UseTransactions"])
        && $this->options["UseTransactions"])
            $this->supported["Transactions"] = 1;
        $this->decimal_factor = pow(10.0, $this->decimal_places);
        return ("");
    }
    
    // ********************
    // new methods for PEAR
    // ********************

   function mysqlRaiseError($errno = null)
   {
       if ($errno == null) {
           $errno = $this->errorCode(mysql_errno($this->connection));
       }
       return $this->raiseError($errno, null, null, null, @mysql_error($this->connection));
   }
   
     function tableInfo($result, $mode = null) {
        $count = 0;
        $id     = 0;
        $res  = array();

        /*
         * depending on $mode, metadata returns the following values:
         *
         * - mode is false (default):
         * $result[]:
         *   [0]["table"]  table name
         *   [0]["name"]   field name
         *   [0]["type"]   field type
         *   [0]["len"]    field length
         *   [0]["flags"]  field flags
         *
         * - mode is DB_TABLEINFO_ORDER
         * $result[]:
         *   ["num_fields"] number of metadata records
         *   [0]["table"]  table name
         *   [0]["name"]   field name
         *   [0]["type"]   field type
         *   [0]["len"]    field length
         *   [0]["flags"]  field flags
         *   ["order"][field name]  index of field named "field name"
         *   The last one is used, if you have a field name, but no index.
         *   Test:  if (isset($result['meta']['myfield'])) { ...
         *
         * - mode is DB_TABLEINFO_ORDERTABLE
         *    the same as above. but additionally
         *   ["ordertable"][table name][field name] index of field
         *      named "field name"
         *
         *      this is, because if you have fields from different
         *      tables with the same field name * they override each
         *      other with DB_TABLEINFO_ORDER
         *
         *      you can combine DB_TABLEINFO_ORDER and
         *      DB_TABLEINFO_ORDERTABLE with DB_TABLEINFO_ORDER |
         *      DB_TABLEINFO_ORDERTABLE * or with DB_TABLEINFO_FULL
         */

        // if $result is a string, then we want information about a
        // table without a resultset
        if (is_string($result)) {
            $id = @mysql_list_fields($this->database_name,
                $result, $this->connection);
            if (empty($id)) {
                return $this->mysqlRaiseError();
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return $this->mysqlRaiseError();
            }
        }

        $count = @mysql_num_fields($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {
            for ($i = 0; $i<$count; $i++) {
                $res[$i]['table'] = @mysql_field_table ($id, $i);
                $res[$i]['name'] = @mysql_field_name  ($id, $i);
                $res[$i]['type'] = @mysql_field_type  ($id, $i);
                $res[$i]['len']  = @mysql_field_len   ($id, $i);
                $res[$i]['flags'] = @mysql_field_flags ($id, $i);
            }
        } else { // full
            $res['num_fields'] = $count;

            for ($i = 0; $i<$count; $i++) {
                $res[$i]['table'] = @mysql_field_table ($id, $i);
                $res[$i]['name'] = @mysql_field_name  ($id, $i);
                $res[$i]['type'] = @mysql_field_type  ($id, $i);
                $res[$i]['len']  = @mysql_field_len   ($id, $i);
                $res[$i]['flags'] = @mysql_field_flags ($id, $i);
                if ($mode & DB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & DB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }

        // free the result only if we were called on a table
        if (is_string($result)) {
            @mysql_free_result($id);
        }
        return $res;
    }
};
}
?>