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

if (!defined('MDB_OCI8_INCLUDED')) {
    define('MDB_OCI8_INCLUDED', 1);

require_once 'MDB/Common.php';

/**
 * MDB OCI8 driver
 * 
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com> 
 */
class MDB_oci8 extends MDB_Common {
    var $connection = 0;
    var $connected_user;
    var $connected_password;

    var $escape_quotes = "'";

    var $manager_class_name = 'MDB_Manager_oci8';
    var $manager_include = 'MDB/Manager/oci8.php';
    var $manager_included_constant = 'MDB_MANAGER_OCI8_INCLUDED';

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
    function MDB_oci8($dsninfo = NULL, $options = NULL)
    {
        if (MDB::isError($common_contructor = $this->MDB_common($dsninfo, $options))) {
            return $common_contructor;
        }
        $this->phptype = 'oci8';
        $this->dbsyntax = 'oci8';

        if (PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return PEAR::raiseError(NULL, MDB_ERROR_NOT_FOUND,
                NULL, NULL, 'extension ' . $this->phptype . ' is not compiled into PHP',
                'MDB_Error', TRUE);
        }

        $this->supported['Sequences'] = 1;
        $this->supported['Indexes'] = 1;
        $this->supported['SummaryFunctions'] = 1;
        $this->supported['OrderByText'] = 1;
        $this->supported['Transactions'] = 1;
        $this->supported['SelectRowRanges'] = 1;
        $this->supported['LOBs'] = 1;
        $this->supported['Replace'] = 1;
        $this->supported['SubSelects'] = 1;

        $this->errorcode_map = array(
            900 => MDB_ERROR_SYNTAX,
            904 => MDB_ERROR_NOSUCHFIELD,
            923 => MDB_ERROR_SYNTAX,
            942 => MDB_ERROR_NOSUCHTABLE,
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
    function errorNative()
    {
        if (is_resource($this->last_stmt)) {
            $error = @OCIError($this->last_stmt);
        } else {
            $error = @OCIError($this->connection);
        }
        if (is_array($error)) {
            return $error['code'];
        }
        return FALSE;
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
    function oci8RaiseError($errno = NULL)
    {
        if ($errno === NULL) {
            $error = @OCIError($this->connection);
            return $this->raiseError($this->errorCode($error['code']),
                NULL, NULL, NULL, $error['message']);
        } elseif (is_resource($errno)) {
            $error = @OCIError($errno);
            return $this->raiseError($this->errorCode($error['code']),
                NULL, NULL, NULL, $error['message']);
        }
        return $this->raiseError($this->errorCode($errno));
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
        $this->debug("AutoCommit: " . ($auto_commit ? "On" : "Off"));
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return(MDB_OK);
        }
        if ($this->connection && $auto_commit && !$this->CommitTransaction()) {
            return(0);
        }
        $this->auto_commit = $auto_commit;
        return($this->_registerTransactionShutdown($auto_commit));
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
        $this->debug("Commit Transaction");
        if ($this->auto_commit) {
            return($this->SetError("Commit transaction", "transaction changes are being auto commited"));
        }
        if ($this->uncommitedqueries) {
            if (!OCICommit($this->connection)) {
                return($this->SetOCIError("Commit transaction", "Could not commit pending transaction", OCIError()));
            }
            $this->uncommitedqueries = 0;
        }
        return(MDB_OK);
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
        $this->debug("Rollback Transaction");
        if ($this->auto_commit) {
            return($this->SetError("Rollback transaction", "transactions can not be rolled back when changes are auto commited"));
        }
        if ($this->uncommitedqueries) {
            if (!OCIRollback($this->connection)) {
                return($this->SetOCIError("Rollback transaction", "Could not rollback pending transaction", OCIError()));
            }
            $this->uncommitedqueries = 0;
        }
        return(MDB_OK);
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     * 
     * @return TRUE on success, MDB_Error on failure
     */
    function connect()
    {
        if (isset($this->options["SID"])) {
            $sid = $this->options["SID"];
        else {
            $sid = getenv("ORACLE_SID");
        }
        if (!strcmp($sid, "")) {
            return($this->SetError("Connect", "it was not specified a valid Oracle Service IDentifier (SID)"));
        }
        if ($this->connection != 0) {
            if (!strcmp($this->connected_user, $user) && !strcmp($this->connected_password, $password) && $this->opened_persistent == $persistent) {
                return(MDB_OK);
            }
            $this->_close();
        }
        if (isset($this->options["HOME"])) {
            putenv("ORACLE_HOME=" . $this->options["HOME"]);
        }
        putenv("ORACLE_SID=" . $sid);
        $function = ($persistent ? "OCIPLogon" : "OCINLogon");
        if (!function_exists($function)) {
            return($this->SetError("Connect", "Oracle OCI API support is not available in this PHP configuration"));
        }
        if (!($this->connection = @$function($user, $password, $sid))) {
            return($this->SetOCIError("Connect", "Could not connect to Oracle server", OCIError()));
        }
        if (!$this->_doQuery("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'")) {
            $this->_close();
            return(0);
        }
        $this->connected_user = $user;
        $this->connected_password = $password;
        $this->opened_persistent = $persistent;
        return(MDB_OK);
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
            OCILogOff($this->connection);
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
        $success = 1;
        $result = 0;
        $descriptors = array();
        if ($prepared_query) {
            $columns = "";
            $variables = "";
            for(reset($this->clobs[$prepared_query]), $clob = 0;$clob < count($this->clobs[$prepared_query]);$clob++, next($this->clobs[$prepared_query])) {
                $position = key($this->clobs[$prepared_query]);
                if (gettype($descriptors[$position] = OCINewDescriptor($this->connection, OCI_D_LOB)) != "object") {
                    $this->SetError("Do query", "Could not create descriptor for clob parameter");
                    $success = 0;
                    break;
                }
                $columns .= ($lobs == 0 ? " RETURNING " : ",") . $this->prepared_queries[$prepared_query-1]["Fields"][$position-1];
                $variables .= ($lobs == 0 ? " INTO " : ",") . ":clob" . $position;
                $lobs++;
            }
            if ($success) {
                for(reset($this->blobs[$prepared_query]), $blob = 0;$blob < count($this->blobs[$prepared_query]);$blob++, next($this->blobs[$prepared_query])) {
                    $position = key($this->blobs[$prepared_query]);
                    if (gettype($descriptors[$position] = OCINewDescriptor($this->connection, OCI_D_LOB)) != "object") {
                        $this->SetError("Do query", "Could not create descriptor for blob parameter");
                        $success = 0;
                        break;
                    }
                    $columns .= ($lobs == 0 ? " RETURNING " : ",") . $this->prepared_queries[$prepared_query-1]["Fields"][$position-1];
                    $variables .= ($lobs == 0 ? " INTO " : ",") . ":blob" . $position;
                    $lobs++;
                }
                $query .= $columns . $variables;
            }
        }
        if ($success) {
            if (($statement = OCIParse($this->connection, $query))) {
                if ($lobs) {
                    for(reset($this->clobs[$prepared_query]), $clob = 0;$clob < count($this->clobs[$prepared_query]);$clob++, next($this->clobs[$prepared_query])) {
                        $position = key($this->clobs[$prepared_query]);
                        if (!OCIBindByName($statement, ":clob" . $position, $descriptors[$position], -1, OCI_B_CLOB)) {
                            $this->SetOCIError("Do query", "Could not bind clob upload descriptor", OCIError($statement));
                            $success = 0;
                            break;
                        }
                    }
                    if ($success) {
                        for(reset($this->blobs[$prepared_query]), $blob = 0;$blob < count($this->blobs[$prepared_query]);$blob++, next($this->blobs[$prepared_query])) {
                            $position = key($this->blobs[$prepared_query]);
                            if (!OCIBindByName($statement, ":blob" . $position, $descriptors[$position], -1, OCI_B_BLOB)) {
                                $this->SetOCIError("Do query", "Could not bind blob upload descriptor", OCIError($statement));
                                $success = 0;
                                break;
                            }
                        }
                    }
                }
                if ($success) {
                    if (($result = @OCIExecute($statement, ($lobs == 0 && $this->auto_commit) ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT))) {
                        if ($lobs) {
                            for(reset($this->clobs[$prepared_query]), $clob = 0;$clob < count($this->clobs[$prepared_query]);$clob++, next($this->clobs[$prepared_query])) {
                                $position = key($this->clobs[$prepared_query]);
                                $clob_stream = $this->prepared_queries[$prepared_query-1]["Values"][$position-1];
                                for($value = "";!MetabaseEndOfLOB($clob_stream);) {
                                    if (MetabaseReadLOB($clob_stream, $data, $this->lob_buffer_length) < 0) {
                                        $this->SetError("Do query", MetabaseLOBError($clob));
                                        $success = 0;
                                        break;
                                    }
                                    $value .= $data;
                                }
                                if ($success && !$descriptors[$position]->save($value)) {
                                    $this->SetOCIError("Do query", "Could not upload clob data", OCIError($statement));
                                    $success = 0;
                                }
                            }
                            if ($success) {
                                for(reset($this->blobs[$prepared_query]), $blob = 0;$blob < count($this->blobs[$prepared_query]);$blob++, next($this->blobs[$prepared_query])) {
                                    $position = key($this->blobs[$prepared_query]);
                                    $blob_stream = $this->prepared_queries[$prepared_query-1]["Values"][$position-1];
                                    for($value = "";!MetabaseEndOfLOB($blob_stream);) {
                                        if (MetabaseReadLOB($blob_stream, $data, $this->lob_buffer_length) < 0) {
                                            $this->SetError("Do query", MetabaseLOBError($blob));
                                            $success = 0;
                                            break;
                                        }
                                        $value .= $data;
                                    }
                                    if ($success && !$descriptors[$position]->save($value)) {
                                        $this->SetOCIError("Do query", "Could not upload blob data", OCIError($statement));
                                        $success = 0;
                                    }
                                }
                            }
                        }
                        if ($this->auto_commit) {
                            if ($lobs) {
                                if ($success) {
                                    if (!OCICommit($this->connection)) {
                                        $this->SetOCIError("Do query", "Could not commit pending LOB updating transaction", OCIError());
                                        $success = 0;
                                    }
                                } else {
                                    if (!OCIRollback($this->connection)) {
                                        $this->SetOCIError("Do query", $this->Error() . " and then could not rollback LOB updating transaction", OCIError());
                                    }
                                }
                            }
                        } else 
                            $this->uncommitedqueries++;
                        }
                        if ($success) {
                            switch (OCIStatementType($statement)) {
                                case "SELECT":
                                    $result_value = intval($statement);
                                    $this->current_row[$result_value] = -1;
                                    if ($limit > 0)
                                        $this->limits[$result_value] = array($first, $limit, 0);
                                    $this->highest_fetched_row[$result_value] = -1;
                                    break;
                                default:
                                    $this->affected_rows = OCIRowCount($statement);
                                    OCIFreeCursor($statement);
                                    break;
                            }
                            $result = $statement;
                        }
                    } else {
                        $this->SetOCIError("Do query", "Could not execute query", OCIError($statement));
                    }
                }
            } else {
                $this->SetOCIError("Do query", "Could not parse query", OCIError($statement));
            }
        }
        for(reset($descriptors), $descriptor = 0;$descriptor < count($descriptors);$descriptor++, next($descriptors)) {
            @OCIFreeDesc($descriptors[key($descriptors)]);
        }
        return($result);
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
    function query($query, $types = NULL)
    {
        $this->debug("Query: $query");
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;
        if (!$this->connect($this->user, $this->password, $this->persistent)) {
            return(0);
        }
        return($this->_doQuery($query, $first, $limit));
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
        if (!$this->connect($this->user, $this->password, $this->persistent)) {
            return(0);
        }
        return($this->_doQuery($query, $first, $limit, $prepared_query));
    }

    // }}}
    // {{{ _skipFirstRows()

    /**
     * Skip the first row of a result set.
     *
     * @param resource $result
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access private
     */
    function _skipFirstRows($result)
    {
        $result_value = intval($result);
        $first = $this->limits[$result_value][0];
        for(;$this->limits[$result_value][2] < $first;$this->limits[$result_value][2]++) {
            if (!OCIFetch($result)) {
                $this->limits[$result_value][2] = $first;
                return($this->SetError("Skip first rows", "could not skip a query result row"));
            }
        }
        return(MDB_OK);
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     * 
     * @param resource $result result identifier
     * @return mixed an associative array variable
     *                               that will hold the names of columns. The
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
        if (!isset($this->highest_fetched_row[$result_value])) {
            return($this->SetError("Get column names", "it was specified an inexisting result set"));
        }
        if (!isset($this->columns[$result_value])) {
            $this->columns[$result_value] = array();
            $columns = OCINumCols($result);
            for($column = 0;$column < $columns;$column++) {
                $this->columns[$result_value][strtolower(OCIColumnName($result, $column + 1))] = $column;
            }
        }
        $column_names = $this->columns[$result_value];
        return(MDB_OK);
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
        if (!isset($this->highest_fetched_row[intval($result)])) {
            $this->SetError("Number of columns", "it was specified an inexisting result set");
            return(-1);
        }
        return(OCINumCols($result));
    }

    // }}}
    // {{{ endOfResult()

    /**
     * check if the end of the result set has been reached
     * 
     * @param resource $result result identifier
     * @return mixed TRUE or FALSE on sucess, a MDB error on failure
     * @access public 
     */
    function endOfResult($result)
    {
        $result_value = intval($result);
        if (!isset($this->current_row[$result_value])) {
            $this->SetError("End of result", "attempted to check the end of an unknown result");
            return(-1);
        }
        if (isset($this->rows[$result_value])) {
            return($this->highest_fetched_row[$result_value] >= $this->rows[$result_value]-1);
        }
        if (isset($this->row_buffer[$result_value])) {
            return(0);
        }
        if (isset($this->limits[$result_value])) {
            if (!$this->_skipFirstRows($result) || $this->current_row[$result_value] + 1 >= $this->limits[$result_value][1]) {
                $this->rows[$result_value] = 0;
                return(MDB_OK);
            }
        }
        if (OCIFetchInto($result, $this->row_buffer[$result_value])) {
            return(0);
        }
        unset($this->row_buffer[$result_value]);
        $this->rows[$result_value] = $this->current_row[$result_value] + 1;
        return(MDB_OK);
    }

    // }}}
    // {{{ _retrieveLob()

    /**
     * fetch a float value from a result set
     * 
     * @param int $lob handle to a lob created by the createLob() function
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access private 
     */
    function _retrieveLob($lob)
    {
        if (!isset($this->lobs[$lob])) {
            return($this->SetError("Retrieve LOB", "it was not specified a valid lob"));
        }
        if (!isset($this->lobs[$lob]["Value"])) {
            unset($lob_object);
            $result = $this->lobs[$lob]["Result"];
            $row = $this->lobs[$lob]["Row"];
            $field = $this->lobs[$lob]["Field"];
            $lob_object = $this->FetchResult($result, $row, $field);
            if (gettype($lob_object) != "object") {
                if (($column = $this->GetColumn($result, $field)) == -1) {
                    return(0);
                }
                if (isset($this->results[intval($result)][$row][$column])) {
                    return($this->SetError("Retrieve LOB", "attemped to retrive a non LOB result column"));
                } else {
                    return($this->SetError("Retrieve LOB", "attemped to retrieve LOB from non existing or NULL column"));
               }
            }
            $this->lobs[$lob]["Value"] = $lob_object->load();
        }
        return(MDB_OK);
    }

    // }}}
    // {{{ fetch()

    /**
     * fetch value from a result set
     * 
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed string on success, a MDB error on failure
     * @access public 
     */
    function fetch($result, $row, $field)
    {
        $result_value = intval($result);
        if (($column = $this->GetColumn($result, $field)) == -1 || !$this->FetchRow($result, $row)) {
            return("");
        }
        if (!isset($this->results[$result_value][$row][$column])) {
            return("");
        }
        $this->highest_fetched_row[$result_value] = max($this->highest_fetched_row[$result_value], $row);
        return($this->results[$result_value][$row][$column]);
    }

    // }}}
    // {{{ fetchClob()

    /**
     * fetch a clob value from a result set
     * 
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed content of the specified data cell, a MDB error on failure,
     *                a MDB error on failure
     * @access public 
     */
    function fetchClob($result, $row, $field)
    {
        return($this->FetchLOBResult($result, $row, $field));
    }
    // }}}
    // {{{ fetchBlob()
    /**
     * fetch a blob value from a result set
     * 
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed content of the specified data cell, a MDB error on failure
     * @access public 
     */
    function fetchBlob($result, $row, $field)
    {
        return($this->FetchLOBResult($result, $row, $field));
    }

    // }}}
    // {{{ resultIsNull()

    /**
     * Determine whether the value of a query result located in given row and
     *    field is a NULL.
     * 
     * @param resource $result result identifier
     * @param int $row number of the row where the data can be found
     * @param int $field field number where the data can be found
     * @return mixed TRUE or FALSE on success, a MDB error on failure
     * @access public 
     */
    function resultIsNull($result, $row, $field)
    {
        $result_value = intval($result);
        if (($column = $this->GetColumn($result, $field)) == -1 || !$this->FetchRow($result, $row)) {
            return(0);
        }
        $this->highest_fetched_row[$result_value] = max($this->highest_fetched_row[$result_value], $row);
        return(!isset($this->results[$result_value][$row][$column]));
    }

    // }}}
    // {{{ convertResult()

    /**
     * convert a value to a RDBMS indepdenant MDB type
     * 
     * @param mixed $value value to be converted
     * @param int $type constant that specifies which type to convert to
     * @return mixed converted value
     * @access public 
     */
    function convertResult($value, $type)
    {
        switch ($type) {
            case MDB_TYPE_BOOLEAN:
                $value = (strcmp($value, "Y") ? 0 : 1);
                return(MDB_OK);
            case MDB_TYPE_DECIMAL:
                return(MDB_OK);
            case MDB_TYPE_FLOAT:
                $value = doubleval($value);
                return(MDB_OK);
            case MDB_TYPE_DATE:
                $value = substr($value, 0, strlen("YYYY-MM-DD"));
                return(MDB_OK);
            case MDB_TYPE_TIME:
                $value = substr($value, strlen("YYYY-MM-DD "), strlen("HH:MI:SS"));
                return(MDB_OK);
            case MDB_TYPE_TIMESTAMP:
                return(MDB_OK);
            default:
                return($this->BaseConvertResult($value, $type));
        }
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
        $result_value = intval($result);
        if (!isset($this->current_row[$result_value])) {
            return($this->SetError("Number of rows", "attemped to obtain the number of rows contained in an unknown query result"));
        }
        if (!isset($this->rows[$result_value])) {
            if (!$this->GetColumnNames($result, $column_names)) {
                return(0);
            }
            if (isset($this->limits[$result_value])) {
                if (!$this->_skipFirstRows($result)) {
                    $this->rows[$result_value] = 0;
                    return(0);
                }
                $limit = $this->limits[$result_value][1];
            } else {
                $limit = 0;
            }
            if ($limit == 0 || $this->current_row[$result_value] + 1 < $limit) {
                if (isset($this->row_buffer[$result_value])) {
                    $this->current_row[$result_value]++;
                    $this->results[$result_value][$this->current_row[$result_value]] = $this->row_buffer[$result_value];
                    unset($this->row_buffer[$result_value]);
                }
                for(;($limit == 0 || $this->current_row[$result_value] + 1 < $limit) && OCIFetchInto($result, $this->results[$result_value][$this->current_row[$result_value] + 1]);$this->current_row[$result_value]++);
            }
            $this->rows[$result_value] = $this->current_row[$result_value] + 1;
        }
        return($this->rows[$result_value]);
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     * 
     * @param  $result result identifier
     * @return bool TRUE on success, FALSE if $result is invalid
     * @access public 
     */
    function freeResult($result)
    {
        $result_value = intval($result);
        if (!isset($this->current_row[$result_value])) {
            return($this->SetError("Free result", "attemped to free an unknown query result"));
        }
        unset($this->highest_fetched_row[$result_value]);
        unset($this->row_buffer[$result_value]);
        unset($this->limits[$result_value]);
        unset($this->current_row[$result_value]);
        unset($this->results[$result_value]);
        unset($this->columns[$result_value]);
        unset($this->rows[$result_value]);
        unset($this->result_types[$result]);
        return(OCIFreeCursor($result));
    }

    // }}}
    // {{{ getTypeDeclaration()

    /**
     * Obtain DBMS specific native datatype as a string
     * 
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Id
     * ently, the types
     *        of supported field properties are as follows:
     * 
     *        unsigned
     *            Boolean flag that indicates whether the field should be
     *            declared as unsigned integer if possible.
     * 
     *        default
     *            Integer value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string with the correct RDBMS native type
     * @access public 
     */
    function getTypeDeclaration($field)
    {
        switch ($field["type"]) {
            case "integer":
                return("INT");
            case "text":
                return("VARCHAR (" . (isset($field["length"]) ? $field["length"] : (isset($this->options["DefaultTextFieldLength"]) ? $this->options["DefaultTextFieldLength"] : 4000)) . ")");
            case "boolean":
                return("CHAR (1)");
            case "date":
            case "time":
            case "timestamp":
                return("DATE");
            case "float":
                return("NUMBER");
            case "decimal":
                return("NUMBER(*," . $this->decimal_places . ")");
        }
        return("");
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Id
     * ently, the types
     *        of supported field properties are as follows:
     * 
     *        unsigned
     *            Boolean flag that indicates whether the field should be
     *            declared as unsigned integer if possible.
     * 
     *        default
     *            Integer value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getIntegerDeclaration($name, $field)
    {
        if (isset($field["unsigned"]))
            $this->warning = "unsigned integer field \"$name\" is being declared as signed integer";
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $field["default"] : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTextDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the text
     *            field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        default
     *            Text value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTextDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getTextValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getClobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the large
     *            object field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getClobDeclaration($name, $field)
    {
        return("$name CLOB" . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getBlobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the large
     *            object field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getBlobDeclaration($name, $field)
    {
        return("$name BLOB" . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getBooleanDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a boolean type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Boolean value to be used as default for this field.
     * 
     *        notnullL
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getBooleanDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getBooleanValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a date type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Date value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getDateDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getDateValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a timestamp
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Timestamp value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTimestampDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getTimestampValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a time
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Time value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTimeDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getTimeValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a float type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Float value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getFloatDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getFloatValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a decimal type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Decimal value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to NULL.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getDecimalDeclaration($name, $field)
    {
        return("$name " . $this->getTypeDeclaration($field) . (isset($field["default"]) ? " DEFAULT " . $this->getDecimalValue($field["default"]) : "") . (isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getClobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param resource $prepared_query query handle from prepare()
     * @param  $parameter 
     * @param  $clob 
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getClobValue($prepared_query, $parameter, $clob)
    {
        $value = "EMPTY_CLOB()";
        return(MDB_OK);
    }

    // }}}
    // {{{ freeClobValue()

    /**
     * free a character large object
     * 
     * @param resource $prepared_query query handle from prepare()
     * @param string $blob 
     * @param string $value 
     * @access public 
     */
    function freeClobValue($prepared_query, $clob, &$value)
    {
        unset($value);
    }

    // }}}
    // {{{ getBlobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param resource $prepared_query query handle from prepare()
     * @param  $parameter 
     * @param  $blob 
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getBlobValue($prepared_query, $parameter, $blob)
    {
        $value = "EMPTY_BLOB()";
        return(MDB_OK);
    }

    // }}}
    // {{{ freeBlobValue()

    /**
     * free a binary large object
     * 
     * @param resource $prepared_query query handle from prepare()
     * @param string $blob 
     * @param string $value 
     * @access public 
     */
    function freeBlobValue($prepared_query, $blob, &$value)
    {
        unset($value);
    }

    // }}}
    // {{{ getDateValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getDateValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "TO_DATE('$value','YYYY-MM-DD')");
    }

    // }}}
    // {{{ getTimestampValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getTimestampValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "TO_DATE('$value','YYYY-MM-DD HH24:MI:SS')");
    }

    // }}}
    // {{{ getTimeValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     *        compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getTimeValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "TO_DATE('0001-01-01 $value','YYYY-MM-DD HH24:MI:SS')");
    }

    // }}}
    // {{{ getFloatValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getFloatValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    // }}}
    // {{{ getDecimalValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getDecimalValue($value)
    {
        return(!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    // }}}
    // {{{ nextId()

    /**
     * returns the next free id of a sequence
     * 
     * @param string $seq_name name of the sequence
     * @param boolean $ondemand when TRUE the seqence is
     *                           automatic created, if it
     *                           not exists
     * @return mixed MDB_Error or id
     * @access public 
     */
    function nextId($seq_name, $ondemand = FALSE)
    {
        if (!$this->connect($this->user, $this->password, $this->persistent)) {
            return(0);
        }
        if (!($result = $this->_doQuery("SELECT $name.nextval FROM DUAL", 0, 0))) {
            return(0);
        }
        if ($this->NumberOfRows($result) == 0) {
            $this->FreeResult($result);
            return($this->SetError("Get sequence next value", "could not find next value in sequence table"));
        }
        $value = intval($this->FetchResult($result, 0, 0));
        $this->FreeResult($result);
        return(MDB_OK);
    }

    // }}}
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     * 
     * @param resource $result result identifier
     * @param int $fetchmode how the array data should be indexed
     * @param int $rownum the row number to fetch
     * @return int data array on success, a MDB error on failure
     * @access public 
     */
    function fetchInto($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        if (!$this->FetchRow($result, $row)) {
            return(0);
        }
        $result_value = intval($result);
        $array = $this->results[$result_value][$row];
        $this->highest_fetched_row[$result_value] = max($this->highest_fetched_row[$result_value], $row);
        return($this->ConvertResultRow($result, $array));
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal mysql result pointer to the next available result
     * Currently not supported
     * 
     * @param a $ valid result resource
     * @return TRUE if a result is available otherwise return FALSE
     * @access public 
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
     * @param resource $result result identifier
     * @param mixed $mode depends on implementation
     * @return array an nested array, or a MDB error
     * @access public 
     */
    function tableInfo($result, $mode = NULL)
    {
        $count = 0;
        $res = array();
        /**
         * depending on $mode, metadata returns the following values:
         * 
         * - mode is FALSE (default):
         * $res[]:
         *    [0]["table"]       table name
         *    [0]["name"]        field name
         *    [0]["type"]        field type
         *    [0]["len"]         field length
         *    [0]["NULLable"]    field can be NULL (boolean)
         *    [0]["format"]      field precision if NUMBER
         *    [0]["default"]     field default value
         * 
         * - mode is MDB_TABLEINFO_ORDER
         * $res[]:
         *    ["num_fields"]     number of fields
         *    [0]["table"]       table name
         *    [0]["name"]        field name
         *    [0]["type"]        field type
         *    [0]["len"]         field length
         *    [0]["NULLable"]    field can be NULL (boolean)
         *    [0]["format"]      field precision if NUMBER
         *    [0]["default"]     field default value
         *    ['order'][field name] index of field named "field name"
         *    The last one is used, if you have a field name, but no index.
         *    Test:  if (isset($result['order']['myfield'])) { ...
         * 
         * - mode is MDB_TABLEINFO_ORDERTABLE
         *     the same as above. but additionally
         *    ["ordertable"][table name][field name] index of field
         *       named "field name"
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
                     NULLable, data_default from user_tab_columns
                     where table_name='$result' order by column_id";
            if (!$stmt = OCIParse($this->connection, $q_fields)) {
                return $this->oci8RaiseError();
            }
            if (!OCIExecute($stmt, OCI_DEFAULT)) {
                return $this->oci8RaiseError($stmt);
            } while (OCIFetch($stmt)) {
                $res[$count]['table'] = $result;
                $res[$count]['name'] = @OCIResult($stmt, 1);
                $res[$count]['type'] = @OCIResult($stmt, 2);
                $res[$count]['len'] = @OCIResult($stmt, 3);
                $res[$count]['format'] = @OCIResult($stmt, 4);
                $res[$count]['nullable'] = (@OCIResult($stmt, 5) == 'Y') ? TRUE : FALSE;
                $res[$count]['default'] = @OCIResult($stmt, 6);
                if ($mode &MDB_TABLEINFO_ORDER) {
                    $res['order'][$res[$count]['name']] = $count;
                }
                if ($mode &MDB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$count]['table']][$res[$count]['name']] = $count;
                }
                $count++;
            }
            $res['num_fields'] = $count;
            @OCIFreeStatement($stmt);
        } else { // else we want information about a resultset
            if ($result === $this->last_stmt) {
                $count = @OCINumCols($result);
                for ($i = 0; $i < $count; $i++) {
                    $res[$i]['name'] = @OCIColumnName($result, $i + 1);
                    $res[$i]['type'] = @OCIColumnType($result, $i + 1);
                    $res[$i]['len'] = @OCIColumnSize($result, $i + 1);

                    $q_fields = "select table_name, data_precision, NULLable, data_default from user_tab_columns where column_name='" . $res[$i]['name'] . "'";
                    if (!$stmt = OCIParse($this->connection, $q_fields)) {
                        return $this->oci8RaiseError();
                    }
                    if (!OCIExecute($stmt, OCI_DEFAULT)) {
                        return $this->oci8RaiseError($stmt);
                    }
                    OCIFetch($stmt);
                    $res[$i]['table'] = OCIResult($stmt, 1);
                    $res[$i]['format'] = OCIResult($stmt, 2);
                    $res[$i]['nullable'] = (OCIResult($stmt, 3) == 'Y') ? TRUE : FALSE;
                    $res[$i]['default'] = OCIResult($stmt, 4);
                    OCIFreeStatement($stmt);

                    if ($mode &MDB_TABLEINFO_ORDER) {
                        $res['order'][$res[$i]['name']] = $i;
                    }
                    if ($mode &MDB_TABLEINFO_ORDERTABLE) {
                        $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                    }
                }
                $res['num_fields'] = $count;
            } else {
                return $this->raiseError(MDB_ERROR_NOT_CAPABLE);
            }
        }
        return $res;
    }
}
};

?>