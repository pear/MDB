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
// | Author: Lorenzo Alberton <l.alberton@quipo.it>                       |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'MDB/Common.php';

/**
 * MDB FireBird/InterBase driver
 *
 * @package MDB
 * @category Database
 * @author  Lorenzo Alberton <l.alberton@quipo.it>
 */

class MDB_ibase extends MDB_Common
{
    // {{{ class vars

    var $connection = 0;
    var $connected_host;
    var $connected_port;
    var $selected_database = '';
    var $selected_database_file = '';
    var $opened_persistent = '';
    var $transaction_id = 0;
    var $auto_commit = true; //added

    var $escape_quotes = "'";
    var $decimal_factor = 1.0;

    var $results = array();
    var $current_row = array();
    var $columns = array();
    var $rows = array();
    var $limits = array();
    var $row_buffer = array();
    var $highest_fetched_row = array();
    var $query_parameters = array();
    var $query_parameter_values = array();

    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_ibase()
    {
        $this->MDB_Common();
        $this->phptype  = 'ibase';
        $this->dbsyntax = 'ibase';

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND,
                null, null, 'extension '.$this->phptype.' is not compiled into PHP',
                'MDB_Error', true);
        }

/*
        if (!function_exists('ibase_connect')) {
            return 'FireBird/InterBase support is not available in this PHP configuration';
        }
*/
        $this->supported['Sequences'] = 1;
        $this->supported['Indexes'] = 1;
        $this->supported['SummaryFunctions'] = 1;
        $this->supported['OrderByText'] = 1;
        $this->supported['Transactions'] = 1;
        $this->supported['CurrId'] = 1;
        $this->supported['SelectRowRanges'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['Replace'] = 0;   // TEMPORARY SECURITY MEASURE...
        $this->supported['SubSelects'] = 1;

        $this->decimal_factor = pow(10.0, $this->decimal_places);

        $this->options['DatabasePath'] = '';
        $this->options['DatabaseExtension'] = '.gdb';
        $this->options['DBAUser'] = false;
        $this->options['DBAPassword'] = false;

        $this->errorcode_map = array(
            -104 => MDB_ERROR_SYNTAX,
            -150 => MDB_ERROR_ACCESS_VIOLATION,
            -151 => MDB_ERROR_ACCESS_VIOLATION,
            -155 => MDB_ERROR_NOSUCHTABLE,
              88 => MDB_ERROR_NOSUCHTABLE,
            -157 => MDB_ERROR_NOSUCHFIELD,
            -158 => MDB_ERROR_VALUE_COUNT_ON_ROW,
            -170 => MDB_ERROR_MISMATCH,
            -171 => MDB_ERROR_MISMATCH,
            -172 => MDB_ERROR_INVALID,
            -204 => MDB_ERROR_INVALID,
            -205 => MDB_ERROR_NOSUCHFIELD,
            -206 => MDB_ERROR_NOSUCHFIELD,
            -208 => MDB_ERROR_INVALID,
            -219 => MDB_ERROR_NOSUCHTABLE,
            -297 => MDB_ERROR_CONSTRAINT,
            -530 => MDB_ERROR_CONSTRAINT,
            -607 => MDB_ERROR_NOSUCHTABLE,
            -803 => MDB_ERROR_CONSTRAINT,
            -551 => MDB_ERROR_ACCESS_VIOLATION,
            -552 => MDB_ERROR_ACCESS_VIOLATION,
            -922 => MDB_ERROR_NOSUCHDB,
            -923 => MDB_ERROR_CONNECT_FAILED,
            -924 => MDB_ERROR_CONNECT_FAILED
        );
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
    // {{{ ibaseRaiseError()

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

    function ibaseRaiseError($errno = null)
    {
        $native_errmsg = $this->errorNative();
        // memo for the interbase php module hackers: we need something similar
        // to mysql_errno() to retrieve error codes instead of this ugly hack
        if (preg_match('/^([^0-9\-]+)([0-9\-]+)\s+(.*)$/', $native_errmsg, $m)) {
            $native_errno = (int)$m[2];
        } else {
            $native_errno = null;
        }
        // try to map the native error to the DB one
        if ($errno === null) {
            if ($native_errno) {
                // try to interpret Interbase error code (that's why we need ibase_errno()
                // in the interbase module to return the real error code)
                switch ($native_errno) {
                    case -204:
                        if (is_int(strpos($m[3], 'Table unknown'))) {
                            $errno = MDB_ERROR_NOSUCHTABLE;
                        }
                    break;
                    default:
                        $errno = $this->errorCode($native_errno);
                }
            } else {
                $error_regexps = array(
                    '/[tT]able .* already exists/' => MDB_ERROR_ALREADY_EXISTS,
                    '/violation of FOREIGN KEY constraint/' => MDB_ERROR_CONSTRAINT,
                    '/conversion error from string/' => MDB_ERROR_INVALID_NUMBER,
                    '/arithmetic exception, numeric overflow, or string truncation/' => MDB_ERROR_DIVZERO
                );
                foreach ($error_regexps as $regexp => $code) {
                    if (preg_match($regexp, $native_errmsg, $m)) {
                        $errno = $code;
                        $native_errno = null;
                        break;
                    }
                }
            }
        }
        return $this->raiseError($errno, null, null, null, $native_errmsg);
    }

    // }}}
    // {{{ errorNative()

    /**
     * Get the native error code of the last error (if any) that
     * occured on the current connection.
     *
     * @access public
     * @return int native ibase error code
     */
    function errorNative()
    {
        return ibase_errmsg();
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
        $this->debug('autoCommit', ($auto_commit ? "On" : "Off"));
        if ((!$this->auto_commit) == (!$auto_commit)) {
            return MDB_OK;
        }
        if ($this->connection && $auto_commit && MDB::isError($commit = $this->commit())) {
            return $commit;
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
        $this->debug('commit', 'commit transaction');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, '', '', 'Commit: transaction changes are being auto commited');
        }
        return @ibase_commit($this->connection);
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
        $this->debug('rollback', 'rolling back transaction');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, '', '', 'Rollback: transactions can not be rolled back when changes are auto commited');
        }

        //return ibase_rollback($this->connection);

        if ($this->transaction_id && !ibase_rollback($this->connection)) {
            return $this->raiseError(MDB_ERROR, '', '', 'Rollback: Could not rollback a pending transaction: '.ibase_errmsg());
        }
        if (!$this->transaction_id = ibase_trans(IBASE_COMMITTED, $this->connection)) {
            return $this->raiseError(MDB_ERROR, '', '', 'Rollback: Could not start a new transaction: '.ibase_errmsg());
        }
        return MDB_OK;
    }

    // }}}
    // {{{ getDatabaseFile()

    function getDatabaseFile($database_name)
    {
        if (isset($this->options['DatabasePath'])) {
            $this->database_path = $this->options['DatabasePath'];
        }
        if (isset($this->options['DatabaseExtension'])) {
            $this->database_extension = $this->options['DatabaseExtension'];
        }
        //$this->database_path = (isset($this->options['DatabasePath']) ? $this->options['DatabasePath'] : '');
        //$this->database_extension = (isset($this->options['DatabaseExtension']) ? $this->options['DatabaseExtension'] : '.gdb');

        //$database_path = (isset($this->options['DatabasePath']) ? $this->options['DatabasePath'] : '');
        //$database_extension = (isset($this->options['DatabaseExtension']) ? $this->options['DatabaseExtension'] : '.gdb');
        return $this->database_path.$database_name.$this->database_extension;
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
        $function = ($persistent ? 'ibase_pconnect' : 'ibase_connect');
        if (!function_exists($function)) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'doConnect: FireBird/InterBase support is not available in this PHP configuration');
        }

        $dbhost = $this->host ?
                  ($this->host . ':' . $database_name) :
                  $database_name;

        $params = array();
        $params[] = $dbhost;
        $params[] = !empty($this->user) ? $this->user : null;
        $params[] = !empty($this->password) ? $this->password : null;

        $connection = @call_user_func_array($function, $params);
        if ($connection > 0) {
            ibase_timefmt("%Y-%m-%d %H:%M:%S", IBASE_TIMESTAMP);
            ibase_timefmt("%Y-%m-%d", IBASE_DATE);
            return $connection;
        }
        if (isset($php_errormsg)) {
            $error_msg = $php_errormsg;
        } else {
            $error_msg = 'Could not connect to FireBird/InterBase server';
        }
        return $this->raiseError(MDB_ERROR_CONNECT_FAILED, '', '', 'doConnect: '.$error_msg);
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

        $database_file = $this->getDatabaseFile($this->database_name);

        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_port, $port)
                && !strcmp($this->selected_database_file, $database_file)
                && ($this->opened_persistent == $this->options['persistent']))
            {
                return MDB_OK;
            }
            ibase_close($this->connection);
            $this->affected_rows = -1;
            $this->connection = 0;
        }
        $connection = $this->_doConnect($database_file, $this->options['persistent']);
        if (MDB::isError($connection)) {
            return $connection;
        }
        $this->connection = $connection;

        //the if below was added after PEAR::DB. Review me!!
        if ($this->dbsyntax == 'fbird') {
            $this->supported['limit'] = 'alter';
        }

        if (!$this->auto_commit && MDB::isError($trans_result = $this->_doQuery('BEGIN'))) {
            ibase_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $trans_result;
        }
        $this->connected_host = $this->host;
        $this->connected_port = $port;
        $this->selected_database_file = $database_file;
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
            ibase_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;

            global $_MDB_databases;
            $_MDB_databases[$this->database] = '';
            return true;
        }
        return false;
    }

    // }}}
    // {{{ _doQuery()

    /**
     * Execute a query
     * @param string $query the SQL query
     * @return mixed result identifier if query executed, else MDB_error
     * @access private
     **/
    function _doQuery($query, $first=0, $limit=0, $prepared_query=0)  // function _doQuery($query)
    {
        $connection = ($this->auto_commit ? $this->connection : $this->transaction_id);
        if ($prepared_query
            && isset($this->query_parameters[$prepared_query])
            && count($this->query_parameters[$prepared_query]) > 2)
        {
            if (function_exists("call_user_func_array")) {
                $this->query_parameters[$prepared_query][0] = $connection;
                $this->query_parameters[$prepared_query][1] = $query;
                $result = @call_user_func_array("ibase_query", $this->query_parameters[$prepared_query]);
            } else {
                switch (count($this->query_parameters[$prepared_query])) {
                    case 3:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2]);
                        break;
                    case 4:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3]);
                        break;
                    case 5:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4]);
                        break;
                    case 6:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4], $this->query_parameters[$prepared_query][5]);
                        break;
                    case 7:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4], $this->query_parameters[$prepared_query][5], $this->query_parameters[$prepared_query][6]);
                        break;
                    case 8:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4], $this->query_parameters[$prepared_query][5], $this->query_parameters[$prepared_query][6], $this->query_parameters[$prepared_query][7]);
                        break;
                    case 9:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4], $this->query_parameters[$prepared_query][5], $this->query_parameters[$prepared_query][6], $this->query_parameters[$prepared_query][7], $this->query_parameters[$prepared_query][8]);
                        break;
                    case 10:
                        $result = @ibase_query($connection, $query, $this->query_parameters[$prepared_query][2], $this->query_parameters[$prepared_query][3], $this->query_parameters[$prepared_query][4], $this->query_parameters[$prepared_query][5], $this->query_parameters[$prepared_query][6], $this->query_parameters[$prepared_query][7], $this->query_parameters[$prepared_query][8], $this->query_parameters[$prepared_query][9]);
                        break;
                }
            }
        } else {
            //Not Prepared Query
            $result = @ibase_query($connection, $query);
        }
        if ($result) {
            if (($select=(substr(strtolower(ltrim($query)), 0, strlen("select")) == 'select'))) {
                $result_value = intval($result);
                $this->results[$result_value]['current_row'] = -1;
                if ($limit>0) {
                    $this->results[$result_value]['limit'] = array($first, $limit, 0);
                }
                $this->results[$result_value]['highest_fetched_row'] = -1;
            } else {
                $this->affected_rows = -1;
            }
        } else {
            return $this->raiseError(MDB_ERROR, null, null, '_doQuery: Could not execute query ("'.$query.'"): ' . ibase_errmsg());
        }
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
        $this->debug('query', $query);
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        $connected = $this->connect();
        if (MDB::isError($connected)) {
            return $connected;
        }
        //return $this->_doQuery($query, $first, $limit, 0);

        if (!MDB::isError($result = $this->_doQuery($query, $first, $limit, 0))) {
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
        }
        return $this->ibaseRaiseError();

    }

    // }}}
    // {{{ _executePreparedQuery()

    /**
     * Execute a prepared query statement.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param string $query query to be executed
     * @param array $types array that contains the types of the columns in
     *       the result set
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access private
     */
    function _executePreparedQuery($prepared_query, $query)
    {
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (MDB::isError($connect = $this->connect())) {
            return $connect;
        }
        return $this->_doQuery($query, $first, $limit, $prepared_query);
    }

    // }}}
    // {{{ _skipLimitOffset()

    /**
     * Skip the first row of a result set.
     *
     * @param resource $result
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access private
     */
    function _skipLimitOffset($result)
    {
        $result_value = intval($result);
        $first = $this->results[$result_value]['limits'][0];
        for (; $this->results[$result_value]['limits'][2] < $first; $this->results[$result_value]['limits'][2]++) {
            if (!is_array(@ibase_fetch_row($result))) {
                $this->results[$result_value]['limits'][2] = $first;
                return $this->raiseError(MDB_ERROR, null, null,
                    'Skip first rows: could not skip a query result row');
            }
        }
        return MDB_OK;
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
            $columns = ibase_num_fields($result);
            for ($column=0; $column<$columns; $column++) {
                $column_info = ibase_field_info($result, $column);
                $this->results[$result_value]['columns'][strtolower($column_info["name"])] = $column;
            }
        }
        return $this->results[$result_value]['columns'];
        //$column_names = $this->results[$result_value]['columns'];
        //return 1;

        /*
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null, 'Get Column Names: specified an nonexistant result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = ibase_num_fields($result);
            for ($column = 0; $column < $columns; $column++) {
                $_fieldInfo = ibase_field_info($result, $column);
                $this->results[$result_value]['columns'][strtolower($_fieldInfo['name'])] = $column;
            }
        }
        return $this->results[$result_value]['columns'];
        */
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
        $result_value = intval($result);
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null, 'numCols: specified an nonexistant result set');
        }
        return ibase_num_fields($result);
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
        if (!isset($this->results[$result_value]['current_row'])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'End of result: attempted to check the end of an unknown result');
        }
        if (isset($this->results[$result_value]['rows'])) {
            return $this->results[$result_value]['highest_fetched_row'] >= $this->results[$result_value]['rows']-1;
        }
        if (isset($this->results[$result_value]['row_buffer'])) {
            return false;
        }
        if (isset($this->results[$result_value]['limits'])) {
            if (MDB::isError($this->_skipLimitOffset($result))
                || $this->results[$result_value]['current_row'] + 1 >= $this->results[$result_value]['limits'][1])
            {
                $this->results[$result_value]['rows'] = 0;
                return true;
            }
        }
        if (is_array($this->results[$result_value]['row_buffer'] = @ibase_fetch_row($result))) {
            return false;
        }
        unset($this->results[$result_value]['row_buffer']);
        $this->results[$result_value]['rows'] = $this->results[$result_value]['current_row']+1;
        return true;
    }

    // }}}
    // {{{ getColumn()

    function getColumn($result, $field)
    {
        $result_value = intval($result);
        $colNames = $this->getColumnNames($result);
        if (is_integer($field)) {
            if (($column = $field)<0 || $column>=count($colNames)) {
                return $this->RaiseError(MDB_ERROR, '', '', 'getColumn attempted to fetch an query result column out of range');
            }
        } else {
            $name = strtolower($field);
            if (!isset($colNames[$name])) {
                return $this->RaiseError(MDB_ERROR, '', '', 'getColumn attempted to fetch an unknown query result column');
            }
            $column = $this->results[$result_value]['columns'][$name];
        }
        return $column;
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
        if ($this->options['result_buffering']) {
            if ($rownum == null) {
                $rownum = $this->results[$result_value]['highest_fetched_row']+1;
            }
            if (isset($this->results[$result_value][$rownum])) {
                return $this->results[$result_value][$rownum];
            }
        }
        if (isset($this->limits[$result_value])) {
            if ($rownum >= $this->limits[$result_value][1]) {
                return null;
            }
            if (MDB::isError($this->_skipLimitOffset($result))) {
                if ($this->options['autofree']) {
                    $this->freeResult($result);
                }
                return null;
            }
        }
        if ($this->options['result_buffering']) {
            if (isset($this->results[$result_value]['row_buffer'])) {
                $this->results[$result_value]['current_row']++;
                $this->results[$result_value][$this->results[$result_value]['current_row']] = $this->results[$result_value]['row_buffer'];
                unset($this->results[$result_value]['row_buffer']);
            }
            for(;$this->results[$result_value]['current_row'] < $rownum;
                $this->results[$result_value]['current_row']++
            ) {
                if ($fetchmode & MDB_FETCHMODE_ASSOC) {
                    $row = @ibase_fetch_assoc($result);
                } else {
                    $row = @ibase_fetch_row($result);
                }
                foreach ($row as $key => $value_with_space) {
                    $row[$key] = rtrim($value_with_space);
                }
                $this->results[$result_value][$this->results[$result_value]['current_row']+1] = $row;
                if (!$row) {
                    if ($this->options['autofree']) {
                        $this->freeResult($result);
                    }
                    return null;
                }
            }
            $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $rownum);
        } else {
            if ($fetchmode & MDB_FETCHMODE_ASSOC) {
                $row = @ibase_fetch_assoc($result);
            } else {
                $row = @ibase_fetch_row($result);
            }
            foreach ($row as $key => $value_with_space) {
                $row[$key] = rtrim($value_with_space);
            }
            $this->results[$result_value][$this->results[$result_value]['current_row']+1] = $row;
            if (!$row) {
                if ($this->options['autofree']) {
                    $this->freeResult($result);
                }
                return null;
            }
        }
        if (isset($this->results[intval($result)]['types'])) {
            $row = $this->datatype->convertResultRow($this, $result, $row);
        }
        return $row;
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
            $result_value = intval($result);
            if (!isset($this->results[$result_value]['rows'])) {
                if (MDB::isError($getcolumnnames = $this->getColumnNames($result))) {
                    return $getcolumnnames;
                }
                if (isset($this->results[$result_value]['limits'])) {
                    if (MDB::isError($skipfirstrow = $this->_skipLimitOffset($result))) {
                        $this->results[$result_value]['rows'] = 0;
                        return $skipfirstrow;
                    }
                    $limit = $this->results[$result_value]['limits'][1];
                } else {
                    $limit = 0;
                }
                if ($limit == 0 || $this->results[$result_value]['current_row'] + 1 < $limit) {
                    if (isset($this->results[$result_value]['row_buffer'])) {
                        $this->results[$result_value]['current_row']++;
                        $this->results[$result_value][$this->results[$result_value]['current_row']] = $this->results[$result_value]['row_buffer'];
                        unset($this->results[$result_value]['row_buffer']);
                    }
                    for (;($limit == 0 || $this->results[$result_value]['current_row'] + 1 < $limit) && $this->results[$result_value][$this->results[$result_value]['current_row'] + 1] = @ibase_fetch_row($result);$this->results[$result_value]['current_row']++);
                }
                $this->results[$result_value]['rows'] = $this->results[$result_value]['current_row'] + 1;
            }
            return $this->results[$result_value]['rows'];
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'Number of rows: nut supported if option "result_buffering" is not enabled');
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
            return @ibase_free_result($result);
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
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->_doQuery("SELECT GEN_ID($sequence_name, 1) as the_value FROM RDB\$DATABASE");
        $this->popExpect();
        if ($ondemand && MDB::isError($result)
            //&& $result->getCode() == MDB_ERROR_NOSUCHTABLE
            )
        {
            // Since we are create the sequence on demand
            // we know the first id = 1 so initialize the
            // sequence at 2
            $result = $this->createSequence($seq_name, 2);
            if (MDB::isError($result)) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'Next ID: on demand sequence could not be created');
            } else {
                // First ID of a newly created sequence is 1
                return 1;
            }
        }
        return $this->fetchOne($result);
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
        if (MDB::isError($result = $this->queryOne("SELECT RDB\$GENERATOR_ID FROM RDB\$GENERATORS WHERE RDB\$GENERATOR_NAME='$seqname'"))) {
            return $this->raiseError(MDB_ERROR, null, null, 'currId: Unable to select from ' . $seqname) ;
        }
        if (!is_numeric($result)) {
            return $this->raiseError(MDB_ERROR, null, null, 'currId: could not find value in sequence table');
        }
        return $result;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal ibase result pointer to the next available result
     *
     * @param a valid ibase result resource
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
     * @param  mixed $resource FireBird/InterBase result identifier or table name
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
            $id = ibase_query($this->connection,"SELECT * FROM $result");
            if (empty($id)) {
                return $this->ibaseRaiseError();
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return $this->ibaseRaiseError();
            }
        }

        $count = @ibase_num_fields($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {
            for ($i=0; $i<$count; $i++) {
                $info = @ibase_field_info($id, $i);
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name']  = $info['name'];
                $res[$i]['type']  = $info['type'];
                $res[$i]['len']   = $info['length'];
                $res[$i]['flags'] = (is_string($result)) ? $this->_ibaseFieldFlags($info['name'], $result) : '';
            }
        } else { // full
            $res['num_fields'] = $count;

            for ($i=0; $i<$count; $i++) {
                $info = @ibase_field_info($id, $i);
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name']  = $info['name'];
                $res[$i]['type']  = $info['type'];
                $res[$i]['len']   = $info['length'];
                $res[$i]['flags'] = (is_string($result)) ? $this->_ibaseFieldFlags($info['name'], $result) : '';
                if ($mode & DB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & DB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }

        // free the result only if we were called on a table
        if (is_string($result) && is_resource($id)) {
            @ibase_free_result($id);
        }
        return $res;
    }

    // }}}
    // {{{ _ibaseFieldFlags()

    /**
     * get the Flags of a Field
     *
     * @param int $resource FireBird/InterBase result identifier
     * @param int $num_field the field number
     * @return string The flags of the field ('not_null', 'default_xx', 'primary_key',
     *                 'unique' and 'multiple_key' are supported)
     * @access private
     **/
    function _ibaseFieldFlags($resource, $num_field, $table_name)
    {
        $sql = 'SELECT  R.RDB$CONSTRAINT_TYPE CTYPE'
               .' FROM  RDB$INDEX_SEGMENTS I'
               .' JOIN  RDB$RELATION_CONSTRAINTS R ON I.RDB$INDEX_NAME=R.RDB$INDEX_NAME'
               .' WHERE I.RDB$FIELD_NAME=\''.$field_name.'\''
               .' AND   R.RDB$RELATION_NAME=\''.$table_name.'\'';
        $result = ibase_query($this->connection, $sql);
        if (empty($result)) {
            return $this->ibaseRaiseError();
        }
        if ($obj = @ibase_fetch_object($result)) {
            ibase_free_result($result);
            if (isset($obj->CTYPE)  && trim($obj->CTYPE) == 'PRIMARY KEY') {
                $flags = 'primary_key ';
            }
            if (isset($obj->CTYPE)  && trim($obj->CTYPE) == 'UNIQUE') {
                $flags .= 'unique_key ';
            }
        }

        $sql = 'SELECT  R.RDB$null_FLAG AS NFLAG,'
                     .' R.RDB$DEFAULT_SOURCE AS DSOURCE,'
                     .' F.RDB$FIELD_TYPE AS FTYPE,'
                     .' F.RDB$COMPUTED_SOURCE AS CSOURCE'
               .' FROM  RDB$RELATION_FIELDS R '
               .' JOIN  RDB$FIELDS F ON R.RDB$FIELD_SOURCE=F.RDB$FIELD_NAME'
              .' WHERE  R.RDB$RELATION_NAME=\''.$table_name.'\''
                .' AND  R.RDB$FIELD_NAME=\''.$field_name.'\'';
        $result = ibase_query($this->connection, $sql);
        if (empty($result)) {
            return $this->ibaseRaiseError();
        }
        if ($obj = @ibase_fetch_object($result)) {
            ibase_free_result($result);
            if (isset($obj->NFLAG)) {
                $flags .= 'not_null ';
            }
            if (isset($obj->DSOURCE)) {
                $flags .= 'default ';
            }
            if (isset($obj->CSOURCE)) {
                $flags .= 'computed ';
            }
            if (isset($obj->FTYPE)  && $obj->FTYPE == 261) {
                $flags .= 'blob ';
            }
        }

         return trim($flags);
    }
}
?>