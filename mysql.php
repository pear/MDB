<?php
/*
 * mysql.php
 *
 * @(#) $Header$
 *
 */

 if (!defined("MDB_MYSQL_INCLUDED")) {
    define("MDB_MYSQL_INCLUDED", 1);

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
    var $sequence_prefix = "_sequence_";
    var $dummy_primary_key = "dummy_primary_key";
    var $manager_class_name = "MDB_manager_mysql_class";
    var $manager_include = "manager_mysql.php";
    var $manager_included_constant = "MDB_MANAGER_MYSQL_INCLUDED"; 

    // }}}
    // {{{ constructor
    /**
    * Constructor
    */
    function MDB_mysql()
    {
        $this->MDB_common();
        $this->phptype = 'mysql';
        $this->dbsyntax = 'mysql';
        
        $this->supported["Sequences"] = 1;
        $this->supported["Indexes"] = 1;
        $this->supported["AffectedRows"] = 1;
        $this->supported["Summaryfunctions"] = 1;
        $this->supported["OrderByText"] = 1;
        $this->supported["currId"] = 1;
        $this->supported["SelectRowRanges"] = 1;
        $this->supported["LOBs"] = 1;
        $this->supported["Replace"] = 1;

        if (isset($this->options["UseTransactions"])
            && $this->options["UseTransactions"])
        {
            $this->supported["Transactions"] = 1;
        }
        if (isset($this->options["UseSubSelects"])
            && $this->options["UseSubSelects"])
        {
            $this->supported["SubSelects"] = 1;
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
    // {{{ errorNative()

    /**
     * Get the native error code of the last error (if any) that
     * occured on the current connection.
     *
     * @access public
     *
     * @return int native MySQL error code
     */

    function errorNative()
    {
        return mysql_errno($this->connection);
    }
    
    function mysqlRaiseError($errno = NULL)
    {
        if ($errno == NULL) {
            $errno = $this->errorCode(mysql_errno($this->connection));
        }
        return $this->raiseError($errno, NULL, NULL, NULL, @mysql_error($this->connection));
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically committed.
     * This function may also implicitly start or end a transaction.
     * 
     * @param boolean $auto_commit    flag that indicates whether the database changes should
     *                                 be committed right after executing every query statement.
     *                                 If this argument is 0 a transaction implicitly started.
     *                                 Otherwise, if a transaction is in progress it is ended by
     *                                 committing any database changes that were pending.
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function autoCommit($auto_commit)
    {
        $this->debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        if (!isset($this->supported["Transactions"])) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                'Auto-commit transactions: transactions are not in use');
        }
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return (DB_OK);
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('COMMIT');
                if (MDB::isError($result)) {
                    return $result;
                }
                $result = $this->query('SET AUTOCOMMIT = 1');
                if (MDB::isError($result)) {
                    return $result;
                }
            } else {
                $result = $this->query('SET AUTOCOMMIT = 0');
                if (MDB::isError($result)) {
                    return $result;
                }
            }
        }
        $this->auto_commit = $auto_commit;
        return ($this->registerTransactionShutdown($auto_commit));
    }

    // }}}
    // {{{ commit()

    /**
     * Commit the database changes done during a transaction that is in progress.
     * This function may only be called when auto-committing is disabled, otherwise
     * it will fail. Therefore, a new transaction is implicitly started after
     * committing the pending changes.
     * 
     * @access public
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function commit()
    {
        $this->debug("Commit Transaction");
        if (!isset($this->supported["Transactions"])) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                'Commit transactions: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(DB_ERROR, "", "", 
                'Commit transactions: transaction changes are being auto commited');
        }
        return ($this->query("COMMIT"));
    }

    // }}}
    // {{{ rollback()

    /**
     * Cancel any database changes done during a transaction that is in progress.
     * This function may only be called when auto-committing is disabled, otherwise
     * it will fail. Therefore, a new transaction is implicitly started after
     * canceling the pending changes.
     * 
     * @access public
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function rollback()
    {
        $this->debug("Rollback Transaction");
        if (!isset($this->supported["Transactions"])) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                'Rollback transactions: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(DB_ERROR, "", "", 
                'Rollback transactions: transactions can not be rolled back when changes are auto commited');
        }
        return ($this->query("ROLLBACK"));
    }

    function connect()
    {
        $port = (isset($this->options["port"]) ? $this->options["port"] : "");
        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_user, $this->user)
                && !strcmp($this->connected_password, $this->password)
                && !strcmp($this->connected_port, $port)
                && $this->opened_persistent == $this->persistent)
            {
                return (DB_OK);
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
        
        @ini_set('track_errors', TRUE);
        $this->connection = @$function(
            $this->host.(!strcmp($port,"") ? "" : ":".$port), 
            $this->user, $this->password);
        @ini_restore('track_errors');
        if ($this->connection <= 0) {
            return ($this->raiseError(DB_ERROR_CONNECT_FAILED, '', '', $php_errormsg));
        }
        
        if (isset($this->options["fixedfloat"])) {
            $this->fixed_float = $this->options["fixedfloat"];
        } else {
            if (($result = mysql_query("SELECT VERSION()", $this->connection))) {
                $version = explode(".",mysql_result($result,0,0));
                $major = intval($version[0]);
                $minor = intval($version[1]);
                $revision = intval($version[2]);
                if ($major > 3 || ($major == 3 && $minor >= 23
                    && ($minor > 23 || $revision >= 6)))
                {
                    $this->fixed_float = 0;
                }
                mysql_free_result($result);
            }
        }
        if (isset($this->supported["Transactions"]) && !$this->auto_commit)    {
            if (!mysql_query("SET AUTOCOMMIT = 0", $this->connection)) {
                mysql_close($this->connection);
                $this->connection = 0;
                $this->affected_rows = -1;
                return (0);
            }
            $this->registerTransactionShutdown(0);
        }
        $this->connected_host = $this->host;
        $this->connected_user = $this->user;
        $this->connected_password = $this->password;
        $this->connected_port = $port;
        $this->opened_persistent = $this->persistent;
        return (DB_OK);
    }

    // }}}
    // {{{ close()

    /**
     * all the RDBMS specific things needed close a DB connection
     * 
     * @access privat
     *
     */
    function close()
    {
        if ($this->connection != 0) {
            if (isset($this->supported["Transactions"]) && !$this->auto_commit) {
                $result = $this->autoCommit(TRUE);
            }
            mysql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            
            if (isset($result) && MDB::isError($result)) {
                return $result;
            }
            global $databases;
            $databases[$database] = "";
            return TRUE;
        }
        return FALSE;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results with a
     * DB_result object.
     *
     * @access public
     *
     * @param string $query  the SQL query
     * @param array    $types array that contains the types of the columns in the result set
     * 
     * @return mixed a result handle or DB_OK on success, a DB error on failure
     */
    function query($query, $types = NULL)
    {
        $this->last_query = $query;
        
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (!strcmp($this->database_name, "")) {
            return $this->raiseError(DB_ERROR_NODBSELECTED);
        }
        
        $result = $this->connect();
        if (MDB::isError($result)) {
            return $result;
        }
        
        if (gettype($space = strpos($query_string = strtolower(ltrim($query)), " ")) == "integer") {
            $query_string = substr($query_string,0,$space);
        }
        if (($select = ($query_string == "select" || $query_string == "show")) && $limit > 0) {
            $query.=" LIMIT $first,$limit";
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
            return $this->mysqlRaiseError(DB_ERROR);
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

    // }}}
    // {{{ subSelect()

    /**
     * simple subselect emulation for Mysql
     *
     * @access public
     *
     * @param string $query the SQL query for the subselect that may only return a column
     * @param string $quote    determines if the data needs to be quoted before being returned
     * 
     * @return string the query
     */
    function subSelect($query, $quote = FALSE)
    {
        if($this->supported["SubSelects"] == 1) {
            return($query);
        }
        $result = $this->queryCol($query, $col);
        if (MDB::isError($result)) {
            return $result;
        }
        if(!is_array($col) || count($col) == 0) {
            return "NULL";
        }
        if($quote) {
            for($i = 0, $j = count($col); $i < $j; ++$i) {
                $col[$i] = $this->getTextFieldValue($col[$i]);
            }
        }
        return(implode(', ', $col));
    }

    // }}}
    // {{{ replace()

    /**
     * Execute a SQL REPLACE query. A REPLACE query is identical to a INSERT query, except
     * that if there is already a row in the table with the same key field values, the
     * REPLACE query just updates its values instead of inserting a new row.
     *
     * The REPLACE type of query does not make part of the SQL standards. Since pratically
     * only MySQL implements it natively, this type of query is emulated through this
     * method for other DBMS using standard types of queries inside a transaction to
     * assure the atomicity of the operation.
     *
     * @access public
     *
     * @param string $table name of the table on which the REPLACE query will be executed.
     * @param array $fields associative array that describes the fields and the values
     *                         that will be inserted or updated in the specified table. The
     *                         indexes of the array are the names of all the fields of the
     *                         table. The values of the array are also associative arrays that
     *                         describe the values and other properties of the table fields.
      *
     *                         Here follows a list of field properties that need to be specified:
     *                        
     *                        Value
     *                        Value to be assigned to the specified field. This value may be of specified in database independent type format as this function can perform the necessary datatype conversions.
     *                        
     *                        Default: this property is required unless the Null property is set to 1.
     *                        
     *                        Type
     *                        Name of the type of the field. Currently, all types Metabase are supported except for clob and blob.
     *                        
     *                        Default: text
     *                        
     *                        Null
     *                        Boolean property that indicates that the value for this field should be set to NULL.
     *                        
     *                        The default value for fields missing in INSERT queries may be specified the definition of a table. Often, the default value is already NULL, but since the REPLACE may be emulated using an UPDATE query, make sure that all fields of the table are listed in this function argument array.
     *                        
     *                        Default: 0
     *                        
     *                        Key
     *                        Boolean property that indicates that this field should be handled as a primary key or at least as part of the compound unique index of the table that will determine the row that will updated if it exists or inserted a new row otherwise.
     *                        
     *                        This function will fail if no key field is specified or if the value of a key field is set to NULL because fields that are part of unique index they may not be NULL.
     *                        
     *                        Default: 0
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
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
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'no value for field "'.$name.'" specified');
                }
                switch(isset($fields[$name]["Type"]) ? $fields[$name]["Type"] : "text") {
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
            $values .= $value;
            if (isset($fields[$name]["Key"]) && $fields[$name]["Key"]) {
                if ($value == "NULL") {
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'key values may not be NULL');
                }
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                'not specified which fields are keys');
        }
        return ($this->query("REPLACE INTO $table ($query) VALUES ($values)"));
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     * 
     * @param resource    $result        result identifier
     * @param array        $columns    reference to an associative array variable that will hold the
     *                                 names of columns. The indexes of the array are the column names
     *                                 mapped to lower case and the values are the respective numbers
     *                                 of the columns starting from 0. Some DBMS may not return any
     *                                 columns when the result set does not contain any rows.
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function getColumnNames($result, &$column_names)
    {
        $result_value = intval($result);
        if (!isset($this->highest_fetched_row[$result_value])) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Get column names: it was specified an inexisting result set');
        }
        if (!isset($this->columns[$result_value])) {
            $this->columns[$result_value] = array();
            $columns = mysql_num_fields($result);
            for($column = 0;$column<$columns;$column++) {
                $this->columns[$result_value][strtolower(mysql_field_name($result, $column))] = $column;
            }
        }
        $column_names = $this->columns[$result_value];
        return (DB_OK);
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     * 
     * @param resource    $result        result identifier
     *  
     * @access public
     *
     * @return mixed integer value with the number of columns, a DB error on failure
     */ 
    function numCols($result)
    {
        if (!isset($this->highest_fetched_row[intval($result)])) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'numCols: it was specified an inexisting result set');
        }
        return (mysql_num_fields($result));
    }

    // }}}
    // {{{ endOfResult()
    /**
    * check if the end of the result set has been reached
    * 
    * @param resource    $result result identifier
    *
    * @return mixed TRUE or FALSE on sucess, a DB error on failure
    *
    * @access public
    */
    function endOfResult($result)
    {
        if (!isset($this->highest_fetched_row[$result])) {
            return $this->raiseError(DB_ERROR, "", "", 
                'End of result: attempted to check the end of an unknown result');
        }
        return ($this->highest_fetched_row[$result] >= $this->numRows($result)-1);
    }

    // }}}
    // {{{ fetch()
    /**
    * fetch value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed string on success, a DB error on failure
    *
    * @access public
    */
    function fetch($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        $res = mysql_result($result, $row, $field);
        if (!$res && $res != NULL) {
            $errno = @mysql_errno($this->connection);
            if($errno) {
                return $this->mysqlRaiseError($errno);
            }
        }
        return ($res);
    }

    // }}}
    // {{{ fetchClobResult()
    /**
    * fetch a clob value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure, a DB error on failure
    *
    * @access public
    */
    function fetchCLobResult($result, $row, $field)
    {
        return ($this->fetchLobResult($result, $row, $field));
    }

    // }}}
    // {{{ fetchBlobResult()
    /**
    * fetch a blob value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchBLobResult($result, $row, $field)
    {
        return ($this->fetchLobResult($result, $row, $field));
    }

    // }}}
    // {{{ convertResult()
    /**
    * convert a value to a RDBMS indepdenant MDB type
    * 
    * @param mixed    $value    refernce to a value to be converted
    * @param int    $type    constant that specifies which type to convert to
    *
    * @return object a DB error on failure
    *
    * @access public
    */
    function convertResult(&$value, $type)
    {
        switch($type) {
            case MDB_TYPE_BOOLEAN:
                $value = (strcmp($value, "Y") ? 0 : 1);
                return (DB_OK);
            case MDB_TYPE_DECIMAL:
                $value = sprintf("%.".$this->decimal_places."f",doubleval($value)/$this->decimal_factor);
                return (DB_OK);
            case MDB_TYPE_FLOAT:
                $value = doubleval($value);
                return (DB_OK);
            case MDB_TYPE_DATE:
            case MDB_TYPE_TIME:
            case MDB_TYPE_TIMESTAMP:
                return (DB_OK);
            default:
                return ($this->baseConvertResult($value, $type));
        }
    }

    // }}}
    // {{{ numRows()
    /**
    * returns the number of rows in a result object
    * renamed for PEAR
    * used to be: NumberOfColumns
    *
    * @param object DB_Result the result object to check
    *
    * @return mixed DB_Error or the number of rows
    *
    * @access public
    */
    function numRows($result)
    {
        return (mysql_num_rows($result));
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param $result result identifier
     *
     * @access public
     *
     * @return bool TRUE on success, FALSE if $result is invalid
     */
    function freeResult($result)
    {
        if(isset($this->highest_fetched_row[$result])) {
            unset($this->highest_fetched_row[$result]);
        }
        if(isset($this->columns[$result])) {
            unset($this->columns[$result]);
        }
        if(isset($this->result_types[$result])) {
            unset($this->result_types[$result]);
        }
        return (mysql_free_result($result));
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    unsigned
     *
     *                                        Boolean flag that indicates whether the field should
     *                                        be declared as unsigned integer if possible.
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getIntegerDeclaration($name, &$field)
    {
        return ("$name ".(isset($field["unsigned"]) ? "INT UNSIGNED" : "INT").(isset($field["default"]) ? " DEFAULT ".$field["default"] : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getCLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character large object type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    length
     *
     *                                        Integer value that determines the maximum length of
     *                                         the large object field. If this argument is missing
     *                                         the field should be declared to have the longest length
     *                                         allowed by the DBMS.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getClobDeclaration($name, &$field)
    {
        if (isset($field["length"])) {
            $length = $field["length"];
            if ($length <= 255) {
                $type = "TINYTEXT";
            } else {
                if ($length <= 65535) {
                    $type = "TEXT";
                } else {
                    if ($length <= 16777215) {
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

    // }}}
    // {{{ getBLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large object type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    length
     *
     *                                        Integer value that determines the maximum length of
     *                                         the large object field. If this argument is missing
     *                                         the field should be declared to have the longest length
     *                                         allowed by the DBMS.
     * 
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getBLobDeclaration($name, &$field)
    {
        if (isset($field["length"])) {
            $length = $field["length"];
            if ($length <= 255) {
                $type = "TINYBLOB";
            } else {
                if ($length <= 65535) {
                    $type = "BLOB";
                } else {
                    if ($length <= 16777215) {
                        $type = "MEDIUMBLOB";
                    } else {
                        $type = "LONGBLOB";
                    }
                }
            }
        }
        else {
            $type = "LONGBLOB";
        }
        return ("$name $type".(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an date type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Date value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getDateDeclaration($name, &$field)
    {
        return ($name." DATE".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an timestamp type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Time stamp value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getTimestampDeclaration($name, &$field)
    {
        return ($name." DATETIME".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an time type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Time value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getTimeDeclaration($name, &$field)
    {
        return ($name." TIME".(isset($field["default"]) ? " DEFAULT '".$field["default"]."'" : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an float type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getFloatDeclaration($name, &$field)
    {
        if (isset($this->options["FixedFloat"])) {
            $this->fixed_float = $this->options["FixedFloat"];
        } else {
            if ($this->connection == 0) {
                // XXX needs more checking
                $this->connect();
            }
        }
        return ("$name DOUBLE".($this->fixed_float ? "(".($this->fixed_float + 2).",".$this->fixed_float.")" : "").(isset($field["default"]) ? " DEFAULT ".$this->getFloatFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an decimal type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getDecimalDeclaration($name, &$field)
    {
        return ("$name BIGINT".(isset($field["default"]) ? " DEFAULT ".$this->getDecimalFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getCLOBFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */    
    function getClobFieldValue($prepared_query, $parameter, $clob, &$value)
    {
        for($value = "'";!endOfLob($clob);) {
            if (readLob($clob, $data, $this->lob_buffer_length) < 0) {
                $value = "";
                return $this->raiseError(DB_ERROR, "", "", 
                    'Get CLOB field value: '.LobError($clob));
            }
            $this->quote($data);
            $value .= $data;
        }
        $value .= "'";
        return (DB_OK);
    }

    // }}}
    // {{{ freeCLOBValue()

    /**
     * free a chracter large object
     * 
     * @param resource     $prepared_query query handle from prepare()
     * @param string    $blob             
     * @param string    $value             
     * @param string    $success         
     * 
     * @access privat
     */
    function freeClobValue($prepared_query, $clob, &$value, $success)
    {
        unset($value);
    }

    // }}}
    // {{{ getBLOBFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getBLobFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        for($value = "'";!endOfLob($blob);) {
            if (!readLob($blob, $data, $this->lob_buffer_length)) {
                $value = "";
                return $this->raiseError(DB_ERROR, "", "", 
                    'Get BLOB field value: '.LobError($blob));
            }
            $value .= addslashes($data);
        }
        $value .= "'";
        return (DB_OK);
    }

    // }}}
    // {{{ freeBLOBValue()

    /**
     * free a binary large object
     * 
     * @param resource     $prepared_query query handle from prepare()
     * @param string    $blob             
     * @param string    $value             
     * @param string    $success         
     * 
     * @access privat
     */
    function freeBLobValue($prepared_query, $blob, &$value, $success)
    {
        unset($value);
    }

    // }}}
    // {{{ getFloatFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getFloatFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    // }}}
    // {{{ getDecimalFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getDecimalFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : strval(round(doubleval($value)*$this->decimal_factor)));
    }

    // }}}
    // {{{ nextId()
    /**
    * returns the next free id of a sequence
    * renamed for PEAR
    * used to be: getSequenceNextValue
    *
    * @param string  $name     name of the sequence
    * @param boolean $ondemand when true the seqence is
    *                          automatic created, if it
    *                          not exists
    *
    * @return mixed DB_Error or id
    */
    function nextId($name, $ondemand = FALSE)
    {
        $sequence_name = $this->sequence_prefix.$name;
        $res = $this->query("INSERT INTO $sequence_name (sequence) VALUES (NULL)");
        if ($ondemand && DB::isError($result) &&
            $result->getCode() == DB_ERROR_NOSUCHTABLE)
        {
            $result = $this->createSequence($sequence_name);
            // Since createSequence initializes the ID to be 1,
            // we do not need to retrieve the ID again (or we will get 2)
            if (DB::isError($result)) {
                return $this->raiseError(DB_ERROR, "", "", 
                    'Next ID: on demand sequence could not be created');
            } else {
                // First ID of a newly created sequence is 1
                return 1;
            }
        }
        $value = intval(mysql_insert_id());
        $res = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB::isError($res)) {
            // XXX warning or error?
            // $this->warning = "could delete previous sequence table values";
            return $this->raiseError(DB_ERROR, "", "", 
                'Next ID: could not delete previous sequence table values');
        }
        return ($value);
    }
    

    // }}}
    // {{{ currId()
    /**
    * returns the current id of a sequence
    * renamed for PEAR
    * used to be: getSequenceCurrentValue
    *
    * @param string  $name     name of the sequence
    *
    * @return mixed DB_Error or id
    */
    function currId($name)
    {
        $sequence_name = $this->sequence_prefix.$name;
        $result = $this->Query("SELECT MAX(sequence) FROM $sequence_name");
        if (MDB::isError($result)) {
            return $result;
        }
        
        $value = intval($this->fetch($result,0,0));
        $this->freeResult($result);
        return ($value);
    }

    // }}}
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     * renamed for PEAR
     * used to be fetchResultArray
     *
     * @param resource    $result     result identifier
     * @param array        $array         reference to an array where data from the row is stored
     * @param int        $fetchmode     how the array data should be indexed
     * @param int        $rownum        the row number to fetch
     * @access public
     *
     * @return int DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $row = NULL)
    {
        if ($row !== NULL) {
            if (!@mysql_data_seek($result, $row)) {
                return NULL;
            }
            $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        } else {
            ++$this->highest_fetched_row[$result];
        }
        if ($fetchmode & DB_FETCHMODE_ASSOC) {
            $array = mysql_fetch_array($result, MYSQL_ASSOC);
        } else {
            $array = mysql_fetch_row($result);
        }
        if (!$array) {
            $errno = @mysql_errno($this->connection);
            if (!$errno) {
                if($this->autofree) {
                    $this->freeResult($result);
                }
                return NULL;
            }
            return $this->mysqlRaiseError($errno);
        }
        if (!$this->convertResultRow($result, $array)) {
            return $this->raiseError();
        }
        return DB_OK;
    }

    /**
     * Move the internal mysql result pointer to the next available result
     * Currently not supported
     *
     * @param a valid result resource
     *
     * @access public
     *
     * @return true if a result is available otherwise return false
     */
     
    function nextResult($result)
    {
        return FALSE;
    }

    // }}}
    // {{{ tableInfo()
    /**
    * returns meta data about the result set
    *
    * @param resource    $result    result identifier
    * @param mixed $mode depends on implementation
    *
    * @return array an nested array, or a DB error
    *
    * @access public
    */
    function tableInfo($result, $mode = NULL) {
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
}
}
?>