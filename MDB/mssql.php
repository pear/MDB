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

    var $connection = 0;
    var $connected_host;
    var $connected_user;
    var $connected_password;
    var $connected_port;
    var $opened_persistent = '';

    var $escape_quotes = "'";
    var $decimal_factor = 1.0;

    var $highest_fetched_row = array();
    var $columns = array();

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

        $this->supported['sequences'] = 1;
        $this->supported['indexes'] = 1;
        $this->supported['affected_rows'] = 1;
        $this->supported['summary_functions'] = 1;
        $this->supported['order_by_text'] = 0;
        $this->supported['current_id'] = 1;
        $this->supported['limit_querys'] = 0;
        $this->supported['LOBs'] = 1;
        $this->supported['replace'] = 0;
        $this->supported['sub_selects'] = 1;

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
       return $row[0];
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
        return $this->raiseError($code, null, null, null, $native_code . ' - ' . $native_msg);
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
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'Auto-commit transactions: transactions are not in use');
        }
        if (!$this->auto_commit == !$auto_commit) {
            return MDB_OK;
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('COMMIT TRANSACTION');
                if (MDB::isError($result)) {
                    return $result;
                }
            } else {
                $result = $this->query('BEGIN TRANSACTION');
                if (MDB::isError($result)) {
                    return $result;
                }
            }
        }
        $this->auto_commit = $auto_commit;
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
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'Commit transactions: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
            'Commit transactions: transaction changes are being auto commited');
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
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'Rollback transactions: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'Rollback transactions: transactions can not be rolled back when changes are auto commited');
        }
        $result = $this->query('COMMIT TRANSACTION');
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query('ROLLBACK TRANSACTION');
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
        $port = (isset($this->port) ? $this->port : '');
        if ($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_user, $this->user)
                && !strcmp($this->connected_password, $this->password)
                && !strcmp($this->connected_port, $port)
                && $this->opened_persistent == $this->options['persistent'])
            {
                return MDB_OK;
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND,
                null, null, 'extension '.$this->phptype.' is not compiled into PHP',
                'MDB_Error', true);
        }

        $function = ($this->options['persistent'] ? 'mssql_pconnect' : 'mssql_connect');
        if (!function_exists($function)) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED);
        }

        @ini_set('track_errors', true);
        $this->connection = @$function(
            $this->host.(!strcmp($port,'') ? '' : ':'.$port),
            $this->user, $this->password);
        @ini_restore('track_errors');
        if ($this->connection <= 0) {
            return $this->raiseError(MDB_ERROR_CONNECT_FAILED, null, null,
                $php_errormsg);
        }

        if (isset($this->supported['transactions']) && !$this->auto_commit
            && !$this->_doQuery("BEGIN TRANSACTION"))
        {
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
            return $this->raiseError("Connect: Could not begin the initial transaction");
        }
        $this->connected_host = $this->host;
        $this->connected_user = $this->user;
        $this->connected_password = $this->password;
        $this->connected_port = $port;
        $this->opened_persistent = $this->options['persistent'];
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
                $result = $this->_doQuery("ROLLBACK TRANSACTION");
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = $this->current_row = -1;

            if (isset($result) && MDB::isError($result)) {
                return $result;
            }

            $GLOBALS['_MDB_databases'][$this->database] = '';
            return true;
        }
        return false;
    }

    function standaloneQuery($query)
    {
        if (!function_exists("mssql_connect")) {
            return $this->raiseError("Query: Microsoft SQL server support is not available in this PHP configuration");
        }
        $connection = mssql_connect($this->host,$this->user,$this->password);
        if ($connection == 0) {
            return $this->mssqlRaiseError("Query: Could not connect to the Microsoft SQL server");
        }
        $result = @mssql_query($query, $connection);
        if (!$result) {
            $this->mssqlRaiseError("Query: Could not query a Microsoft SQL server");
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
     * @param array   $types  array that contains the types of the columns in
     *                        the result set
     * @param mixed $return_obj boolean or string which specifies which class to use
     *
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     *
     * @access public
     */
    function &query($query, $types = null, $return_obj = false)
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
                if ($ismanip) {
                    preg_replace("/^SELECT/", "SELECT TOP $limit", $query);
                }
            }
            if ( $last_connection != $this->connection
                || !strcmp($this->selected_database, '')
                || strcmp($this->selected_database, $this->database_name))
            {
                if (!mssql_select_db($this->database_name, $this->connection)) {
                    return $this->mssqlRaiseError();
                }
            }
            if ($result = $this->_doQuery($query)) {
                if ($ismanip) {
                    $this->affected_rows = mssql_affected_rows($this->connection);
                    return MDB_OK;
                } else {
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
                    return $this->_return_result($result, $return_obj);
                }
            }
        }

        return $this->mssqlRaiseError();
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
        if (!isset($this->results[$result_value]['highest_fetched_row'])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'Get column names: it was specified an inexisting result set');
        }
        if (!isset($this->results[$result_value]['columns'])) {
            $this->results[$result_value]['columns'] = array();
            $columns = mssql_num_fields($result);
            for($column = 0; $column < $columns; $column++) {
                $this->results[$result_value]['columns'][strtolower(mssql_field_name($result, $column))] = $column;
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
        if (!isset($this->results[intval($result)]['highest_fetched_row'])) {
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
            if (!isset($this->results[$result_value]['highest_fetched_row'])) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'End of result: attempted to check the end of an unknown result');
            }
            $numrows = $this->numRows($result);
            if (MDB::isError($numrows)) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'End of result: error when calling numRows: '.$numrows->getUserInfo());
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
            $result_value = intval($result);
            return isset($this->results[$result_value]['ranges']) ? count($this->results[$result_value]['ranges']) : mssql_num_rows($result);
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
            return @mssql_free_result($result);
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
     *
     * @return mixed MDB_Error or id
     * @access public
     */
    function nextId($seq_name, $ondemand = true)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->query("INSERT INTO $sequence_name (sequence) VALUES (NULL)");
        $this->popExpect();
        if (MDB::isError($result)) {
            if ($ondemand && $result->getCode() == MDB_ERROR_NOSUCHTABLE) {
                $this->loadModule('manager');
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
        $value = intval(mssql_insert_id($this->connection));
        $res = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB::isError($res)) {
            $this->warnings[] = 'Next ID: could not delete previous sequence table values';
        }
        return $value;
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
        $sequence_name = $this->getSequenceName($seq_name);
        $result = $this->query("SELECT MAX(sequence) FROM $sequence_name", 'integer');
        if (MDB::isError($result)) {
            return $result;
        }

        return $this->fetchOne($result);
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
    function fetch($result, $rownum, $field)
    {
        $result_value = intval($result);
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $rownum);
        $value = @mssql_result($result, $rownum, $field);
        if ($value === false && $value != null) {
            return($this->mssqlRaiseError($errno));
        }
        if (isset($this->results[$result_value]['types'][$field])) {
            $value = $this->datatype->convertResult($this, $result, $value, $this->results[$result_value]['types'][$field]);
        }
        return($value);
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
        if ($rownum == null) {
            ++$this->results[$result_value]['highest_fetched_row'];
        } else {
            if (!@mssql_data_seek($result, $rownum)) {
                return null;
            }
            $this->results[$result_value]['highest_fetched_row'] = max($this->results[$result_value]['highest_fetched_row'], $rownum);
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode == MDB_FETCHMODE_ASSOC) {
            $row = @mssql_fetch_array($result, MYSQL_ASSOC);
        } else {
            $row = @mssql_fetch_row($result);
        }
        if (!$row) {
            if ($this->options['autofree']) {
                $this->freeResult($result);
            }
            return null;
        }
        if (isset($this->results[$result_value]['types'])) {
            $array = $this->datatype->convertResultRow($this, $result, $array);
        }
        return $array;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal mssql result pointer to the next available result
     * Currently not supported
     *
     * @param a valid result resource
     * @return true if a result is available otherwise return false
     * @access public
     */
    function nextResult($result)
    {
        return mssql_next_result($result);
    }

    // }}}
    // {{{ tableInfo()

    /**
    * returns meta data about the result set
    *
    * @param resource    $result    result identifier
    * @param mixed $mode depends on implementation
    * @return array an nested array, or a MDB error
    * @access public
    */
    function tableInfo($result, $mode = null)
    {
        $count = 0;
        $id     = 0;
        $res  = array();

        /*
         * depending on $mode, metadata returns the following values:
         *
         * - mode is false (default):
         * $result[]:
         *   [0]['table']  table name
         *   [0]['name']   field name
         *   [0]['type']   field type
         *   [0]['len']    field length
         *   [0]['flags']  field flags
         *
         * - mode is MDB_TABLEINFO_ORDER
         * $result[]:
         *   ['num_fields'] number of metadata records
         *   [0]['table']  table name
         *   [0]['name']   field name
         *   [0]['type']   field type
         *   [0]['len']    field length
         *   [0]['flags']  field flags
         *   ['order'][field name]  index of field named "field name"
         *   The last one is used, if you have a field name, but no index.
         *   Test:  if (isset($result['meta']['myfield'])) { ...
         *
         * - mode is MDB_TABLEINFO_ORDERTABLE
         *    the same as above. but additionally
         *   ['ordertable'][table name][field name] index of field
         *      named 'field name'
         *
         *      this is, because if you have fields from different
         *      tables with the same field name * they override each
         *      other with MDB_TABLEINFO_ORDER
         *
         *      you can combine MDB_TABLEINFO_ORDER and
         *      MDB_TABLEINFO_ORDERTABLE with MDB_TABLEINFO_ORDER |
         *      MDB_TABLEINFO_ORDERTABLE * or with MDB_TABLEINFO_FULL
         */

        // if $result is a string, then we want information about a
        // table without a resultset
        if (is_string($result)) {
            $id = @mssql_list_fields($this->database_name,
                $result, $this->connection);
            if (empty($id)) {
                return $this->mssqlRaiseError();
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return $this->mssqlRaiseError();
            }
        }

        $count = @mssql_num_fields($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {
            for ($i = 0; $i<$count; $i++) {
                $res[$i]['table'] = @mssql_field_table ($id, $i);
                $res[$i]['name'] = @mssql_field_name  ($id, $i);
                $res[$i]['type'] = @mssql_field_type  ($id, $i);
                $res[$i]['len']  = @mssql_field_len   ($id, $i);
                $res[$i]['flags'] = @mssql_field_flags ($id, $i);
            }
        } else { // full
            $res['num_fields'] = $count;

            for ($i = 0; $i<$count; $i++) {
                $res[$i]['table'] = @mssql_field_table ($id, $i);
                $res[$i]['name'] = @mssql_field_name  ($id, $i);
                $res[$i]['type'] = @mssql_field_type  ($id, $i);
                $res[$i]['len']  = @mssql_field_len   ($id, $i);
                $res[$i]['flags'] = @mssql_field_flags ($id, $i);
                if ($mode & MDB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & MDB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }

        // free the result only if we were called on a table
        if (is_string($result)) {
            @mssql_free_result($id);
        }
        return $res;
    }
}

?>
