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
//
// $Id$

/**
 * @package  MDB
 * @category Database
 * @author   Lukas Smith <smith@backendmedia.com>
 */

/**
 * include the PEAR core
 */
require_once 'PEAR.php';

/**
 * MDB_Common: Base class that is extended by each MDB driver
 *
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com>
 */
class MDB_Common extends PEAR
{
    // {{{ properties
    /**
     * index of the MDB object withing the global $_MDB_databases array
     * @var integer
     * @access private
     */
    var $db_index = 0;

    /**
     * assoc mapping native error codes to DB ones
     * @var array
     */
    var $errorcode_map = array();

    /**
     * @var array
     * @access private
     */
    var $dsn = array();

    /**
     * @var array
     * @access private
     */
    var $connected_dsn = array();

    /**
     * @var mixed
     * @access private
     */
    var $connection = 0;

    /**
     * @var boolean
     * @access private
     */
    var $opened_persistent;

    /**
     * @var string
     * @access private
     */
    var $database_name = '';

    /**
     * @var string
     * @access private
     */
    var $connected_database_name = '';

    /**
     * @var array
     * @access private
     */
    var $supported = array();

    /**
     * $options["result_buffering"] -> boolean should results be buffered or not?
     * $options['persistent'] -> boolean persistent connection?
     * $options['debug'] -> integer numeric debug level
     * $options['debug_handler'] -> string function/meothd that captures debug messages
     * $options['lob_buffer_length'] -> integer LOB buffer length
     * $options['log_line_break'] -> string line-break format
     * $options['seqname_format'] -> string pattern for sequence name
     * $options['include_lob'] -> boolean
     * $options['include_manager'] -> boolean
     * $options['use_transactions'] -> boolean
     * @var array
     * @access private
     */
    var $options = array(
            'result_buffering' => true,
            'persistent' => false,
            'debug' => 0,
            'debug_handler' => 'MDB_defaultDebugOutput',
            'lob_buffer_length' => 8192,
            'log_line_break' => "\n",
            'seqname_format' => '%s_seq',
            'include_lob' => false,
            'include_manager' => false,
            'use_transactions' => false,
        );

    /**
     * @var string
     * @access private
     */
    var $escape_quotes = '';

    /**
     * @var integer
     * @access private
     */
    var $decimal_places = 2;

    /**
     * @var object
     * @access private
     */
    var $manager;

    /**
     * @var array
     * @access private
     */
    var $warnings = array();

    /**
     * @var string
     * @access private
     */
    var $debug_output = '';

    /**
     * @var boolean
     * @access private
     */
    var $auto_commit = true;

    /**
     * @var boolean
     * @access private
     */
    var $in_transaction = false;

    /**
     * @var integer
     * @access private
     */
    var $first_selected_row = 0;

    /**
     * @var integer
     * @access private
     */
    var $selected_row_limit = 0;

    /**
     * Database backend used in PHP (mysql, odbc etc.)
     * @var string
     * @access private
     */
    var $phptype;

    /**
     * Database used with regards to SQL syntax etc.
     * @var string
     * @access private
     */
    var $dbsyntax;

    /**
     * @var array
     * @access private
     */
    var $prepared_queries = array();

    /**
     * @var string
     * @access private
     */
    var $last_query = '';

    /**
     * @var mixed
     * @access private
     */
    var $result_mode = false;

    /**
     * @var integer
     * @access private
     */
    var $fetchmode = MDB_FETCHMODE_ORDERED;

    /**
     * @var integer
     * @access private
     */
    var $affected_rows = -1;

    /**
    * @var array
    * @access private
    */
    var $lobs = array();

    /**
    * @var array
    * @access private
    */
    var $clobs = array();

    /**
    * @var array
    * @access private
    */
    var $blobs = array();

    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_Common()
    {
        $database = count($GLOBALS['_MDB_databases']) + 1;
        $GLOBALS['_MDB_databases'][$database] = &$this;
        $this->db_index = $database;

        $this->PEAR('MDB_Error');
    }

    // }}}
    // {{{ _toString()

    /**
     * String conversation
     *
     * @return string
     * @access public
     */
    function toString()
    {
        $info = get_class($this);
        $info .= ': (phptype = '.$this->phptype.', dbsyntax = '.$this->dbsyntax.')';
        if ($this->connection) {
            $info .= ' [connected]';
        }
        return $info;
    }

    // }}}
    // {{{ errorCode()

    /**
     * Map native error codes to MDB's portable ones.  Requires that
     * the DB implementation's constructor fills in the $errorcode_map
     * property.
     *
     * @param mixed $nativecode the native error code, as returned by the
     *      backend database extension (string or integer)
     * @return int a portable MDB error code, or false if this MDB
     *      implementation has no mapping for the given error code.
     * @access public
     */
    function errorCode($nativecode)
    {
        if (isset($this->errorcode_map[$nativecode])) {
            return $this->errorcode_map[$nativecode];
        }
        // Fall back to MDB_ERROR if there was no mapping.
        return MDB_ERROR;
    }

    // }}}
    // {{{ errorMessage()

    /**
     * Map a MDB error code to a textual message.  This is actually
     * just a wrapper for MDB::errorMessage().
     *
     * @param integer $dbcode the MDB error code
     * @return string the corresponding error message, of false
     *      if the error code was unknown
     * @access public
     */
    function errorMessage($dbcode)
    {
        return MDB::errorMessage($this->errorcode_map[$dbcode]);
    }

    // }}}
    // {{{ raiseError()

    /**
     * This method is used to communicate an error and invoke error
     * callbacks etc.  Basically a wrapper for PEAR::raiseError
     * without the message string.
     *
     * @param mixed $code integer error code, or a PEAR error object (all
     *      other parameters are ignored if this parameter is an object
     * @param int $mode error mode, see PEAR_Error docs
     * @param mixed $options If error mode is PEAR_ERROR_TRIGGER, this is the
     *      error level (E_USER_NOTICE etc).  If error mode is
     *      PEAR_ERROR_CALLBACK, this is the callback function, either as a
     *      function name, or as an array of an object and method name. For
     *      other error modes this parameter is ignored.
     * @param string $userinfo Extra debug information.  Defaults to the last
     *      query and native error code.
     * @param mixed $nativecode Native error code, integer or string depending
     *      the backend.
     * @return object a PEAR error object
     * @access public
     * @see PEAR_Error
     */
    function &raiseError($code = MDB_ERROR, $mode = null, $options = null,
        $userinfo = null, $nativecode = null)
    {
        // The error is yet a MDB error object
        if (is_object($code)) {
            $error =& PEAR::raiseError($code, null, null, null, null, null, true);
            return $error;
        }

        if ($userinfo === null) {
            $userinfo = $this->last_query;
        }

        if ($nativecode) {
            $userinfo .= " [nativecode = $nativecode]";
        }

        $error =& PEAR::raiseError(null, $code, $mode, $options, $userinfo,
            'MDB_Error', true);

        return $error;
    }

    // }}}
    // {{{ errorNative()

    /**
     * returns an errormessage, provides by the database
     *
     * @return mixed MDB_Error or message
     * @access public
     */
    function errorNative()
    {
        return $this->raiseError(MDB_ERROR_NOT_CAPABLE);
    }

    // }}}
    // {{{ resetWarnings()

    /**
     * reset the warning array
     *
     * @access public
     */
    function resetWarnings()
    {
        $this->warnings = array();
    }

    // }}}
    // {{{ getWarnings()

    /**
     * get all warnings in reverse order.
     * This means that the last warning is the first element in the array
     *
     * @return array with warnings
     * @access public
     * @see resetWarnings()
     */
    function getWarnings()
    {
        return array_reverse($this->warnings);
    }

    // }}}
    // {{{ setFetchMode()

    /**
     * Sets which fetch mode should be used by default on queries
     * on this connection.
     *
     * @param integer $fetchmode MDB_FETCHMODE_ORDERED or MDB_FETCHMODE_ASSOC,
     *       possibly bit-wise OR'ed with MDB_FETCHMODE_FLIPPED.
     * @access public
     * @see MDB_FETCHMODE_ORDERED
     * @see MDB_FETCHMODE_ASSOC
     * @see MDB_FETCHMODE_FLIPPED
     */
    function setFetchMode($fetchmode)
    {
        switch ($fetchmode) {
            case MDB_FETCHMODE_ORDERED:
            case MDB_FETCHMODE_ASSOC:
                $this->fetchmode = $fetchmode;
                break;
            default:
                return $this->raiseError('invalid fetchmode mode');
        }
    }

    // }}}
    // {{{ setResultMode()

    /**
     * set result mode
     * Setting the mode to a string value will mean that all ressources are
     * Setting the mode to false or null will mean that ressources are returned
     * wrapped inside a class with the name $mode
     *
     * @param mixed $mode false or string name of the class
     * @param boolean $prefix determine if the class name shoul be prefixed with 'MDB_'
     * @return mixed MDB_OK or MDB_Error
     * @access public
     */
    function setResultMode($mode, $prefix = true)
    {
        if (is_string($mode)) {
            if ($prefix) {
                $class_name = 'MDB_'.$mode;
            } else {
                $class_name = $mode;
            }
            @MDB::loadFile($mode);
            if (class_exists($class_name)) {
                $this->result_mode = $class_name;
                return MDB_OK;
            }
            return $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
            "setResultMode: result class ($class_name) does not exist");
        }
        $this->result_mode = false;
        return MDB_OK;
    }

    // }}}
    // {{{ _return_result()

    /**
     * determine if the resource should be wrapped in a class
     *
     * @param resource $result result ressource
     * @param mixed $result_mode boolean or string which specifies which class to use
     * @return mixed resource or result object or MDB_Error
     * @access private
     */
    function &_return_result($result, $result_mode)
    {
        if ($result_mode || ($result_mode !== false && $this->result_mode)) {
            if (is_string($result_mode)) {
                $class_name = 'MDB_'.$result_mode;
                @MDB::loadFile($result_mode);
                if (!class_exists($class_name)) {
                    $error =& $this->raiseError(MDB_ERROR, null, null,
                        "result class ($class_name) does not exist");
                    return $error;
                }
            } elseif ($this->result_mode) {
                $class_name = $this->result_mode;
            } else {
                $error =& $this->raiseError(MDB_ERROR, null, null,
                    'no result class defined');
                return $error;
            }
            $result =& new $class_name($this, $result);
        }
        return $result;
    }

    // }}}
    // {{{ setOption()

    /**
     * set the option for the db class
     *
     * @param string $option option name
     * @param mixed $value value for the option
     * @return mixed MDB_OK or MDB_Error
     * @access public
     */
    function setOption($option, $value)
    {
        if (isset($this->options[$option])) {
            if (is_null($value)) {
                return $this->raiseError(MDB_ERROR, null, null,
                    'may not set an option to value null');
            }
            $this->options[$option] = $value;
            return MDB_OK;
        }
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            "unknown option $option");
    }

    // }}}
    // {{{ getOption()

    /**
     * returns the value of an option
     *
     * @param string $option option name
     * @return mixed the option value or error object
     * @access public
     */
    function getOption($option)
    {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        }
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            "unknown option $option");
    }

    // }}}
    // {{{ debug()

    /**
     * set a debug message
     *
     * @param string $message Message with information for the user.
     * @access public
     */
    function debug($message, $scope = '')
    {
        if ($this->options['debug'] && $this->options['debug_handler']) {
            call_user_func($this->options['debug_handler'], $this, $scope, $message);
        }
    }

    // }}}
    // {{{ debugOutput()

    /**
     * output debug info
     *
     * @return string content of the debug_output class variable
     * @access public
     */
    function debugOutput()
    {
        return $this->debug_output;
    }

    // }}}
    // {{{ escape()

    /**
     * Quotes a string so it can be safely used in a query. It will quote
     * the text so it can safely be used within a query.
     *
     * @param string $text the input string to quote
     * @return string quoted string
     * @access public
     */
    function escape($text)
    {
        if (strcmp($this->escape_quotes, "'")) {
            $text = str_replace($this->escape_quotes, $this->escape_quotes.$this->escape_quotes, $text);
        }
        return str_replace("'", $this->escape_quotes . "'", $text);
    }

    // }}}
    // {{{ loadModule()

    /**
     * loads an module
     *
     * @param string $module name of the module that should be loaded
     *      (only used for error messages)
     * @param string $property name of the property into which the class will be loaded
     * @return object on success a reference to the given module is returned
     *                and on failure a PEAR error
     * @access public
     */
    function &loadModule($module, $property = null)
    {
        $module = strtolower($module);
        if (!$property) {
            $property = $module;
        }
        if (!isset($this->{$property}) || !is_object($this->{$property})) {
            $include_dir = 'MDB/Modules/';
            if (@include_once($include_dir.ucfirst($module).'.php')) {
                $class_name = 'MDB_'.ucfirst($module);
            } elseif (@include_once($include_dir.ucfirst($module).'/'.$this->phptype.'.php')) {
                $class_name = 'MDB_'.ucfirst($module).'_'.$this->phptype;
            } else {
                $error =& $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                    'unable to find module: '.$module);
                return $error;
            }
            if (!class_exists($class_name)) {
                $error =& $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                    'unable to load module: '.$module);
                return $error;
            }
            $this->{$property} =& new $class_name($this->db_index);
        }
        return $this->{$property};
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     *
     * @param boolean $auto_commit flag that indicates whether the database
     *      changes should be committed right after executing every query
     *      statement. If this argument is 0 a transaction implicitly started.
     *      Otherwise, if a transaction is in progress it is ended by committing
     *      any database changes that were pending.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function autoCommit($auto_commit)
    {
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'autoCommit: transactions are not supported');
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
        $this->debug('commiting transaction', 'commit');
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'commit: commiting transactions is not supported');
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'rollback: rolling back transactions is not supported');
    }

    // }}}
    // {{{ disconnect()

    /**
     * Log out and disconnect from the database.
     *
     * @return mixed true on success, false if not connected and error
     *                object on error
     * @access public
     */
    function disconnect()
    {
        if ($this->in_transaction
            && !MDB::isError($this->rollback())
            && !MDB::isError($this->autoCommit(true))
        ) {
            $this->in_transaction = false;
        }
        return $this->_close();
    }

    // }}}
    // {{{ _close()

    /**
     * all the RDBMS specific things needed to close a DB connection
     *
     * @access private
     */
    function _close()
    {
        unset($GLOBALS['_MDB_databases'][$this->db_index]);
    }

    // }}}
    // {{{ setDatabase()

    /**
     * Select a different database
     *
     * @param string $name name of the database that should be selected
     * @return string name of the database previously connected to
     * @access public
     */
    function setDatabase($name)
    {
        $previous_database_name = (isset($this->database_name)) ? $this->database_name : '';
        $this->database_name = $name;
        return $previous_database_name;
    }

    // }}}
    // {{{ setDSN()

    /**
     * set the DSN
     *
     * @param mixed     $dsn    DSN string or array
     * @return MDB_OK
     * @access public
     */
    function setDSN($dsn)
    {
        $dsn_default = array (
            'username' => false,
            'password' => false,
            'protocol' => false,
            'hostspec' => false,
            'port'     => false,
            'socket'   => false,
        );
        $dsn = MDB::parseDSN($dsn);
        if (isset($dsn['database'])) {
            $this->database_name = $dsn['database'];
            unset($dsn['database']);
        }
        $this->dsn = array_merge($dsn_default, $dsn);
        return MDB_OK;
    }

    // }}}
    // {{{ getDSN()

    /**
     * return the DSN as a string
     *
     * @param string     $type    type to return
     * @return mixed DSN in the chosen type
     * @access public
     */
    function getDSN($type = 'string')
    {
        switch($type) {
            case 'array':
                $dsn = array(
                    'phptype' => $this->phptype,
                    'username' => $this->dsn['username'],
                    'password' => $this->dsn['password'],
                    'hostspec' => $this->dsn['hostspec'],
                    'database' => $this->database_name
                );
                break;
            default:
                $dsn = $this->phptype.'://'.$this->dsn['username'];':'.
                    $this->dsn['password'].'@'.$this->dsn['hostspec'].
                    ($this->dsn['port'] ? (':'.$this->dsn['port']) : '').
                    '/'.$this->database_name;
                break;
        }
        return $dsn;
    }

    // }}}
    // {{{ standaloneQuery()

   /**
     * execute a query as database administrator
     * 
     * @param string $query the SQL query
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function standaloneQuery($query)
    {
        return $this->query($query, null, false);
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string $query the SQL query
     * @param mixed   $types  array that contains the types of the columns in
     *                        the result set
     * @param mixed $result_mode boolean or string which specifies which class to use
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     */
    function &query($query, $types = null, $result_mode = false)
    {
        $this->debug($query, 'query');
        $error =& $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'query: method not implemented');
        return $error;
    }

    // }}}
    // {{{ setLimit()

    /**
     * set the range of the next query
     *
     * @param string $first first row to select
     * @param string $limit number of rows to select
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function setLimit($first, $limit)
    {
        if (!isset($this->supported['limit_queries'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'setLimit: limit is not supported by this driver');
        }
        $first = (int)$first;
        if ($first < 0) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'setLimit: it was not specified a valid first selected range row');
        }
        $limit = (int)$limit;
        if ($limit < 1) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'setLimit: it was not specified a valid selected range row limit');
        }
        $this->first_selected_row = $first;
        $this->selected_row_limit = $limit;
        return MDB_OK;
    }

    // }}}
    // {{{ limitQuery()

    // }}}
    // {{{ subSelect()

    /**
     * simple subselect emulation: leaves the query untouched for all RDBMS
     * that support subselects
     *
     * @access public
     *
     * @param string $query the SQL query for the subselect that may only
     *                      return a column
     * @param string $type determines type of the field
     *
     * @return string the query
     */
    function subSelect($query, $type = false)
    {
        if ($this->supported['sub_selects'] == true) {
            return $query;
        }
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'subSelect: method not implemented');
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
     * pratically only MySQL implements it natively, this type of query is
     * emulated through this method for other DBMS using standard types of
     * queries inside a transaction to assure the atomicity of the operation.
     *
     * @param string $table name of the table on which the REPLACE query will
     *       be executed.
     * @param array $fields associative array that describes the fields and the
     *       values that will be inserted or updated in the specified table. The
     *       indexes of the array are the names of all the fields of the table.
     *       The values of the array are also associative arrays that describe
     *       the values and other properties of the table fields.
     *
     *       Here follows a list of field properties that need to be specified:
     *
     *       value
     *           Value to be assigned to the specified field. This value may be
     *           of specified in database independent type format as this
     *           function can perform the necessary datatype conversions.
     *
     *           Default: this property is required unless the Null property is
     *           set to 1.
     *
     *       type
     *           Name of the type of the field. Currently, all types Metabase
     *           are supported except for clob and blob.
     *
     *           Default: no type conversion
     *
     *       null
     *           Boolean property that indicates that the value for this field
     *           should be set to null.
     *
     *           The default value for fields missing in INSERT queries may be
     *           specified the definition of a table. Often, the default value
     *           is already null, but since the REPLACE may be emulated using
     *           an UPDATE query, make sure that all fields of the table are
     *           listed in this function argument array.
     *
     *           Default: 0
     *
     *       key
     *           Boolean property that indicates that this field should be
     *           handled as a primary key or at least as part of the compound
     *           unique index of the table that will determine the row that will
     *           updated if it exists or inserted a new row otherwise.
     *
     *           This function will fail if no key field is specified or if the
     *           value of a key field is set to null because fields that are
     *           part of unique index they may not be null.
     *
     *           Default: 0
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function replace($table, $fields)
    {
        if (!$this->supported['replace']) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'replace: replace query is not supported');
        }
        $count = count($fields);
        for ($keys = 0, $condition = $insert = $values = '', reset($fields), $field = 0;
            $field < $count;
            next($fields), $field++)
        {
            $name = key($fields);
            if ($field > 0) {
                $insert .= ', ';
                $values .= ', ';
            }
            $insert .= $name;
            if (isset($fields[$name]['null']) && $fields[$name]['null']) {
                $value = 'NULL';
            } else {
                if (isset($fields[$name]['type'])) {
                    $value = $this->getValue($fields[$name]['type'], $fields[$name]['value']);
                } else {
                    $value = $fields[$name]['value'];
                }
            }
            $values .= $value;
            if (isset($fields[$name]['key']) && $fields[$name]['key']) {
                if ($value === 'NULL') {
                    return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                        'replace: key value '.$name.' may not be NULL');
                }
                $condition .= ($keys ? ' AND ' : ' WHERE ') . $name . '=' . $value;
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                'replace: not specified which fields are keys');
        }
        $in_transaction = $this->in_transaction;
        if (!$in_transaction && MDB::isError($result = $this->autoCommit(false))) {
            return $result;
        }
        $success = $this->query("DELETE FROM $table$condition");
        if (!MDB::isError($success)) {
            $affected_rows = $this->affected_rows;
            $success = $this->query("INSERT INTO $table ($insert) VALUES ($values)");
            $affected_rows += $this->affected_rows;
        }

        if (!$in_transaction) {
            if (!MDB::isError($success)) {
                if (!MDB::isError($success = $this->commit())
                    && !MDB::isError($success = $this->autoCommit(TRUE))
                    && isset($this->supported['AffectedRows'])
                ) {
                    $this->affected_rows = $affected_rows;
                }
            } else {
                $this->rollback();
                $this->autoCommit(true);
            }
        }
        return $success;
    }

    // }}}
    // {{{ prepare()

    /**
     * Prepares a query for multiple execution with execute().
     * With some database backends, this is emulated.
     * prepare() requires a generic query as string like
     * 'INSERT INTO numbers VALUES(?,?,?)'. The ? are wildcards.
     * Types of wildcards:
     *    ? - a quoted scalar value, i.e. strings, integers
     *
     * @param string $ the query to prepare
     * @return mixed resource handle for the prepared query on success, a DB
     *        error on failure
     * @access public
     * @see execute
     */
    function prepare($query)
    {
        $this->debug($query, 'prepare');
        $positions = array();
        for ($position = 0;
            $position < strlen($query) && is_int($question = strpos($query, '?', $position));
        ) {
            if (is_int($quote = strpos($query, "'", $position)) && $quote < $question) {
                if (!is_int($end_quote = strpos($query, "'", $quote + 1))) {
                    return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                        'prepare: query with an unterminated text string specified');
                }
                switch ($this->escape_quotes) {
                    case '':
                    case "'":
                        $position = $end_quote + 1;
                        break;
                    default:
                        if ($end_quote == $quote + 1) {
                            $position = $end_quote + 1;
                        } else {
                            if ($query[$end_quote-1] == $this->escape_quotes) {
                                $position = $end_quote;
                            } else {
                                $position = $end_quote + 1;
                            }
                        }
                        break;
                }
            } else {
                $positions[] = $question;
                $position = $question + 1;
            }
        }
        $this->prepared_queries[] = array(
            'query' => $query,
            'positions' => $positions,
            'values' => array(),
            'types' => array()
        );
        $prepared_query = count($this->prepared_queries);
        if ($this->selected_row_limit > 0) {
            $this->prepared_queries[$prepared_query-1]['first'] = $this->first_selected_row;
            $this->prepared_queries[$prepared_query-1]['limit'] = $this->selected_row_limit;
        }
        return $prepared_query;
    }

    // }}}
    // {{{ _validatePrepared()

    /**
     * validate that a handle is infact a prepared query
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepare()
     * @access private
     */
    function _validatePrepared($prepared_query)
    {
        if ($prepared_query < 1 || $prepared_query > count($this->prepared_queries)) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'invalid prepared query');
        }
        if (!is_array($this->prepared_queries[$prepared_query-1])) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'prepared query was already freed');
        }
        return MDB_OK;
    }

    // }}}
    // {{{ setParam()

    /**
     * Set the value of a parameter of a prepared query.
     *
     * @param int $prepared_query argument is a handle that was returned
     *       by the function prepare()
     * @param int $parameter the order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param mixed $value value that is meant to be assigned to specified
     *       parameter. The type of the value depends on the $type argument.
     * @param string $type designation of the type of the parameter to be set.
     *       The designation of the currently supported types is as follows:
     *           text, boolean, integer, decimal, float, date, time, timestamp,
     *           clob, blob
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function setParam($prepared_query, $parameter, $value, $type = null)
    {
        if ($type) {
            $result = $this->loadModule('datatype');
            if (MDB::isError($result)) {
                return $result;
            }
        }

        $result = $this->_validatePrepared($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }

        $index = $prepared_query-1;
        if ($parameter < 1
            || $parameter > count($this->prepared_queries[$index]['positions'])
        ) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'setParam: it was not specified a valid argument number');
        }

        $this->prepared_queries[$index]['values'][$parameter-1] = $value;
        $this->prepared_queries[$index]['types'][$parameter-1] = $type;
        return MDB_OK;
    }

    // }}}
    // {{{ setParamArray()

    /**
     * Set the values of multiple a parameter of a prepared query in bulk.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepare()
     * @param array $params array thats specifies all necessary infromation
     *       for setParam() the array elements must use keys corresponding to
     *       the number of the position of the parameter.
     * @param array $types array thats specifies the types of the fields
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamArray($prepared_query, $params, $types = null)
    {
        if (is_array($types)) {
            if (count($params) != count($types)) {
                return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                    'setParamArray: the number of given types ('.count($types).')'
                    .'is not corresponding to the number of given parameters ('.count($params).')');
            }
            for ($i = 0, $j = count($params); $i < $j; ++$i) {
                $success = $this->setParam($prepared_query, $i + 1, $params[$i], $types[$i]);
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        } else {
            for ($i = 0, $j = count($params); $i < $j; ++$i) {
                $success = $this->setParam($prepared_query, $i + 1, $params[$i]);
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ freePrepared()

    /**
     * Release resources allocated for the specified prepared query.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepare()
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function freePrepared($prepared_query)
    {
        $result = $this->_validatePrepared($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $this->prepared_queries[$prepared_query-1] = '';
        return MDB_OK;
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
     * @param mixed $result_mode boolean or string which specifies which class to use
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access private
     */
    function &_executePrepared($prepared_query, $query, $types = null, $result_mode = false)
    {
        $result = &$this->query($query, $types, $result_mode);
        return $result;
    }

    // }}}
    // {{{ execute()

    /**
     * Execute a prepared query statement.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepare()
     * @param array $types array that contains the types of the columns in the
     *       result set
     * @param mixed $result_mode boolean or string which specifies which class to use
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     */
    function &execute($prepared_query, $types = null, $result_mode = false)
    {
        $result = $this->_validatePrepared($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }

        $query = '';
        $index = $prepared_query-1;
        $this->clobs[$prepared_query] = $this->blobs[$prepared_query] = array();
        $count = count($this->prepared_queries[$index]['positions']);
        for ($last_position = $position = 0; $position < $count; $position++) {
            $current_position = $this->prepared_queries[$index]['positions'][$position];
            $query .= substr($this->prepared_queries[$index]['query'],
                $last_position, $current_position - $last_position);
            if (!isset($this->prepared_queries[$index]['values'][$position])
                && !isset($this->prepared_queries[$index]['types'][$position])
            ) {
                $success = 'NULL';
            } else {
                $value = $this->prepared_queries[$index]['values'][$position];
                $type = $this->prepared_queries[$index]['types'][$position];
                if ($type == 'clob' || $type == 'blob') {
                    if (is_array($value)) {
                        $value['database'] = &$this;
                        $value['prepared_query'] = $prepared_query;
                        $value['parameter'] = $position + 1;
                        $this->prepared_queries[$index]['fields'][$position] = $value['field'];
                        $value = $this->datatype->createLOB($value);
                        if (MDB::isError($value)) {
                            return $value;
                        }
                    }
                }
                $value_quoted = $this->getValue($type, $value);
                if (MDB::isError($value_quoted)) {
                    return $value_quoted;
                }
                if (is_numeric($value)) {
                    if($type == 'clob') {
                        $this->clobs[$prepared_query][$value] = $value_quoted;
                    } elseif ($type == 'blob') {
                        $this->blobs[$prepared_query][$value] = $value_quoted;
                    }
                }
            }
            $query .= $value_quoted;
            $last_position = $current_position + 1;
        }

        $query .= substr($this->prepared_queries[$index]['query'], $last_position);
        if ($this->selected_row_limit > 0) {
            $this->prepared_queries[$index]['first'] = $this->first_selected_row;
            $this->prepared_queries[$index]['limit'] = $this->selected_row_limit;
        }
        if (isset($this->prepared_queries[$index]['limit'])
            && $this->prepared_queries[$index]['limit'] > 0
        ) {
            $this->first_selected_row = $this->prepared_queries[$index]['first'];
            $this->selected_row_limit = $this->prepared_queries[$index]['limit'];
        } else {
            $this->first_selected_row = $this->selected_row_limit = 0;
        }
        $success = $this->_executePrepared($prepared_query, $query, $types, $result_mode);

        foreach ($this->clobs[$prepared_query] as $key => $value) {
             $this->datatype->destroyLOB($key);
             $this->datatype->freeCLOBValue($key, $value);
        }
        unset($this->clobs[$prepared_query]);
        foreach ($this->blobs[$prepared_query] as $key => $value) {
             $this->datatype->destroyLOB($key);
             $this->datatype->freeBLOBValue($key, $value);
        }
        unset($this->blobs[$prepared_query]);
        return $success;
    }

    // }}}
    // {{{ setResultTypes()

    /**
     * Define the list of types to be associated with the columns of a given
     * result set.
     *
     * This function may be called before invoking fetch(),
     * fetchRow(), fetchCol() and fetchAll() so that the necessary data type
     * conversions are performed on the data to be retrieved by them. If this
     * function is not called, the type of all result set columns is assumed
     * to be text, thus leading to not perform any conversions.
     *
     * @param resource $result result identifier
     * @param string $types array variable that lists the
     *       data types to be expected in the result set columns. If this array
     *       contains less types than the number of columns that are returned
     *       in the result set, the remaining columns are assumed to be of the
     *       type text. Currently, the types clob and blob are not fully
     *       supported.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function setResultTypes($result, $types)
    {
        $load = $this->loadModule('datatype');
        if (MDB::isError($load)) {
            return $load;
        }
        return $this->datatype->setResultTypes($result, $types);
    }

    // }}}
    // {{{ affectedRows()

    /**
     * returns the affected rows of a query
     *
     * @return mixed MDB_Error or number of rows
     * @access public
     */
    function affectedRows()
    {
        if (!$this->support('affected_rows')) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'affectedRows: method not implemented');
        }
        if ($this->affected_rows == -1) {
            return $this->raiseError(MDB_ERROR_NEED_MORE_DATA);
        }
        return $this->affected_rows;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @param resource $result result identifier
     * @return mixed associative array variable
     *       that holds the names of columns. The indexes of the array are
     *       the column names mapped to lower case and the values are the
     *       respective numbers of the columns starting from 0. Some DBMS may
     *       not return any columns when the result set does not contain any
     *       rows.
     *      a MDB error on failure
     * @access public
     */
    function getColumnNames($result)
    {
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'getColumnNames: method not implemented');
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @param resource $result result identifier
     * @return mixed integer value with the number of columns, a MDB error
     *       on failure
     * @access public
     */
    function numCols($result)
    {
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'numCols: method not implemented');
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'endOfResult: method not implemented');
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'numRows: method not implemented');
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param  $result result identifier
     * @return boolean true on success, false if $result is invalid
     * @access public
     */
    function freeResult($result)
    {
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'freeResult: method not implemented');
    }

    // }}}
    // {{{ getValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param string $type type to which the value should be converted to
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getValue($type, $value)
    {
        $result = $this->loadModule('datatype');
        if (MDB::isError($result)) {
            return $result;
        }
        if (method_exists($this->datatype,"get{$type}Value")) {
            return $this->datatype->{"get{$type}Value"}($value);
        }
        return $value;
    }

    // }}}
    // {{{ getDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare
     * of the given type
     *
     * @param string $type type to which the value should be converted to
     * @param string  $name   name the field to be declared.
     * @param string  $field  definition of the field
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getDeclaration($type, $name, $field)
    {
        $result = $this->loadModule('datatype');
        if (MDB::isError($result)) {
            return $result;
        }
        if (method_exists($this->datatype,"get{$type}Declaration")) {
            return $this->datatype->{"get{$type}Declaration"}($name, $field);
        }
        return $value;
    }

    // }}}
    // {{{ support()

    /**
     * Tell whether a DB implementation or its backend extension
     * supports a given feature.
     *
     * @param string $feature name of the feature (see the MDB class doc)
     * @return boolean whether this DB implementation supports $feature
     * @access public
     */
    function support($feature)
    {
        return (isset($this->supported[$feature]) && $this->supported[$feature]);
    }

    // }}}
    // {{{ getSequenceName()

    /**
     * adds sequence name formating to a sequence name
     *
     * @param string $sqn name of the sequence
     * @return string formatted sequence name
     * @access public
     */
    function getSequenceName($sqn)
    {
        return sprintf($this->options['seqname_format'],
            preg_replace('/[^a-z0-9_]/i', '_', $sqn));
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
    function nextID($seq_name, $ondemand = false)
    {
        return $this->raiseError(MDB_ERROR_NOT_CAPABLE, null, null,
            'nextID: method not implemented');
    }

    // }}}
    // {{{ currID()

    /**
     * returns the current id of a sequence
     *
     * @param string $seq_name name of the sequence
     * @return mixed MDB_Error or id
     * @access public
     */
    function currID($seq_name)
    {
        $this->warnings[] = 'database does not support getting current
            sequence value, the sequence value was incremented';
        $this->expectError(MDB_ERROR_NOT_CAPABLE);
        $id = $this->nextID($seq_name);
        $this->popExpectError(MDB_ERROR_NOT_CAPABLE);
        if (MDB::isError($id)) {
            if ($id->getCode() == MDB_ERROR_NOT_CAPABLE) {
                return $this->raiseError(MDB_ERROR_NOT_CAPABLE, null, null,
                    'currID: getting current sequence value not supported');
            }
            return $id;
        }
        return $id;
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, NULL, NULL,
            'fetch: method not implemented');
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch and return a row of data
     *
     * @param resource $result result identifier
     * @param int $fetchmode how the array data should be indexed
     * @param int $rownum the row number to fetch
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function fetchRow($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rownum)
    {
        if (MDB::isError($this->endOfResult($result))) {
            return null;
        }
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode == MDB_FETCHMODE_ASSOC) {
            $column_names = $this->getColumnNames($result);
            if (MDB::isError($column_names)) {
                return $column_names;
            }
        }
        for ($column = 0; $column < $columns; $column++) {
            $result = $this->fetch($result, $rownum, $column);
            if (!is_array($result)) {
                return null;
            }
            if (isset($column_names)) {
                $row[$column_names[$column]] = $result;
            } else {
                $row[$column] = $result;
            }
        }
        if (isset($this->results[intval($result)]['types'])) {
            $row = $this->datatype->convertResultRow($result, $row);
        }
        return $row;
    }

    // }}}
    // {{{ fetchCol()

    /**
     * Fetch and return a column of data (it uses fetchRow for that)
     *
     * @param resource $result result identifier
     * @param int $colnum the row number to fetch
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function fetchCol($result, $colnum = 0)
    {
        $fetchmode = is_numeric($colnum) ? MDB_FETCHMODE_ORDERED : MDB_FETCHMODE_ASSOC;
        $column = array();
        $rownum = 0;
        $row = $this->fetchRow($result, $fetchmode, $rownum);
        if (is_array($row)) {
            if (isset($row[$colnum])) {
                $column[] = $row[$colnum];
                ++$rownum;
                while (is_array($row = $this->fetchRow($result, $fetchmode, $rownum))) {
                    $column[] = $row[$colnum];
                    ++$rownum;
                }
            } else {
                return $this->raiseError(MDB_ERROR_TRUNCATED);
            }
        }

        if (MDB::isError($row)) {
            return $row;
        }
        return $column;
    }

    // }}}
    // {{{ fetchAll()

    /**
     * Fetch and return a column of data (it uses fetchRow for that)
     *
     * @param resource $result result identifier
     * @param int $fetchmode how the array data should be indexed
     * @param boolean $rekey if set to true, the $all will have the first
     *       column as its first dimension
     * @param boolean $force_array used only when the query returns exactly
     *       two columns. If true, the values of the returned array will be
     *       one-element arrays instead of scalars.
     * @param boolean $group if true, the values of the returned array is
     *       wrapped in another array.  If the same key value (in the first
     *       column) repeats itself, the values will be appended to this array
     *       instead of overwriting the existing values.
     * @return mixed data array on success, a MDB error on failure
     * @access public
     * @see getAssoc()
     */
    function fetchAll($result, $fetchmode = MDB_FETCHMODE_DEFAULT, $rekey = false, $force_array = false, $group = false)
    {
        if ($rekey) {
            $cols = $this->numCols($result);
            if (MDB::isError($cols)) {
                return $cols;
            }
            if ($cols < 2) {
                return $this->raiseError(MDB_ERROR_TRUNCATED);
            }
        }
        $all = array();
        $rownum = 0;
        while (is_array($row = $this->fetchRow($result, $fetchmode, $rownum))) {
            if ($rekey) {
                if ($fetchmode == MDB_FETCHMODE_ASSOC) {
                    $key = reset($row);
                    unset($row[key($row)]);
                } else {
                    $key = array_shift($row);
                }
                if (!$force_array && count($row) == 1) {
                    $row = array_shift($row);
                }
                if ($group) {
                    $all[$key][] = $row;
                } else {
                    $all[$key] = $row;
                }
            } else {
                if ($fetchmode & MDB_FETCHMODE_FLIPPED) {
                    foreach ($row as $key => $val) {
                        $all[$key][] = $val;
                    }
                } else {
                   $all[] = $row;
                }
            }
            ++$rownum;
        }

        if (MDB::isError($row)) {
            return $row;
        }
        return $all;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal result pointer to the next available result
     *
     * @param a valid result resource
     * @return true on success or an error object on failure
     * @access public
     */
    function nextResult($result)
    {
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'nextResult: method not implemented');
    }

    // }}}
    // {{{ Destructor

    /**
    * this function closes open transactions to be executed at shutdown
    *
    * @access private
    */
    function _MDB_Common()
    {
        if ($this->in_transaction && !MDB::isError($this->rollback())) {
            $this->autoCommit(true);
        }
    }
};

// }}}
// {{{ MDB_defaultDebugOutput()

/**
 * default debug output handler
 *
 * @param object $db reference to an MDB database object
 * @param string $message message that should be appended to the debug
 *       variable
 * @return string the corresponding error message, of false
 * if the error code was unknown
 * @access public
 */
function MDB_defaultDebugOutput(&$db, $scope, $message)
{
    $db->debug_output .= $scope.'('.$db->db_index.'): '.$message.$db->getOption('log_line_break');
}

// Used by many drivers
if (!function_exists('array_change_key_case')) {
    if (!defined('CASE_UPPER')) {
        define('CASE_UPPER', 1);
    }
    if (!defined('CASE_LOWER')) {
        define('CASE_LOWER', 0);
    }
    function &array_change_key_case(&$array, $case)
    {
        $casefunc = ($case == CASE_LOWER) ? 'strtolower' : 'strtoupper';
        $ret = array();
        foreach ($array as $key => $value) {
            $ret[$casefunc($key)] = $value;
        }
        return $ret;
    }
}
?>