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
    // {{{ properties
    var $escape_quotes = "\\";
    var $decimal_factor = 1.0;

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

        $this->supported['sequences'] = 1;
        $this->supported['indexes'] = 1;
        $this->supported['summary_functions'] = 1;
        $this->supported['order_by_text'] = 1;
        $this->supported['transactions'] = 1;
        $this->supported['current_id'] = 1;
        $this->supported['limit_queries'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['replace'] = 1;
        $this->supported['sub_selects'] = 1;
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
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        if (!$this->auto_commit == !$auto_commit) {
            return MDB_OK;
        }
        if ($this->connection) {
            $result = $this->_doQuery($auto_commit ? 'END' : 'BEGIN');
            if (MDB::isError($result))
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
        $this->debug('commit transaction', 'commit');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'commit: transaction changes are being auto commited');
        }
        $result = $this->_doQuery('COMMIT');
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->_doQuery('BEGIN');
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
        $this->debug('rolling back transaction', 'rollback');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        $result = $this->_doQuery('ROLLBACK');
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->_doQuery('BEGIN');
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
        if ($database_name == '') {
            $database_name = 'template1';
        }
        $dsninfo = $this->dsn;
        $protocol = (isset($dsninfo['protocol'])) ? $dsninfo['protocol'] : 'tcp';
        $connstr = '';

        if ($protocol == 'tcp') {
            if (!empty($dsninfo['hostspec'])) {
                $connstr = 'host=' . $dsninfo['hostspec'];
            }
            if (!empty($dsninfo['port'])) {
                $connstr .= ' port=' . $dsninfo['port'];
            }
        }

        if (isset($database_name)) {
            $connstr .= ' dbname=\'' . addslashes($database_name) . '\'';
        }
        if (!empty($dsninfo['username'])) {
            $connstr .= ' user=\'' . addslashes($dsninfo['username']) . '\'';
        }
        if (!empty($dsninfo['password'])) {
            $connstr .= ' password=\'' . addslashes($dsninfo['password']) . '\'';
        }
        if (!empty($dsninfo['options'])) {
            $connstr .= ' options=' . $dsninfo['options'];
        }
        if (!empty($dsninfo['tty'])) {
            $connstr .= ' tty=' . $dsninfo['tty'];
        }

        $function = ($persistent ? 'pg_pconnect' : 'pg_connect');
        // catch error
        ob_start();
        $connection = $function($connstr);
        $error_msg = ob_get_contents();
        ob_end_clean();

        if ($connection > 0) {
            return $connection;
        }
        if (!$error_msg) {
            $error_msg = 'Could not connect to PostgreSQL server';
        }
        return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null,
            $error_msg);
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
        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && !strcmp($this->connected_database_name, $this->database_name)
                && ($this->opened_persistent == $this->options['persistent']))
            {
                return MDB_OK;
            }
            pg_close($this->connection);
            $this->affected_rows = -1;
            $this->connection = 0;
        }

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return $this->raiseError(MDB_ERROR_NOT_FOUND, null, null,
                'connect: extension '.$this->phptype.' is not compiled into PHP');
        }

        if (function_exists('pg_cmdtuples')) {
            $connection = $this->_doConnect('template1', 0);
            if (!MDB::isError($connection)) {
                if (($result = @pg_exec($connection, 'BEGIN'))) {
                    $error_reporting = error_reporting(63);
                    @pg_cmdtuples($result);
                    if (!isset($php_errormsg)
                        || strcmp($php_errormsg, 'This compilation does not support pg_cmdtuples()')
                    ) {
                        $this->supported['affected_rows'] = 1;
                    }
                    error_reporting($error_reporting);
                } else {
                    $err = $this->pgsqlRaiseError();
                }
                pg_close($connection);
            } else {
                $err = $this->raiseError(MDB_ERROR, null, null,
                    'connect: could not execute BEGIN');
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
        $this->connected_dsn = $this->dsn;
        $this->connected_database_name = $this->database_name;
        $this->opened_persistent = $this->options['persistent'];
        
        if (!$this->auto_commit
            && MDB::isError($trans_result = $this->_doQuery('BEGIN'))
        ) {
            pg_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $trans_result;
        }
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

            $GLOBALS['_MDB_databases'][$this->db_index] = '';
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
            $this->affected_rows = (isset($this->supported['affected_rows']) ? pg_cmdTuples($result) : -1);
        } else {
            return $this->pgsqlRaiseError();
        }
        return $result;
    }

    // }}}
    // {{{ standaloneQuery()

   /**
     * execute a query as DBA
     * 
     * @param string $query the SQL query
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function standaloneQuery($query)
    {
        if (($connection = $this->_doConnect('template1', 0)) == 0) {
            return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null,
                'Cannot connect to template1');
        }
        if (!($result = @pg_Exec($connection, $query))) {
            $this->pgsqlRaiseError();
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
     * @param mixed $result_mode boolean or string which specifies which class to use
     *
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     *
     * @access public
     */
    function &query($query, $types = null, $result_mode = null)
    {
        $this->debug($query, 'query');
        $ismanip = MDB::isManip($query);
        $this->last_query = $query;
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        $connected = $this->connect();
        if (MDB::isError($connected)) {
            return $connected;
        }
        
        if (!$ismanip && $limit > 0) {
            if ($this->auto_commit && MDB::isError($this->_doQuery('BEGIN'))) {
                $error =& $this->raiseError(MDB_ERROR);
                return $error;
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
        } elseif ((preg_match('/^\s*\(?\s*SELECT\s+/si', $query)
                && !preg_match('/^\s*\(?\s*SELECT\s+INTO\s/si', $query)
            ) || preg_match('/^\s*EXPLAIN/si',$query )
        ) {
            /* PostgreSQL commands:
               ABORT, ALTER, BEGIN, CLOSE, CLUSTER, COMMIT, COPY,
               CREATE, DECLARE, DELETE, DROP TABLE, EXPLAIN, FETCH,
               GRANT, INSERT, LISTEN, LOAD, LOCK, MOVE, NOTIFY, RESET,
               REVOKE, ROLLBACK, SELECT, SELECT INTO, SET, SHOW,
               UNLISTEN, UPDATE, VACUUM
            */
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
            $result =& $this->_return_result($result, $result_mode);
            return $result;
        } else {
            $this->affected_rows = 0;
            return MDB_OK;
        }
        $error =& $this->pgsqlRaiseError();
        return $error;
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
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'getColumnNames: specified an nonexistant result set');
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
        if (!isset($this->results[intval($result)])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'numCols: specified an nonexistant result set');
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
        if ($this->options['result_buffering']) {
            $result_value = intval($result);
            if (!isset($this->results[$result_value])) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'endOfResult: attempted to check the end of an unknown result');
            }
            $numrows = $this->numRows($result);
            if (MDB::isError($numrows)) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'endOfResult: error when calling numRows: '.$numrows->getUserInfo());
            }
            return $this->results[$result_value]['highest_fetched_row'] >= $numrows-1;
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'endOfResult: not supported if option "result_buffering" is not enabled');
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
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $row);
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
        if ($this->options['result_buffering']) {
            return pg_num_rows($result);
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'numRows: nut supported if option "result_buffering" is not enabled');
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
        if (!is_resource($result)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'freeResult: attemped to free an unknown query result');
        }
        unset($this->results[intval($result)]);
        return @pg_free_result($result);
    }

    // }}}
    // {{{ nextID()

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
    function nextID($seq_name, $ondemand = true)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->query("SELECT NEXTVAL('$sequence_name')", 'integer', false);
        $this->popExpect();
        if (MDB::isError($result)) {
            if ($ondemand && $result->getCode() == MDB_ERROR_NOSUCHTABLE) {
                $this->loadModule('manager');
                // Since we are creating the sequence on demand
                // we know the first id = 1 so initialize the
                // sequence at 2
                $result = $this->manager->createSequence($seq_name, 2);
                if (MDB::isError($result)) {
                    return $this->raiseError(MDB_ERROR, null, null,
                        'nextID: on demand sequence could not be created');
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }
            }
            return $result;
        }
        $value = $this->fetch($result);
        $this->freeResult($result);
        return $value;
    }

    // }}}
    // {{{ currID()

    /**
     * returns the current id of a sequence
     *
     * @param string  $seq_name name of the sequence
     * @return mixed MDB_Error or id
     * @access public
     */
    function currID($seq_name)
    {
        $seqname = $this->getSequenceName($seq_name);
        $result = $this->query("SELECT last_value FROM $seqname", null, false);
        if (MDB::isError($result)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'currID: Unable to select from ' . $seqname) ;
        }
        $value = $this->fetch($result);
        $this->freeResult($result);
        if (MDB::isError($value)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'currID: Unable to select from ' . $seqname) ;
        }
        if (!is_numeric($value)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'currID: could not find value in sequence table');
        }
        return $value;
    }

    // }}}
    // {{{ fetch()

    /**
    * fetch value from a result set
    *
    * @param resource    $result result identifier
    * @param int    $rownum    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed string on success, a MDB error on failure
    * @access public
    */
    function fetch($result, $rownum = 0, $field = 0)
    {
        $result_value = intval($result);
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $rownum);
        $value = @pg_result($result, $rownum, $field);
        if ($value === false && $value != null) {
            return $this->pgsqlRaiseError();
        }
        if (isset($this->results[$result_value]['types'][$field])) {
            $type = $this->results[$result_value]['types'][$field];
            $value = $this->datatype->convertResult($value, $type);
        }
        return $value;
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
            $this->results[$result_value]['highest_fetched_row'] =
                max($this->results[$result_value]['highest_fetched_row'], $rownum);
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode == MDB_FETCHMODE_ASSOC) {
            $row = @pg_fetch_array($result, $rownum, PGSQL_ASSOC);
        } else {
            $row = @pg_fetch_row($result, $rownum);
        }
        if (!$row) {
            $errno = @pg_errormessage($this->connection);
            if (!$errno) {
                return null;
            }
            return $this->pgsqlRaiseError($errno);
        }
        if (isset($this->results[$result_value]['types'])) {
            $row = $this->datatype->convertResultRow($result, $row);
        }
        return $row;
    }
}
?>