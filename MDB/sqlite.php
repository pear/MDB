<?php
// vim: set et ts=4 sw=4 fdm=marker:
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
//
// $Id$
//

require_once 'MDB/Common.php';

/**
 * MDB SQLite driver
 *
 * @package MDB
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB_sqlite extends MDB_Common
{
    // {{{ properties
    var $escape_quotes = "'";

    var $decimal_factor = 1.0;
    var $_lasterror = '';

    var $database_path = '';
    var $database_extension = '';

    // }}}
    // {{{ constructor

    /**
    * Constructor
    */
    function MDB_sqlite()
    {
        $this->MDB_Common();
        $this->phptype = 'sqlite';
        $this->dbsyntax = 'sqlite';

        $this->supported['sequences'] = 1;
        $this->supported['indexes'] = 1;
        $this->supported['affected_rows'] = 1;
        $this->supported['summary_functions'] = 1;
        $this->supported['order_by_text'] = 1;
        $this->supported['current_id'] = 1;
        $this->supported['limit_queries'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['replace'] = 1;
        $this->supported['transactions'] = 1;
        $this->supported['sub_selects'] = 1;

        $this->options['base_transaction_name'] = '___php_MDB_sqlite_auto_commit_off';
        $this->options['fixed_float'] = 0;

        $this->errorcode_map = array(
           1  => MDB_ERROR_SYNTAX,
           19 => MDB_ERROR_CONSTRAINT,
           20 => MDB_ERROR_MISMATCH,
           23 => MDB_ERROR_ACCESS_VIOLATION
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
     * @return int native SQLite error code
     */
    function errorNative()
    {
        return($this->_lasterror);
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
                '/^no such table:/' => MDB_ERROR_NOSUCHTABLE,
                '/^table .* already exists$/' => MDB_ERROR_ALREADY_EXISTS,
                '/^no such column:/' => MDB_ERROR_NOSUCHFIELD,
                '/^near ".*": syntax error$/' => MDB_ERROR_SYNTAX
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
    // {{{ sqliteRaiseError()

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
    function sqliteRaiseError($errno = null)
    {
        $native = $this->errorNative();
        if (is_null($errno)) {
            $errno = $this->errorCode($native);
        }

        $errorcode = sqlite_last_error($this->connection); 
        $userinfo = "$errorcode ** $this->last_query";

        return $this->raiseError($errno, null, null, $userinfo, $native);
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
                'autoCommit: transactions are not in use');
        }
        if (!$this->auto_commit == !$auto_commit) {
            return MDB_OK;
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('END TRANSACTION '.$this->options['base_transaction_name']);
                if (MDB::isError($result)) {
                    return $result;
                }
            } else {
                $result = $this->query('BEGIN TRANSACTION '.$this->options['base_transaction_name']);
                if (MDB::isError($result)) {
                    return $result;
                }
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
        if (!isset($this->supported['transactions'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'commit: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
            'commit: transaction changes are being auto commited');
        }
        $result = $this->query('COMMIT TRANSACTION '.$this->options['base_transaction_name']);
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query('BEGIN TRANSACTION '.$this->options['base_transaction_name']);
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
                'rollback: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        $result = $this->query('ROLLBACK TRANSACTION '.$this->options['base_transaction_name']);
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query('BEGIN TRANSACTION '.$this->options['base_transaction_name']);
    }

    // }}}
    // {{{ getDatabaseFile()

    /**
     * Builds the string with path+dbname+extension
     *
     * @return string full database path+file
     * @access private
     */
    function _getDatabaseFile($database_name)
    {
        $this->database_path = $this->options['database_path'];
        $this->database_extension = $this->options['database_extension'];

        return $this->database_path.$database_name.$this->database_extension;
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
        $database_file = $this->_getDatabaseFile($this->database_name);

        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && $this->connected_database_name == $database_file
                && $this->opened_persistent == $this->options['persistent']
            ) {
                return MDB_OK;
            }
            sqlite_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }

        if (!PEAR::loadExtension($this->phptype)) {
            return $this->raiseError(MDB_ERROR_NOT_FOUND, null, null,
                'connect: extension '.$this->phptype.' is not compiled into PHP');
        }

        if (isset($database_file)) {
            if (!file_exists($database_file)) {
                return $this->raiseError(MDB_ERROR_NOT_FOUND);
            }
            if (!is_file($database_file)) {
                return $this->raiseError(MDB_ERROR_INVALID);
            }
            if (!is_readable($database_file)) {
                return $this->raiseError(MDB_ERROR_ACCESS_VIOLATION);
            }
        } else {
            return $this->raiseError(MDB_ERROR_ACCESS_VIOLATION);
        }

        $function = $persistent ? 'sqlite_popen' : 'sqlite_open';
        $connection = @$function($dsninfo['database']);
        if (!$connection) {
            return $this->sqliteRaiseError(MDB_ERROR_NODBSELECTED);
        }
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->connected_database_name = $database_file;
        $this->opened_persistent = $this->getoption('persistent');

        if (isset($this->supported['transactions']) && !$this->auto_commit) {
            if (!sqlite_query('BEGIN TRANSACTION '.$this->options['base_transaction_name'],$this->connection)) {
                sqlite_close($this->connection);
                $this->connection = 0;
                $this->affected_rows = -1;
                return $this->raiseError('connect: Could not start transaction');
            }
            $this->in_transaction = true;
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
                $result = $this->autoCommit(true);
            }
            sqlite_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;

            if (isset($result) && MDB::isError($result)) {
                return $result;
            }

            $GLOBALS['_MDB_databases'][$this->db_index] = '';
            return true;
        }
        return false;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string  $query  the SQL query
     * @param mixed   $types  string or array that contains the types of the
     *                        columns in the result set
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

        $result = $this->connect();
        if (MDB::isError($result)) {
            return $result;
        }
        if ($limit > 0) {
            if ($ismanip) {
                $query .= " LIMIT $limit";
            } else {
                $query .= " LIMIT $limit OFFSET $first";
            }
        }

        $function = $this->options['result_buffering'] ? 'sqlite_query' : 'sqlite_unbuffered_query';
        ini_set('track_errors', true);
        $result = $function($query.';', $this->connection);
        ini_restore('track_errors');
        $this->_lasterror = isset($php_errormsg) ? $php_errormsg : '';
        if ($result) {
            if ($ismanip) {
                $this->affected_rows = sqlite_affected_rows($this->connection);
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
                $result =& $this->_return_result($result, $result_mode);
                return $result;
            }
        }
        $error =& $this->sqliteRaiseError();
        return $error;
    }

    // }}}
    // {{{ replace()

    /**
     * Execute a SQL REPLACE query. A REPLACE query is identical to a INSERT
     * query, except that if there is already a row in the table with the same
     * key field values, the REPLACE query just updates its values instead of
     * inserting a new row.
     *
     * The REPLACE type of query does not make part of the SQL standards. Since
     * practically only SQLite implements it natively, this type of query is
     * emulated through this method for other DBMS using standard types of
     * queries inside a transaction to assure the atomicity of the operation.
     *
     * @access public
     *
     * @param string $table name of the table on which the REPLACE query will
     *  be executed.
     * @param array $fields associative array that describes the fields and the
     *  values that will be inserted or updated in the specified table. The
     *  indexes of the array are the names of all the fields of the table. The
     *  values of the array are also associative arrays that describe the
     *  values and other properties of the table fields.
     *
     *  Here follows a list of field properties that need to be specified:
     *
     *    value:
     *          Value to be assigned to the specified field. This value may be
     *          of specified in database independent type format as this
     *          function can perform the necessary datatype conversions.
     *
     *    Default:
     *          this property is required unless the Null property
     *          is set to 1.
     *
     *    type
     *          Name of the type of the field. Currently, all types Metabase
     *          are supported except for clob and blob.
     *
     *    Default: no type conversion
     *
     *    null
     *          Boolean property that indicates that the value for this field
     *          should be set to null.
     *
     *          The default value for fields missing in INSERT queries may be
     *          specified the definition of a table. Often, the default value
     *          is already null, but since the REPLACE may be emulated using
     *          an UPDATE query, make sure that all fields of the table are
     *          listed in this function argument array.
     *
     *    Default: 0
     *
     *    key
     *          Boolean property that indicates that this field should be
     *          handled as a primary key or at least as part of the compound
     *          unique index of the table that will determine the row that will
     *          updated if it exists or inserted a new row otherwise.
     *
     *          This function will fail if no key field is specified or if the
     *          value of a key field is set to null because fields that are
     *          part of unique index they may not be null.
     *
     *    Default: 0
     *
     * @return mixed MDB_OK on success, a MDB error on failure
     */
    function replace($table, $fields)
    {
        $count = count($fields);
        for ($keys = 0, $query = $values = '',reset($fields), $field = 0;
            $field < $count;
            next($fields), $field++)
        {
            $name = key($fields);
            if ($field > 0) {
                $query .= ',';
                $values .= ',';
            }
            $query .= $name;
            if (isset($fields[$name]['null']) && $fields[$name]['null']) {
                $value = 'NULL';
            } else {
                $value = $this->getValue($fields[$name]['type'], $fields[$name]['value']);
            }
            $values .= $value;
            if (isset($fields[$name]['key']) && $fields[$name]['key']) {
                if ($value === 'NULL') {
                    return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                        'replace: key value '.$name.' may not be NULL');
                }
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                'replace: not specified which fields are keys');
        }
        return $this->query("REPLACE INTO $table ($query) VALUES ($values)");
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
            $fields = sqlite_fetch_field_array($result);
            $columns = count($fields);
            for ($column = 0; $column < $columns; $column++) {
                $this->results[$result_value]['columns'][strtolower(sqlite_field_name($result, $column))] = $column;
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
        return sqlite_num_fields($result);
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
            return sqlite_num_rows($result);
        }
        return $this->raiseError(MDB_ERROR, null, null,
            'numRows: not supported if option "result_buffering" is not enabled');
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
    function freeResult(&$result)
    {
        $result_value = intval($result);
        if (!isset($this->results[$result_value])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'freeResulr: it was specified an inexisting result set');
        }
        unset($this->results[$result_value]);
        unset($result);
        return MDB_OK;
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
        $result = $this->query("INSERT INTO $sequence_name (sequence) VALUES (NULL)");
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
                        'nextID: on demand sequence '.$seq_name.' could not be created');
                } else {
                    // First ID of a newly created sequence is 1
                    return 1;
                }
            }
            return $result;
        }
        $value = sqlite_last_insert_rowid($this->connection);
        $result = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB::isError($result)) {
            $this->warnings[] = 'nextID: could not delete previous sequence table values from '.$seq_name;
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
        if (!sqlite_seek($result, $rownum)) {
            return null;
        }
        $row = sqlite_fetch_array($result, SQLITE_BOTH);
        if (!is_array($row) && !is_null($row)) {
            return $this->sqliteRaiseError();
        }
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $rownum);
        if (!isset($row[$field])) {
            return null;
        }
        $value = $row[$field];
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
            if (!sqlite_seek($result, $rownum)) {
                return null;
            }
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode == MDB_FETCHMODE_ASSOC) {
            $row = sqlite_fetch_array($result, SQLITE_ASSOC);
        } else {
            $row = sqlite_fetch_array($result, SQLITE_NUM);
        }
        if (!$row) {
            return null;
        }
        $this->results[$result_value]['highest_fetched_row'] =
            max($this->results[$result_value]['highest_fetched_row'], $rownum);
        if (isset($this->results[$result_value]['types'])) {
            $row = $this->datatype->convertResultRow($result, $row);
        }
        return $row;
    }
}
?>