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

require_once('MDB/Common.php');

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

    // MSSQL specific class variable
    var $default_table_type = '';
    var $fixed_float = 0;
    var $dummy_primary_key = 'dummy_primary_key';

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

        $this->supported['Sequences'] = 1;
        $this->supported['Indexes'] = 1;
        $this->supported['AffectedRows'] = 1;
        $this->supported['Summaryfunctions'] = 1;
        $this->supported['OrderByText'] = 0;
        $this->supported['CurrId'] = 1;
        $this->supported['SelectRowRanges'] = 0;
        $this->supported['LOBs'] = 1;
        $this->supported['Replace'] = 0;
        $this->supported['SubSelects'] = 1;
        
        $this->decimal_factor = pow(10.0, $this->decimal_places);
        
        $this->options['DefaultTableType'] = FALSE;
        $this->options['fixed_float'] = FALSE;
        
        $this->errorcode_map = array(
            1004 => MDB_ERROR_CANNOT_CREATE,
            1005 => MDB_ERROR_CANNOT_CREATE,
            1006 => MDB_ERROR_CANNOT_CREATE,
            1007 => MDB_ERROR_ALREADY_EXISTS,
            1008 => MDB_ERROR_CANNOT_DROP,
            1046 => MDB_ERROR_NODBSELECTED,
            1050 => MDB_ERROR_ALREADY_EXISTS,
            1051 => MDB_ERROR_NOSUCHTABLE,
            1054 => MDB_ERROR_NOSUCHFIELD,
            1062 => MDB_ERROR_ALREADY_EXISTS,
            1064 => MDB_ERROR_SYNTAX,
            1100 => MDB_ERROR_NOT_LOCKED,
            1136 => MDB_ERROR_VALUE_COUNT_ON_ROW,
            1146 => MDB_ERROR_NOSUCHTABLE,
            1048 => MDB_ERROR_CONSTRAINT,
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
     * @return int native MSSQL error code
     */
    function errorNative()
    {
        return mssql_errno($this->connection);
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
    function mssqlRaiseError($errno = NULL)
    {
        if ($errno == NULL) {
            $errno = $this->errorCode(mssql_errno($this->connection));
        }
        return($this->raiseError($errno, NULL, NULL, NULL, @mssql_error($this->connection)));
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
        $this->debug("AutoCommit: ".($auto_commit ? "On" : "Off"));
        if (!isset($this->supported['Transactions'])) {
            return($this->raiseError(MDB_ERROR_UNSUPPORTED, NULL, NULL,
                'Auto-commit transactions: transactions are not in use'));
        }
        if (((!$this->auto_commit) == (!$auto_commit))) {
            return(MDB_OK);
        }
        if ($this->connection) {
            if ($auto_commit) {
                $result = $this->query('COMMIT');
                if (MDB::isError($result)) {
                    return($result);
                }
                $result = $this->query('SET IMPLICIT_TRANSACTIONS ON');
                if (MDB::isError($result)) {
                    return($result);
                }
            } else {
                $result = $this->query('SET IMPLICIT_TRANSACTIONS OFF');
                if (MDB::isError($result)) {
                    return($result);
                }
            }
        }
        $this->auto_commit = $auto_commit;
        return(MDB_OK);
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
        $this->debug("Commit Transaction");
        if (!isset($this->supported['Transactions'])) {
            return($this->raiseError(MDB_ERROR_UNSUPPORTED, NULL, NULL,
                'Commit transactions: transactions are not in use'));
        }
        if ($this->auto_commit) {
            return($this->raiseError(MDB_ERROR, NULL, NULL,
            'Commit transactions: transaction changes are being auto commited'));
        }
        return($this->query('COMMIT'));
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
        $this->debug("Rollback Transaction");
        if (!isset($this->supported['Transactions'])) {
            return($this->raiseError(MDB_ERROR_UNSUPPORTED, NULL, NULL,
                'Rollback transactions: transactions are not in use'));
        }
        if ($this->auto_commit) {
            return($this->raiseError(MDB_ERROR, NULL, NULL,
                'Rollback transactions: transactions can not be rolled back when changes are auto commited'));
        }
        return($this->query('ROLLBACK'));
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
        $port = (isset($this->port) ? $this->port : '');
        if($this->connection != 0) {
            if (!strcmp($this->connected_host, $this->host)
                && !strcmp($this->connected_user, $this->user)
                && !strcmp($this->connected_password, $this->password)
                && !strcmp($this->connected_port, $port)
                && $this->opened_persistent == $this->options['persistent'])
            {
                return(MDB_OK);
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;
        }

        if(PEAR::isError(PEAR::loadExtension($this->phptype))) {
            return(PEAR::raiseError(NULL, MDB_ERROR_NOT_FOUND,
                NULL, NULL, 'extension '.$this->phptype.' is not compiled into PHP',
                'MDB_Error', TRUE));
        }

        $UseTransactions = $this->getOption('UseTransactions');
        if(!MDB::isError($UseTransactions) && $UseTransactions) {
            $this->supported['Transactions'] = 1;
            $this->default_table_type = 'BDB';
        } else {
            $this->default_table_type = '';
        }
        $DefaultTableType = $this->getOption('DefaultTableType');
        if(!MDB::isError($DefaultTableType) && $DefaultTableType) {
            switch($this->default_table_type = strtoupper($DefaultTableType)) {
                case 'BERKELEYDB':
                    $this->default_table_type = 'BDB';
                case 'BDB':
                case 'INNODB':
                case 'GEMINI':
                    break;
                case 'HEAP':
                case 'ISAM':
                case 'MERGE':
                case 'MRG_MYISAM':
                case 'MYISAM':
                    if(isset($this->supported['Transactions'])) {
                        $this->warnings[] = $DefaultTableType
                            .' is not a transaction-safe default table type';
                    }
                    break;
                default:
                    $this->warnings[] = $DefaultTableType
                        .' is not a supported default table type';
            }
        }

        $this->fixed_float = 30;
        $function = ($this->options['persistent'] ? 'mssql_pconnect' : 'mssql_connect');
        if (!function_exists($function)) {
            return($this->raiseError(MDB_ERROR_UNSUPPORTED));
        }

        @ini_set('track_errors', TRUE);
        $this->connection = @$function(
            $this->host.(!strcmp($port,'') ? '' : ':'.$port),
            $this->user, $this->password);
        @ini_restore('track_errors');
        if ($this->connection <= 0) {
            return($this->raiseError(MDB_ERROR_CONNECT_FAILED, NULL, NULL,
                    $php_errormsg));
        }

        if (isset($this->options['fixedfloat'])) {
            $this->fixed_float = $this->options['fixedfloat'];
        } else {
            if (($result = mssql_query('SELECT @@VERSION', $this->connection))) {
                $version = explode('.', mssql_result($result,0,0));
                $major = intval($version[0]);
                $minor = intval($version[1]);
                $revision = intval($version[2]);
                if ($major > 3 || ($major == 3 && $minor >= 23
                    && ($minor > 23 || $revision >= 6)))
                {
                    $this->fixed_float = 0;
                }
                mssql_free_result($result);
            }
        }
        if (isset($this->supported['Transactions']) && !$this->auto_commit) {
            if (!mssql_query('SET IMPLICIT_TRANSACTIONS OFF', $this->connection)) {
                mssql_close($this->connection);
                $this->connection = 0;
                $this->affected_rows = -1;
                return($this->raiseError());
            }
        }
        $this->connected_host = $this->host;
        $this->connected_user = $this->user;
        $this->connected_password = $this->password;
        $this->connected_port = $port;
        $this->opened_persistent = $this->getoption('persistent');
        return(MDB_OK);
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
            if (isset($this->supported['Transactions']) && !$this->auto_commit) {
                $result = $this->autoCommit(TRUE);
            }
            mssql_close($this->connection);
            $this->connection = 0;
            $this->affected_rows = -1;

            if (isset($result) && MDB::isError($result)) {
                return($result);
            }
            global $_MDB_databases;
            $_MDB_databases[$this->database] = '';
            return(TRUE);
        }
        return(FALSE);
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @access public
     *
     * @param string  $query  the SQL query
     * @param array   $types  array that contains the types of the columns in
     *                        the result set
     *
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     */
    function query($query, $types = NULL)
    {
        $this->debug("Query: $query");
        $ismanip = MDB::isManip($query);
        $this->last_query = $query;
        $first = $this->first_selected_row;
        $limit = $this->selected_row_limit;
        $this->first_selected_row = $this->selected_row_limit = 0;

        $result = $this->connect();
        if (MDB::isError($result)) {
            return($result);
        }
        if($limit > 0) {
            if ($ismanip) {
                eregi_replace('SELECT', "SELECT TOP $limit", $query);
            }
        }
        if ($this->database_name) {
            if(!mssql_select_db($this->database_name, $this->connection)) {
                return($this->mssqlRaiseError());
            }
        }
        if ($result = mssql_query($query, $this->connection)) {
            if ($ismanip) {
                $this->affected_rows = mssql_affected_rows($this->connection);
                return(MDB_OK);
            } else {
                $this->highest_fetched_row[$result] = -1;
                if ($types != NULL) {
                    if (!is_array($types)) {
                        $types = array($types);
                    }
                    if (MDB::isError($err = $this->setResultTypes($result, $types))) {
                        $this->freeResult($result);
                        return($err);
                    }
                }
                return($result);
            }
        }
        return($this->mssqlRaiseError());
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
        if (!isset($this->highest_fetched_row[$result_value])) {
            return($this->raiseError(MDB_ERROR_INVALID, NULL, NULL,
                'Get column names: it was specified an inexisting result set'));
        }
        if (!isset($this->columns[$result_value])) {
            $this->columns[$result_value] = array();
            $columns = mssql_num_fields($result);
            for($column = 0; $column < $columns; $column++) {
                $this->columns[$result_value][strtolower(mssql_field_name($result, $column))] = $column;
            }
        }
        return($this->columns[$result_value]);
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
        if (!isset($this->highest_fetched_row[intval($result)])) {
            return($this->raiseError(MDB_ERROR_INVALID, NULL, NULL,
                'numCols: it was specified an inexisting result set'));
        }
        return(mssql_num_fields($result));
    }

    // }}}
    // {{{ endOfResult()

    /**
    * check if the end of the result set has been reached
    *
    * @param resource    $result result identifier
    * @return mixed TRUE or FALSE on sucess, a MDB error on failure
    * @access public
    */
    function endOfResult($result)
    {
        if (!isset($this->highest_fetched_row[$result])) {
            return($this->raiseError(MDB_ERROR, NULL, NULL,
                'End of result: attempted to check the end of an unknown result'));
        }
        return($this->highest_fetched_row[$result] >= $this->numRows($result)-1);
    }

    // }}}
    // {{{ fetch()

    /**
    * fetch value from a result set
    *
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed string on success, a MDB error on failure
    * @access public
    */
    function fetch($result, $row, $field)
    {
        $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        $res = @mssql_result($result, $row, $field);
        if ($res === FALSE && $res != NULL) {
            return($this->mssqlRaiseError($errno));
        }
        return($res);
    }

    // }}}
    // {{{ fetchClob()

    /**
    * fetch a clob value from a result set
    *
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed content of the specified data cell, a MDB error on failure,
    *               a MDB error on failure
    * @access public
    */
    function fetchClob($result, $row, $field)
    {
        return($this->fetchLob($result, $row, $field));
    }

    // }}}
    // {{{ fetchBlob()

    /**
    * fetch a blob value from a result set
    *
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found
    * @return mixed content of the specified data cell, a MDB error on failure
    * @access public
    */
    function fetchBlob($result, $row, $field)
    {
        return($this->fetchLob($result, $row, $field));
    }

    // }}}
    // {{{ convertResult()

    /**
    * convert a value to a RDBMS indepdenant MDB type
    *
    * @param mixed  $value   value to be converted
    * @param int    $type    constant that specifies which type to convert to
    * @return mixed converted value
    * @access public
    */
    function convertResult($value, $type)
    {
        switch($type) {
            case MDB_TYPE_BOOLEAN:
                return(strcmp($value, 'Y') ? 0 : 1);
            case MDB_TYPE_DECIMAL:
                return(sprintf('%.'.$this->decimal_places.'f', doubleval($value)/$this->decimal_factor));
            case MDB_TYPE_FLOAT:
                return(doubleval($value));
            case MDB_TYPE_DATE:
                return($value);
            case MDB_TYPE_TIME:
                return($value);
            case MDB_TYPE_TIMESTAMP:
                return($value);
            default:
                return($this->_baseConvertResult($value, $type));
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
        return(mssql_num_rows($result));
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param $result result identifier
     * @return boolean TRUE on success, FALSE if $result is invalid
     * @access public
     */
    function freeResult($result)
    {
        if(isset($this->highest_fetched_row[$result])) {
            unset($this->highest_fetched_row[$result]);
        }
        if(isset($this->columns[$result])) {
            unset($this->columns[$result]);
        }
        if(isset($this->result_types[$result])) {
            unset($this->result_types[$result]);
        }
        return(mssql_free_result($result));
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       unsigned
     *                        Boolean flag that indicates whether the field
     *                        should be declared as unsigned integer if
     *                        possible.
     *
     *                       default
     *                        Integer value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getIntegerDeclaration($name, $field)
    {
        return("$name INT".
                (isset($field['unsigned']) ? ' UNSIGNED' : '').
                (isset($field['default']) ? ' DEFAULT '.$field['default'] : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               );
    }

    // }}}
    // {{{ getClobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the
     *                        properties of the field being declared as array
     *                        indexes. Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       length
     *                        Integer value that determines the maximum length
     *                        of the large object field. If this argument is
     *                        missing the field should be declared to have the
     *                        longest length allowed by the DBMS.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field
     *                        is constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getClobDeclaration($name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            if ($length <= 255) {
                $type = 'TINYTEXT';
            } else {
                if ($length <= 65535) {
                    $type = 'TEXT';
                } else {
                    if ($length <= 16777215) {
                        $type = 'MEDIUMTEXT';
                    } else {
                        $type = 'LONGTEXT';
                    }
                }
            }
        } else {
            $type = 'LONGTEXT';
        }
        return("$name $type".
                 (isset($field['notnull']) ? ' NOT NULL' : ''));
    }

    // }}}
    // {{{ getBlobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       length
     *                        Integer value that determines the maximum length
     *                        of the large object field. If this argument is
     *                        missing the field should be declared to have the
     *                        longest length allowed by the DBMS.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getBlobDeclaration($name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            if ($length <= 255) {
                $type = 'TINYBLOB';
            } else {
                if ($length <= 65535) {
                    $type = 'BLOB';
                } else {
                    if ($length <= 16777215) {
                        $type = 'MEDIUMBLOB';
                    } else {
                        $type = 'LONGBLOB';
                    }
                }
            }
        }
        else {
            $type = 'LONGBLOB';
        }
        return("$name $type".
                (isset($field['notnull']) ? ' NOT NULL' : ''));
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an date type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field properties
     *                        are as follows:
     *
     *                       default
     *                        Date value to be used as default for this field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getDateDeclaration($name, $field)
    {
        return("$name DATE".
                (isset($field['default']) ? " DEFAULT '".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               );
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an timestamp
     * type field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Time stamp value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getTimestampDeclaration($name, $field)
    {
        return("$name DATETIME".
                (isset($field['default']) ? " DEFAULT '".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               );
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an time type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Time value to be used as default for this field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getTimeDeclaration($name, $field)
    {
        return("$name TIME".
                (isset($field['default']) ? " DEFAULT '".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               );
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an float type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Integer value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getFloatDeclaration($name, $field)
    {
        if (isset($this->options['fixedfloat'])) {
            $this->fixed_float = $this->options['fixedfloat'];
        } else {
            if ($this->connection == 0) {
                // XXX needs more checking
                $this->connect();
            }
        }
        return("$name DOUBLE".
                ($this->fixed_float ?
                 '('.($this->fixed_float + 2).','.$this->fixed_float.')' : '').
                (isset($field['default']) ?
                 ' DEFAULT '.$this->getFloatValue($field['default']) : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               );
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an decimal type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Integer value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to NULL.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getDecimalDeclaration($name, $field)
    {
        return("$name BIGINT".
                (isset($field['default']) ?
                 ' DEFAULT '.$this->getDecimalValue($field['default']) : '').
                 (isset($field['notnull']) ? ' NOT NULL' : '')
               );
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
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getClobValue($prepared_query, $parameter, $clob)
    {
        $value = "'";
        while(!$this->endOfLob($clob)) {
            if (MDB::isError($result = $this->readLob($clob, $data, $this->options['lob_buffer_length']))) {
                return($result);
            }
            $value .= $this->_quote($data);
        }
        $value .= "'";
        return($value);
    }

    // }}}
    // {{{ freeClobValue()

    /**
     * free a character large object
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $clob
     * @return MDB_OK
     * @access public
     */
    function freeClobValue($prepared_query, $clob)
    {
        unset($this->lobs[$clob]);
        return(MDB_OK);
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
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getBlobValue($prepared_query, $parameter, $blob)
    {
        $value = "'";
        while(!$this->endOfLob($blob)) {
            if (MDB::isError($result = $this->readLob($blob, $data, $this->options['lob_buffer_length']))) {
                return($result);
            }
            $value .= addslashes($data);
        }
        $value .= "'";
        return($value);
    }

    // }}}
    // {{{ freeBlobValue()

    /**
     * free a binary large object
     *
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $blob
     * @return MDB_OK
     * @access public
     */
    function freeBlobValue($prepared_query, $blob)
    {
        unset($this->lobs[$blob]);
        return(MDB_OK);
    }

    // }}}
    // {{{ getFloatValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string  $value text string value that is intended to be converted.
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getFloatValue($value)
    {
        return(($value === NULL) ? 'NULL' : (float)$value);
    }

    // }}}
    // {{{ getDecimalValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string  $value text string value that is intended to be converted.
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getDecimalValue($value)
    {
        return(($value === NULL) ? 'NULL' : strval(round(doubleval($value)*$this->decimal_factor)));
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
    function nextId($seq_name, $ondemand = TRUE)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB_ERROR_NOSUCHTABLE);
        $result = $this->query("INSERT INTO $sequence_name (sequence) VALUES (NULL)");
        $this->popExpect();
        if ($ondemand && MDB::isError($result) &&
            $result->getCode() == MDB_ERROR_NOSUCHTABLE)
        {
            // Since we are create the sequence on demand
            // we know the first id = 1 so initialize the
            // sequence at 2
            $result = $this->createSequence($seq_name, 2);
            if (MDB::isError($result)) {
                return($this->raiseError(MDB_ERROR, NULL, NULL,
                    'Next ID: on demand sequence could not be created'));
            } else {
                // First ID of a newly created sequence is 1
                return(1);
            }
        }
        $value = intval(mssql_insert_id($this->connection));
        $res = $this->query("DELETE FROM $sequence_name WHERE sequence < $value");
        if (MDB::isError($res)) {
            $this->warnings[] = 'Next ID: could not delete previous sequence table values';
        }
        return($value);
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
            return($result);
        }

        return($this->fetchOne($result));
    }

    // }}}
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param resource  $result     result identifier
     * @param int       $fetchmode  how the array data should be indexed
     * @param int       $rownum     the row number to fetch
     * @return int data array on success, a MDB error on failure
     * @access public
     */
    function fetchInto($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        if ($rownum == NULL) {
            ++$this->highest_fetched_row[$result];
        } else {
            if (!@mssql_data_seek($result, $rownum)) {
                return(NULL);
            }
            $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $rownum);
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode & MDB_FETCHMODE_ASSOC) {
            $array = @mssql_fetch_array($result, MYSQL_ASSOC);
        } else {
            $array = @mssql_fetch_row($result);
        }
        if (!$array) {
            $errno = @mssql_errno($this->connection);
            if (!$errno) {
                if($this->options['autofree']) {
                    $this->freeResult($result);
                }
                return(NULL);
            }
            return($this->mssqlRaiseError($errno));
        }
        if (isset($this->result_types[$result])) {
            $array = $this->convertResultRow($result, $array);
        }
        return($array);
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
        return(mssql_next_result($result));
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
    function tableInfo($result, $mode = NULL) {
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
                return($this->mssqlRaiseError());
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return($this->mssqlRaiseError());
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
        return($res);
    }
}

?>
