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
    var $connection = 0;
    var $connected_user;
    var $connected_password;

    var $escape_quotes = "'";

    var $auto_commit = 1;
    var $uncommitedqueries = 0;

    var $results = array();
    var $current_row = array();
    var $columns = array();
    var $rows = array();
    var $limits = array();
    var $row_buffer = array();
    var $highest_fetched_row = array();

    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_oci8()
    {
        $this->MDB_Common();
        $this->phptype = 'oci8';
        $this->dbsyntax = 'oci8';
        
        $this->supported['Sequences'] = 1;
        $this->supported['Indexes'] = 1;
        $this->supported['SummaryFunctions'] = 1;
        $this->supported['OrderByText'] = 1;
        $this->supported["AffectedRows"]= 1;
        $this->supported['Transactions'] = 1;
        $this->supported['SelectRowRanges'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['Replace'] = 1;
        $this->supported['SubSelects'] = 1;
        
        $this->options['DBAUser'] = false;
        $this->options['DBAPassword'] = false;
        
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
        $this->debug('autoCommit', ($auto_commit ? 'On' : 'Off'));
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
        $this->debug('commit', 'commit transaction');
        if (!isset($this->supported['Transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'Commit transactions: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
            'Commit transactions: transaction changes are being auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCICommit($this->connection)) {
                return $this->oci8RaiseError(null,
                    'Commit transactions: Could not commit pending transaction: '."$message. Error: ".$error['code'].' ('.$error['message'].')');
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
        $this->debug('rollback', 'rolling back transaction');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Rollback transactions: transactions can not be rolled back when changes are auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCIRollback($this->connection)) {
                return $this->oci8RaiseError(null,
                    'Rollback transaction: Could not rollback pending transaction');
            }
            $this->uncommitedqueries = 0;
        }
        return MDB_OK;
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     * 
     * @return true on success, MDB_Error on failure
     */
    function connect($user = null , $password = null, $persistent = null)
    {
        if ($user === null) {
            $user = $this->user;
        }
        if ($password === null) {
            $password = $this->password;
        }
        if ($persistent === null) {
            $persistent = $this->options['persistent'];
        }
        if (isset($this->host)) {
            $sid = $this->host;
        } else {
            $sid = getenv('ORACLE_SID');
        }
        if (!strcmp($sid, '')) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Connect: it was not specified a valid Oracle Service IDentifier (SID)');
        }
        if ($this->connection != 0) {
            if (!strcmp($this->connected_user, $user)
                && !strcmp($this->connected_password, $password)
                && $this->opened_persistent == $persistent)
            {
                return MDB_OK;
            }
            $this->_close();
        }

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND,
                null, null, 'extension '.$this->phptype.' is not compiled into PHP',
                'MDB_Error', true);
        }

        if (isset($this->options['HOME'])) {
            putenv('ORACLE_HOME='.$this->options['HOME']);
        }
        putenv('ORACLE_SID='.$sid);
        $function = ($persistent ? 'OCIPLogon' : 'OCINLogon');
        if (!function_exists($function)) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Connect: Oracle OCI API support is not available in this PHP configuration');
        }
        if (!($this->connection = @$function($user, $password, $sid))) {
            return $this->oci8RaiseError(null,
                'Connect: Could not connect to Oracle server');
        }
        if (MDB::isError($doquery = $this->_doQuery("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'"))) {
            $this->_close();
            return $doquery;
        }
        if (MDB::isError($doquery = $this->_doQuery("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='. '"))) {
            $this->_close();
            return $doquery;
        }

        $this->connected_user = $user;
        $this->connected_password = $password;
        $this->opened_persistent = $persistent;
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
    // {{{ daQuery()

    /**
     * all the RDBMS specific things needed close a DB connection
     * 
     * @access private 
     */

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
                $clob++, next($this->clobs[$prepared_query]))
            {
                $position = key($this->clobs[$prepared_query]);
                if (gettype($descriptors[$position] = @OCINewDescriptor($this->connection, OCI_D_LOB)) != 'object') {
                    $success = $this->raiseError(MDB_ERROR, null, null,
                        'Do query: Could not create descriptor for clob parameter');
                    break;
                }
                $columns.= ($lobs == 0 ? ' RETURNING ' : ',').$this->prepared_queries[$prepared_query-1]['Fields'][$position-1];
                $variables.= ($lobs == 0 ? ' INTO ' : ',').':clob'.$position;
                $lobs++;
            }
            if (!MDB::isError($success)) {
                for(reset($this->blobs[$prepared_query]), $blob = 0;$blob < count($this->blobs[$prepared_query]);$blob++, next($this->blobs[$prepared_query])) {
                    $position = key($this->blobs[$prepared_query]);
                    if (gettype($descriptors[$position] = @OCINewDescriptor($this->connection, OCI_D_LOB)) != 'object') {
                        $success = $this->raiseError(MDB_ERROR, null, null,
                            'Do query: Could not create descriptor for blob parameter');
                        break;
                    }
                    $columns.= ($lobs == 0 ? ' RETURNING ' : ',').$this->prepared_queries[$prepared_query-1]['Fields'][$position-1];
                    $variables.= ($lobs == 0 ? ' INTO ' : ',').':blob'.$position;
                    $lobs++;
                }
                $query.= $columns.$variables;
            }
        }
        if (!MDB::isError($success)) {
            if (($statement = @OCIParse($this->connection, $query))) {
                if ($lobs) {
                    for(reset($this->clobs[$prepared_query]), $clob = 0;$clob < count($this->clobs[$prepared_query]);$clob++, next($this->clobs[$prepared_query])) {
                        $position = key($this->clobs[$prepared_query]);
                        if (!@OCIBindByName($statement, ':clob'.$position, $descriptors[$position], -1, OCI_B_CLOB)) {
                            $success = $this->oci8RaiseError(null,
                                'Do query: Could not bind clob upload descriptor');
                            break;
                        }
                    }
                    if (!MDB::isError($success)) {
                        for(reset($this->blobs[$prepared_query]), $blob = 0;
                            $blob < count($this->blobs[$prepared_query]);
                            $blob++, next($this->blobs[$prepared_query]))
                        {
                            $position = key($this->blobs[$prepared_query]);
                            if (!@OCIBindByName($statement, ':blob'.$position, $descriptors[$position], -1, OCI_B_BLOB)) {
                                $success = $this->oci8RaiseError(null,
                                    'Do query: Could not bind blob upload descriptor');
                                break;
                            }
                        }
                    }
                }
                if (!MDB::isError($success)) {
                    if (($result = @OCIExecute($statement, ($lobs == 0 && $this->auto_commit) ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT))) {
                        if ($lobs) {
                            for(reset($this->clobs[$prepared_query]), $clob = 0;
                                $clob < count($this->clobs[$prepared_query]);
                                $clob++, next($this->clobs[$prepared_query]))
                            {
                                $position = key($this->clobs[$prepared_query]);
                                $clob_stream = $this->prepared_queries[$prepared_query-1]['Values'][$position-1];
                                for($value = '';!$this->endOfLOB($clob_stream);) {
                                    if ($this->readLOB($clob_stream, $data, $this->options['lob_buffer_length']) < 0) {
                                        $success = $this->raiseError();
                                        break;
                                    }
                                    $value.= $data;
                                }
                                if (!MDB::isError($success) && !$descriptors[$position]->save($value)) {
                                    $success = $this->oci8RaiseError(null,
                                        'Do query: Could not upload clob data');
                                }
                            }
                            if (!MDB::isError($success)) {
                                for(reset($this->blobs[$prepared_query]), $blob = 0;$blob < count($this->blobs[$prepared_query]);$blob++, next($this->blobs[$prepared_query])) {
                                    $position = key($this->blobs[$prepared_query]);
                                    $blob_stream = $this->prepared_queries[$prepared_query-1]['Values'][$position-1];
                                    for($value = '';!$this->endOfLOB($blob_stream);) {
                                        if ($this->readLOB($blob_stream, $data, $this->options['lob_buffer_length']) < 0) {
                                            $success = $this->raiseError();
                                            break;
                                        }
                                        $value.= $data;
                                    }
                                    if (!MDB::isError($success) && !$descriptors[$position]->save($value)) {
                                        $success = $this->oci8RaiseError(null,
                                                'Do query: Could not upload blob data');
                                    }
                                }
                            }
                        }
                        if ($this->auto_commit) {
                            if ($lobs) {
                                if (MDB::isError($success)) {
                                    if (!@OCIRollback($this->connection)) {
                                        $success = $this->oci8RaiseError(null,
                                            'Do query: '.$success->getUserinfo().' and then could not rollback LOB updating transaction');
                                    }
                                } else {
                                    if (!@OCICommit($this->connection)) {
                                        $success = $this->oci8RaiseError(null,
                                            'Do query: Could not commit pending LOB updating transaction');
                                    }
                                }
                            }
                        } else {
                            $this->uncommitedqueries++;
                        }
                        if (!MDB::isError($success)) {
                            switch (@OCIStatementType($statement)) {
                                case 'SELECT':
                                    $result_value = intval($statement);
                                    $this->results[$result_value]['current_row'] = -1;
                                    if ($limit > 0) {
                                        $this->limits[$result_value] = array($first, $limit, 0);
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
                        return $this->oci8RaiseError($statement, 'Do query: Could not execute query');
                    }
                }
            } else {
                return $this->oci8RaiseError(null, 'Do query: Could not parse query');
            }
        }
        for(reset($descriptors), $descriptor = 0;
            $descriptor < count($descriptors);
            $descriptor++, next($descriptors))
        {
            @OCIFreeDesc($descriptors[key($descriptors)]);
        }
        return $result;
    }

    // }}}
    // {{{ query()

   /**
     * Send a query to the database and return any results
     * 
     * @access public 
     * @param string $query the SQL query
     * @param array $types array that contains the types of the columns in
     *                         the result set
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     */
    function query($query, $types = null)
    {
        $this->debug('query', $query);
        $this->last_query = $query;
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (MDB::isError($connect = $this->connect())) {
            return $connect;
        }
        if (!MDB::isError($result = $this->_doQuery($query, $first, $limit))) {
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
        return $this->oci8RaiseError();
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
        $first = $this->limits[$result_value][0];
        for(;$this->limits[$result_value][2] < $first;$this->limits[$result_value][2]++) {
            if (!@OCIFetch($result)) {
                $this->limits[$result_value][2] = $first;
                return $this->raiseError(MDB_ERROR, null, null,
                    'Skip first rows: could not skip a query result row');
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
         if (gettype($field) == 'integer') {
            if (($column = $field) < 0
                || $column >= count($this->results[$result_value]['columns']))
            {
                return $this->raiseError('Get column: attempted to fetch an query result column out of range');
            }
        } else {
            $name = strtolower($field);
            if (!isset($this->results[$result_value]['columns'][$name])) {
                return $this->raiseError('Get column: attempted to fetch an unknown query result column');
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
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Get column names: it was specified an inexisting result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = @OCINumCols($result);
            for($column = 0; $column < $columns; $column++) {
                $this->results[$result_value]['columns'][strtolower(@OCIColumnName($result, $column + 1))] = $column;
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
        if (!isset($this->results[intval($result)]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Number of columns: it was specified an inexisting result set');
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
        $result_value = intval($result);
        if (!isset($this->results[$result_value]['current_row'])) {
            return $this->raiseError(MDB_ERROR, null, null,
                'End of result: attempted to check the end of an unknown result');
        }
        if (isset($this->rows[$result_value])) {
            return $this->results[$result_value]['highest_fetched_row'] >= $this->rows[$result_value]-1;
        }
        if (isset($this->results[$result_value]['row_buffer'])) {
            return false;
        }
        if (isset($this->limits[$result_value])) {
            if (MDB::isError($this->_skipLimitOffset($result)) || $this->results[$result_value]['current_row'] + 1 >= $this->limits[$result_value][1]) {
                $this->rows[$result_value] = 0;
                return true;
            }
        }
        if (@OCIFetchInto($result, $this->results[$result_value]['row_buffer'])) {
            return false;
        }
        unset($this->results[$result_value]['row_buffer']);
        $this->rows[$result_value] = $this->results[$result_value]['current_row'] + 1;
        return true;
    }

    // }}}
    // {{{ resultIsNull()

    /**
     * Determine whether the value of a query result located in given row and
     *    field is a null.
     * 
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed true or false on success, a MDB error on failure
     * @access public 
     */
    function resultIsNull($result, $row, $field)
    {
        $result_value = intval($result);
        if (MDB::isError($column = $this->_getColumn($result, $field))) {
            return $column;
        }
        if (MDB::isError($fetchrow = $this->fetchRow($result, MDB_FETCHMODE_ORDERED, $row))) {
            return $fetchrow;
        }
        $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $row);
        return !isset($this->results[$result_value][$row][$column]);
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
            if (!isset($this->rows[$result_value])) {
                if (MDB::isError($getcolumnnames = $this->getColumnNames($result))) {
                    return $getcolumnnames;
                }
                if (isset($this->limits[$result_value])) {
                    if (MDB::isError($skipfirstrow = $this->_skipLimitOffset($result))) {
                        $this->rows[$result_value] = 0;
                        return $skipfirstrow;
                    }
                    $limit = $this->limits[$result_value][1];
                } else {
                    $limit = 0;
                }
                if ($limit == 0 || $this->results[$result_value]['current_row'] + 1 < $limit) {
                    if (isset($this->results[$result_value]['row_buffer'])) {
                        $this->results[$result_value]['current_row']++;
                        $this->results[$result_value][$this->results[$result_value]['current_row']] = $this->results[$result_value]['row_buffer'];
                        unset($this->results[$result_value]['row_buffer']);
                    }
                    for(;($limit == 0 || $this->results[$result_value]['current_row'] + 1 < $limit) && @OCIFetchInto($result, $this->results[$result_value][$this->results[$result_value]['current_row'] + 1]);$this->results[$result_value]['current_row']++);
                }
                $this->rows[$result_value] = $this->results[$result_value]['current_row'] + 1;
            }
            return $this->rows[$result_value];
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'Number of rows: nut supported if option "result_buffering" is not enabled');
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
        $result_value = intval($result);
        if (isset($this->results[$result_value])) {
            unset($this->results[$result_value]);
        }
        if (is_resource($result)) {
            return @OCIFreeCursor($result);
        }

        return $this->raiseError(MDB_ERROR, null, null,
            'Free result: attemped to free an unknown query result');
    }

    // }}}
    // {{{ nextId()

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
    function nextId($seq_name, $ondemand = true)
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
                $this->loadManager();
                // Since we are creating the sequence on demand
                // we know the first id = 1 so initialize the
                // sequence at 2
                $result = $this->manager->createSequence($this, $seq_name, 2);
                if (MDB::isError($result)) {
                    return $this->raiseError(MDB_ERROR, null, null,
                        'Next ID: on demand sequence could not be created');
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }
            }
            return $result;
        }
        $value = $this->fetchOne($result);
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
    function fetch($result, $rownum, $field)
    {
        $result_value = intval($result);
        if (MDB::isError($column = $this->_getColumn($result, $field))) {
            return $column;
        }
        if (MDB::isError($row = $this->fetchRow($result, MDB_FETCHMODE_ORDERED, $rownum))) {
            return $row;
        }
        return $row[$column]);
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and insert the data into an existing array.
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
                if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                    $moredata = @OCIFetchInto($result, $this->results[$result_value][$this->results[$result_value]['current_row']+1], OCI_ASSOC+OCI_RETURN_NULLS+OCI_RETURN_LOBS);
                } else {
                    $moredata = @OCIFetchInto($result, $this->results[$result_value][$this->results[$result_value]['current_row']+1], OCI_RETURN_NULLS+OCI_RETURN_LOBS);
                }
                if (!$moredata) {
                    if ($this->options['autofree']) {
                        $this->freeResult($result);
                    }
                    return null;
                }
            }
            $row = $this->results[$result_value][$rownum];
            $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $rownum);
        } else {
            if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                $moredata = @OCIFetchInto($result, $row, OCI_ASSOC+OCI_RETURN_NULLS+OCI_RETURN_LOBS);
            } else {
                $moredata = @OCIFetchInto($result, $row, OCI_RETURN_NULLS+OCI_RETURN_LOBS);
            }
            if (!$moredata) {
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
    // {{{ nextResult()

    /**
     * Move the internal mysql result pointer to the next available result
     * Currently not supported
     * 
     * @param a $ valid result resource
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
     * @param resource $result result identifier
     * @param mixed $mode depends on implementation
     * @return array an nested array, or a MDB error
     * @access public 
     */
    function tableInfo($result, $mode = null)
    {
        $count = 0;
        $res = array();
        /**
         * depending on $mode, metadata returns the following values:
         * 
         * - mode is false (default):
         * $res[]:
         *    [0]['table']       table name
         *    [0]['name']        field name
         *    [0]['type']        field type
         *    [0]['len']         field length
         *    [0]['nullable']    field can be null (boolean)
         *    [0]['format']      field precision if NUMBER
         *    [0]['default']     field default value
         * 
         * - mode is MDB_TABLEINFO_ORDER
         * $res[]:
         *    ['num_fields']     number of fields
         *    [0]['table']       table name
         *    [0]['name']        field name
         *    [0]['type']        field type
         *    [0]['len']         field length
         *    [0]['nullable']    field can be null (boolean)
         *    [0]['format']      field precision if NUMBER
         *    [0]['default']     field default value
         *    ['order'][field name] index of field named 'field name'
         *    The last one is used, if you have a field name, but no index.
         *    Test:  if (isset($result['order']['myfield'])) { ...
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
         *       you can combine DB_TABLEINFO_ORDER and
         *       MDB_TABLEINFO_ORDERTABLE with MDB_TABLEINFO_ORDER |
         *       MDB_TABLEINFO_ORDERTABLE * or with MDB_TABLEINFO_FULL
         */ 
        // if $result is a string, we collect info for a table only
        if (is_string($result)) {
            $result = strtoupper($result);
            $q_fields = "select column_name, data_type, data_length, data_precision,
                     nullable, data_default from user_tab_columns
                     where table_name='$result' order by column_id";
            if (!$stmt = @OCIParse($this->connection, $q_fields)) {
                return $this->oci8RaiseError();
            }
            if (!@OCIExecute($stmt, OCI_DEFAULT)) {
                return $this->oci8RaiseError($stmt);
            } while (@OCIFetch($stmt)) {
                $res[$count]['table'] = $result;
                $res[$count]['name'] = @OCIResult($stmt, 1);
                $res[$count]['type'] = @OCIResult($stmt, 2);
                $res[$count]['len'] = @OCIResult($stmt, 3);
                $res[$count]['format'] = @OCIResult($stmt, 4);
                $res[$count]['nullable'] = (@OCIResult($stmt, 5) == 'Y') ? true : false;
                $res[$count]['default'] = @OCIResult($stmt, 6);
                if ($mode & MDB_TABLEINFO_ORDER) {
                    $res['order'][$res[$count]['name']] = $count;
                }
                if ($mode & MDB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$count]['table']][$res[$count]['name']] = $count;
                }
                $count++;
            }
            $res['num_fields'] = $count;
            @OCIFreeStatement($stmt);
        } else { // else we want information about a resultset
            #if ($result === $this->last_stmt) {
                $count = @OCINumCols($result);
                for ($i = 0; $i < $count; $i++) {
                    $res[$i]['name'] = @OCIColumnName($result, $i + 1);
                    $res[$i]['type'] = @OCIColumnType($result, $i + 1);
                    $res[$i]['len'] = @OCIColumnSize($result, $i + 1);

                    $q_fields = "select table_name, data_precision, nullable, data_default from user_tab_columns where column_name='".$res[$i]['name']."'";
                    if (!$stmt = @OCIParse($this->connection, $q_fields)) {
                        return $this->oci8RaiseError();
                    }
                    if (!@OCIExecute($stmt, OCI_DEFAULT)) {
                        return $this->oci8RaiseError($stmt);
                    }
                    @OCIFetch($stmt);
                    $res[$i]['table'] = @OCIResult($stmt, 1);
                    $res[$i]['format'] = @OCIResult($stmt, 2);
                    $res[$i]['nullable'] = (@OCIResult($stmt, 3) == 'Y') ? true : false;
                    $res[$i]['default'] = @OCIResult($stmt, 4);
                    @OCIFreeStatement($stmt);

                    if ($mode & MDB_TABLEINFO_ORDER) {
                        $res['order'][$res[$i]['name']] = $i;
                    }
                    if ($mode & MDB_TABLEINFO_ORDERTABLE) {
                        $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                    }
                }
                $res['num_fields'] = $count;
            #} else {
            #    return $this->raiseError(MDB_ERROR_NOT_CAPABLE);
            #}
        }
        return $res;
    }
}

?>