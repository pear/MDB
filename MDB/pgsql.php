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
//
// $Id$

require_once 'MDB/Common.php';

/**
 * MDB PostGreSQL driver
 *
 * @package MDB
 * @category Database
 * @author  Paul Cooper <pgc@ucecom.com>
 */

class MDB_pgsql extends MDB_Common
{
    var $connection = 0;
    var $connected_host;
    var $connected_port;
    var $selected_database = '';
    var $opened_persistent = '';

    var $escape_quotes = "\\";
    var $decimal_factor = 1.0;

    var $highest_fetched_row = array();
    var $columns = array();

    // }}}
    // {{{ constructor

    /**
    * Constructor
    */
    function MDB_pgsql()
    {
        $this->MDB_Common();
        $this->phptype = 'pgsql';
        $this->dbsyntax = 'pgsql';

        $this->supported['Sequences'] = 1;
        $this->supported['Indexes'] = 1;
        $this->supported['SummaryFunctions'] = 1;
        $this->supported['OrderByText'] = 1;
        $this->supported['Transactions'] = 1;
        $this->supported['CurrId'] = 1;
        $this->supported['SelectRowRanges'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['Replace'] = 1;
        $this->supported['SubSelects'] = 1;
        
        $this->decimal_factor = pow(10.0, $this->decimal_places);
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
     * @return int a portable MDB error code, or false if this DB
     * implementation has no mapping for the given error code.
     */
    function errorCode($errormsg)
    {
        static $error_regexps;
        if (empty($error_regexps)) {
            $error_regexps = array(
                '/(Table does not exist\.|Relation [\"\'].*[\"\'] does not exist|sequence does not exist|class ".+" not found)$/' => MDB_ERROR_NOSUCHTABLE,
                '/Relation [\"\'].*[\"\'] already exists|Cannot insert a duplicate key into (a )?unique index.*/'      => MDB_ERROR_ALREADY_EXISTS,
                '/divide by zero$/'                     => MDB_ERROR_DIVZERO,
                '/pg_atoi: error in .*: can\'t parse /' => MDB_ERROR_INVALID_NUMBER,
                '/ttribute [\"\'].*[\"\'] not found$|Relation [\"\'].*[\"\'] does not have attribute [\"\'].*[\"\']/' => MDB_ERROR_NOSUCHFIELD,
                '/parser: parse error at or near \"/'   => MDB_ERROR_SYNTAX,
                '/referential integrity violation/'     => MDB_ERROR_CONSTRAINT
            );
        }
        foreach ($error_regexps as $regexp => $code) {
            if (preg_match($regexp, $errormsg)) {
                return $code;
            }
        }
        // Fall back to MDB_ERROR if there was no mapping.
        return MDB_ERROR;
    }

    // }}}
    // {{{ pgsqlRaiseError()

    /**
     * This method is used to communicate an error and invoke error
     * callbacks etc.  Basically a wrapper for MDB::raiseError
     * that checks for native error msgs.
     *
     * @param integer $errno error code
     * @return object a PEAR error object
     * @access public
     * @see PEAR_Error
     */

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
    // {{{ errorNative()

    /**
     * Get the native error code of the last error (if any) that
     * occured on the current connection.
     *
     * @access public
     *
     * @return int native pgsql error code
     */
    function errorNative()
    {
        return pg_ErrorMessage($this->connection);
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
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function autoCommit($auto_commit)
    {
        $this->debug('AutoCommit: '.($auto_commit ? 'On' : 'Off'));
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return MDB_OK;
        }
        if ($this->connection) {
            if (MDB::isError($result = $this->_doQuery($auto_commit ? 'END' : 'BEGIN')))
                return $result;
        }
        $this->auto_commit = $auto_commit;
        $this->in_transaction = !$auto_commit;
        return MDB_OK;
    }

    // }}}
    // {{{ commit()

    /**
     * Commit the database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after committing the pending changes.
     *
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function commit()
    {
         $this->debug('Commit Transaction');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null, 'Commit: transaction changes are being auto commited');
        }
        return $this->_doQuery('COMMIT') && $this->_doQuery('BEGIN');
    }

    // }}}
    // {{{ rollback()

    /**
     * Cancel any database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after canceling the pending changes.
     *
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function rollback()
    {
         $this->debug('Rollback Transaction');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null, 'Rollback: transactions can not be rolled back when changes are auto commited');
        }
        return $this->_doQuery('ROLLBACK') && $this->_doQuery('BEGIN');
    }

    // }}}
    // {{{ _doConnect()

    /**
     * Does the grunt work of connecting to the database
     *
     * @return mixed connection resource on success, MDB_Error on failure
     * @access private
     **/
    function _doConnect($database_name, $persistent)
    {
        $function = ($persistent ? 'pg_pconnect' : 'pg_connect');
        if (!function_exists($function)) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'doConnect: PostgreSQL support is not available in this PHP configuration');
        }
        $port = (isset($this->port) ? $this->port : '');
        if ($database_name == '') {
            $database_name = 'template1';
        }
        $connect_string = 'dbname='.$database_name;
        if ($this->host != '') {
            $connect_string .= ' host='.$this->host;
        }
        if ($port != '') {
            $connect_string .= ' port='.strval($port);
        }
        if ($this->user != '') {
            $connect_string .= ' user='.$this->user;
        }
        if ($this->password != '') {
            $connect_string .= ' password='.$this->password;
        }
        if (($connection = @$function($connect_string)) > 0) {
            return $connection;
        }
        if (isset($php_errormsg)) {
            $error_msg = $php_errormsg;
        } else {
            $error_msg = 'Could not connect to PostgreSQL server';
        }
        return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null, 'doConnect: '.$error_msg);
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     *
     * @return true on success, MDB_Error on failure
     * @access public
     **/
    function connect()
    {
        $port = (isset($this->options['port']) ? $this->options['port'] : '');
        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_port, $port)
                && !strcmp($this->selected_database, $this->database_name)
                && ($this->opened_persistent == $this->options['persistent']))
            {
                return MDB_OK;
            }
            pg_Close($this->connection);
            $this->affected_rows = -1;
            $this->connection = 0;
        }

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND,
                null, null, 'extension '.$this->phptype.' is not compiled into PHP',
                'MDB_Error', true);
        }

        if (function_exists('pg_cmdTuples')) {
            $connection = $this->_doConnect('template1', 0);
            if (!MDB::isError($connection)) {
                if (($result = @pg_Exec($connection, 'BEGIN'))) {
                    $error_reporting = error_reporting(63);
                    @pg_cmdTuples($result);
                    if (!isset($php_errormsg) || strcmp($php_errormsg, 'This compilation does not support pg_cmdtuples()')) {
                        $this->supported['AffectedRows'] = 1;
                    }
                    error_reporting($error_reporting);
                } else {
                    $err = $this->raiseError(MDB_ERROR, null, null, 'Setup: '.pg_ErrorMessage($connection));
                }
                pg_Close($connection);
            } else {
                $err = $this->raiseError(MDB_ERROR, null, null, 'Setup: could not execute BEGIN');
            }
            if (isset($err) && MDB::isError($err)) {
                return $err;
            }
        }
        $connection = $this->_doConnect($this->database_name, $this->options['persistent']);
        if (MDB::isError($connection)) {
            return $connection;
        }
        $this->connection = $connection;
        
        if (!$this->auto_commit && MDB::isError($trans_result = $this->_doQuery('BEGIN'))) {
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $trans_result;
        }
        $this->connected_host = $this->host;
        $this->connected_port = $port;
        $this->selected_database = $this->database_name;
        $this->opened_persistent = $this->options['persistent'];
        return MDB_OK;
    }

    // }}}
    // {{{ _close()
    /**
     * Close the database connection
     *
     * @return boolean
     * @access private
     **/
    function _close()
    {
        if ($this->connection != 0) {
            if (!$this->auto_commit) {
                $this->_doQuery('END');
            }
            pg_Close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            
            global $_MDB_databases;
            $_MDB_databases[$this->database] = '';
            return MDB_OK;
        }
        return MDB_ERROR;
    }

    // }}}
    // {{{ _doQuery()

    /**
     * Execute a query
     * @param string $query the SQL query
     * @return mixed result identifier if query executed, else MDB_error
     * @access private
     **/
    function _doQuery($query)
    {
        if (($result = @pg_Exec($this->connection, $query))) {
            $this->affected_rows = (isset($this->supported['AffectedRows']) ? pg_cmdTuples($result) : -1);
        } else {
            $error = pg_ErrorMessage($this->connection);
            return $this->pgsqlRaiseError();
        }
        return $result;
    }

    // }}}
    // {{{ _standaloneQuery()

    /**
     * execute a query
     *
     * @param string $query
     * @return
     * @access private
     */
    function _standaloneQuery($query)
    {
        if (($connection = $this->_doConnect('template1', 0)) == 0) {
            return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null, '_standaloneQuery: Cannot connect to template1');
        }
        if (!($result = @pg_Exec($connection, $query))) {
            $this->raiseError(MDB_ERROR, null, null, '_standaloneQuery: ' . pg_ErrorMessage($connection));
        }
        pg_Close($connection);
        return $result;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string $query the SQL query
     * @param array $types array that contains the types of the columns in
     *                         the result set
     * @return mixed result identifier if query executed, else MDB_error
     * @access public
     **/
    function query($query, $types = null)
    {
        $this->debug("Query: $query");
        $ismanip = MDB::isManip($query);
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        $connected = $this->connect();
        if (MDB::isError($connected)) {
            return $connected;
        }
        
        if (!$ismanip && $limit > 0 &&
            substr(strtolower(ltrim($query)),
            0, 6) == 'select')
        {
             if ($this->auto_commit && MDB::isError($this->_doQuery('BEGIN'))) {
                 return $this->raiseError(MDB_ERROR);
             }
             $result = $this->_doQuery('DECLARE select_cursor SCROLL CURSOR FOR '.$query);
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
             if ($this->auto_commit && MDB::isError($result2 = $this->_doQuery('END'))) {
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
            return MDB_OK;
        } elseif  (preg_match('/^\s*\(?\s*SELECT\s+/si', $query) && !preg_match('/^\s*\(?\s*SELECT\s+INTO\s/si', $query)) {
            $this->results[intval($result)]['highest_fetched_row'] = -1;
            if ($types != null) {
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
            return MDB_OK;
        }
        return $this->raiseError(MDB_ERROR);
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @param resource $result  result identifier
     * @return mixed associative array variable
     *      that holds the names of columns. The indexes of the array are
     *      the column names mapped to lower case and the values are the
     *      respective numbers of the columns starting from 0. Some DBMS may
     *      not return any columns when the result set does not contain any
     *      rows.
     *     a MDB error on failure
     * @access public
     */
    function getColumnNames($result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null, 'Get Column Names: specified an nonexistant result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = pg_numfields($result);
            for($column = 0; $column < $columns; $column++) {
                $this->results[$result_value]['columns'][strtolower(pg_fieldname($result, $column))] = $column;
            }
        }
        return $this->results[$result_value]['columns'];
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @param resource $result result identifier
     * @return mixed integer value with the number of columns, a MDB error
     *      on failure
     * @access public
     */
    function numCols($result)
    {
        if (!isset($this->results[intval($result)]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null, 'numCols: specified an nonexistant result set');
        }
        return pg_numfields($result);
    }

    // }}}
    // {{{ endOfResult()

    /**
    * check if the end of the result set has been reached
    *
    * @param resource    $result result identifier
    * @return mixed true or false on sucess, a MDB error on failure
    * @access public
    */
    function endOfResult($result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->RaiseError(MDB_ERROR, null, null,
                'End of result attempted to check the end of an unknown result');
        }
        return $this->results[$result_value]['highest_fetched_row'] >= $this->numRows($result) - 1;
    }

    // }}}
    // {{{ resultIsNull()

    /**
     * Determine whether the value of a query result located in given row and
     *   field is a null.
     *
     * @param resource    $result result identifier
     * @param int    $row    number of the row where the data can be found
     * @param int    $field    field number where the data can be found
     * @return mixed true or false on success, a MDB error on failure
     * @access public
     */
    function resultIsNull($result, $row, $field)
    {
        $result_value = intval($result);
        $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $row);
        return @pg_FieldIsNull($result, $row, $field);
    }

    // }}}
    // {{{ numRows()

    /**
     * returns the number of rows in a result object
     *
     * @param ressource $result a valid result ressouce pointer
     * @return mixed MDB_Error or the number of rows
     * @access public
     */
    function numRows($result)
    {
        return pg_numrows($result);
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param $result result identifier
     * @return boolean true on success, false if $result is invalid
     * @access public
     */
    function freeResult($result)
    {
        $result_value = intval($result);
        if (isset($this->results[$result_value])) {
            unset($this->results[$result_value]);
        }
        if (is_resource($result)) {
            return @pg_free_result($result);
        }

        return $this->raiseError(MDB_ERROR, null, null,
            'Free result: attemped to free an unknown query result');
    }

    // }}}
    // {{{ nextId()

    /**
     * returns the next free id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @param boolean $ondemand when true the seqence is
     *                          automatic created, if it
     *                          not exists
     * @return mixed MDB_Error or id
     * @access public
     */
    function nextId($seq_name, $ondemand = true)
    {
        $seqname = $this->getSequenceName($seq_name);
        $repeat = 0;
        do {
            $this->pushErrorHandling(PEAR_ERROR_RETURN);
            $result = $this->query("SELECT NEXTVAL('$seqname')");
            $this->popErrorHandling();
            if ($ondemand && MDB::isError($result) && $result->getCode() == MDB_ERROR_NOSUCHTABLE) {
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
        $next_id = $this->fetchOne($result, MDB_FETCHMODE_ORDERED);
        $this->freeResult($result);
        return $next_id;
    }

    // }}}
    // {{{ currId()

    /**
     * returns the current id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @return mixed MDB_Error or id
     * @access public
     */
    function currId($seq_name)
    {
        $seqname = $this->getSequenceName($seq_name);
        if (MDB::isError($result = $this->queryOne("SELECT last_value FROM $seqname"))) {
            return $this->raiseError(MDB_ERROR, null, null, 'currId: Unable to select from ' . $seqname) ;
        }
        if (!is_numeric($result)) {
            return $this->raiseError(MDB_ERROR, null, null, 'currId: could not find value in sequence table');
        }
        return $result;
    }


    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and return data in an array.
     *
     * @param resource $result result identifier
     * @param int $fetchmode ignored
     * @param int $rownum the row number to fetch
     * @return mixed data array or null on success, a MDB error on failure
     * @access public
     */
    function fetchRow($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = null)
    {
        $result_value = intval($result);
        if ($rownum == null) {
            ++$this->results[$result_value]['highest_fetched_row'];
            $rownum = $this->results[$result_value]['highest_fetched_row'];
        } else {
            $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $rownum);
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode & MDB_FETCHMODE_ASSOC) {
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
                return null;
            }
            return $this->pgsqlRaiseError($errno);
        }
        if (isset($this->results[$result_value]['types'])) {
            $array = $this->datatype->convertResultRow($this, $result, $array);
        }
        return $array;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal pgsql result pointer to the next available result
     *
     * @param a valid fbsql result resource
     * @return true if a result is available otherwise return false
     * @access public
     */
    function nextResult($result)
    {
        return false;
    }

    // }}}
    // {{{ tableInfo()

    /**
     * returns meta data about the result set
     *
     * @param  mixed $resource PostgreSQL result identifier or table name
     * @param mixed $mode depends on implementation
     * @return array an nested array, or a MDB error
     * @access public
     */
    function tableInfo($result, $mode = null)
    {
        $count = 0;
        $id = 0;
        $res = array();
        
        /**
         * depending on $mode, metadata returns the following values:
         *
         * - mode is false (default):
         * $result[]:
         *    [0]['table']  table name
         *    [0]['name']   field name
         *    [0]['type']   field type
         *    [0]['len']    field length
         *    [0]['flags']  field flags
         *
         * - mode is MDB_TABLEINFO_ORDER
         * $result[]:
         *    ['num_fields'] number of metadata records
         *    [0]['table']  table name
         *    [0]['name']   field name
         *    [0]['type']   field type
         *    [0]['len']    field length
         *    [0]['flags']  field flags
         *    ['order'][field name]  index of field named 'field name'
         *    The last one is used, if you have a field name, but no index.
         *    Test:  if (isset($result['meta']['myfield'])) { ...
         *
         * - mode is MDB_TABLEINFO_ORDERTABLE
         *     the same as above. but additionally
         *    ['ordertable'][table name][field name] index of field
         *       named 'field name'
         *
         *       this is, because if you have fields from different
         *       tables with the same field name * they override each
         *       other with MDB_TABLEINFO_ORDER
         *
         *       you can combine MDB_TABLEINFO_ORDER and
         *       MDB_TABLEINFO_ORDERTABLE with MDB_TABLEINFO_ORDER |
         *       MDB_TABLEINFO_ORDERTABLE * or with MDB_TABLEINFO_FULL
         **/
        
        // if $result is a string, then we want information about a
        // table without a resultset
        if (is_string($result)) {
            $id = pg_exec($this->connection, "SELECT * FROM $result LIMIT 0");
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
            $res['num_fields'] = $count;
            
            for ($i = 0; $i < $count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name'] = @pg_fieldname ($id, $i);
                $res[$i]['type'] = @pg_fieldtype ($id, $i);
                $res[$i]['len'] = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldFlags($id, $i, $result) : '';
                if ($mode &MDB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode &MDB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }
        
        // free the result only if we were called on a table
        if (is_string($result) && is_resource($id)) {
            @pg_freeresult($id);
        }
        return $res;
    }

    // }}}
    // {{{ _pgFieldFlags()

    /**
     * Flags of a Field
     *
     * @param int $resource PostgreSQL result identifier
     * @param int $num_field the field number
     * @return string The flags of the field ('not_null', 'default_xx', 'primary_key',
     *                 'unique' and 'multiple_key' are supported)
     * @access private
     **/
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
            $flags = ($row[0] == 't') ? 'not_null ' : '';
            
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
            $keys = explode(' ', $row[2]);
            
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

?>