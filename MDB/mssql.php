<?php
// vim: set et ts=4 sw=4 fdm=marker:
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2003 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith, Frank M. Kromann                       |
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
// | Author: Frank M. Kromann <frank@kromann.info>                        |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'MDB/Common.php';

/**
 * MDB MSSQL Server driver
 *
 * @package MDB
 * @category Database
 * @author  Frank M. Kromann <frank@kromann.info>
 */
class MDB_mssql extends MDB_Common
{
    // {{{ properties
    var $escape_quotes = "'";

    // }}}
    // {{{ constructor

    /**
    * Constructor
    */
    function MDB_mssql()
    {
        $this->MDB_Common();
        $this->phptype = 'mssql';
        $this->dbsyntax = 'mssql';

        $this->supported['sequences'] = true;
        $this->supported['indexes'] = true;
        $this->supported['affected_rows'] = true;
        $this->supported['summary_functions'] = true;
        $this->supported['order_by_text'] = true;
        $this->supported['current_id'] = true;
        $this->supported['limit_queries'] = true;
        $this->supported['LOBs'] = true;
        $this->supported['replace'] = true;
        $this->supported['sub_selects'] = true;
        $this->supported['transactions'] = true;

        $db->options['database_device'] = false;
        $db->options['database_size'] = false;

        $this->errorcode_map = array(
            208   => MDB_ERROR_NOSUCHTABLE,
            3701  => MDB_ERROR_NOSUCHTABLE
        );
    }

    // }}}
    // {{{ errorCode()

    /**
     * Get the native error code of the last error (if any) that
     * occured on the current connection.
     *
     * @access public
     *
     * @return int native MSSQL error code
     */
    function errorCode()
    {
       $res = mssql_query('select @@ERROR as ErrorCode', $this->connection);
       if (!$res) {
           return MDB_ERROR;
       }
       $row = mssql_fetch_row($res);
       if ($row[0] > 0) {
           return $row[0];
       }
       else {
           return null;
       }
    }

    // }}}
    // {{{ mssqlRaiseError()

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
    function mssqlRaiseError($code = null)
    {
        $native_msg = mssql_get_last_message();
        $native_code = $this->errorCode();
        if ($code === null) {
            if (isset($this->errorcode_map[$native_code])) {
                $code = $this->errorcode_map[$native_code];
            } else {
                $code = MDB_ERROR;
            }
        }
        return $this->raiseError($code, null, null, null,
            $native_code.' - '.$native_msg);
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     *
     * @param boolean $auto_commit    flag that indicates whether the database
     *                                changes should be committed right after
     *                                executing every query statement. If this
     *                                argument is 0 a transaction implicitly
     *                                started. Otherwise, if a transaction is
     *                                in progress it is ended by committing any
     *                                database changes that were pending.
     *
     * @access public
     *
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function autoCommit($auto_commit)
    {
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        if ($this->auto_commit == $auto_commit) {
            return MDB_OK;
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('COMMIT TRANSACTION');
            } else {
                $result = $this->query('BEGIN TRANSACTION');
            }
            if (MDB::isError($result)) {
                return $result;
            }
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
     *
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function commit()
    {
        $this->debug('commit transaction', 'commit');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
            'commit: transaction changes are being auto commited');
        }
        $result = $this->query('COMMIT TRANSACTION');
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query('BEGIN TRANSACTION');
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
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function rollback()
    {
        $this->debug('rolling back transaction', 'rollback');
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        $result = $this->query('ROLLBACK TRANSACTION');
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query('BEGIN TRANSACTION');
    }

    function _doQuery($query)
    {
        $this->current_row = $this->affected_rows = -1;
        return @mssql_query($query, $this->connection);
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     *
     * @return true on success, MDB_Error on failure
     **/
    function connect()
    {
        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && $this->opened_persistent == $this->options['persistent'])
            {
                return MDB_OK;
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }

        if (!PEAR::loadExtension($this->phptype)) {
            return $this->raiseError(null, MDB_ERROR_NOT_FOUND, null, null,
                'connect: extension '.$this->phptype.' is not compiled into PHP');
        }

        $function = ($this->options['persistent'] ? 'mssql_pconnect' : 'mssql_connect');

        $dsninfo = $this->dsn;
        $user = $dsninfo['username'];
        $pw = $dsninfo['password'];
        $dbhost = $dsninfo['hostspec'] ? $dsninfo['hostspec'] : 'localhost';
        $port   = $dsninfo['port'] ? ':' . $dsninfo['port'] : '';
        $dbhost .= $port;

        @ini_set('track_errors', true);
        if ($dbhost && $user && $pw) {
            $connection = @$function($dbhost, $user, $pw);
        } elseif ($dbhost && $user) {
            $connection = @$function($dbhost, $user);
        } elseif ($dbhost) {
            $connection = @$function($dbhost);
        } else {
            $connection = 0;
        }
        @ini_restore('track_errors');
        if ($connection <= 0) {
            return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null,
                $php_errormsg);
        }
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->connected_database_name = '';
        $this->opened_persistent = $this->getoption('persistent');

        if (isset($this->supported['transactions'])
            && !$this->auto_commit
            && MDB::isError($this->_doQuery('BEGIN TRANSACTION'))
        ) {
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $this->raiseError('connect: Could not begin the initial transaction');
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _close()
    /**
     * all the RDBMS specific things needed close a DB connection
     *
     * @return boolean
     * @access private
     **/
    function _close()
    {
        if ($this->connection != 0) {
            if (isset($this->supported['transactions']) && !$this->auto_commit) {
                $result = $this->_doQuery('ROLLBACK TRANSACTION');
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = $this->current_row = -1;

            if (isset($result) && MDB::isError($result)) {
                return $result;
            }

            unset($GLOBALS['_MDB_databases'][$this->db_index]);
            return true;
        }
        return false;
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
        if (!PEAR::loadExtension($this->phptype)) {
            return $this->raiseError(null, MDB_ERROR_NOT_FOUND, null, null,
                'standaloneQuery: extension '.$this->phptype.' is not compiled into PHP');
        }
        $connection = mssql_connect($this->dsn['hostspec'],$this->dsn['username'],$this->dsn['password']);
        if ($connection == 0) {
            return $this->mssqlRaiseError('standaloneQuery: Could not connect to the Microsoft SQL server');
        }
        $result = @mssql_query($query, $connection);
        if (!$result) {
            return $this->mssqlRaiseError('standaloneQuery: Could not query a Microsoft SQL server');
        }
        mssql_close($connection);
        return MDB_OK;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string  $query  the SQL query
     * @param mixed   $types  array that contains the types of the columns in
     *                        the result set
     * @param mixed $result_mode boolean or string which specifies which class to use
     *
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     *
     * @access public
     */
    function &query($query, $types = null, $result_mode = null)
    {
        $this->debug($query, 'query');
        if ($this->database_name) {
            $ismanip = MDB::isManip($query);
            $this->last_query = $query;
            $first = $this->first_selected_row;
            $limit = $this->selected_row_limit;
            $this->first_selected_row = $this->selected_row_limit = 0;

            $last_connection = $this->connection;
            $result = $this->connect();
            if (MDB::isError($result)) {
                return $result;
            }
            if ($limit > 0) {
                $fetch = $first + $limit;
                if (!$ismanip) {
                    $query = str_replace('SELECT', "SELECT TOP $fetch", $query);
                }
            }
            if ($this->database_name
                && $this->database_name != $this->connected_database_name
            ) {
                if (!mssql_select_db($this->database_name, $this->connection)) {
                    $error =& $this->mssqlRaiseError();
                    return $error;
                }
                $this->connected_database_name = $this->database_name;
            }
            if ($result = $this->_doQuery($query)) {
                if ($ismanip) {
                    $this->affected_rows = mssql_rows_affected($this->connection);
                    return MDB_OK;
                } else {
                    $result_value = intval($result);
                    if($first > 0 || $limit > 0) {
                        $this->results[$result_value]['limits'] = array($first, $limit);
                    }
                    $this->results[$result_value]['highest_fetched_row'] = -1;
                    if ($types != null) {
                        if (!is_array($types)) {
                            $types = array($types);
                        }
                        $err = $this->setResultTypes($result, $types);
                        if (MDB::isError($err)) {
                            $this->freeResult($result);
                            return $err;
                        }
                    }
                    $result =& $this->_return_result($result, $result_mode);
                    return $result;
                }
            }
        }
        $error =& $this->mssqlRaiseError();
        return $error;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @param resource   $result    result identifier
     * @return mixed                an associative array variable
     *                              that will hold the names of columns. The
     *                              indexes of the array are the column names
     *                              mapped to lower case and the values are the
     *                              respective numbers of the columns starting
     *                              from 0. Some DBMS may not return any
     *                              columns when the result set does not
     *                              contain any rows.
     *
     *                              a MDB error on failure
     * @access public
     */
    function getColumnNames($result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'getColumnNames: it was specified an inexisting result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = mssql_num_fields($result);
            for ($column = 0; $column < $columns; $column++) {
                $field_name = strtolower(mssql_field_name($result, $column));
                $this->results[$result_value]['columns'][$field_name] = $column;
            }
        }
        return $this->results[$result_value]['columns'];
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @param resource    $result        result identifier
     * @access public
     * @return mixed integer value with the number of columns, a MDB error
     *                       on failure
     */
    function numCols($result)
    {
        if (!isset($this->results[intval($result)])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'numCols: it was specified an inexisting result set');
        }
        return mssql_num_fields($result);
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
            if (!isset($this->results[intval($result)])) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'numRows: attempted to check the end of an unknown result');
            }
            $rows = mssql_num_rows($result);
            if (isset($this->limits[$result])) {
                $rows -= $this->limits[$result][0];
                if ($rows < 0) $rows = 0;
            }
            return $rows;
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
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'freeResult: it was specified an inexisting result set');
        }
        unset($this->results[$result_value]);
        return @mssql_free_result($result);
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
     *
     * @return mixed MDB_Error or id
     * @access public
     */
    function nextID($seq_name, $ondemand = true)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->query("INSERT INTO $sequence_name DEFAULT VALUES");
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
        $result = $this->query("SELECT @@IDENTITY FROM $sequence_name", 'integer', false);
        if (MDB::isError($result)) {
            return $result;
        }
        $value = $this->fetch($result);
        $this->freeResult($result);
        $result = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB::isError($result)) {
            $this->warnings[] = 'nextID: could not delete previous sequence table values';
        }
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
        $sequence_name = $this->getSequenceName($seq_name);
        $result = $this->query("SELECT MAX(sequence) FROM $sequence_name", 'integer', false);
        if (MDB::isError($result)) {
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
    * @param resource    $result result identifier
    * @param int    $rownum    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed string on success, a MDB error on failure
    * @access public
    */
    function fetch($result, $rownum = 0, $field = 0)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'fetch: it was specified an inexisting result set');
        }
        $value = @mssql_result($result, $rownum, $field);
        if ($value === false && $value != null) {
            return $this->mssqlRaiseError();
        }
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $rownum);
        if (isset($this->results[$result_value]['limits'])) {
             $rownum += $this->results[$result_value]['limits'][0];
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
     * Fetch a row and insert the data into an existing array.
     *
     * @param resource  $result     result identifier
     * @param int       $fetchmode  how the array data should be indexed
     * @param int       $rownum     the row number to fetch
     * @return int data array on success, a MDB error on failure
     * @access public
     */
    function fetchRow($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = null)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'fetchRow: it was specified an inexisting result set');
        }
        if (is_null($rownum)) {
            ++$this->results[$result_value]['highest_fetched_row'];
        } else {
            if (isset($this->results[$result_value]['limits'])) {
                $row = $rownum + $this->results[$result_value]['limits'][0];
            }
            if (!@mssql_data_seek($result, $row)) {
                return null;
            }
            $this->results[$result_value]['highest_fetched_row'] =
                max($this->results[$result_value]['highest_fetched_row'], $rownum);
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode == MDB_FETCHMODE_ASSOC) {
            $row = @mssql_fetch_array($result, MSSQL_ASSOC);
        } else {
            $row = @mssql_fetch_row($result);
        }
        if (!$row) {
            return null;
        }
        if (isset($this->results[$result_value]['types'])) {
            $row = $this->datatype->convertResultRow($result, $row);
        }
        return $row;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal result pointer to the next available result
     * Currently not supported
     * 
     * @param $result valid result resource
     * @return true if a result is available otherwise return false
     * @access public
     */
    function nextResult($result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'nextResult: it was specified an inexisting result set');
        }
        // not sure how to best handle setting the values that usually
        // set per query in query() like affected_rows and highest_fetched_row
        return @mssql_next_result($result);
    }
}
?>