<?php
/*
 * pgsql.php
 *
 * @(#) $Header$
 *
 */

if (!defined("MDB_PGSQL_INCLUDED")) {
    define("MDB_PGSQL_INCLUDED", 1);


require_once "common.php";

class MDB_pgsql extends MDB_common
{
    var $connection = 0;
    var $connected_host;
    var $connected_port;
    var $selected_database = "";
    var $opened_persistent = "";
    var $transaction_started = 0;
    var $decimal_factor = 1.0;
    var $highest_fetchd_row = array();
    var $columns = array();
    var $escape_quotes = "\\";

    // }}}
    // {{{ constructor
    /**
     * Constructor
     */
    function MDB_pgsql()
    {
        $this->MDB_common();
        $this->phptype = 'pgsql';
        $this->dbsyntax = 'pgsql';
    
        $this->supported["Sequences"] = 1;
        $this->supported["Indexes"] = 1;
        $this->supported["AffectedRows"] = 1;
        $this->supported["Summaryfunctions"] = 1;
        $this->supported["OrderByText"] = 1;
        $this->supported["currId"] = 1;
        $this->supported["SelectRowRanges"] = 1;
        $this->supported["LOBs"] = 1;
        $this->supported["Replace"] = 1;
        $this->supported["SubSelects"] = 1;

        if (isset($this->options["UseTransactions"])
            && $this->options["UseTransactions"]) {
            $this->supported["Transactions"] = 1;
        }
        $this->decimal_factor = pow(10.0, $this->decimal_places);
    
        $this->errorcode_map = array(
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
                                     1048 => DB_ERROR_CONSTRAINT,
                                     );
    }


    // }}}
    // {{{ _doConnect
    /**
     * Does the grunt work of connecting to the database
     *
     * @access private
     * @return mixed connection resource on success, MDB_Error on failure
     */
    function _doConnect($database_name, $persistent)
    {
        $function=($persistent ? "pg_pconnect" : "pg_connect");
        if(!function_exists($function))
            return($this->raiseError(DB_ERROR_UNSUPPORTED, NULL, NULL, "doConnect: PostgreSQL support is not available in this PHP configuration"));
        $port = (IsSet($this->options["port"]) ? $this->options["port"] : "");
        $connect_string = "dbname=$database_name host=" . $this->host;
        if ($port != "") {
            $connect_string .= " port=" . strval($port);
        }
        if ($this->user != "") {
            $connect_string .= " user=" . $this->user;
        }
        if ($this->password != "") {
            $connect_string .= " password=" . $this->password;
        }
        if(($connection = @$function($connect_string)) > 0) {
            return($connection);
        }

        return($this->raiseError(DB_ERROR_CONNECT_FAILED, '', '', "doConnect: " . IsSet($php_errormsg) ? $php_errormsg : "Could not connect to PostgreSQL server"));
    }

    // }}}
    // {{{ Connect to the database
    /**
     * Connect to the database
     *
     * @return TRUE on success, MDB_Error on failure
     */
    function connect()
    {
        $port = (IsSet($this->options["port"]) ? $this->options["port"] : "");
        if($this->connection != 0) {
            if(!strcmp($this->connected_host, $this->host)
               && !strcmp($this->connected_port, $port)
               && !strcmp($this->selected_database, $this->database_name)
               && ($this->opened_persistent == $this->persistent)) 
                return(1);
            pg_Close($this->connection);
            $this->affected_rows = -1;
            $this->connection = 0;
        }
        $this->connection = $this->_doConnect($this->database_name, $this->persistent);
        if(MDB::isError($this->connection)) {
            return $this->connection;
        }
        if(!$this->auto_commit && !MDB::isError($trans_result = $this->query("BEGIN"))) {
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $trans_result;
        }
        $this->connected_host = $this->host;
        $this->connected_port = $port;
        $this->selected_database = $this->database_name;
        $this->opened_persistent = $this->persistent;
        return(1);
    }

    // }}}
    // {{{ Close the database connection
    /**
     * Close the database connection
     */
    function close()
    {
        if($this->connection != 0) {
            if(!$this->auto_commit) {
                $this->query("END");
            }
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }
    }

    // }}}
    // {{{ Execute a query
    /**
     * Execute a query
     *
     * @param string $query the SQL query
     *
     * @return mixed result identifier if query executed, else MDB_error
     * @access private
     */
    function _doQuery($query)
    {
        if(($result = @pg_Exec($this->connection, $query))) {
            $this->affected_rows=(IsSet($this->supported["AffectedRows"]) ? pg_cmdTuples($result) : -1);
        } else {
            $error = pg_ErrorMessage($this->connection);
            return $this->raiseError(NULL, NULL, NULL, "query: $query:" . $error, $error);
        }
        return($result);
    }

    // }}}
    // {{{ Execute a query 
    /**
     * Execute a query
     *
     * @param string $query the SQL query
     * @param array   $types  array that contains the types of the columns in
     *                        the result set
     *
     * @return mixed result identifier if query executed, else MDB_error
     * @access public
     */
    function query($query, $types = NULL)
    {
        $this->last_query = $query;
        $this->debug("Query: $query");
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit=0;
        $connected = $this->connect(); 
        if(MDB::isError($connected)) {
            return $connected;
        }

        if (($select = (substr(strtolower(ltrim($query)), 
                               0, strlen("select")) == "select") ) && $limit > 0) {
            if($this->auto_commit && !MDB::isError($this->_doQuery("BEGIN"))) {
                return $this->raiseError(DB_ERROR);
            }
            $error = "";
        
            if(!MDB::isError($result = $this->_doQuery("DECLARE select_cursor SCROLL CURSOR FOR $query")))
                {
                    $result = $this->_doQuery("MOVE FORWARD $first FROM select_cursor");
                    if($first > 0 && MDB::isError($result)) {
                        $error = pg_Errormessage($this->connection);
                    }
                    if(!MDB::isError($result)
                       && MDB::isError($result = $this->_doQuery("FETCH FORWARD $limit FROM select_cursor"))) {
                        $error = pg_Errormessage($this->connection);
                    }
                } else {
                    $error = pg_Errormessage($this->connection);
                }
            if($this->auto_commit && MDB::isError($result = $this->_doQuery("END"))) {
                if($result) {
                    $error = pg_Errormessage($this->connection);
                    $this->freeResult($result);
                    $result = 0;
                } else {
                    $error .= " and could not end the implicit transaction (".$this->Error().")";
                }
            }
            $this->raiseError(DB_ERROR, NULL, NULL, $error);
        } else {
            $result = $this->_doQuery($query);
        }
        if (!MDB::isError($result) && $select) {
            $this->highest_fetched_row[$result] = -1;
        }
        if (is_resource($result)) {
            if ($types != NULL) {
                if (!is_array($types)) {
                    $types = array($types);
                }
                if (MDB::isError($err = $this->setResultTypes($result, $types))) {
                    $this->freeResult($result);
                    return $err;
                }
            }
            return $result;
        }
        return DB_OK;
    }

    function endOfResult($result)
    {
        if(!isset($this->highest_fetched_row[$result]))
            {
                return $this->RaiseError(DB_ERROR, '', '', "End of result attempted to check the end of an unknown result");
            }
        return($this->highest_fetched_row[$result]>=$this->numRows($result)- 1);
    }

    function fetchResult($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return(pg_result($result, $row, $field));
    }

    function fetchResultArray($result,&$array,$row)
    {
        if(!($array = pg_fetch_row($result,$row))) {
            return($this->SetError("Fetch result array", pg_ErrorMessage($this->connection)));
        }
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return($this->ConvertResultRow($result,$array));
    }

    function retrieveLOB($lob)
    {
        if(!IsSet($this->lobs[$lob])) {
            return($this->SetError("Retrieve LOB","it was not specified a valid lob"));
        }
        if(!IsSet($this->lobs[$lob]["Value"])) {
            if($this->auto_commit) {
                if(!@pg_Exec($this->connection, "BEGIN")) {
                    return($this->SetError("Retrieve LOB", pg_ErrorMessage($this->connection)));
                }
                $this->lobs[$lob]["InTransaction"] = 1;
            }
            $this->lobs[$lob]["Value"] = $this->FetchResult($this->lobs[$lob]["Result"], $this->lobs[$lob]["Row"], $this->lobs[$lob]["Field"]);
            if(!($this->lobs[$lob]["Handle"] = pg_loopen($this->connection, $this->lobs[$lob]["Value"], "r"))) {
                if(IsSet($this->lobs[$lob]["InTransaction"])) {
                    @pg_Exec($this->connection, "END");
                    UnSet($this->lobs[$lob]["InTransaction"]);
                }
                Unset($this->lobs[$lob]["Value"]);
                return($this->SetError("Retrieve LOB",pg_ErrorMessage($this->connection)));
            }
        }
        return(1);
    }

    function endOfResultLOB($lob)
    {
        if(!$this->retrieveLOB($lob)) {
            return(0);
        }
        return(IsSet($this->lobs[$lob]["EndOfLOB"]));
    }

    function readResultLOB($lob, &$data, $length)
    {
        if(!$this->RetrieveLOB($lob)) {
            return(-1);
        }
        $data = pg_loread($this->lobs[$lob]["Handle"], $length);
        if(GetType($data) != "string") {
            $this->SetError("Read Result LOB", pg_ErrorMessage($this->connection));
            return(-1);
        }
        if(($length = strlen($data)) == 0) {
            $this->lobs[$lob]["EndOfLOB"] = 1;
        }
        return($length);
    }

    function destroyResultLOB($lob)
    {
        if(IsSet($this->lobs[$lob])) {
            if(IsSet($this->lobs[$lob]["Value"])) {
                pg_loclose($this->lobs[$lob]["Handle"]);
                if(IsSet($this->lobs[$lob]["InTransaction"])) {
                    @pg_Exec($this->connection, "END");
                }
            }
            $this->lobs[$lob]="";
        }
    }

    function fetchCLOBResult($result, $row, $field)
    {
        return($this->fetchLOBResult($result, $row, $field));
    }

    function fetchBLOBResult($result, $row, $field)
    {
        return($this->FetchLOBResult($result, $row, $field));
    }

    function resultIsNull($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return(@pg_FieldIsNull($result, $row, $field));
    }

    function numberOfRows($result)
    {
        return(pg_numrows($result));
    }

    function freeResult($result)
    {
        UnSet($this->highest_fetched_row[$result]);
        UnSet($this->columns[$result]);
        UnSet($this->result_types[$result]);
        return(pg_freeresult($result));
    }

    function standaloneQuery($query)
    {
        if(($connection = $this->_doConnect("template1", 0)) == 0) {
            return(0);
        }
        if(!($success= @pg_Exec($connection, $query))) {
            $this->SetError("Standalone query", pg_ErrorMessage($connection));
        }
        pg_Close($connection);
        return($success);
    }

    function createDatabase($name)
    {
        return($this->StandaloneQuery("CREATE DATABASE $name"));
    }

    function dropDatabase($name)
    {
        return($this->StandaloneQuery("DROP DATABASE $name"));
    }

    function getTextFieldTypeDeclaration($name, &$field)
    {
        return((IsSet($field["length"]) ? "$name VARCHAR (".$field["length"].")" : "$name TEXT").(IsSet($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getCLOBFieldTypeDeclaration($name, &$field)
    {
        return("$name OID" . (IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getBLOBFieldTypeDeclaration($name, &$field)
    {
        return("$name OID" . (IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDateFieldTypeDeclaration($name, &$field)
    {
        return($name." DATE".(IsSet($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getTimeFieldTypeDeclaration($name, &$field)
    {
        return($name." TIME".(IsSet($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getFloatFieldTypeDeclaration($name, &$field)
    {
        return("$name FLOAT8 ".(IsSet($field["default"]) ? " DEFAULT ".$this->GetFloatFieldValue($field["default"]) : "").(IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getDecimalFieldTypeDeclaration($name, &$field)
    {
        return("$name INT8 ".(IsSet($field["default"]) ? " DEFAULT ".$this->GetDecimalFieldValue($field["default"]) : "").(IsSet($field["notnull"]) ? " NOT NULL" : ""));
    }

    function getLOBFieldValue($prepared_query, $parameter, $lob, &$value)
    {
        if(!$this->connect()) {
            return(0);
        }
        if($this->auto_commit && !@pg_Exec($this->connection, "BEGIN")) {
            return(0);
        }
        $success = 1;
        if(($lo = pg_locreate($this->connection))) {
            if(($handle=pg_loopen($this->connection,$lo,"w"))) {
                while(!MetabaseEndOfLOB($lob)) {
                    if(MetabaseReadLOB($lob, $data, $this->lob_buffer_length) < 0) {
                        $this->SetError("Get LOB field value", MetabaseLOBError($lob));
                        $success = 0;
                        break;
                    }
                    if(!pg_lowrite($handle, $data)) {
                        $this->SetError("Get LOB field value", pg_ErrorMessage($this->connection));
                        $success = 0;
                        break;
                    }
                }
                pg_loclose($handle);
                if($success)
                    $value = strval($lo);
            } else {
                $this->SetError("Get LOB field value", pg_ErrorMessage($this->connection));
                $success = 0;
            }
            if(!$success)
                pg_lounlink($this->connection,$lo);
        } else {
            $this->SetError("Get LOB field value", pg_ErrorMessage($this->connection));
            $success = 0;
        }
        if($this->auto_commit)
            @pg_Exec($this->connection, "END");
        return($success);
    }

    function getCLOBFieldValue($prepared_query, $parameter, $clob, &$value)
    {
        return($this->GetLOBFieldValue($prepared_query, $parameter, $clob, $value));
    }

    function freeCLOBValue($prepared_query, $clob, &$value, $success)
    {
        if(!$success) {
            pg_lounlink($this->connection,intval($value));
        }
    }

    function getBLOBFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        return($this->GetLOBFieldValue($prepared_query, $parameter, $blob, $value));
    }

    function freeBLOBValue($prepared_query, $blob, &$value, $success)
    {
        if(!$success) {
            pg_lounlink($this->connection, intval($value));
        }
    }

    function getFloatFieldValue($value)
    {
        return(!strcmp($value,"NULL") ? "NULL" : "$value");
    }

    function getDecimalFieldValue($value)
    {
        return(!strcmp($value,"NULL") ? "NULL" : strval(round($value * $this->decimal_factor)));
    }

    function getColumnNames($result, &$column_names)
    {
        if(!IsSet($this->highest_fetched_row[$result])) {
            return($this->SetError("Get Column Names", "it was specified an inexisting result set"));
        }
        if(!IsSet($this->columns[$result])) {
            $this->columns[$result] = array();
            $columns = pg_numfields($result);
            for($column = 0; $column < $columns; $column++)
                $this->columns[$result][strtolower(pg_fieldname($result, $column))] = $column;
        }
        $column_names = $this->columns[$result];
        return(1);
    }

    function numberOfColumns($result)
    {
        if(!IsSet($this->highest_fetched_row[$result])) {
            $this->SetError("Number of columns", "it was specified an inexisting result set");
            return(-1);
        }
        return(pg_numfields($result));
    }

    function convertResult(&$value, $type)
    {
        switch($type) {
        case METABASE_TYPE_BOOLEAN:
            $value = (strcmp($value,"Y") ? 0 : 1);
            return(1);
        case METABASE_TYPE_DECIMAL:
            $value = sprintf("%." . $this->decimal_places . "f", doubleval($value)/$this->decimal_factor);
            return(1);
        case METABASE_TYPE_FLOAT:
            $value = doubleval($value);
            return(1);
        case METABASE_TYPE_DATE:
        case METABASE_TYPE_TIME:
            return(1);
        case METABASE_TYPE_TIMESTAMP:
            $value = substr($value, 0, strlen("YYYY-MM-DD HH:MM:SS"));
            return(1);
        default:
            return($this->BaseConvertResult($value, $type));
        }
    }

    function alterTable($name, &$changes, $check)
    {
        if($check) {
            for($change=0, Reset($changes); $change < count($changes); Next($changes), $change++) {
                switch(Key($changes)) {
                case "AddedFields":
                    break;
                case "RemovedFields":
                    return($this->SetError("Alter table", "database server does not support dropping table columns"));
                case "name":
                case "RenamedFields":
                case "ChangedFields":
                default:
                    return($this->SetError("Alter table", "change type \"" . Key($changes) . "\" not yet supported"));
                }
            }
            return(1);
        } else {
            if(IsSet($changes[$change="name"])
               || IsSet($changes[$change="RenamedFields"])
               || IsSet($changes[$change="ChangedFields"]))
                return($this->SetError("Alter table", "change type \"$change\" not yet supported"));
            $query = "";
            if(IsSet($changes["AddedFields"])) {
                $fields = $changes["AddedFields"];
                for($field = 0, Reset($fields); $field < count($fields); Next($fields), $field++) {
                    if(!$this->Query("ALTER TABLE $name ADD " . $fields[Key($fields)]["Declaration"])) {
                        return(0);
                    }
                }
            }
            if(IsSet($changes["RemovedFields"])) {
                $fields = $changes["RemovedFields"];
                for($field = 0, Reset($fields); $field < count($fields); Next($fields), $field++) {
                    if(!$this->Query("ALTER TABLE $name DROP " . Key($fields))) {
                        return(0);
                    }
                }
            }
            return(1);
        }
    }

    function createSequence($name, $start)
    {
        return($this->query("CREATE SEQUENCE $name INCREMENT 1" . ($start < 1 ? " MINVALUE $start" : "")." START $start"));
    }

    function dropSequence($name)
    {
        return($this->query("DROP SEQUENCE $name"));
    }

    function getSequenceNextValue($name, &$value)
    {
        if(!($result = $this->Query("SELECT NEXTVAL ('$name')"))) {
            return(0);
        }
        if($this->numRows($result) == 0) {
            $this->freeResult($result);
            return($this->SetError("Get sequence next value","could not find value in sequence table"));
        }
        $value = intval($this->fetchResult($result, 0, 0));
        $this->freeResult($result);
        return(1);
    }

    function getSequenceCurrentValue($name, &$value)
    {
        if(!($result = $this->query("SELECT last_value FROM $name"))) {
            return(0);
        }
        if($this->numRows($result) == 0) {
            $this->FreeResult($result);
            return($this->SetError("Get sequence current value","could not find value in sequence table"));
            }
        $value = intval($this->fetchResult($result,0,0));
        $this->freeResult($result);
        return(1);
    }

    function autoCommit($auto_commit)
    {
        $this->Debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        if(((!$this->auto_commit) == (!$auto_commit))) {
            return(1);
        }
        if($this->connection) {
            if(!$this->Query($auto_commit ? "END" : "BEGIN"))
                return(0);
        }
        $this->auto_commit=$auto_commit;
        return($this->registerTransactionShutdown($auto_commit));
    }

    function commit()
    {
        $this->debug("Commit Transaction");
        if($this->auto_commit) {
            return($this->SetError("Commit transaction","transaction changes are being auto commited"));
        }
        return($this->query("COMMIT") && $this->query("BEGIN"));
    }

    function rollback()
    {
        $this->febug("Rollback Transaction");
        if($this->auto_commit) {
            return($this->SetError("Rollback transaction","transactions can not be rolled back when changes are auto commited"));
        }
        return($this->query("ROLLBACK") && $this->query("BEGIN"));
    }

    function fetch($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        $res = @pg_result($result, $row, $field);
        if (!$res && $res != NULL) {
            return $this->pgsqlRaiseError();
        }
        return ($res);
    }

    // }}}
    // {{{ numCols()

    /**
     * Get the number of columns in a result set.
     *
     * @param $result resource PostgreSQL result identifier
     *
     * @return int the number of columns per row in $result
     */
    function numCols($result)
    {
        $cols = @pg_numfields($result);
        if (!$cols) {
            return $this->pgsqlRaiseError();
        }
        return $cols;
    }

    // }}}
    // {{{ pgsqlRaiseError()

    function pgsqlRaiseError($errno = null)
    {
        $native = $this->errorNative();
        if ($errno === null) {
            $err = $this->errorCode($native);
        } else {
            $err = $errno;
        }
        return $this->raiseError($err, null, null, null, $native);
    }

    // }}}
    // {{{ tableInfo()

    /**
     * Returns information about a table or a result set
     *
     * NOTE: doesn't support table name and flags if called from a db_result
     *
     * @param  mixed $resource PostgreSQL result identifier or table name
     * @param  int $mode A valid tableInfo mode (DB_TABLEINFO_ORDERTABLE or
     *                   DB_TABLEINFO_ORDER)
     *
     * @return array An array with all the information
     */
    function tableInfo($result, $mode = null)
    {
        $count = 0;
        $id    = 0;
        $res   = array();

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
            $id = pg_exec($this->connection,"SELECT * FROM $result");
            if (empty($id)) {
                return $this->pgsqlRaiseError();
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return $this->pgsqlRaiseError();
            }
        }

        $count = @pg_numfields($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {

            for ($i=0; $i<$count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name']  = @pg_fieldname ($id, $i);
                $res[$i]['type']  = @pg_fieldtype ($id, $i);
                $res[$i]['len']   = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldflags($id, $i, $result) : '';
            }

        } else { // full
            $res["num_fields"]= $count;

            for ($i=0; $i<$count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name']  = @pg_fieldname ($id, $i);
                $res[$i]['type']  = @pg_fieldtype ($id, $i);
                $res[$i]['len']   = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldFlags($id, $i, $result) : '';
                if ($mode & DB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & DB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }

        // free the result only if we were called on a table
        if (is_resource($id)) {
            @pg_freeresult($id);
        }
        return $res;
    }

    // }}}
    // {{{ nextId()

    /**
     * Get the next value in a sequence.
     *
     * We are using native PostgreSQL sequences. If a sequence does
     * not exist, it will be created, unless $ondemand is false.
     *
     * @access public
     * @param string $seq_name the name of the sequence
     * @param bool $ondemand whether to create the sequence on demand
     * @return a sequence integer, or a DB error
     */
    function nextId($seq_name, $ondemand = true)
    {
        //temp hack - need to figure this out in pear
        //        $seqname = $this->getSequenceName($seq_name);
        $seqname = $seq_name;
        $repeat = 0;
        do {
            $this->pushErrorHandling(PEAR_ERROR_RETURN);
            $result = $this->query("SELECT NEXTVAL('${seqname}')");
            $this->popErrorHandling();
            if ($ondemand && MDB::isError($result) &&
                $result->getCode() == DB_ERROR_NOSUCHTABLE) {
                $repeat = 1;
                $result = $this->createSequence($seq_name);
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                }
            } else {
                $repeat = 0;
            }
        } while ($repeat);
        if (MDB::isError($result)) {
            return $this->raiseError($result);
        }
        $this->fetch($result, $arr, DB_FETCHMODE_ORDERED);
        $this->freeResult($result);
        return $arr[0];
    }

    /**
     * Get the number of rows in a result set.
     *
     * @param $result resource PostgreSQL result identifier
     *
     * @return int the number of rows in $result
     */
    function numRows($result)
    {
        $rows = @pg_numrows($result);
        if ($rows === null) {
            return $this->pgsqlRaiseError();
        }
        return $rows;
    }

    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param $result PostgreSQL result identifier
     * @param $array (reference) array where data from the row is stored
     * @param $fetchmode how the array data should be indexed
     * @param $rownum the row number to fetch
     *
     * @return int DB_OK on success, a DB error code on failure
     */
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        if($rownum == NULL) {
            ++$this->highest_fetched_row[$result];
            $rownum = $this->highest_fetched_row[$result];
        } else {
            $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        }
        if ($rownum + 1 > $this->numRows($result)) {
            return NULL;
        }
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $rownum);
        if ($fetchmode & DB_FETCHMODE_ASSOC) {
            $array = @pg_fetch_array($result, $rownum, PGSQL_ASSOC);
        } else {
            $array = @pg_fetch_row($result, $rownum);
        }
        if (!$array) {
            $err = pg_errormessage($this->connection);
            if (!$err) {
                return null;
            }
            return $this->pgsqlRaiseError();
        }
        if (isset($this->result_types[$result])) {
            if (!$this->convertResultRow($result, $array)) {
                return $this->raiseError();
            }
        }
        return DB_OK;
    }

    // }}}
    // {{{ _pgFieldFlags()

    /**
     * Flags of a Field
     *
     * @param int $resource PostgreSQL result identifier
     * @param int $num_field the field number
     *
     * @return string The flags of the field ("not_null", "default_xx", "primary_key",
     *                "unique" and "multiple_key" are supported)
     * @access private
     */
    function _pgFieldFlags($resource, $num_field, $table_name)
    {
        $field_name = @pg_fieldname($resource, $num_field);

        $result = pg_exec($this->connection, "SELECT f.attnotnull, f.atthasdef
                            FROM pg_attribute f, pg_class tab, pg_type typ
                            WHERE tab.relname = typ.typname
                            AND typ.typrelid = f.attrelid
                            AND f.attname = '$field_name'
                            AND tab.relname = '$table_name'");
        if (pg_numrows($result) > 0) {
            $row = pg_fetch_row($result, 0);
            $flags  = ($row[0] == 't') ? 'not_null ' : '';

            if ($row[1] == 't') {
                $result = pg_exec($this->connection, "SELECT a.adsrc
                                FROM pg_attribute f, pg_class tab, pg_type typ, pg_attrdef a
                                WHERE tab.relname = typ.typname AND typ.typrelid = f.attrelid
                                AND f.attrelid = a.adrelid AND f.attname = '$field_name'
                                AND tab.relname = '$table_name'");
                $row = pg_fetch_row($result, 0);
                $num = str_replace('\'', '', $row[0]);

                $flags .= "default_$num ";
            }
        }
        $result = pg_exec($this->connection, "SELECT i.indisunique, i.indisprimary, i.indkey
                            FROM pg_attribute f, pg_class tab, pg_type typ, pg_index i
                            WHERE tab.relname = typ.typname
                            AND typ.typrelid = f.attrelid
                            AND f.attrelid = i.indrelid
                            AND f.attname = '$field_name'
                            AND tab.relname = '$table_name'");
        $count = pg_numrows($result);

        for ($i = 0; $i < $count ; $i++) {
            $row = pg_fetch_row($result, $i);
            $keys = explode(" ", $row[2]);

            if (in_array($num_field + 1, $keys)) {
                $flags .= ($row[0] == 't') ? 'unique ' : '';
                $flags .= ($row[1] == 't') ? 'primary ' : '';
                if (count($keys) > 1)
                    $flags .= 'multiple_key ';
            }
        }

        return trim($flags);
    }

}
}
?>