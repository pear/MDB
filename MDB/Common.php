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
 * @package MDB
 * @author Lukas Smith <smith@backendmedia.com>
 */

require_once 'PEAR.php';

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
function MDB_defaultDebugOutput(&$db, $message)
{
    $db->debug_output .= $db->database.': '.$message.$db->getOption('log_line_break');
}

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
    var $database = 0;

    /**
     * assoc mapping native error codes to DB ones
     * @var array
     */
    var $errorcode_map = array();

    /**
     * @var string
     * @access private
     */
    var $host = '';

    /**
     * @var string
     * @access private
     */
    var $port = '';

    /**
     * @var string
     * @access private
     */
    var $user = '';

    /**
     * @var string
     * @access private
     */
    var $password = '';

    /**
     * @var string
     * @access private
     */
    var $database_name = '';

    /**
     * @var array
     * @access private
     */
    var $supported = array();

    /**
     * $options["optimize"] -> string 'performance' or 'portability'
     * $options['persistent'] -> boolean persistent connection?
     * $options['debug'] -> integer numeric debug level
     * $options['debug_handler'] -> string function/meothd that captures debug messages
     * $options['autofree'] -> boolean automatically free result that have been read to the end?
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
            'optimize' => 'portability',
            'persistent' => false,
            'debug' => 0,
            'debug_handler' => 'MDB_defaultDebugOutput',
            'autofree' => false,
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
    var $debug = '';

    /**
     * @var string
     * @access private
     */
    var $debug_output = '';

    /**
     * @var boolean
     * @access private
     */
    var $auto_commit = 1;

    /**
     * @var boolean
     * @access private
     */
    var $in_transaction = 0;

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
     * @var integer
     * @access private
     */
    var $fetchmode = MDB_FETCHMODE_ORDERED;

    /**
     * @var integer
     * @access private
     */
    var $affected_rows = -1;

    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_Common()
    {
        global $_MDB_databases;
        $database = count($_MDB_databases) + 1;
        $_MDB_databases[$database] = &$this;
        $this->database = $database;

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
        $info .= ': (phptype = ' . $this->phptype . ', dbsyntax = ' . $this->dbsyntax . ')';
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
            return PEAR::raiseError($code, null, null, null, null, null, true);
        }

        if ($userinfo === null) {
            $userinfo = $this->last_query;
        }

        if ($nativecode) {
            $userinfo .= " [nativecode = $nativecode]";
        }

        return PEAR::raiseError(null, $code, $mode, $options, $userinfo,
            'MDB_Error', true);
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
            $this->options[$option] = $value;
            return MDB_OK;
        }
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, "unknown option $option");
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, "unknown option $option");
    }

    // }}}
    // {{{ debug()

    /**
     * set a debug message
     *
     * @param string $message Message with information for the user.
     * @access public
     */
    function debug($message)
    {
        if ($this->debug && $this->option['debug_handler']) {
            call_user_func($this->option['debug_handler'], $this, $message);
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
    // {{{ quote()

    /**
     * Quotes a string so it can be safely used in a query. It will quote
     * the text so it can safely be used within a query.
     *
     * @param string $text the input string to quote
     * @return string quoted string
     * @access public
     */
    function quote($text)
    {
        if (strcmp($this->escape_quotes, "'")) {
            $text = str_replace($this->escape_quotes, $this->escape_quotes . $this->escape_quotes, $text);
        }
        return str_replace("'", $this->escape_quotes . "'", $text);
    }

    // }}}
    // {{{ _loadModule()

    /**
     * loads an module
     *
     * @param string $module name of the module that should be loaded
     *      (only used for error messages)
     * @param string $include name of the script that includes the module
     * @access private
     */
    function _loadModule($module, $include)
    {
        if ($include) {
            $include = 'MDB/Modules/'.$include;
            if ($this->getOption('debug') > 2) {
                include_once $include;
            } else {
                @include_once $include;
            }
        } else {
            return $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                'it was not specified an existing ' . $module . ' file (' . $include . ')');
        }
        return MDB_OK;
    }

    // }}}
    // {{{ loadManager()

    /**
     * loads the Manager module
     *
     * @return object on success a reference to the given module is returned
     *                and on failure a PEAR error
     * @access public
     */
    function &loadManager()
    {
        if (!isset($this->manager) || !is_object($this->manager)) {
            $result = $this->_loadModule('Manager', 'Manager/'.$this->phptype.'.php');
            if (MDB::isError($result)) {
                return $result;
            }
            $class_name = 'MDB_Manager_'.$this->phptype;
            if (!class_exists($class_name)) {
                return $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                    'Unable to load extension');
            }
            @$this->manager = new $class_name;
        }
        return $this->manager;
    }

    // }}}
    // {{{ loadDatatype()

    /**
     * loads the Datatype module
     *
     * @return object on success a reference to the given module is returned
     *                and on failure a PEAR error
     * @access public
     */
    function &loadDatatype()
    {
        if (!isset($this->datatype) || !is_object($this->datatype)) {
            $result = $this->_loadModule('Datatype', 'Datatype/'.$this->phptype.'.php');
            if (MDB::isError($result)) {
                return $result;
            }
            $class_name = 'MDB_Datatype_'.$this->phptype;
            if (!class_exists($class_name)) {
                return $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                    'Unable to load extension');
            }
            @$this->datatype = new $class_name;
        }
        return $this->datatype;
    }

    // }}}
    // {{{ loadExtended()

    /**
     * loads the Extended module
     *
     * @return object on success a reference to the given module is returned
     *                and on failure a PEAR error
     * @access public
     */
    function &loadExtended()
    {
        if (!isset($this->extended) || !is_object($this->extended)) {
            $result = $this->_loadModule('Extended', 'Extended.php');
            if (MDB::isError($result)) {
                return $result;
            }
            $class_name = 'MDB_Extended';
            if (!class_exists($class_name)) {
                return $this->raiseError(MDB_ERROR_LOADMODULE, null, null,
                    'Unable to load extension');
            }
            @$this->extended = new $class_name;
        }
        return $this->extended;
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
        $this->debug('AutoCommit: ' . ($auto_commit ? 'On' : 'Off'));
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Auto-commit transactions: transactions are not supported');
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
        $this->debug('Commit Transaction');
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Commit transaction: commiting transactions are not supported');
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
        $this->debug('Rollback Transaction');
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Rollback transaction: rolling back transactions are not supported');
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
        if ($this->in_transaction && !MDB::isError($this->rollback()) && !MDB::isError($this->autoCommit(true))) {
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
        global $_MDB_databases;
        $_MDB_databases[$database] = '';
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
        $previous_database_name = $this->database_name;
        $this->database_name = $name;
        return $previous_database_name;
    }

    // }}}
    // {{{ setDSN()

    /**
     * set the DSN
     *
     * @param mixed     $dsninfo    DSN string or array
     * @return MDB_OK
     * @access public
     */
    function setDSN($dsn)
    {
        $dsninfo = MDB::parseDSN($dsn);
        if (isset($dsninfo['hostspec'])) {
            $this->host = $dsninfo['hostspec'];
        }
        if (isset($dsninfo['port'])) {
            $this->port = $dsninfo['port'];
        }
        if (isset($dsninfo['username'])) {
            $this->user = $dsninfo['username'];
        }
        if (isset($dsninfo['password'])) {
            $this->password = $dsninfo['password'];
        }
        if (isset($dsninfo['database'])) {
            $this->database_name = $dsninfo['database'];
        }
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
                    'username' => $this->user,
                    'password' => $this->password,
                    'hostspec' => $this->host,
                    'database' => $this->database_name
                );
                break;
            default:
                $dsn = $this->phptype.'://'.$this->user.':'
                    .$this->password.'@'.$this->host
                    .(isset($this->port) ? (':'.$this->port) : '')
                    .'/'.$this->database_name;
                break;
        }
        return $dsn;
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results
     *
     * @param string $query the SQL query
     * @param array   $types  array that contains the types of the columns in
     *                        the result set
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     */
    function query($query, $types = null)
    {
        $this->debug("Query: $query");
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'Query: database queries are not implemented');
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
        if (!isset($this->supported['SelectRowRanges'])) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                'Set selected row range: selecting row ranges is not supported by this driver');
        }
        $first = (int)$first;
        if ($first < 0) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'Set selected row range: it was not specified a valid first selected range row');
        }
        $limit = (int)$limit;
        if ($limit < 1) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'Set selected row range: it was not specified a valid selected range row limit');
        }
        $this->first_selected_row = $first;
        $this->selected_row_limit = $limit;
        return MDB_OK;
    }

    // }}}
    // {{{ limitQuery()

    /**
     * Generates a limited query
     *
     * @param string $query query
     * @param array   $types  array that contains the types of the columns in
     *                        the result set
     * @param integer $from the row to start to fetching
     * @param integer $count the numbers of rows to fetch
     * @return mixed a valid ressource pointer or a MDB_Error
     * @access public
     */
    function limitQuery($query, $types = null, $from, $count)
    {
        $result = $this->setLimit($from, $count);
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query($query, $types);
    }

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
     * @param string $quote determines if the data needs to be quoted before
     *                      being returned
     *
     * @return string the query
     */
    function subSelect($query, $quote = false)
    {
        if ($this->supported['SubSelects'] == 1) {
            return $query;
        }
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'Subselect: subselect not implemented');
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
     *       Value
     *           Value to be assigned to the specified field. This value may be
     *           of specified in database independent type format as this
     *           function can perform the necessary datatype conversions.
     *
     *           Default: this property is required unless the Null property is
     *           set to 1.
     *
     *       Type
     *           Name of the type of the field. Currently, all types Metabase
     *           are supported except for clob and blob.
     *
     *           Default: no type conversion
     *
     *       Null
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
     *       Key
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
        if (!$this->supported['Replace']) {
            return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'Replace: replace query is not supported');
        }
        $count = count($fields);
        for($keys = 0, $condition = $insert = $values = '', reset($fields), $field = 0;
            $field < $count;
            next($fields), $field++)
        {
            $name = key($fields);
            if ($field > 0) {
                $insert .= ', ';
                $values .= ', ';
            }
            $insert .= $name;
            if (isset($fields[$name]['Null']) && $fields[$name]['Null']) {
                $value = 'NULL';
            } else {
                if (isset($fields[$name]['Type'])) {
                    switch ($fields[$name]['Type']) {
                        case 'text':
                            $value = $this->getValue('text', $fields[$name]['Value']);
                            break;
                        case 'boolean':
                            $value = $this->getValue('boolean', $fields[$name]['Value']);
                            break;
                        case 'integer':
                            $value = $this->getValue('integer', $fields[$name]['Value']);
                            break;
                        case 'decimal':
                            $value = $this->getValue('decimal', $fields[$name]['Value']);
                            break;
                        case 'float':
                            $value = $this->getValue('float', $fields[$name]['Value']);
                            break;
                        case 'date':
                            $value = $this->getValue('date', $fields[$name]['Value']);
                            break;
                        case 'time':
                            $value = $this->getValue('time', $fields[$name]['Value']);
                            break;
                        case 'timestamp':
                            $value = $this->getValue('timestamp', $fields[$name]['Value']);
                            break;
                        default:
                            return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                                'no supported type for field "' . $name . '" specified');
                    }
                } else {
                    $value = $fields[$name]['Value'];
                }
            }
            $values .= $value;
            if (isset($fields[$name]['Key']) && $fields[$name]['Key']) {
                if ($value === 'NULL') {
                    return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                        'key values may not be NULL');
                }
                $condition .= ($keys ? ' AND ' : ' WHERE ') . $name . '=' . $value;
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(MDB_ERROR_CANNOT_REPLACE, null, null,
                'not specified which fields are keys');
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
                if (($success = (!MDB::isError($this->commit()) && !MDB::isError($this->autoCommit(true)))) && isset($this->supported['AffectedRows'])) {
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
    // {{{ prepareQuery()

    /**
     * Prepares a query for multiple execution with execute().
     * With some database backends, this is emulated.
     * prepareQuery() requires a generic query as string like
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
    function prepareQuery($query)
    {
        $this->debug("PrepareQuery: $query");
        $positions = array();
        for($position = 0;
            $position < strlen($query) && gettype($question = strpos($query, '?', $position)) == 'integer';) {
            if (gettype($quote = strpos($query, "'", $position)) == 'integer' && $quote < $question) {
                if (gettype($end_quote = strpos($query, "'", $quote + 1)) != 'integer') {
                    return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                        'Prepare query: query with an unterminated text string specified');
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
        $this->prepared_queries[] = array('Query' => $query,
            'Positions' => $positions,
            'Values' => array(),
            'Types' => array()
            );
        $prepared_query = count($this->prepared_queries);
        if ($this->selected_row_limit > 0) {
            $this->prepared_queries[$prepared_query-1]['First'] = $this->first_selected_row;
            $this->prepared_queries[$prepared_query-1]['Limit'] = $this->selected_row_limit;
        }
        return $prepared_query;
    }

    // }}}
    // {{{ _validatePreparedQuery()

    /**
     * validate that a handle is infact a prepared query
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @access private
     */
    function _validatePreparedQuery($prepared_query)
    {
        if ($prepared_query < 1 || $prepared_query > count($this->prepared_queries)) {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'Validate prepared query: invalid prepared query');
        }
        if (gettype($this->prepared_queries[$prepared_query-1]) != 'array') {
            return $this->raiseError(MDB_ERROR_INVALID, null, null,
                'Validate prepared query: prepared query was already freed');
        }
        return MDB_OK;
    }

    // }}}
    // {{{ freePreparedQuery()

    /**
     * Release resources allocated for the specified prepared query.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function freePreparedQuery($prepared_query)
    {
        $result = $this->_validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $this->prepared_queries[$prepared_query-1] = '';
        return MDB_OK;
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
    function _executePreparedQuery($prepared_query, $query, $types = null)
    {
        return $this->query($query, $types);
    }

    // }}}
    // {{{ executeQuery()

    /**
     * Execute a prepared query statement.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param array $types array that contains the types of the columns in the
     *       result set
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     */
    function executeQuery($prepared_query, $types = null)
    {
        $result = $this->_validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query-1;
        for($this->clobs[$prepared_query] = $this->blobs[$prepared_query] = array(), $query = '', $last_position = $position = 0;
            $position < count($this->prepared_queries[$index]['Positions']);
            $position++) {
            if (!isset($this->prepared_queries[$index]['Values'][$position])) {
                return $this->raiseError(MDB_ERROR_NEED_MORE_DATA, null, null,
                    'Execute query: it was not defined query argument '.($position + 1));
            }
            $current_position = $this->prepared_queries[$index]['Positions'][$position];
            $query .= substr($this->prepared_queries[$index]['Query'], $last_position, $current_position - $last_position);
            $value = $this->prepared_queries[$index]['Values'][$position];
            switch ($this->prepared_queries[$index]['Types'][$position]) {
                case 'clob':
                    if (!MDB::isError($success = $this->getValue('clob', $prepared_query, $position + 1, $value))) {
                        $this->clobs[$prepared_query][$position + 1] = $success;
                        $query .= $this->clobs[$prepared_query][$position + 1];
                    }
                    break;
                case 'blob':
                    if (!MDB::isError($success = $this->getValue('blob', $prepared_query, $position + 1, $value))) {
                        $this->blobs[$prepared_query][$position + 1] = $success;
                        $query .= $this->blobs[$prepared_query][$position + 1];
                    }
                    break;
                default:
                    $query .= $value;
                    break;
            }
            $last_position = $current_position + 1;
        }
        if (!isset($success) || !MDB::isError($success)) {
            $query .= substr($this->prepared_queries[$index]['Query'], $last_position);
            if ($this->selected_row_limit > 0) {
                $this->prepared_queries[$index]['First'] = $this->first_selected_row;
                $this->prepared_queries[$index]['Limit'] = $this->selected_row_limit;
            }
            if (isset($this->prepared_queries[$index]['Limit']) && $this->prepared_queries[$index]['Limit'] > 0) {
                $this->first_selected_row = $this->prepared_queries[$index]['First'];
                $this->selected_row_limit = $this->prepared_queries[$index]['Limit'];
            } else {
                $this->first_selected_row = $this->selected_row_limit = 0;
            }
            $success = $this->_executePreparedQuery($prepared_query, $query, $types);
        }
        for(reset($this->clobs[$prepared_query]), $clob = 0;
            $clob < count($this->clobs[$prepared_query]);
            $clob++, next($this->clobs[$prepared_query])) {
            $this->freeClobValue($prepared_query, key($this->clobs[$prepared_query]), $this->clobs[$prepared_query][key($this->clobs[$prepared_query])], $success);
        }
        unset($this->clobs[$prepared_query]);
        for(reset($this->blobs[$prepared_query]), $blob = 0;
            $blob < count($this->blobs[$prepared_query]);
            $blob++, next($this->blobs[$prepared_query])) {
            $this->freeBlobValue($prepared_query, key($this->blobs[$prepared_query]), $this->blobs[$prepared_query][key($this->blobs[$prepared_query])], $success);
        }
        unset($this->blobs[$prepared_query]);
        return $success;
    }

    // }}}
    // {{{ execute()

    /**
     * Executes a prepared SQL query
     * With execute() the generic query of prepare is assigned with the given
     * data array. The values of the array inserted into the query in the same
     * order like the array order
     *
     * @param resource $prepared_query query handle from prepare()
     * @param array $types array that contains the types of the columns in
     *        the result set
     * @param array $params numeric array containing the data to insert into
     *        the query
     * @param array $param_types array that contains the types of the values
     *        defined in $params
     * @return mixed a new result handle or a MDB_Error when fail
     * @access public
     * @see prepare()
     */
    function execute($prepared_query, $types = null, $params = false, $param_types = null)
    {
        $this->setParamArray($prepared_query, $params, $param_types);

        return $this->executeQuery($prepared_query, $types);
    }

    // }}}
    // {{{ executeMultiple()

    /**
     * This function does several execute() calls on the same statement handle.
     * $params must be an array indexed numerically from 0, one execute call is
     * done for every 'row' in the array.
     *
     * If an error occurs during execute(), executeMultiple() does not execute
     * the unfinished rows, but rather returns that error.
     *
     * @param resource $stmt query handle from prepare()
     * @param array $types array that contains the types of the columns in
     *        the result set
     * @param array $params numeric array containing the
     *        data to insert into the query
     * @param array $parAM_types array that contains the types of the values
     *        defined in $params
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     * @see prepare(), execute()
     */
    function executeMultiple($prepared_query, $types = null, $params, $param_types = null)
    {
        for($i = 0, $j = count($params); $i < $j; $i++) {
            $result = $this->execute($prepared_query, $types, $params[$i], $param_types);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ setParam()

    /**
     * Set the value of a parameter of a prepared query.
     *
     * @param int $prepared_query argument is a handle that was returned
     *       by the function prepareQuery()
     * @param int $parameter the order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $type designation of the type of the parameter to be set.
     *       The designation of the currently supported types is as follows:
     *           text, boolean, integer, decimal, float, date, time, timestamp,
     *           clob, blob
     * @param mixed $value value that is meant to be assigned to specified
     *       parameter. The type of the value depends on the $type argument.
     * @param boolean $is_null flag that indicates whether whether the
     *       parameter is a null
     * @param string $field name of the field that is meant to be assigned
     *       with this parameter value when it is of type clob or blob
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function setParam($prepared_query, $parameter, $type, $value, $is_null = 0, $field = '')
    {
        $result = $this->_validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query - 1;
        if ($parameter < 1 || $parameter > count($this->prepared_queries[$index]['Positions'])) {
            return $this->raiseError(MDB_ERROR_SYNTAX, null, null,
                'Query set: it was not specified a valid argument number');
        }
        $this->prepared_queries[$index]['Values'][$parameter-1] = $value;
        $this->prepared_queries[$index]['Types'][$parameter-1] = $type;
        $this->prepared_queries[$index]['Fields'][$parameter-1] = $field;
        return MDB_OK;
    }

    // }}}
    // {{{ setParamArray()

    /**
     * Set the values of multiple a parameter of a prepared query in bulk.
     *
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
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
                $success = $this->setParam($prepared_query, $i + 1, $types[$i], $this->getValue($types, $params[$i]));
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        } else {
            for ($i = 0, $j = count($params); $i < $j; ++$i) {
                $success = $this->setParam($prepared_query, $i + 1, 'text', $this->getValue('text', $params[$i]));
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ setResultTypes()

    /**
     * Define the list of types to be associated with the columns of a given
     * result set.
     *
     * This function may be called before invoking fetchOne(),
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
        $load = $this->loadDatatype('setResultTypes');
        if (MDB::isError($load)) {
            return $load;
        }
        return $this->datatype->setResultTypes($this, $result, $types);
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
            'Get column names: obtaining result column names is not implemented');
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
            'Number of columns: obtaining the number of result columns is not implemented');
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
            'End of result: end of result method not implemented');
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
        $result = $this->fetch($result, $row, $field);
        if (MDB::isError($result)) {
            return $result;
        }
        return !isset($result);
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'Num Rows: number of rows method not implemented');
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
        return $this->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'Free Result: free result method not implemented');
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
        $result = $this->loadDatatype('getValue');
        if (MDB::isError($result)) {
            return $result;
        }
        if (method_exists($this->datatype,"get{$type}Value")) {
            return $this->datatype->{"get{$type}Value"}($this, $value);
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
        $result = $this->loadDatatype('getDeclaration');
        if (MDB::isError($result)) {
            return $result;
        }
        if (method_exists($this->datatype,"get{$type}Declaration")) {
            return $this->datatype->{"get{$type}Declaration"}($this, $name, $field);
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
    function nextId($seq_name, $ondemand = false)
    {
        return $this->raiseError(MDB_ERROR_NOT_CAPABLE, null, null,
            'Next Sequence: getting next sequence value not supported');
    }

    // }}}
    // {{{ currId()

    /**
     * returns the current id of a sequence
     *
     * @param string $seq_name name of the sequence
     * @return mixed MDB_Error or id
     * @access public
     */
    function currId($seq_name)
    {
        $this->warnings[] = 'database does not support getting current
            sequence value, the sequence value was incremented';
        expectError(MDB_ERROR_NOT_CAPABLE);
        $id = $this->nextId($seq_name);
        popExpectError(MDB_ERROR_NOT_CAPABLE);
        if (MDB::isError($id)) {
            if ($id->getCode() == MDB_ERROR_NOT_CAPABLE) {
                return $this->raiseError(MDB_ERROR_NOT_CAPABLE, null, null,
                    'Current Sequence: getting current sequence value not supported');
            }
            return $id;
        }
        return $id;
    }

    // }}}
    // {{{ fetchOne()

    /**
     * Fetch and return a field of data (it uses fetchRow for that)
     *
     * @param resource $result result identifier
     * @return mixed data on success, a MDB error on failure
     * @access public
     */
    function fetchOne($result)
    {
        $row = $this->fetchRow($result, MDB_FETCHMODE_ORDERED);
        if (!$this->options['autofree'] || $row != null) {
            $this->freeResult($result);
        }
        if (is_array($row)) {
            return $row[0];
        }
        return $row;
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
    function fetchRow($result, $fetchmode = MDB_FETCHMODE_DEFAULT)
    {
        if (MDB::isError($this->endOfResult($result))) {
            if ($this->options['autofree']) {
                $this->freeResult($result);
            }
            return null;
        }
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        if ($fetchmode == MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }
        if ($fetchmode & MDB_FETCHMODE_ASSOC) {
            $column_names = $this->getColumnNames($result);
            if (MDB::isError($column_names)) {
                return $column_names;
            }
        }
        for($column = 0; $column < $columns; $column++) {
            if (!$this->resultIsNull($result, $rownum, $column)) {
                $result = $this->fetch($result, $rownum, $column);
                if ($result == null) {
                    if ($this->options['autofree']) {
                        $this->freeResult($result);
                    }
                    return null;
                }
            }
            if (isset($column_names)) {
                $array[$column_names[$column]] = $result;
            } else {
                $array[$column] = $result;
            }
        }
        if (isset($this->results[intval($result)]['types'])) {
            $array = $this->datatype->convertResultRow($this, $result, $array);
        }
        return $array;
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
                $this->freeResult($result);
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
                if ($fetchmode & MDB_FETCHMODE_ASSOC) {
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
        return $this->raiseError(MDB_ERROR_NOT_CAPABLE);
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

// Used by many drivers
if (!function_exists('array_change_key_case')) {
    define('CASE_UPPER', 1);
    define('CASE_LOWER', 0);
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
