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

// $Id$

require_once 'MDB/Common.php';

/**
 * MDB OCI8 driver
 * 
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com> 
 */
class MDB_oci8 extends MDB_Common
{
    // {{{ properties
    var $escape_quotes = "'";

    var $auto_commit = 1;
    var $uncommitedqueries = 0;

    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_oci8()
    {
        $this->MDB_Common();
        $this->phptype = 'oci8';
        $this->dbsyntax = 'oci8';
        
        $this->supported['sequences'] = 1;
        $this->supported['indexes'] = 1;
        $this->supported['summary_functions'] = 1;
        $this->supported['order_by_text'] = 1;
        $this->supported['affected_rows']= 1;
        $this->supported['transactions'] = 1;
        $this->supported['limit_queries'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['replace'] = 1;
        $this->supported['sub_selects'] = 1;
        
        $this->options['DBA_username'] = false;
        $this->options['DBA_password'] = false;
        $this->options['default_tablespace'] = false;
        $this->options['HOME'] = false;
        $this->options['default_text_field_length'] = 4000;
        
        $this->errorcode_map = array(
            900 => MDB_ERROR_SYNTAX,
            904 => MDB_ERROR_NOSUCHFIELD,
            923 => MDB_ERROR_SYNTAX,
            942 => MDB_ERROR_NOSUCHTABLE,
            2289 => MDB_ERROR_NOSUCHTABLE,
            955 => MDB_ERROR_ALREADY_EXISTS,
            1476 => MDB_ERROR_DIVZERO,
            1722 => MDB_ERROR_INVALID_NUMBER,
            2289 => MDB_ERROR_NOSUCHTABLE,
            2291 => MDB_ERROR_CONSTRAINT,
            2449 => MDB_ERROR_CONSTRAINT,
        );
    }

    // }}}
    // {{{ errorNative()

    /**
     * Get the native error code of the last error (if any) that
     * occured on the current connection.
     * 
     * @access public 
     * @return int native oci8 error code
     */
    function errorNative($statement = null)
    {
        if (is_resource($statement)) {
            $error = @OCIError($statement);
        } else {
            $error = @OCIError($this->connection);
        }
        if (is_array($error)) {
            return $error['code'];
        }
        return false;
    }

    // }}}
    // {{{ oci8RaiseError()

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
    function oci8RaiseError($errno = null, $message = null)
    {
        if ($errno === null) {
            $error = @OCIError($this->connection);
            return $this->raiseError($this->errorCode($error['code']),
                null, null, null, $error['message']);
        } elseif (is_resource($errno)) {
            $error = @OCIError($errno);
            return $this->raiseError($this->errorCode($error['code']),
                null, null, null, $error['message']);
        }
        return $this->raiseError($this->errorCode($errno), null, null, $message);
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     * 
     * @param boolean $auto_commit flag that indicates whether the database
     *                                 changes should be committed right after
     *                                 executing every query statement. If this
     *                                 argument is 0 a transaction implicitly
     *                                 started. Otherwise, if a transaction is
     *                                 in progress it is ended by committing any
     *                                 database changes that were pending.
     * @access public 
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function autoCommit($auto_commit)
    {
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        if (!$this->auto_commit == !$auto_commit) {
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
     * @access public 
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function commit()
    {
        $this->debug('commit transaction', 'commit');
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'commit: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
            'commit: transaction changes are being auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCICommit($this->connection)) {
                return $this->oci8RaiseError(null,
                    'commit: Could not commit pending transaction: '."$message. Error: ".$error['code'].' ('.$error['message'].')');
            }
            $this->uncommitedqueries = 0;
        }
        return MDB_OK;
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
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function rollback()
    {
        $this->debug('rolling back transaction', 'rollback');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCIRollback($this->connection)) {
                return $this->oci8RaiseError(null,
                    'rollback: Could not rollback pending transaction');
            }
            $this->uncommitedqueries = 0;
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _doConnect()

    /**
     * do the grunt work of the connect
     * 
     * @return connection on success or MDB_Error on failure
     * @access private
     */
    function _doConnect($username, $password)
    {
        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return $this->raiseError(MDB_ERROR_NOT_FOUND, null, null,
                'extension '.$this->phptype.' is not compiled into PHP');
        }

        if (isset($this->dsn['hostspec'])) {
            $sid = $this->dsn['hostspec'];
        } else {
            $sid = getenv('ORACLE_SID');
        }
        if (empty($sid)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'it was not specified a valid Oracle Service IDentifier (SID)');
        }

        if ($this->options['HOME']) {
            putenv('ORACLE_HOME='.$this->options['HOME']);
        }
        putenv('ORACLE_SID='.$sid);

        $function = ($this->options['persistent'] ? 'OCIPLogon' : 'OCINLogon');
        $connection = $function($username, $password, $sid);
        if (!$connection) {
            $connection =  $this->oci8RaiseError(null,
                'Connect: Could not connect to Oracle server');
        }
        return $connection;
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     * 
     * @return MDB_OK on success, MDB_Error on failure
     * @access public 
     */
    function connect()
    {
        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && $this->opened_persistent == $this->options['persistent'])
            {
                return MDB_OK;
            }
            $this->_close();
        }

        $connection = $this->_doConnect($this->database_name, $this->dsn['password']);
        if (MDB::isError($connection)) {
            return $connection;
        }
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->opened_persistent = $this->options['persistent'];
        $doquery = $this->_doQuery("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'");
        if (MDB::isError($doquery)) {
            $this->_close();
            return $doquery;
        }
        $doquery = $this->_doQuery("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='. '");
        if (MDB::isError($doquery)) {
            $this->_close();
            return $doquery;
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _close()

    /**
     * all the RDBMS specific things needed close a DB connection
     * 
     * @access private 
     */
    function _close()
    {
        if ($this->connection != 0) {
            @OCILogOff($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            $this->uncommitedqueries = 0;
        }
    }

    // }}}
    // {{{ _doQuery()

    /**
     * Execute a query
     * @param string $query the SQL query
     * @return mixed result identifier if query executed, else MDB_error
     * @access private
     **/
    function _doQuery($query, $first = 0, $limit = 0, $prepared_query = 0)
    {
        $lobs = 0;
        $success = MDB_OK;
        $result = 0;
        $descriptors = array();

        if ($prepared_query) {
            $columns = '';
            $variables = '';
            for(reset($this->clobs[$prepared_query]), $clob = 0;
                $clob < count($this->clobs[$prepared_query]);
                $clob++, next($this->clobs[$prepared_query])
            ) {
                $clob_stream = key($this->clobs[$prepared_query]);
                if (!is_object($descriptors[$clob_stream] = @OCINewDescriptor($this->connection, OCI_D_LOB))) {
                    $success = $this->raiseError(MDB_ERROR, null, null,
                        'Could not create descriptor for clob parameter');
                    break;
                }
                $parameter = $GLOBALS['_MDB_LOBs'][$clob_stream]->parameter;
                $columns.= ($lobs == 0 ? ' RETURNING ' : ',').
                    $this->prepared_queries[$prepared_query-1]['fields'][$parameter-1];
                $variables.= ($lobs == 0 ? ' INTO ' : ',').':clob'.$parameter;
                $lobs++;
            }
            if (!MDB::isError($success)) {
                for(reset($this->blobs[$prepared_query]), $blob = 0;
                    $blob < count($this->blobs[$prepared_query]);
                    $blob++, next($this->blobs[$prepared_query])
                ) {
                    $blob_stream = key($this->blobs[$prepared_query]);
                    if (!is_object($descriptors[$blob_stream] = @OCINewDescriptor($this->connection, OCI_D_LOB))) {
                        $success = $this->raiseError(MDB_ERROR, null, null,
                            'Could not create descriptor for blob parameter');
                        break;
                    }
                    $parameter = $GLOBALS['_MDB_LOBs'][$blob_stream]->parameter;
                    $columns.= ($lobs == 0 ? ' RETURNING ' : ',').
                        $this->prepared_queries[$prepared_query-1]['fields'][$parameter-1];
                    $variables.= ($lobs == 0 ? ' INTO ' : ',').':blob'.$parameter;
                    $lobs++;
                }
                $query.= $columns.$variables;
            }
        }

        if (!MDB::isError($success)) {
            if (($statement = @OCIParse($this->connection, $query))) {
                if ($lobs) {
                    for(reset($this->clobs[$prepared_query]), $clob = 0;
                        $clob < count($this->clobs[$prepared_query]);
                        $clob++, next($this->clobs[$prepared_query])
                    ) {
                        $clob_stream = key($this->clobs[$prepared_query]);
                        $parameter = $GLOBALS['_MDB_LOBs'][$clob_stream]->parameter;
                        if (!OCIBindByName($statement, ':clob'.$parameter, $descriptors[$clob_stream], -1, OCI_B_CLOB)) {
                            $success = $this->oci8RaiseError(null,
                                'Could not bind clob upload descriptor');
                            break;
                        }
                    }
                    if (!MDB::isError($success)) {
                        for(reset($this->blobs[$prepared_query]), $blob = 0;
                            $blob < count($this->blobs[$prepared_query]);
                            $blob++, next($this->blobs[$prepared_query]))
                        {
                            $blob_stream = key($this->blobs[$prepared_query]);
                            $parameter = $GLOBALS['_MDB_LOBs'][$blob_stream]->parameter;
                            if (!OCIBindByName($statement, ':blob'.$parameter, $descriptors[$blob_stream], -1, OCI_B_BLOB)) {
                                $success = $this->oci8RaiseError(null,
                                    'Could not bind blob upload descriptor');
                                break;
                            }
                        }
                    }
                }
                if (!MDB::isError($success)) {
                    $mode = ($lobs == 0 && $this->auto_commit) ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT;
                    $result = @OCIExecute($statement, $mode);
                    if ($result) {
                        if ($lobs) {
                            for(reset($this->clobs[$prepared_query]), $clob = 0;
                                $clob < count($this->clobs[$prepared_query]);
                                $clob++, next($this->clobs[$prepared_query]))
                            {
                                $clob_stream = key($this->clobs[$prepared_query]);
                                for($value = ''; !$this->datatype->endOfLOB($clob_stream);) {
                                    if ($this->datatype->readLOB($clob_stream, $data, $this->options['lob_buffer_length']) < 0) {
                                        $success = $this->raiseError();
                                        break;
                                    }
                                    $value.= $data;
                                }
                                if (!MDB::isError($success) && !$descriptors[$clob_stream]->save($value)) {
                                    $success = $this->oci8RaiseError(null,
                                        'Could not upload clob data');
                                }
                            }
                            if (!MDB::isError($success)) {
                                for(reset($this->blobs[$prepared_query]), $blob = 0;
                                    $blob < count($this->blobs[$prepared_query]);
                                    $blob++, next($this->blobs[$prepared_query])
                                ) {
                                    $blob_stream = key($this->blobs[$prepared_query]);
                                    for($value = ''; !$this->datatype->endOfLOB($blob_stream);) {
                                        if ($this->datatype->readLOB($blob_stream, $data, $this->options['lob_buffer_length']) < 0) {
                                            $success = $this->raiseError();
                                            break;
                                        }
                                        $value.= $data;
                                    }
                                    if (!MDB::isError($success) && !$descriptors[$blob_stream]->save($value)) {
                                        $success = $this->oci8RaiseError(null,
                                            'Could not upload blob data');
                                    }
                                }
                            }
                        }
                        if ($this->auto_commit) {
                            if ($lobs) {
                                if (MDB::isError($success)) {
                                    if (!OCIRollback($this->connection)) {
                                        $success = $this->oci8RaiseError(null,
                                            $success->getUserinfo().' and then could not rollback LOB updating transaction');
                                    }
                                } else {
                                    if (!OCICommit($this->connection)) {
                                        $success = $this->oci8RaiseError(null,
                                            'Could not commit pending LOB updating transaction');
                                    }
                                }
                            }
                        } else {
                            $this->uncommitedqueries++;
                        }
                        if (!MDB::isError($success)) {
                            switch (OCIStatementType($statement)) {
                                case 'SELECT':
                                    $result_value = intval($statement);
                                    $this->results[$result_value]['current_row'] = -1;
                                    if ($limit > 0) {
                                        $this->results[$result_value]['limits'] = array($first, $limit, 0);
                                    }
                                    $this->results[$result_value]['highest_fetched_row'] = -1;
                                    break;
                                default:
                                    $this->affected_rows = @OCIRowCount($statement);
                                    @OCIFreeCursor($statement);
                                    break;
                            }
                            $result = $statement;
                        }
                    } else {
                        return $this->oci8RaiseError($statement, 'Could not execute query');
                    }
                }
            } else {
                return $this->oci8RaiseError(null, 'Could not parse query');
            }
        }
        for(reset($descriptors), $descriptor = 0;
            $descriptor < count($descriptors);
            $descriptor++, next($descriptors))
        {
            @$descriptors[key($descriptors)]->free();
        }
        if (MDB::isError($success)) {
            return $success;
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
        $connection = $this->_doConnect($this->options['DBA_username'], $this->options['DBA_password']);
        if (MDB::isError($connection)) {
            return $connection;
        }
        $result = @OCIParse($connection, $query);
        if (!$result) {
            return $this->oci8RaiseError('standaloneQuery: Could not query a Microsoft SQL server');
        }
        if ($this->auto_commit) {
            $success = @OCIExecute($result, OCI_COMMIT_ON_SUCCESS);
        } else {
            $success = @OCIExecute($result, OCI_DEFAULT);
        }
        if (!$success) {
            return $this->oci8RaiseError($result);
        }
        @OCILogOff(connection);
        return MDB_OK;
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
    * @access public
     */
    function &query($query, $types = null, $result_mode = null)
    {
        $this->debug($query, 'query');
        $this->last_query = $query;
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (MDB::isError($connect = $this->connect())) {
            return $connect;
        }

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
            $result =& $this->_return_result($result, $result_mode);
            return $result;
        }
        $error =& $this->oci8RaiseError();
        return $error;
    }

    // }}}
    // {{{ _executePrepared()

    /**
     * Execute a prepared query statement.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepare()
     * @param string $query query to be executed
     * @param array $types array that contains the types of the columns in
     *       the result set
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access private
     */
    function _executePrepared($prepared_query, $query)
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
        while ($this->results[$result_value]['limits'][2] < $first) {
            $this->results[$result_value]['limits'][2]++;
            if (!@OCIFetch($result)) {
                $this->results[$result_value]['limits'][2] = $first;
                return $this->raiseError(MDB_ERROR, null, null,
                    'could not skip a query result row');
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _getColumn()

    /**
     * Get key for a given field with a result set.
     *
     * @param resource $result
     * @param mixed $field integer or string key for the column
     * @return mixed column from the result handle or a MDB error on failure
     * @access private
     */
    function _getColumn($result, $field)
    {
        $result_value = intval($result);
        if (MDB::isError($names = $this->getColumnNames($result))) {
            return $names;
        }
         if (is_int($field)) {
            if ($field < 0 || $field >= count($names)) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'attempted to fetch an query result column out of range');
            }
            $column = $field;
        } else {
            $name = strtolower($field);
            if (!isset($this->results[$result_value]['columns'][$name])) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'attempted to fetch an unknown query result column');
            }
            $column = $this->results[$result_value]['columns'][$name];
        }
        return $column;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     * 
     * @param resource $result result identifier
     * @return mixed an associative array variable
     *                               that will hold the names of columns.The
     *                               indexes of the array are the column names
     *                               mapped to lower case and the values are the
     *                               respective numbers of the columns starting
     *                               from 0. Some DBMS may not return any
     *                               columns when the result set does not
     *                               contain any rows.
     * 
     *                               a MDB error on failure
     * @access public 
     */
    function getColumnNames($result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'getColumnNames: it was specified an inexisting result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = @OCINumCols($result);
            for($column = 0; $column < $columns; $column++) {
                $name = strtolower(@OCIColumnName($result, $column + 1));
                $this->results[$result_value]['columns'][$name] = $column;
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
     * @access public 
     * @return mixed integer value with the number of columns, a MDB error
     *                        on failure
     */
    function numCols($result)
    {
        if (!isset($this->results[intval($result)])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'numCols: it was specified an inexisting result set');
        }
        return @OCINumCols($result);
    }

    // }}}
    // {{{ endOfResult()

    /**
     * check if the end of the result set has been reached
     * 
     * @param resource $result result identifier
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
            if (isset($this->results[$result_value]['rows'])) {
                return $this->results[$result_value]['highest_fetched_row'] >=
                    $this->results[$result_value]['rows']-1;
            }
            if (isset($this->results[$result_value]['row_buffer'])) {
                return false;
            }
            if (isset($this->results[$result_value]['limits'])) {
                if (MDB::isError($this->_skipLimitOffset($result))
                    || $this->results[$result_value]['current_row'] + 1 >=
                        $this->results[$result_value]['limits'][1]
                ) {
                    $this->results[$result_value]['rows'] = 0;
                    return true;
                }
            }
            if (@OCIFetchInto($result, $this->results[$result_value]['row_buffer'], OCI_ASSOC+OCI_RETURN_NULLS)) {
                return false;
            }
            unset($this->results[$result_value]['row_buffer']);
            return true;
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'endOfResult: not supported if option "result_buffering" is not enabled');
    }

    // }}}
    // {{{ resultIsNull()

    /**
     * Determine whether the value of a query result located in given row and
     *    field is a null.
     * 
     * @param resource $result result identifier
     * @param int $rownum number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed true or false on success, a MDB error on failure
     * @access public 
     */
    function resultIsNull($result, $rownum, $field)
    {
        $value = $this->fetch($result, $rownum, $field);
        if (MDB::isError($value)) {
            return $value;
        }
        return !isset($value);
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
                if (isset($this->results[$result_value]['limits'])) {
                    $skipfirstrow = $this->_skipLimitOffset($result);
                    if (MDB::isError($skipfirstrow)) {
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
                        $this->results[$result_value][$this->results[$result_value]['current_row']] =
                            $this->results[$result_value]['row_buffer'];
                        unset($this->results[$result_value]['row_buffer']);
                    }
                    while (($limit == 0 || $this->results[$result_value]['current_row'] + 1 < $limit)
                        && @OCIFetchInto($result, $row, OCI_ASSOC+OCI_RETURN_NULLS)
                    ) {
                        $row = array_change_key_case($row);
                        $this->results[$result_value]['current_row']++;
                        $this->results[$result_value][$this->results[$result_value]['current_row']] = $row;
                    }
                }
                $this->results[$result_value]['rows'] = $this->results[$result_value]['current_row'] + 1;
            }
            return $this->results[$result_value]['rows'];
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'numRows: nut supported if option "result_buffering" is not enabled');
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     * 
     * @param  $result result identifier
     * @return bool true on success, false if $result is invalid
     * @access public 
     */
    function freeResult($result)
    {
        if (!is_resource($result)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'freeResult: attemped to free an unknown query result');
        }
        unset($this->results[intval($result)]);
        return @OCIFreeCursor($result);
    }

    // }}}
    // {{{ nextID()

    /**
     * returns the next free id of a sequence
     * 
     * @param string $seq_name name of the sequence
     * @param boolean $ondemand when true the seqence is
     *                           automatic created, if it
     *                           not exists
     * @return mixed MDB_Error or id
     * @access public 
     */
    function nextID($seq_name, $ondemand = true)
    {
        if (MDB::isError($connect = $this->connect())) {
            return $connect;
        }
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->_doQuery("SELECT $sequence_name.nextval FROM DUAL");
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
    // {{{ fetch()

    /**
     * fetch value from a result set
     *
     * @param resource $result result identifier
     * @param int $rownum number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed string on success, a MDB error on failure
     * @access public
     */
    function fetch($result, $rownum = 0, $field = 0)
    {
        if (MDB::isError($column = $this->_getColumn($result, $field))) {
            return $column;
        }
        $row = $this->fetchRow($result, MDB_FETCHMODE_ORDERED, $rownum);
        if (MDB::isError($row)) {
            return $row;
        }
        return $row[$column];
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and return data in an array.
     * 
     * @param resource $result result identifier
     * @param int $fetchmode how the array data should be indexed
     * @param int $rownum the row number to fetch
     * @return int data array on success, a MDB error on failure
     * @access public 
     */
    function fetchRow($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = null)
    {
        $result_value = intval($result);
        if ($this->options['result_buffering']) {
            if (is_null($rownum)) {
                $rownum = $this->results[$result_value]['highest_fetched_row'] + 1;
            }
            if (isset($this->results[$result_value][$rownum])) {
                $this->results[$result_value]['highest_fetched_row'] =
                    max($this->results[$result_value]['highest_fetched_row'], $rownum);
                if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                    $row = $this->results[$result_value][$rownum];
                } else {
                    $row = array_values($this->results[$result_value][$rownum]);
                }
                if (isset($this->results[$result_value]['types'])) {
                    $row = $this->datatype->convertResultRow($result, $row);
                }
                return $row;
            }
        }
        if (isset($this->results[$result_value]['limits'])) {
            if ($rownum >= $this->results[$result_value]['limits'][1]) {
                return null;
            }
            if (MDB::isError($this->_skipLimitOffset($result))) {
                return null;
            }
        }
        if ($this->options['result_buffering']) {
            if (isset($this->results[$result_value]['row_buffer'])) {
                $this->results[$result_value]['current_row']++;
                $this->results[$result_value][$this->results[$result_value]['current_row']] =
                    $this->results[$result_value]['row_buffer'];
                unset($this->results[$result_value]['row_buffer']);
            }
            while ($this->results[$result_value]['current_row'] < $rownum) {
                $this->results[$result_value]['current_row']++;
                $moredata = @OCIFetchInto($result, $row, OCI_ASSOC+OCI_RETURN_NULLS);
                if (!$moredata) {
                    return null;
                }
                $row = array_change_key_case($row);
                $this->results[$result_value][$this->results[$result_value]['current_row']] = $row;
            }
            if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                $row = $this->results[$result_value][$rownum];
            } else {
                $row = array_values($this->results[$result_value][$rownum]);
            }
            $this->results[$result_value]['highest_fetched_row'] =
                max($this->results[$result_value]['highest_fetched_row'], $rownum);
        } else {
            if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                $moredata = @OCIFetchInto($result, $row, OCI_ASSOC+OCI_RETURN_NULLS);
                $row = array_change_key_case($row);
            } else {
                $moredata = @OCIFetchInto($result, $row, OCI_RETURN_NULLS);
            }
            if (!$moredata) {
                return null;
            }
        }
        if (isset($this->results[$result_value]['types'])) {
            $row = $this->datatype->convertResultRow($result, $row);
        }
        return $row;
    }
}
?>