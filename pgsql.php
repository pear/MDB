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
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+

// $Id$

// MDB postgresql driver.

if (!defined("MDB_PGSQL_INCLUDED")) {
    define("MDB_PGSQL_INCLUDED", 1);

require_once (dirname(__FILE__) . "/common.php");

class MDB_driver_pgsql extends MDB_common {
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
    var $manager_class_name = "MDB_manager_pgsql_class";
    var $manager_include = "manager_pgsql.php";
    var $manager_included_constant = "MDB_MANAGER_PGSQL_INCLUDED"; 

    // }}} 
    // {{{ constructor
    /**
     * Constructor
     **/
    function MDB_driver_pgsql($dsninfo, $options)
    {
        if(MDB::isError($common_contructor = $this->MDB_common($dsninfo, $options))) {
            return $common_contructor;
        }
        $this->phptype = 'pgsql';
        $this->dbsyntax = 'pgsql';

        if (!function_exists("pg_connect")) {
            return ("PostgreSQL support is not available in this PHP configuration");
        }
        $this->supported["Sequences"] = 1;
        $this->supported["Indexes"] = 1;
        $this->supported["SummaryFunctions"] = 1;
        $this->supported["OrderByText"] = 1;
        $this->supported["Transactions"] = 1;
        $this->supported["CurrId"] = 1;
        $this->supported["SelectRowRanges"] = 1;
        $this->supported["LOBs"] = 1;
        $this->supported["Replace"] = 1;
        $this->supported["SubSelects"] = 1;
        
        $this->decimal_factor = pow(10.0, $this->options['decimal_places']);

        if (function_exists("pg_cmdTuples")) {
            $connection = $this->_doConnect("template1", 0);
            if (!MDB::isError($connection)) {
                if (($result = @pg_Exec($connection, "BEGIN"))) {
                    $error_reporting = error_reporting(63);
                    @pg_cmdTuples($result);
                    if (!isset($php_errormsg) || strcmp($php_errormsg, "This compilation does not support pg_cmdtuples()")) {
                        $this->supported["AffectedRows"] = 1;
                    }
                    error_reporting($error_reporting);
                } else {
                    $err = $this->raiseError(DB_ERROR, NULL, NULL, "Setup: ".pg_ErrorMessage($connection));
                }
                pg_Close($connection);
            } else {
                $err = $this->raiseError(DB_ERROR, NULL, NULL, "Setup: could not execute BEGIN");
            }
            if (MDB::isError($err)) {
                return ($err);
            }
        }
    }

    // }}}
    // {{{ errorCode()

    /**
     * Map native error codes to DB's portable ones.  Requires that
     * the DB implementation's constructor fills in the $errorcode_map
     * property.
     *
     * @param $nativecode the native error code, as returned by the backend
     * database extension (string or integer)
     *
     * @return int a portable DB error code, or FALSE if this DB
     * implementation has no mapping for the given error code.
     */

    function errorCode($errormsg)
    {
        static $error_regexps;
        if (empty($error_regexps)) {
            $error_regexps = array(
                '/(Table does not exist\.|Relation [\"\'].*[\"\'] does not exist|sequence does not exist|class ".+" not found)$/' => DB_ERROR_NOSUCHTABLE,
                '/Relation [\"\'].*[\"\'] already exists|Cannot insert a duplicate key into (a )?unique index.*/'      => DB_ERROR_ALREADY_EXISTS,
                '/divide by zero$/'                     => DB_ERROR_DIVZERO,
                '/pg_atoi: error in .*: can\'t parse /' => DB_ERROR_INVALID_NUMBER,
                '/ttribute [\"\'].*[\"\'] not found$|Relation [\"\'].*[\"\'] does not have attribute [\"\'].*[\"\']/' => DB_ERROR_NOSUCHFIELD,
                '/parser: parse error at or near \"/'   => DB_ERROR_SYNTAX,
                '/referential integrity violation/'     => DB_ERROR_CONSTRAINT
            );
        }
        foreach ($error_regexps as $regexp => $code) {
            if (preg_match($regexp, $errormsg)) {
                return $code;
            }
        }
        // Fall back to DB_ERROR if there was no mapping.
        return DB_ERROR;
    }

    // }}} 
    // {{{ pgsqlRaiseError()
    function pgsqlRaiseError($errno = NULL)
    {
        $native = $this->errorNative();
        if ($errno === NULL) {
            $err = $this->errorCode($native);
        } else {
            $err = $errno;
        }
        return $this->raiseError($err, NULL, NULL, NULL, $native);
    }

    // }}}
    // {{{ autoCommit()
    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     *
     * @param boolean $auto_commit flag that indicates whether the database
     *     changes should be committed right after executing every query
     *     statement. If this argument is 0 a transaction implicitly started.
     *     Otherwise, if a transaction is in progress it is ended by committing
     *     any database changes that were pending.
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function autoCommit($auto_commit)
    {
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return (DB_OK);
        }
        if ($this->connection) {
            if (MDB::isError($result = $this->_doQuery($auto_commit ? "END" : "BEGIN")))
                return ($result);
        }
        $this->auto_commit = $auto_commit;
        return ($this->registerTransactionShutdown($auto_commit));
    }

    // }}}
    // {{{ commit()
    /**
     * Commit the database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after committing the pending changes.
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function commit()
    {
        if ($this->auto_commit) {
            return ($this->raiseError(DB_ERROR, '', '', "Commit: transaction changes are being auto commited"));
        }
        return ($this->_doQuery("COMMIT") && $this->_doQuery("BEGIN"));
    }

    // }}}
    // {{{ rollback()
    /**
     * Cancel any database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after canceling the pending changes.
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function rollback()
    {
        if ($this->auto_commit) {
            return ($this->raiseError(DB_ERROR, '', '', "Rollback: transactions can not be rolled back when changes are auto commited"));
        }
        return ($this->_doQuery("ROLLBACK") && $this->_doQuery("BEGIN"));
    }

    // }}} 
    // {{{ _doConnect
    /**
     * Does the grunt work of connecting to the database
     *
     * @access private 
     * @return mixed connection resource on success, MDB_Error on failure
     **/
    function _doConnect($database_name, $persistent)
    {
        $function = ($persistent ? "pg_pconnect" : "pg_connect");
        if (!function_exists($function)) {
            return ($this->raiseError(DB_ERROR_UNSUPPORTED, NULL, NULL, "doConnect: PostgreSQL support is not available in this PHP configuration"));
        }
        $port = (isset($this->options["port"]) ? $this->options["port"] : "");
        $connect_string = "dbname=".$database_name;
        if ($this->host != "") {
            $connect_string .= " host=".$this->host;
        }
        if ($port != "") {
            $connect_string .= " port=".strval($port);
        }
        if ($this->user != "") {
            $connect_string .= " user=".$this->user;
        }
        if ($this->password != "") {
            $connect_string .= " password=".$this->password;
        }
        if (($connection = @$function($connect_string)) > 0) {
            return ($connection);
        }
        return ($this->raiseError(DB_ERROR_CONNECT_FAILED, '', '', "doConnect: " . isset($php_errormsg) ? $php_errormsg : "Could not connect to PostgreSQL server"));
    }

    // }}} 
    // {{{ connect()
    /**
     * Connect to the database
     *
     * @return TRUE on success, MDB_Error on failure
     **/
    function connect()
    {
        $port = (isset($this->options["port"]) ? $this->options["port"] : "");
        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_port, $port)
                && !strcmp($this->selected_database, $this->database_name)
                && ($this->opened_persistent == $this->options['persistent']))
            {
                return (DB_OK);
            }
            pg_Close($this->connection);
            $this->affected_rows = -1;
            $this->connection = 0;
        }
        $this->connection = $this->_doConnect($this->database_name, $this->options['persistent']);
        if (MDB::isError($this->connection)) {
            return $this->connection;
        }

        if (!$this->auto_commit && MDB::isError($trans_result = $this->_doQuery("BEGIN"))) {
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $trans_result;
        }
        $this->connected_host = $this->host;
        $this->connected_port = $port;
        $this->selected_database = $this->database_name;
        $this->opened_persistent = $this->options['persistent'];
        return (DB_OK);
    }

    // }}} 
    // {{{ close()
    /**
     * Close the database connection
     **/
    function close()
    {
        if ($this->connection != 0) {
            if (!$this->auto_commit) {
                $this->_doQuery("END");
            }
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }
    }

    // }}} 
    // {{{ _doQuery()
    /**
     * Execute a query
     * @param string $query the SQL query
     *
     * @return mixed result identifier if query executed, else MDB_error
     * @access private 
     **/
    function _doQuery($query)
    {
        if (($result = @pg_Exec($this->connection, $query))) {
            $this->affected_rows = (isset($this->supported["AffectedRows"]) ? pg_cmdTuples($result) : -1);
        } else {
            $error = pg_ErrorMessage($this->connection);
            return $this->raiseError(NULL, NULL, NULL, "query: $query:" . $error, $error);
        }
        return ($result);
    }

    // }}} 
    // {{{ query()
    /**
     * Execute a query
     * @param string $query the SQL query
     * @param array $types array that contains the types of the columns in
     *                         the result set
     * @return mixed result identifier if query executed, else MDB_error
     * @access public 
     **/
    function query($query, $types = NULL)
    {
        $ismanip = MDB::isManip($query);
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        $connected = $this->connect();
        if (MDB::isError($connected)) {
            return $connected;
        }

        if (!$ismanip && $limit > 0 &&
            substr(strtolower(ltrim($query)), 0, 6) == "select")
        {
            if ($this->auto_commit && MDB::isError($this->_doQuery("BEGIN"))) {
                return $this->raiseError(DB_ERROR);
            }
            $result = $this->_doQuery("DECLARE select_cursor SCROLL CURSOR FOR $query");
            if (!MDB::isError($result)) {
                if ($first > 0 && MDB::isError($result = $this->_doQuery("MOVE FORWARD $first FROM select_cursor"))) {
                    $this->freeResult($result);
                    return $result;
                }
                if (MDB::isError($result = $this->_doQuery("FETCH FORWARD $limit FROM select_cursor"))) {
                    $this->freeResult($result);
                    return $result;
                }
            } else {
                return $result;
            }
            if ($this->auto_commit && MDB::isError($result2 = $this->_doQuery("END"))) {
                $this->freeResult($result);
                return $result2;
            }
        } else {
            $result = $this->_doQuery($query);
            if (MDB::isError($result)) {
                return $result;
            }
        }

        if ($ismanip) {
            $this->affected_rows = @pg_cmdtuples($result);
            return DB_OK;
        } elseif  (preg_match('/^\s*\(?\s*SELECT\s+/si', $query) && !preg_match('/^\s*\(?\s*SELECT\s+INTO\s/si', $query)) {
            $this->highest_fetched_row[$result] = -1;
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
        } else {
            $this->affected_rows = 0;
            return DB_OK;
        }
        return $this->raiseError(DB_ERROR);
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
            return $this->RaiseError(DB_ERROR, '', '', "End of result attempted to check the end of an unknown result");
        }
        return ($this->highest_fetched_row[$result] >= $this->numRows($result) - 1);
    }


    // }}}
    // {{{ retrieveLob()
    /**
     * fetch a float value from a result set
     *
     * @param int $lob handle to a lob created by the createLob() function
     *
     * @return mixed DB_Ok on success, a DB error on failure
     *
     * @access public
     */
    function retrieveLob($lob)
    {
        if (!isset($this->lobs[$lob])) {
            return ($this->raiseError(DB_ERROR_INVALID, NULL, NULL, "Retrieve LOB: did not specified a valid lob"));
        }
        if (!isset($this->lobs[$lob]["Value"])) {
            if ($this->auto_commit) {
                if (!@pg_exec($this->connection, "BEGIN")) {
                    return ($this->raiseError(DB_ERROR,  NULL, NULL, "Retrieve LOB: " . pg_ErrorMessage($this->connection)));
                }
                $this->lobs[$lob]["InTransaction"] = 1;
            }
            $this->lobs[$lob]["Value"] = $this->fetch($this->lobs[$lob]["Result"], $this->lobs[$lob]["Row"], $this->lobs[$lob]["Field"]);
            if (!($this->lobs[$lob]["Handle"] = @pg_loopen($this->connection, $this->lobs[$lob]["Value"], "r"))) {
                if (isset($this->lobs[$lob]["InTransaction"])) {
                    @pg_Exec($this->connection, "END");
                    unSet($this->lobs[$lob]["InTransaction"]);
                }
                unset($this->lobs[$lob]["Value"]);
                return ($this->raiseError(DB_ERROR, NULL, NULL, "Retrieve LOB: " . pg_ErrorMessage($this->connection)));
            }
        }
        return (DB_OK);
    }

    // }}}
    // {{{ endOfResultLob()
    /**
     * Determine whether it was reached the end of the large object and
     * therefore there is no more data to be read for the its input stream.
     *
     * @param int    $lob handle to a lob created by the createLob() function
     *
     * @return mixed TRUE or FALSE on success, a DB error on failure
     *
     * @access public
     */
    function endOfResultLob($lob)
    {
        $lobresult = $this->retrieveLob($lob);
        if (MDB::isError($lobresult)) {
            return ($lobresult);
        }
        return (isset($this->lobs[$lob]["EndOfLOB"]));
    }

    // }}}
    // {{{ readResultLob()
    /**
     * Read data from large object input stream.
     *
     * @param int $lob handle to a lob created by the createLob() function
     * @param blob $data reference to a variable that will hold data to be
     *      read from the large object input stream
     * @param int $length integer value that indicates the largest ammount of
     *      data to be read from the large object input stream.
     *
     * @return mixed length on success, a DB error on failure
     *
     * @access public
     */
    function readResultLob($lob, &$data, $length)
    {
        $lobresult = $this->retrieveLob($lob);
        if (MDB::isError($lobresult)) {
            return ($lobresult);
        }
        $data = pg_loread($this->lobs[$lob]["Handle"], $length);
        if (GetType($data) != "string") {
            $this->raiseError(DB_ERROR, NULL, NULL, "Read Result LOB: " . pg_ErrorMessage($this->connection));
        }
        if (($length = strlen($data)) == 0) {
            $this->lobs[$lob]["EndOfLOB"] = 1;
        }
        return ($length);
    }

    // }}}
    // {{{ destroyResultLob()
    /**
     * Free any resources allocated during the lifetime of the large object
     * handler object.
     *
     * @param int $lob handle to a lob created by the createLob() function
     *
     * @access public
     */
    function destroyResultLob($lob)
    {
        if (isset($this->lobs[$lob])) {
            if (isset($this->lobs[$lob]["Value"])) {
                pg_loclose($this->lobs[$lob]["Handle"]);
                if (isset($this->lobs[$lob]["InTransaction"])) {
                    @pg_Exec($this->connection, "END");
                }
            }
            $this->lobs[$lob] = "";
        }
    }

    // }}}
    // {{{ fetchClob()
    /**
     * fetch a clob value from a result set
     *
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     *
     * @return mixed content of the specified data cell, a DB error on failure,
     *       a DB error on failure
     *
     * @access public
     */
    function fetchClob($result, $row, $field)
    {
        return ($this->fetchLob($result, $row, $field));
    }

    // }}}
    // {{{ fetchBlob()
    /**
     * fetch a blob value from a result set
     *
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     *
     * @return mixed content of the specified data cell, a DB error on failure
     *
     * @access public
     */
    function fetchBlob($result, $row, $field)
    {
        return ($this->fetchLob($result, $row, $field));
    }

    // }}}
    // {{{ resultIsNull()
    /**
     * Determine whether the value of a query result located in given row and
     *   field is a NULL.
     *
     * @param resource    $result result identifier
     * @param int    $row    number of the row where the data can be found
     * @param int    $field    field number where the data can be found
     *
     * @return mixed TRUE or FALSE on success, a DB error on failure
     *
     * @access public
     */
    function resultIsNull($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        return (@pg_FieldIsNull($result, $row, $field));
    }

    // }}}
    // {{{ numRows()
    /**
     * returns the number of rows in a result object
     *
     * @param object DB_Result the result object to check
     *
     * @return mixed DB_Error or the number of rows
     *
     * @access public
     */
    function numRows($result)
    {
        return (pg_numrows($result));
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
        UnSet($this->highest_fetched_row[$result]);
        UnSet($this->columns[$result]);
        UnSet($this->result_types[$result]);
        return (pg_freeresult($result));
    }

    // }}}
    // {{{ _standaloneQuery()
    /**
     * 
     *
     * @param string $query
     *
     * @access private
     */
    function _standaloneQuery($query)
    {
        if (($connection = $this->_doConnect("template1", 0)) == 0) {
            return $this->raiseError(DB_ERROR_CONNECT_FAILED, NULL, NULL, '_standaloneQuery: Cannot connect to template1');
        }
        if (!($success = @pg_Exec($connection, $query))) {
            $this->raiseError(DB_ERROR, NULL, NULL, "_standaloneQuery: " . pg_ErrorMessage($connection));
        }
        pg_Close($connection);
        return ($success);
    }

    // }}}
    // {{{ getTextDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getTextDeclaration($name, &$field)
    {
        return ((isset($field["length"]) ? "$name VARCHAR (" . $field["length"] . ")" : "$name TEXT") . (isset($field["default"]) ? " DEFAULT '" . $field["default"] . "'" : "") . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getClobDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the large
     *          object field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getClobDeclaration($name, &$field)
    {
        return ("$name OID" . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getBlobDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the large
     *          object field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getBlobDeclaration($name, &$field)
    {
        return ("$name OID" . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDateDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare a date type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Date value to be used as default for this field.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getDateDeclaration($name, &$field)
    {
        return ($name . " DATE" . (isset($field["default"]) ? " DEFAULT '" . $field["default"] . "'" : "") . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimeDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare a time
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Time value to be used as default for this field.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getTimeDeclaration($name, &$field)
    {
        return ($name . " TIME" . (isset($field["default"]) ? " DEFAULT '" . $field["default"] . "'" : "") . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getFloatDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare a float type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Float value to be used as default for this field.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getFloatDeclaration($name, &$field)
    {
        return ("$name FLOAT8 " . (isset($field["default"]) ? " DEFAULT " . $this->getFloatValue($field["default"]) : "") . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDecimalDeclaration()
    /**
     * Obtain DBMS specific SQL code portion needed to declare a decimal type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Decimal value to be used as default for this field.
     *
     *      notNULL
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to NULL.
     *
     * @access public
     *
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     */
    function getDecimalDeclaration($name, &$field)
    {
        return ("$name INT8 " . (isset($field["default"]) ? " DEFAULT " . $this->getDecimalValue($field["default"]) : "") . (isset($field["notNULL"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getLobValue()
    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $lob
     *
     * @access public
     *
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     */
    function getLobValue($prepared_query, $parameter, $lob)
    {
        $connect = $this->connect();
        if (MDB::isError($connect)) {
            return $connect;
        }
        if ($this->auto_commit && !@pg_Exec($this->connection, "BEGIN")) {
            return $this->raiseError(DB_ERROR, NULL, NULL, 'getLobValue: error starting transaction');
        }
        $success = 1;
        if (($lo = pg_locreate($this->connection))) {
            if (($handle = pg_loopen($this->connection, $lo, "w"))) {
                while (!endOfLob($lob)) {
                    if (readLob($lob, $data, $this->options['lob_buffer_length']) < 0) {
                        $this->raiseError(DB_ERROR, NULL, NULL, "Get LOB field value: " . lobError($lob));
                        $success = 0;
                        break;
                    }
                    if (!pg_lowrite($handle, $data)) {
                        $this->raiseError(DB_ERROR, NULL, NULL, "Get LOB field value: " . pg_ErrorMessage($this->connection));
                        $success = 0;
                        break;
                    }
                }
                pg_loclose($handle);
                if ($success) {
                    $value = strval($lo);
                }
            } else {
                $this->raiseError(DB_ERROR, NULL, NULL, "Get LOB field value: " .  pg_ErrorMessage($this->connection));
                $success = 0;
            }
            if (!$success) {
                pg_lounlink($this->connection, $lo);
            }
        } else {
            $this->raiseError(DB_ERROR, NULL, NULL, "Get LOB field value: " . pg_ErrorMessage($this->connection));
            $success = 0;
        }
        if ($this->auto_commit) {
            @pg_Exec($this->connection, "END");
        }
        return ($value);
    }

    // }}}
    // {{{ getClobValue()
    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $clob
     *
     * @access public
     *
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     */
    function getClobValue($prepared_query, $parameter, $clob)
    {
        return ($this->getLobValue($prepared_query, $parameter, $clob));
    }

    // }}}
    // {{{ freeClobValue()
    /**
     * free a character large object
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $blob
     *
     * @access private
     */
    function freeClobValue($prepared_query, $clob)
    {
        unset($this->lobs[$clob]);
        return DB_OK;
    }

    // }}}
    // {{{ getBlobValue()
    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $blob
     *
     * @access public
     *
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     */
    function getBlobValue($prepared_query, $parameter, $blob)
    {
        return ($this->getLobValue($prepared_query, $parameter, $blob));
    }

    // }}}
    // {{{ freeBlobValue()
    /**
     * free a binary large object
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $blob
     *
     * @access private
     */
    function freeBlobValue($prepared_query, $blob)
    {
        unset($this->lobs[$blob]);
        return DB_OK;
    }

    // }}}
    // {{{ getFloatValue()
    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     *
     * @access public
     *
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     */
    function getFloatValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    // }}}
    // {{{ getDecimalValue()
    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $value text string value that is intended to be converted.
     *
     * @access public
     *
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     */
    function getDecimalValue($value)
    {
        return (!strcmp($value,"NULL") ? "NULL" : strval(round($value*$this->decimal_factor)));
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @param resource $result  result identifier
     *
     * @access public
     *
     * @return mixed associative array variable
     *      that holds the names of columns. The indexes of the array are
     *      the column names mapped to lower case and the values are the
     *      respective numbers of the columns starting from 0. Some DBMS may
     *      not return any columns when the result set does not contain any
     *      rows.
     *     a DB error on failure
     */
    function getColumnNames($result)
    {
        if (!isset($this->highest_fetched_row[$result])) {
            return ($this->raiseError(DB_ERROR, NULL, NULL, "Get Column Names: specified an nonexistant result set"));
        }
        if (!isset($this->columns[$result])) {
            $this->columns[$result] = array();
            $columns = pg_numfields($result);
            for($column = 0; $column < $columns; $column++)
            $this->columns[$result][strtolower(pg_fieldname($result, $column))] = $column;
        }
        return ($this->columns[$result]);
    }

    // }}}
    // {{{ numCols()
    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @param resource $result result identifier
     *
     * @access public
     *
     * @return mixed integer value with the number of columns, a DB error
     *      on failure
     */
    function numCols($result)
    {
        if (!isset($this->highest_fetched_row[$result])) {
            return $this->raiseError(DB_ERROR, NULL, NULL, "numCols: specified an nonexistant result set");
        }
        return (pg_numfields($result));
    }

    // }}}
    // {{{ convertResult()
    /**
     * convert a value to a RDBMS indepdenant MDB type
     *
     * @param mixed $value value to be converted
     * @param int $type constant that specifies which type to convert to
     *
     * @return mixed converted value or a DB error on failure
     *
     * @access public
     */
    function convertResult($value, $type)
    {
        switch ($type) {
            case MDB_TYPE_BOOLEAN:
                return (strcmp($value, "Y") ? 0 : 1);
            case MDB_TYPE_DECIMAL:
                return (sprintf("%.".$this->options['decimal_places']."f",doubleval($value)/$this->decimal_factor));
            case MDB_TYPE_FLOAT:
                return doubleval($value);
            case MDB_TYPE_DATE:
                return ($value);
            case MDB_TYPE_TIME:
                return ($value);
            case MDB_TYPE_TIMESTAMP:
                return substr($value, 0, strlen("YYYY-MM-DD HH:MM:SS"));
            default:
                return ($this->baseConvertResult($value, $type));
        }
    }

    // }}}
    // {{{ nextId()
    /**
     * returns the next free id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @param boolean $ondemand when TRUE the seqence is
     *                          automatic created, if it
     *                          not exists
     *
     * @return mixed DB_Error or id
     */
    function nextId($seq_name, $ondemand = TRUE)
    {
        $seqname = $this->getSequenceName($seq_name);
        $repeat = 0;
        do {
            $this->pushErrorHandling(PEAR_ERROR_RETURN);
            $result = $this->query("SELECT NEXTVAL('${seqname}')");
            $this->popErrorHandling();
            if ($ondemand && MDB::isError($result) && $result->getCode() == DB_ERROR_NOSUCHTABLE) {
                $repeat = 1;
                $result = $this->createSequence($seq_name);
                if (MDB::isError($result)) {
                    return $this->raiseError($result);
                }
            } else {
                $repeat = 0;
            }
        }
        while ($repeat);
        if (MDB::isError($result)) {
            return $this->raiseError($result);
        }
        $arr = $this->fetchInto($result, DB_FETCHMODE_ORDERED);
        $this->freeResult($result);
        return ($arr[0]);
    }

    // }}}
    // {{{ currId()
    /**
     * returns the current id of a sequence
     *
     * @param string  $seq_name name of the sequence
     *
     * @return mixed DB_Error or id
     */
    function currId($name)
    {
        $seqname = $this->getSequenceName($seq_name);
        if (MDB::isError($result = $this->query("SELECT last_value FROM $seqname"))) {
            return $this->raiseError(DB_ERROR, NULL, NULL, 'currId: Unable to select from ' . $seqname) ;
        }
        if ($this->numRows($result) == 0) {
            $this->freeResult($result);
            return ($this->raiseError(DB_ERROR, NULL, NULL, "currId: could not find value in sequence table"));
        }
        $value = intval($this->fetch($result, 0, 0));
        $this->freeResult($result);
        return ($value);
    }

    // }}}
    // {{{ fetch()
    /**
     * fetch value from a result set
     *
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     *
     * @return mixed string on success, a DB error on failure
     *
     * @access public
     */
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
    // {{{ nextResult()

    /**
     * Move the internal pgsql result pointer to the next available result
     *
     * @param a valid fbsql result resource
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
    function tableInfo($result, $mode = NULL)
    {
        $count = 0;
        $id = 0;
        $res = array();

        /**
         * depending on $mode, metadata returns the following values:
         * 
         * - mode is FALSE (default):
         * $result[]:
         *    [0]["table"]  table name
         *    [0]["name"]   field name
         *    [0]["type"]   field type
         *    [0]["len"]    field length
         *    [0]["flags"]  field flags
         * 
         * - mode is DB_TABLEINFO_ORDER
         * $result[]:
         *    ["num_fields"] number of metadata records
         *    [0]["table"]  table name
         *    [0]["name"]   field name
         *    [0]["type"]   field type
         *    [0]["len"]    field length
         *    [0]["flags"]  field flags
         *    ["order"][field name]  index of field named "field name"
         *    The last one is used, if you have a field name, but no index.
         *    Test:  if (isset($result['meta']['myfield'])) { ...
         * 
         * - mode is DB_TABLEINFO_ORDERTABLE
         *     the same as above. but additionally
         *    ["ordertable"][table name][field name] index of field
         *       named "field name"
         * 
         *       this is, because if you have fields from different
         *       tables with the same field name * they override each
         *       other with DB_TABLEINFO_ORDER
         * 
         *       you can combine DB_TABLEINFO_ORDER and
         *       DB_TABLEINFO_ORDERTABLE with DB_TABLEINFO_ORDER |
         *       DB_TABLEINFO_ORDERTABLE * or with DB_TABLEINFO_FULL
         **/ 

        // if $result is a string, then we want information about a 
        // table without a resultset
        if (is_string($result)) {
            $id = pg_exec($this->connection, "SELECT * FROM $result");
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
            for ($i = 0; $i < $count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name'] = @pg_fieldname ($id, $i);
                $res[$i]['type'] = @pg_fieldtype ($id, $i);
                $res[$i]['len'] = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldflags($id, $i, $result) : '';
            }
        } else { // full
            $res["num_fields"] = $count;

            for ($i = 0; $i < $count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name'] = @pg_fieldname ($id, $i);
                $res[$i]['type'] = @pg_fieldtype ($id, $i);
                $res[$i]['len'] = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldFlags($id, $i, $result) : '';
                if ($mode &DB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode &DB_TABLEINFO_ORDERTABLE) {
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
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param resource  $result     result identifier
     * @param int       $fetchmode  how the array data should be indexed
     * @param int       $rownum     the row number to fetch
     *
     * @return int data array on success, a DB error on failure
     * 
     * @access public
     */
    function fetchInto($result, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        if ($rownum == NULL) {
            ++$this->highest_fetched_row[$result];
            $rownum = $this->highest_fetched_row[$result];
        } else {
            $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $rownum);
        }
        if ($rownum + 1 > $this->numRows($result)) {
            return NULL;
        }
        if ($fetchmode &DB_FETCHMODE_ASSOC) {
            $array = @pg_fetch_array($result, $rownum, PGSQL_ASSOC);
        } else {
            $array = @pg_fetch_row($result, $rownum);
        }
        if (!$array) {
            $errno = @pg_errormessage($this->connection);
            if (!$errno) {
                if ($this->options['autofree']) {
                    $this->freeResult($result);
                }
                return NULL;
            }
            return $this->pgsqlRaiseError($errno);
        }
        if (!$this->convertResultRow($result, $array)) {
            return $this->raiseError();
        }
        return ($array);
    }

    // }}} 
    // {{{ _pgFieldFlags()
    /**
     * Flags of a Field
     * @param int $resource PostgreSQL result identifier
     * @param int $num_field the field number
     * @return string The flags of the field ("not_NULL", "default_xx", "primary_key",
     *                 "unique" and "multiple_key" are supported)
     * @access private 
     **/
    function _pgFieldFlags($resource, $num_field, $table_name)
    {
        $field_name = @pg_fieldname($resource, $num_field);

        $result = pg_exec($this->connection, "SELECT f.attnotNULL, f.atthasdef
            FROM pg_attribute f, pg_class tab, pg_type typ
            WHERE tab.relname = typ.typname
            AND typ.typrelid = f.attrelid
            AND f.attname = '$field_name'
            AND tab.relname = '$table_name'");
        if (pg_numrows($result) > 0) {
            $row = pg_fetch_row($result, 0);
            $flags = ($row[0] == 't') ? 'not_NULL ' : '';

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