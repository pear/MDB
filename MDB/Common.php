<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Lukas Smith <smith@dybnet.de>                               |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id$
//
// Base class for DB implementations.
//

define('MDB_TYPE_TEXT', 0);
define('MDB_TYPE_BOOLEAN', 1);
define('MDB_TYPE_INTEGER', 2);
define('MDB_TYPE_DECIMAL', 3);
define('MDB_TYPE_FLOAT', 4);
define('MDB_TYPE_DATE', 5);
define('MDB_TYPE_TIME', 6);
define('MDB_TYPE_TIMESTAMP', 7);
define('MDB_TYPE_CLOB', 8);
define('MDB_TYPE_BLOB', 9);

$registered_transactions_shutdown = 0;

// }}}
// {{{ shutdownTransactions()
/**
* this function closes all open transactions
* registerTransactionShutdown() registers this method to be executed at shutdown
*
* @access private
*/
function shutdownTransactions()
{
    global $databases;
    
    foreach($databases as $database) {
        if ($database->in_transaction && !MDB::is_Error($database->rollback())){
            $database->autoCommit(TRUE);
        }
    }
}

// }}}
// {{{ defaultDebugOutput()

/**
 * default debug output handler
 *
 * @param integer $database key in the $databases array that references to the
 *                          proper db object
 * @param string  $message  message htat should be appended to the debug
 *                          variable
 *
 * @return string the corresponding error message, of FALSE
 * if the error code was unknown
 *
 * @access public
 */
function defaultDebugOutput($database, $message)
{
    global $databases;

    $databases[$database]->debug_output.="$database $message".
                           $databases[$database]->log_line_break;
}

/*
 * MDB_common is a base class for DB implementations, and must be
 * inherited by all such.
 */

class MDB_common extends PEAR
{
    /* PUBLIC DATA */

    var $database = 0;
    var $host = "";
    var $user = "";
    var $password = "";
    var $options = array();
    var $supported = array();
    var $persistent = 1;
    var $database_name = "";
    var $warning = "";
    var $affected_rows = -1;
    var $auto_commit = 1;
    var $prepared_queries = array();
    var $decimal_places = 2;
    var $first_selected_row = 0;
    var $selected_row_limit = 0;
    var $lob_buffer_length = 8000;
    var $escape_quotes = "";
    var $log_line_break = "\n";

    /* PRIVATE DATA */
    
    var $lobs = array();
    var $clobs = array();
    var $blobs = array();
    var $last_error = "";
    var $in_transaction = 0;
    var $debug = "";
    var $debug_output = "";
    var $pass_debug_handle = 0;
    var $fetchmodes = array();
    var $error_handler = "";
    var $manager;
    var $include_path = "";
    var $manager_included_constant = "";
    var $manager_include = "";
    var $manager_class_name = "";

    // new for PEAR
    var $last_query = "";
    var $autofree = FALSE;

    // }}}
    // {{{ constructor
    /**
    * Constructor
    */
    function MDB_common()
    {
        $this->PEAR('MDB_Error');
        $this->supported = array();
        $this->errorcode_map = array();
        $this->fetchmode = DB_FETCHMODE_ORDERED;
    }

    // }}}
    // {{{ toString()
    /**
    * String conversation
    *
    * @return string
    * @access private
    */
    function toString()
    {
        $info = get_class($this);
        $info .=  ": (phptype = " . $this->phptype .
                  ", dbsyntax = " . $this->dbsyntax .
                  ")";
        if ($this->connection) {
            $info .= " [connected]";
        }
        return $info;
    }

    // }}}
    // {{{ errorCode()

    /**
     * Map native error codes to DB's portable ones.  Requires that
     * the DB implementation's constructor fills in the $errorcode_map
     * property.
     *
     * @param  mixed $nativecode the native error code, as returned by the
     *     backend database extension (string or integer)
     *
     * @return int a portable DB error code, or FALSE if this DB
     *     implementation has no mapping for the given error code.
     *
     * @access public
     */
    function errorCode($nativecode)
    {
        if (isset($this->errorcode_map[$nativecode])) {
            return $this->errorcode_map[$nativecode];
        }
        // Fall back to DB_ERROR if there was no mapping.
        return DB_ERROR;
    }

    // }}}
    // {{{ errorMessage()

    /**
     * Map a DB error code to a textual message.  This is actually
     * just a wrapper for DB::errorMessage().
     *
     * @param  integer $dbcode the DB error code
     *
     * @return string the corresponding error message, of FALSE
     *     if the error code was unknown
     *
     * @access public
     */
    function errorMessage($dbcode)
    {
        return DB::errorMessage($this->errorcode_map[$dbcode]);
    }

    // }}}
    // {{{ raiseError()

    /**
     * This method is used to communicate an error and invoke error
     * callbacks etc.  Basically a wrapper for PEAR::raiseError
     * without the message string.
     *
     * @param  mixed $code integer error code, or a PEAR error object (all
     *     other parameters are ignored if this parameter is an object
     *
     * @param  int   $mode error mode, see PEAR_Error docs
     *
     * @param mixed  $options If error mode is PEAR_ERROR_TRIGGER, this is the
     *     error level (E_USER_NOTICE etc).  If error mode is 
     *     PEAR_ERROR_CALLBACK, this is the callback function, either as a
     *     function name, or as an array of an object and method name. For
     *     other error modes this parameter is ignored.
     *
     * @param string $userinfo Extra debug information.  Defaults to the last
     *     query and native error code.
     *
     * @param mixed  $nativecode Native error code, integer or string depending
     *     the backend.
     *
     * @return object  a PEAR error object
     *
     * @access public
     * @see PEAR_Error
     */
    function &raiseError($code = DB_ERROR, $mode = NULL, $options = NULL,
                         $userinfo = NULL, $nativecode = NULL)
    {
        // The error is yet a DB error object
        if (is_object($code)) {
            return PEAR::raiseError($code, NULL, NULL, NULL, NULL, NULL, TRUE);
        }

        if ($userinfo === NULL) {
            $userinfo = $this->last_query;
        }

        if ($nativecode) {
            $userinfo .= " [nativecode = $nativecode]";
        }

        return PEAR::raiseError(NULL, $code, $mode, $options, $userinfo,
                                  'MDB_Error', TRUE);
    }

    // }}}
    // {{{ setOption()
    /**
    * set the option for the db class
    *
    * @param string $option option name
    * @param mixed  $value value for the option
    *
    * @return mixed a result handle or DB_OK on success, a DB error on failure
    */
    function setOption($option, $value)
    {
        if (isset($this->$option)) {
            $this->$option = $value;
            return (DB_OK);
        }
        return $this->raiseError("unknown option $option");
    }

    // }}}
    // {{{ getOption()
    /**
    * returns the value of an option
    *
    * @param string $option option name
    *
    * @return mixed the option value
    */
    function getOption($option)
    {
        if (isset($this->$option)) {
            return $this->$option;
        }
        return $this->raiseError("unknown option $option");
    }

    // }}}
    // {{{ captureDebugOutput()

    /**
     * set a debug handler (deprecated)
     * 
     * @param string $capture name of the function that should be used in
     *     debug()
     *
     * @access public
     * @see debug()
     */
    function captureDebugOutput($capture)
    {
        $this->pass_debug_handle = $capture;
        $this->debug = ($capture ? "defaultDebugOutput" : "");
    }
      
    // }}}
    // {{{ debug()

    /**
     * set a debug message (deprecated)
     * 
     * @param string $message Message with information for the user.
     *
     * @access public
     */
    function debug($message)
    {
        if (strcmp($function = $this->debug, "")) {
            if ($this->pass_debug_handle) {
                $function($this->database, $message);
            } else {
                $function($message);
            }
        }
    }

    // }}}
    // {{{ debugOutput()

    /**
     * output debug info (deprecated)
     *
     * @access public
     *
     * @return string content of the debug_output class variable
     */
    function debugOutput()
    {
        return ($this->debug_output);
    }
    
    // }}}
    // {{{ setError()

    /**
     * set an error (deprecated)
     * 
     * @param string $scope   Scope of the error message
     *    (usually the method tht caused the error)
     * @param string $message Message with information for the user.
     *
     * @access private
     *
     * @return boolean FALSE
     */
    function setError($scope, $message)
    {
        $this->last_error = $message;
        $this->debug($scope.": ".$message);
        if(strcmp($function = $this->error_handler, ""))
        {
            $error = array(
                "Scope" => $scope,
                "Message" => $message
            );
            $function($this, $error);
        }
        return (0);
    }

    // }}}
    // {{{ setErrorHandler() (deprecated)

    /**
     * Specify a function that is called when an error occurs.
     * 
     * @param string $function  Name of the function that will be called on
     *     error. If an empty string is specified, no handler function is
     *     called on error. The error handler function receives two arguments.
     *     The first argument a reference to the driver class object that
     *     triggered the error.
     *
     *     The second argument is a reference to an associative array that
     *     provides details about the error that occured. These details provide
     *     more information than it is returned by the MetabaseError function.
     *                                
     *     These are the currently supported error detail entries:
     *
     *     Scope
     *      String that indicates the scope of the driver object class
     *      within which the error occured.
     *                            
     *     Message
     *      Error message as is returned by the MetabaseError function.
     * 
     * @access public
     *
     * @return string name of last function
     */
    function setErrorHandler($function)
    {
        $last_function = $this->error_handler;
        $this->error_handler = $function;
        return ($last_function);
    }
    
    // }}}
    // {{{ error() (deprecated)

    /**
     * Retrieve the error message text associated with the last operation that
     * failed. Some functions may fail but they do not return the reason that
     * makes them to fail. This function is meant to retrieve a textual
     * description of the failure cause.
     * 
     * @access public
     *
     * @return string the error message text associated with the last failure.
     */
    function error()
    {
        return($this->last_error);
    }

    // }}}
    // {{{ quote()

    /**
     * Quotes a string so it can be safely used in a query. It will quote
     * the text so it can safely be used within a query.
     *
     * @param string $text the input string to quote
     * 
     * @access private
     *
     */
    function quote(&$text)
    {
        if (strcmp($this->escape_quotes, "'")) {
            $text = str_replace($this->escape_quotes, $this->escape_quotes.$this->escape_quotes, $text);
        }
        $text = str_replace("'", $this->escape_quotes."'", $text);
    }

    // }}}
    // {{{ loadExtension()

    /**
     * loads an extension
     * 
     * @param string $scope information about what method is being loaded,
     *     that is used for error messages
     * @param string $extension name of the extension that should be loaded
     *     (only used for error messages)
     * @param string $included_constant name of the constant that should be
     *     defined when the extension has been loaded
     * @param string $include name of the script that includes the extension
     * 
     * @access private
     *
     */
    function loadExtension($scope, $extension, $included_constant, $include)
    {
        if (strlen($included_constant) == 0
            || !defined($included_constant))
        {
            $include_path = $this->include_path;
            $length = strlen($include_path);
            $separator = "";
            if ($length) {
                $directory_separator =
                   (defined("DIRECTORY_SEPARATOR") ? DIRECTORY_SEPARATOR : "/");
                if ($include_path[$length - 1] != $directory_separator) {
                    $separator=$directory_separator;
                }
            }
            if (!file_exists($include_path.$separator.$include)) {
                $directory = 0;
                if (!strcmp($include_path, "")
                    || ($directory = @opendir($include_path)))
                {
                    if ($directory) {
                        closedir($directory);
                    }
                    return $this->raiseError(DB_ERROR_LOADEXTENSION, "", "", 
                        $scope.': it was not specified an existing '.
                        $extension.' file ('.$include.')');
                } else {
                    return $this->raiseError(DB_ERROR_LOADEXTENSION, "", "", 
                        $scope.': it was not specified a valid '.
                        $extension.' include path');
                }
            }
            include($include_path.$separator.$include);
        }
        return (DB_OK);
    }

    // }}}
    // {{{ loadManager()

    /**
     * loads the Manager extension
     * 
     * @param string $scope information about what method is being loaded,
     *                      that is used for error messages
     * 
     * @access private
     *
     */
    function loadManager($scope)
    {
        if (isset($this->manager)) {
            return (DB_OK);
        }
        $result = $this->loadExtension($scope, "database manager",
                         "MDB_MANAGER_DATABASE_INCLUDED", "manager_common.php");
        if (MDB::isError($result)) {
            return($result);
        }
        if (strlen($this->manager_class_name)) {
            if(strlen($this->manager_include) == 0)
                return $this->raiseError(DB_LOADEXTENSION, "", "", $scope.
                       ': no valid database manager include file');
            $result = $this->loadExtension($scope, "database manager",
                      $this->manager_included_constant, $this->manager_include);
            if (MDB::isError($result)) {
                return($result);
            }
            $class_name = $this->manager_class_name;
        } else {
            $class_name = "MDB_manager_database_class";
        }
        $this->manager = new $class_name;
        return (DB_OK);
    }

    // }}}
    // {{{ registerTransactionShutdown()

    /**
     * register the shutdown function to automatically commit open transactions
     * 
     * @param string $auto_commit
     *
     * @access private
     *
     * @return DB_OK
     */ 
    function registerTransactionShutdown($auto_commit)
    {
        global $registered_transactions_shutdown;

        if (($this->in_transaction = !$auto_commit)
            && !$registered_transactions_shutdown)
        {
            register_shutdown_function("shutdownTransactions");
            $registered_transactions_shutdown = 1;
        }
        return (DB_OK);
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
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function autoCommit($auto_commit)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
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
     * @access public
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function commit()
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
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
     * @access public
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function rollback()
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
           'Rollback transaction: rolling back transactions are not supported');
    }

    // }}}
    // {{{ disconnect()

    /**
     * Log out and disconnect from the database.
     * renamed for PEAR
     * used to be: CloseSetup
     *
     * @access public
     *
     * @return mixed TRUE on success, FALSE if not connected and error
     *               object on error
     */
    function disconnect()
    {
        if ($this->in_transaction
        && !MDB::isError($this->rollback())
        && !MDB::isError($this->autoCommit(TRUE))) {
            $this->in_transaction = FALSE;
        }
        return $this->close();
    }

    // }}}
    // {{{ close()

    /**
     * all the RDBMS specific things needed close a DB connection
     * 
     * @access private
     *
     */
    function close()
    {
        global $databases;
        $databases[$database] = "";
    }

    // }}}
    // {{{ setDatabase()

    /**
     * Select a different database
     * 
     * @param string $name name of the database that should be selected
     * 
     * @access public
     *
     * @return string name of the database previously connected to
     */
    function setDatabase($name)
    {
        $previous_database_name = $this->database_name;
        $this->database_name = $name;
        return ($previous_database_name);
    }

    // }}}
    // {{{ createDatabase()

    /**
     * create a new database
     * 
     * @param string $name name of the database that should be created
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function createDatabase($name)
    {
        $result = $this->loadManager("Create database");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->createDatabase($this, $name));
    }

    // }}}
    // {{{ dropDatabase()

    /**
     * drop an existing database
     * 
     * @param string $name name of the database that should be dropped
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function dropDatabase($name)
    {
        $result = $this->loadManager("Drop database");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->dropDatabase($this, $name));
    }

    // }}}
    // {{{ createTable()

    /**
     * create a new table
     * 
     * @param string $name  Name of the database that should be created
     * @param array $fields Associative array that contains the definition of
     *     each field of the new table. The indexes of the array entries are
     *     the names of the fields of the table an the array entry values are
     *     associative arrays like those that are meant to be passed with the
     *     field definitions to get[Type]Declaration() functions.
     *
     *     Example
     *       array(
     *           "id" => array(
     *               "type" => "integer",
     *               "unsigned" => 1
     *               "notnull" => 1
     *               "default" => 0
     *           ),
     *           "name" => array(
     *               "type"=>"text",
     *               "length"=>12
     *           ),
     *           "password"=>array(
     *               "type"=>"text",
     *               "length"=>12
     *           )
     *       );
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function createTable($name, &$fields)
    {
        $result = $this->loadManager("Create table");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->createTable($this, $name, $fields));
    }

    // }}}
    // {{{ dropTable()

    /**
     * drop an existing table
     * 
     * @param string $name name of the table that should be dropped
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function dropTable($name)
    {
        $result = $this->loadManager("Drop table");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->dropTable($this, $name));
    }

    // }}}
    // {{{ alterTable()

    /**
     * alter an existing table
     * 
     * @param string $name   name of the table that is intended to be changed.
     *
     * @param array $changes associative array that contains the details of
     *  each type of change that is intended to be performed. The types of
     *  changes that are currently supported are defined as follows:
     * 
     * name
     *  New name for the table.
     *
     * AddedFields
     *  Associative array with the names of fields to be added as indexes of
     *  the array. The value of each entry of the array should be set to
     *  another associative array with the properties of the fields to be
     *  added. The properties of the fields should be the same as defined by
     *  the Metabase parser.
     *
     *  Additionally, there should be an entry named Declaration that is
     *  expected to contain the portion of the field declaration already in
     *  DBMS specific SQL code as it is used in the CREATE TABLE statement.
     *
     * RemovedFields
     *  Associative array with the names of fields to be removed as indexes of
     *  the array. Currently the values assigned to each entry are ignored. An
     *  empty array should be used for future compatibility.
     *
     * RenamedFields
     *  Associative array with the names of fields to be renamed as indexes of
     *  the array. The value of each entry of the array should be set to another
     *  associative array with the entry named name with the new field name and
     *  the entry named Declaration that is expected to contain the portion of
     *  the field declaration already in DBMS specific SQL code as it is used
     *  in the CREATE TABLE statement.
     *
     * ChangedFields
     *  Associative array with the names of the fields to be changed as indexes
     *  of the array. Keep in mind that if it is intended to change either the
     *  name of a field and any other properties, the ChangedFields array
     *  entries should have the new names of the fields as array indexes.
     *
     *  The value of each entry of the array should be set to another
     *  associative array with the properties of the fields to that are meant
     *  to be changed as array entries. These entries should be assigned to the
     *  new values of the respective properties. The properties of the fields
     *  should be the* same as defined by the Metabase parser.
     *
     *  If the default property is meant to be added, removed or changed, there
     *  should also be an entry with index ChangedDefault assigned to 1.
     *  Similarly, if the notnull constraint is to be added or removed, there
     *  should also be an entry with index ChangedNotNull assigned to 1.
     *
     *  Additionally, there should be an entry named Declaration that is
     *  expected to contain the portion of the field changed declaration
     *  already in DBMS specific SQL code as it is used in the CREATE TABLE
     *  statement.
     *
     * Example
     *  array(
     *      "name" => "userlist",
     *      "AddedFields" => array(
     *          "quota" => array(
     *              "type" => "integer",
     *              "unsigned" => 1,
     *              "Declaration" => "quota INT"
     *              )
     *          ),
     *      "RemovedFields" => array(
     *          "file_limit" => array(),
     *          "time_limit" => array()
     *          ),
     *      "ChangedFields" => array(
     *          "gender" => array(
     *              "default" => "M",
     *              "ChangeDefault" => 1,
     *              "Declaration" => "gender CHAR(1) DEFAULT 'M'"
     *              )
     *          ),
     *      "RenamedFields" => array(
     *          "sex" => array(
     *              "name" => "gender",
     *              "Declaration" => "gender CHAR(1) DEFAULT 'M'"
     *          )
     *      )
     *  ) 
     *
     * @param boolean $check indicates whether the function should just check
     *                       if the DBMS driver can perform the requested table
     *                       alterations if the value is true or actually
     *                       perform them otherwise.
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function alterTable($name, &$changes, $check)
    {
        $result = $this->loadManager("Alter table");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->alterTable($this, $name, $changes, $check));
    }

    // }}}
    // {{{ listDatabases()

    /**
     * list all databases
     * 
     * @param array $dbs reference to an empty array into which the list is
     *                   stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listDatabases(&$dbs)
    {
        $result = $this->loadManager("List databases");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listDatabases($this, $dbs));
    }

    // }}}
    // {{{ listUsers()

    /**
     * list all users
     * 
     * @param array $users reference to an empty array into which the list is
     *     stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listUsers(&$users)
    {
        $result = $this->loadManager("List users");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listUsers($this, $users));
    }

    // }}}
    // {{{ listViews()

    /**
     * list all viewes in the current database
     * 
     * @param array $views reference to an empty array into which the list is
     *     stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listViews(&$views)
    {
        $result = $this->loadManager("List views");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listViews($this, $users));
    }

    // }}}
    // {{{ listFunctions()

    /**
     * list all functions in the current database
     * 
     * @param array $functions reference to an empty array into which the list
     *     is stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listFunctions(&$functions)
    {
        $result = $this->loadManager("List functions");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listFunctions($this, $users));
    }

    // }}}
    // {{{ listTables()

    /**
     * list all tables in the current database
     * 
     * @param array $tables reference to an empty array into which the list is
     *                      stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listTables(&$tables)
    {
        $result = $this->loadManager("List tables");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listTables($this, $tables));
    }

    // }}}
    // {{{ listTableFields()

    /**
     * list all fields in a tables in the current database
     * 
     * @param string $table name of table that should be used in method
     * @param array $fields reference to an empty array into which the list is
     *                      stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listTableFields($table, &$fields)
    {
        $result = $this->loadManager("List table fields");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listTableFields($this, $table, $fields));
    }

    // }}}
    // {{{ getTableFieldDefinition()

    /**
     * get the stucture of a field into an array
     * 
     * @param string  $table   name of table that should be used in method
     * @param string  $fields  name of field that should be used in method
     * @param array   $definition reference to an empty array into which the
     *                            structure of the field should be stored
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function getTableFieldDefinition($table, $field, &$definition)
    {
        $result = $this->loadManager("Get table field definition");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->getTableFieldDefinition($this, $table, $field, $definition));
    }

    // }}}
    // {{{ createIndex()

    /**
     * get the stucture of a field into an array
     * 
     * @param string $table      name of the table on which the index is to be
     *                           created
     * @param string $name       name of the index to be created
     * @param array  $definition associative array that defines properties of
     *                           the index to be created. Currently, only one
     *                           property named FIELDS is supported. This
     *                           property is also an associative with the names
     *                           of the index fields as array indexes. Each
     *                           entry of this array is set to another type of
     *                           associative array that specifies properties of
     *                           the index that are specific to each field.
     *
     *                           Currently, only the sorting property is
     *                           supported. It should be used to define the
     *                           sorting direction of the index. It may be set
     *                           to either ascending or descending.
     *
     *                           Not all DBMS support index sorting direction
     *                           configuration. The DBMS drivers of those that
     *                           do not support it ignore this property. Use
     *                           the function support() to determine whether
     *                           the DBMS driver can manage indexes.
     *                         Example
     *                            array(
     *                                "FIELDS"=>array(
     *                                    "user_name"=>array(
     *                                        "sorting"=>"ascending"
     *                                    ),
     *                                    "last_login"=>array()
     *                                )
     *                            )
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function createIndex($table, $name, &$definition)
    {
        $result = $this->loadManager("Create index");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->createIndex($this, $table, $name, $definition));
    }

    // }}}
    // {{{ dropIndex()

    /**
     * drop existing index
     * 
     * @param string  $table name of table that should be used in method
     * @param string  $name  name of the index to be dropped
      * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function dropIndex($table, $name)
    {
        $result = $this->loadManager("Drop index");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->dropIndex($this, $table ,$name));
    }

    // }}}
    // {{{ createSequence()

    /**
     * create sequence
     * 
     * @param string  $name  name of the sequence to be created
     * @param string  $start start value of the sequence; default is 1
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function createSequence($name, $start = 1)
    {
        $result = $this->loadManager("Create sequence");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->createSequence($this, $name, $start));
    }

    // }}}
    // {{{ dropSequence()

    /**
     * drop existing sequence
     * 
     * @param string  $name name of the sequence to be dropped
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function dropSequence($name)
    {
        $result = $this->loadManager("Drop sequence");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->dropSequence($this, $name));
    }

    // }}}
    // {{{ listSequences()

    /**
     * list all tables in the current database
     * 
     * @param array $sequences reference to an empty array into which the list
     *                         is stored
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function listSequences(&$sequences)
    {
        $result = $this->loadManager("List sequences");
        if (MDB::isError($result)) {
            return ($result);
        }
        return ($this->manager->listSequences($this, $sequences));
    }

    // }}}
    // {{{ query()

    /**
     * Send a query to the database and return any results with a
     * DB_result object.
     *
     * @access public
     *
     * @param string $query  the SQL query
     * 
     * @return mixed a result handle or DB_OK on success, a DB error on failure
     */
    function query($query)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Query: database queries are not implemented");
    }

    // }}}
    // {{{ setSelectedRowRange()

    /**
     * set the range of the next query
     * 
     * @param string    $first    first row to select
     * @param string    $limit  number of rows to select
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function setSelectedRowRange($first, $limit)
    {
        if (!isset($this->supported["SelectRowRanges"])) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                'Set selected row range: selecting row ranges is not supported by this driver');
        }
        if (gettype($first)!= "integer" || $first < 0) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set selected row range: it was not specified a valid first selected range row');
        }
        if (gettype($limit)!= "integer" || $limit < 1) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set selected row range: it was not specified a valid selected range row limit');
        }
        $this->first_selected_row = $first;
        $this->selected_row_limit = $limit;
        return (DB_OK);
    }

    // }}}
    // {{{ limitQuery()
    /**
    * Generates a limited query
    *
    * @param string  $query query
    * @param integer $from  the row to start to fetching
    * @param integer $count the numbers of rows to fetch
    *
    * @return mixed a DB_Result object or a DB_Error
    *
    * @access public
    */
    function limitQuery($query, $from, $count)
    {
        $result = $this->setSelectedRowRange($from, $count);
        if (MDB::isError($result)) {
            return $result;
        }
        return $this->query($query);
    }

    // }}}
    // {{{ subSelect()

    /**
     * simple subselect emulation
     *
     * @access public
     *
     * @param string $query  the SQL query for the subselect
     * 
     * @return string the query
     */
    function subSelect($query)
    {
        if($this->supported["SubSelects"] == 1) {
            return ($query);
        }
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Subselect: subselect not implemented");
    }

    // }}}
    // {{{ replace()

    /**
     * Execute a SQL REPLACE query. A REPLACE query is identical to a INSERT query, except
     * that if there is already a row in the table with the same key field values, the
     * REPLACE query just updates its values instead of inserting a new row.
     *
     * The REPLACE type of query does not make part of the SQL standards. Since pratically
     * only MySQL implements it natively, this type of query is emulated through this
     * method for other DBMS using standard types of queries inside a transaction to
     * assure the atomicity of the operation.
     *
     * @access public
     *
     * @param string $table name of the table on which the REPLACE query will be executed.
     * @param array $fields associative array that describes the fields and the values
     *                         that will be inserted or updated in the specified table. The
     *                         indexes of the array are the names of all the fields of the
     *                         table. The values of the array are also associative arrays that
     *                         describe the values and other properties of the table fields.
      *
     *                         Here follows a list of field properties that need to be specified:
     *                        
     *                        Value
     *                        Value to be assigned to the specified field. This value may be of specified in database independent type format as this function can perform the necessary datatype conversions.
     *                        
     *                        Default: this property is required unless the Null property is set to 1.
     *                        
     *                        Type
     *                        Name of the type of the field. Currently, all types Metabase are supported except for clob and blob.
     *                        
     *                        Default: text
     *                        
     *                        Null
     *                        Boolean property that indicates that the value for this field should be set to NULL.
     *                        
     *                        The default value for fields missing in INSERT queries may be specified the definition of a table. Often, the default value is already NULL, but since the REPLACE may be emulated using an UPDATE query, make sure that all fields of the table are listed in this function argument array.
     *                        
     *                        Default: 0
     *                        
     *                        Key
     *                        Boolean property that indicates that this field should be handled as a primary key or at least as part of the compound unique index of the table that will determine the row that will updated if it exists or inserted a new row otherwise.
     *                        
     *                        This function will fail if no key field is specified or if the value of a key field is set to NULL because fields that are part of unique index they may not be NULL.
     *                        
     *                        Default: 0
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
    function replace($table, &$fields)
    {
        if (!$this->supported["Replace"]) {
            return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Replace: replace query is not supported");
        }
        $count = count($fields);
        for($keys = 0, $condition = $update = $insert = $values = "", reset($fields), $field = 0;
            $field < $count;
            next($fields), $field++)
        {
            $name = key($fields);
            if ($field>0) {
                $update.= ", ";
                $insert.= ", ";
                $values.= ", ";
            }
            $update.= $name;
            $insert.= $name;
            if (isset($fields[$name]["Null"])
            && $fields[$name]["Null"]) {
                $value = "NULL";
            } else {
                if (!isset($fields[$name]["Value"])) {
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'no value for field "'.$name.'" specified');
                }
                switch(isset($fields[$name]["Type"]) ? $fields[$name]["Type"] : "text")    {
                    case "text":
                        $value = $this->getTextFieldValue($fields[$name]["Value"]);
                        break;
                    case "boolean":
                        $value = $this->getBooleanFieldValue($fields[$name]["Value"]);
                        break;
                    case "integer":
                        $value = strval($fields[$name]["Value"]);
                        break;
                    case "decimal":
                        $value = $this->getDecimalFieldValue($fields[$name]["Value"]);
                        break;
                    case "float":
                        $value = $this->getFloatFieldValue($fields[$name]["Value"]);
                        break;
                    case "date":
                        $value = $this->getDateFieldValue($fields[$name]["Value"]);
                        break;
                    case "time":
                        $value = $this->getTimeFieldValue($fields[$name]["Value"]);
                        break;
                    case "timestamp":
                        $value = $this->getTimestampFieldValue($fields[$name]["Value"]);
                        break;
                    default:
                        return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                            'no supported type for field "'.$name.'" specified');
                }
            }
            $update .= "=".$value;
            $values.= $value;
            if (isset($fields[$name]["Key"]) && $fields[$name]["Key"]) {
                if ($value == "NULL") {
                    return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                        'key values may not be NULL');
                }
                $condition.= ($keys ? " AND " : " WHERE ").$name."=".$value;
                $keys++;
            }
        }
        if ($keys == 0) {
            return $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                'not specified which fields are keys');
        }
        if (!($in_transaction = $this->in_transaction)
            && MDB::isError($result = $this->autoCommit(FALSE)))
        {
            return $result;
        }
        if (!MDB::isError($success = $this->queryOne("SELECT COUNT(*) FROM $table$condition", $affected_rows, "integer"))) {
            switch($affected_rows) {
                case 0:
                    $success = $this->query("INSERT INTO $table ($insert) VALUES ($values)");
                    $affected_rows = 1;
                    break;
                case 1:
                    $success = $this->query("UPDATE $table SET $update$condition");
                    $affected_rows = $this->affected_rows*2;
                    break;
                default:
                    $success = $this->raiseError(DB_ERROR_CANNOT_REPLACE, "", "", 
                                   'replace keys are not unique');
                    break;
            }
        }

        if (!$in_transaction) {
            if (!MDB::isError($success)) {
                if (($success = (!MDB::isError($this->commit())
                    && !MDB::isError($this->autoCommit(TRUE))))
                    && isset($this->supported["AffectedRows"]))
                {
                    $this->affected_rows = $affected_rows;
                }
            } else {
                $this->rollback();
                $this->autoCommit(TRUE);
            }
        }
        return ($success);
    }

    // }}}
    // {{{ prepareQuery()

    /**
    * Prepares a query for multiple execution with execute().
    * With some database backends, this is emulated.
    * prepareQuery() requires a generic query as string like
    * "INSERT INTO numbers VALUES(?,?,?)". The ? are wildcards.
    * Types of wildcards:
    *   ? - a quoted scalar value, i.e. strings, integers
    *
    * @param string the query to prepare
    *
    * @return mixed resource handle for the prepared query on success, a DB error on failure
    *
    * @access public
    * @see execute
    */
    function prepareQuery($query)
    {
        $positions = array();
        for($position = 0;
            $position < strlen($query) && gettype($question = strpos($query, "?", $position)) == "integer";)
        {
            if (gettype($quote = strpos($query, "'", $position)) == "integer"
                && $quote<$question)
            {
                if (gettype($end_quote = strpos($query, "'", $quote+1))!= "integer") {
                    return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                        'Prepare query: query with an unterminated text string specified');
                }
                switch($this->escape_quotes) {
                    case "":
                    case "'":
                        $position = $end_quote+1;
                        break;
                    default:
                        if ($end_quote == $quote+1) {
                            $position = $end_quote+1;
                        }
                        else {
                            if ($query[$end_quote-1] == $this->escape_quotes) {
                                $position = $end_quote;
                            } else {
                                $position = $end_quote+1;
                            }
                        }
                        break;
                }
            } else {
                $positions[] = $question;
                $position = $question+1;
            }
        }
        $this->prepared_queries[] = array(
            "Query" =>$query,
            "Positions" =>$positions,
            "Values" =>array(),
            "Types" =>array()
        );
        $prepared_query = count($this->prepared_queries);
        if ($this->selected_row_limit>0) {
            $this->prepared_queries[$prepared_query-1]["First"] = $this->first_selected_row;
            $this->prepared_queries[$prepared_query-1]["Limit"] = $this->selected_row_limit;
        }
        return ($prepared_query);
    }

    // }}}
    // {{{ validatePreparedQuery()

    /**
     * validate that a handle is infact a prepared query
     * 
     * @param int $prepared_query argument is a handle that was returned by the function prepareQuery()
     *  
     * @access private
     *
     */
    function validatePreparedQuery($prepared_query)
    {
        if ($prepared_query < 1 || $prepared_query > count($this->prepared_queries)) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Validate prepared query: invalid prepared query');
        }
        if (gettype($this->prepared_queries[$prepared_query-1])!= "array") {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Validate prepared query: prepared query was already freed');
        }
       return (DB_OK);
    }

    // }}}
    // {{{ freePreparedQuery()

    /**
     * Release resources allocated for the specified prepared query.
     * 
     * @param int $prepared_query argument is a handle that was returned by the function prepareQuery()
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function freePreparedQuery($prepared_query)
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $this->prepared_queries[$prepared_query-1] = "";
        return (DB_OK);
    }

    // }}}
    // {{{ executePreparedQuery()

    /**
     * Execute a prepared query statement.
     * 
     * @param int $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param string $query query to be executed
     * @param array    $types array that contains the types of the columns in the result set
     *  
     * @access private
     *
     * @return mixed a result handle or DB_OK on success, a DB error on failure
     */ 
    function executePreparedQuery($prepared_query, $query, $types = NULL)
    {
        return ($this->query($query, $types));
    }

    // }}}
    // {{{ executeQuery()

    /**
     * Execute a prepared query statement.
     * 
     * @param int $prepared_query argument is a handle that was returned by the function prepareQuery()
     * 
     * @param array    $types array that contains the types of the columns in the result set
     * 
     * @access public
     *
     * @return mixed a result handle or DB_OK on success, a DB error on failure
     */ 
    function executeQuery($prepared_query, $types = NULL)
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query-1;
        for($this->clobs[$prepared_query] = $this->blobs[$prepared_query] = array(), $success = 1, $query = "", $last_position = $position = 0;
            $position<count($this->prepared_queries[$index]["Positions"]);
            $position++)
        {
            if (!isset($this->prepared_queries[$index]["Values"][$position])) {
                return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Execute query: it was not defined query argument '.($position+1));
            }
            $current_position = $this->prepared_queries[$index]["Positions"][$position];
            $query .= substr($this->prepared_queries[$index]["Query"], $last_position, $current_position-$last_position);
            $value = $this->prepared_queries[$index]["Values"][$position];
            if ($this->prepared_queries[$index]["IsNULL"][$position]) {
                $query .= $value;
            } else {
                switch($this->prepared_queries[$index]["Types"][$position]) {
                    case "clob":
                        if (MDB::isError($success = $this->getCLOBFieldValue($prepared_query, $position+1, $value, $this->clobs[$prepared_query][$position+1]))) {
                            unset($this->clobs[$prepared_query][$position+1]);
                            break;
                        }
                        $query .= $this->clobs[$prepared_query][$position+1];
                        break;
                    case "blob":
                        if (MDB::isError($success = $this->getBLOBFieldValue($prepared_query, $position+1, $value, $this->blobs[$prepared_query][$position+1]))) {
                            unset($this->blobs[$prepared_query][$position+1]);
                            break;
                        }
                        $query .= $this->blobs[$prepared_query][$position+1];
                        break;
                    default:
                        $query .= $value;
                        break;
                }
            }
            $last_position = $current_position+1;
        }
        if (!MDB::isError($success)) {
            $query.= substr($this->prepared_queries[$index]["Query"], $last_position);
            if ($this->selected_row_limit>0) {
                $this->prepared_queries[$index]["First"] = $this->first_selected_row;
                $this->prepared_queries[$index]["Limit"] = $this->selected_row_limit;
            }
            if (isset($this->prepared_queries[$index]["Limit"])
                && $this->prepared_queries[$index]["Limit"]>0)
            {
                $this->first_selected_row = $this->prepared_queries[$index]["First"];
                $this->selected_row_limit = $this->prepared_queries[$index]["Limit"];
            } else {
                $this->first_selected_row = $this->selected_row_limit = 0;
            }
            $success = $this->executePreparedQuery($prepared_query, $query, $types);
        }
        for(reset($this->clobs[$prepared_query]), $clob = 0;
            $clob<count($this->clobs[$prepared_query]);
            $clob++,next($this->clobs[$prepared_query]))
        {
            $this->freeCLOBValue($prepared_query,key($this->clobs[$prepared_query]), $this->clobs[$prepared_query][Key($this->clobs[$prepared_query])], $success);
        }
        unset($this->clobs[$prepared_query]);
        for(reset($this->blobs[$prepared_query]), $blob = 0;
            $blob<count($this->blobs[$prepared_query]);
            $blob++,next($this->blobs[$prepared_query]))
        {
            $this->freeBLOBValue($prepared_query,key($this->blobs[$prepared_query]), $this->blobs[$prepared_query][key($this->blobs[$prepared_query])], $success);
        }
        unset($this->blobs[$prepared_query]);
        return ($success);
    }

    // }}}
    // {{{ execute()
    /**
    * Executes a prepared SQL query
    * With execute() the generic query of prepare is
    * assigned with the given data array. The values
    * of the array inserted into the query in the same
    * order like the array order
    *
    * @param resource $prepared_query query handle from prepare()
    * @param array    $types array that contains the types of the columns in the result set
    * @param array    $params numeric array containing the
    *                       data to insert into the query
    * @param array    $param_types array that contains the types of the values defined in $params
    *
    * @return mixed   a new result handle or a DB_Error when fail
    *
    * @access public
    * @see prepare()
    */
    function execute($prepared_query, $types = NULL, $params = FALSE, $param_types = NULL)
    {
        $this->querySetArray($prepared_query, $params, $param_types);

        return $this->executeQuery($prepared_query, $types);
    }

    // }}}
    // {{{ executeMultiple()

    /**
    * This function does several execute() calls on the same
    * statement handle.  $params must be an array indexed numerically
    * from 0, one execute call is done for every "row" in the array.
    *
    * If an error occurs during execute(), executeMultiple() does not
    * execute the unfinished rows, but rather returns that error.
    *
    * @param resource $stmt query handle from prepare()
    * @param array    $types array that contains the types of the columns in the result set
    * @param array    $params numeric array containing the
    *                       data to insert into the query
    * @param array    $parAM_types array that contains the types of the values defined in $params
    *
    * @return mixed a result handle or DB_OK on success, a DB error on failure
    *
    * @access public
    * @see prepare(), execute()
    */
    function executeMultiple($prepared_query, $types = NULL, &$params, $param_types = NULL)
    {
        for($i = 0, $j = count($params); $i < $j; $i++) {
            $result = $this->execute($prepared_query, $types, $params[$i], $param_types);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return (DB_OK);
    }

    // }}}
    // {{{ autoPrepare()

    /**
    * Make automaticaly an insert or update query and call prepare() with it
    *
    * @param string $table name of the table
    * @param array $table_fields ordered array containing the fields names
    * @param int $mode type of query to make (DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE)
    * @param string $where in case of update queries, this string will be put after the sql WHERE statement
    * @return resource handle for the query
    * @see buildManipSQL
    * @access public
    */
    function autoPrepare($table, $table_fields, $mode = DB_AUTOQUERY_INSERT, $where = FALSE)
    {
        $query = $this->buildManipSQL($table, $table_fields, $mode, $where);
        return $this->prepare($query);
    }

    // {{{
    // }}} autoExecute()

    /**
    * Make automaticaly an insert or update query and call prepare() and execute() with it
    *
    * @param string $table name of the table
    * @param array $fields_values assoc ($key=>$value) where $key is a field name and $value its value
    * @param int $mode type of query to make (DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE)
    * @param string $where in case of update queries, this string will be put after the sql WHERE statement
    * @return mixed  a new DB_Result or a DB_Error when fail
    * @see buildManipSQL
    * @see autoPrepare
    * @access public
    */
    function autoExecute($table, $fields_values, $mode = DB_AUTOQUERY_INSERT, $where = FALSE)
    {
        $sth = $this->autoPrepare($table, array_keys($fields_values), $mode, $where);
        return $this->execute($sth, array_values($fields_values));
    }

    // {{{
    // }}} buildManipSQL()

    /**
    * Make automaticaly an sql query for prepare()
    *
    * Example : buildManipSQL('table_sql', array('field1', 'field2', 'field3'), DB_AUTOQUERY_INSERT)
    *           will return the string : INSERT INTO table_sql (field1,field2,field3) VALUES (?,?,?)
    * NB : This belongs more to a SQL Builder class, but this is a simple facility
    *
    * @param string $table name of the table
    * @param array $table_fields ordered array containing the fields names
    * @param int $mode type of query to make (DB_AUTOQUERY_INSERT or DB_AUTOQUERY_UPDATE)
    * @param string $where in case of update queries, this string will be put after the sql WHERE statement
    * @return string sql query for prepare()
    * @access public
    */
    function buildManipSQL($table, $table_fields, $mode, $where = FALSE)
    {
        if (count($table_fields)==0) {
            $this->raiseError(DB_ERROR_NEED_MORE_DATA);
        }
        $first = TRUE;
        switch($mode) {
        case DB_AUTOQUERY_INSERT:
            $values = '';
            $names = '';
            while (list(, $value) = each($table_fields)) {
                if ($first) {
                    $first = FALSE;
                } else {
                    $names .= ',';
                    $values .= ',';
                }
                $names .= $value;
                $values .= '?';
            }
            return "INSERT INTO $table ($names) VALUES ($values)";
            break;
        case DB_AUTOQUERY_UPDATE:
            $set = '';
            while (list(, $value) = each($table_fields)) {
                if ($first) {
                    $first = false;
                } else {
                    $set .= ',';
                }
                $set .= "$value = ?";
            }
            $sql = "UPDATE $table SET $set";
            if($where) {
                $sql .= " WHERE $where";
            }
            return $sql;
            break;
        default:
            $this->raiseError(DB_ERROR_SYNTAX);
        }
    }

    // }}}
    // {{{ querySet()

    /**
     * Set the value of a parameter of a prepared query.
     * 
     * @param int         $prepared_query    argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter      the order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $type             designation of the type of the parameter to be set.
     *                                    The designation of the currently supported types is as follows:
                                        text, boolean, integer, decimal, float, date, time, timestamp, clob, blob
     * @param mixed        $value             value that is meant to be assigned to specified parameter. The type of
     *                                     the value depends on the $type argument.
     * @param boolean    $is_null        flag that indicates whether whether the parameter is a NULL
     * @param string    $field            name of the field that is meant to be assigned with this parameter value
     *                                     when it is of type clob or blob
       * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySet($prepared_query, $parameter, $type, $value, $is_null = 0, $field = "")
    {
        $result = $this->validatePreparedQuery($prepared_query);
        if (MDB::isError($result)) {
            return $result;
        }
        $index = $prepared_query-1;
        if ($parameter<1
            || $parameter>count($this->prepared_queries[$index]["Positions"]))
        {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Query set: it was not specified a valid argument number');
        }
        $this->prepared_queries[$index]["Values"][$parameter-1] = $value;
        $this->prepared_queries[$index]["Types"][$parameter-1] = $type;
        $this->prepared_queries[$index]["Fields"][$parameter-1] = $field;
        $this->prepared_queries[$index]["IsNULL"][$parameter-1] = $is_null;
        return (DB_OK);
    }

    // }}}
    // {{{ querySetArray()

    /**
     * Set the values of multiple a parameter of a prepared query in bulk.
     * 
     * @param int       $prepared_query    argument is a handle that was returned by the function prepareQuery()
     * @param array     $params            array thats specifies all necessary infromation for querySet()
     *                                    the array elements must use keys corresponding to the number of the
     *                                    position of the parameter.
     *                                    single dimensional array:
     *                                         querySet with type text for all values of the array
     *                                    multi dimensional array :
                                    
     *                                        0:    value
     *                                        1:    optional data
     * @param array     $types            array thats specifies the types of the fields
     * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetArray($prepared_query, $params, $types = NULL)
    {
        if (is_array($types)) {
            for($i = 0, $j = count($params); $i<$j; ++$i) {
                switch($types[$i]) {
                    case "null":
                        // maybe it would be cleaner to use $array[$i][2] instead of $array[$i][1] here
                        // but it might not be as nice when defining the array itself
                        $success = $this->querySet($prepared_query, $i+1, $params[$i][0], "NULL", 1, "");
                        break;
                    case "text":
                        $success = $this->querySet($prepared_query, $i+1, "text", $this->getTextFieldValue($params[$i][0]));
                        break;
                    case "clob":
                        $success = $this->querySet($prepared_query, $i+1, "clob", $params[$i][0],0, $params[$i][1]);
                        break;
                    case "blob":
                        $success = $this->querySet($prepared_query, $i+1, "blob", $params[$i][0],0, $params[$i][1]);
                        break;
                    case "integer":
                        $success = $this->querySet($prepared_query, $i+1, "integer", $this->getIntegerFieldValue($params[$i][0]));
                        break;
                    case "boolean":
                        $success = $this->querySet($prepared_query, $i+1, "boolean", $this->getBooleanFieldValue($params[$i][0]));
                        break;
                    case "date":
                        $success = $this->querySet($prepared_query, $i+1, "date", $this->getDateFieldValue($params[$i][0]));
                        break;
                    case "timestamp":
                        $success = $this->querySet($prepared_query, $i+1, "timestamp", $this->getTimestampFieldValue($params[$i][0]));
                        break;
                    case "time":
                        $success = $this->querySet($prepared_query, $i+1, "time", $this->getTimeFieldValue($params[$i][0]));
                        break;
                    case "float":
                        $success = $this->querySet($prepared_query, $i+1, "float", $this->getFloatFieldValue($params[$i][0]));
                        break;
                    case "decimal":
                        $success = $this->querySet($prepared_query, $i+1, "decimal", $this->getDecimalFieldValue($params[$i][0]));
                        break;
                    default:
                        $success = $this->querySet($prepared_query, $i+1, "text", $this->getTextFieldValue($params[$i][0]));                    
                        break;
                }
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        else
        {
            for($i = 0, $j = count($params); $i<$j; ++$i) {
                $success = $this->querySet($prepared_query, $i+1, "text", $this->getTextFieldValue($params[$i]));
                if (MDB::isError($success)) {
                    return $success;
                }
            }
        }
        return (DB_OK);
    }

    // }}}
    // {{{ querySetNull()

    /**
     * Set the value of a parameter of a prepared query to NULL.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $type             designation of the type of the parameter to be set. The designation of
     *                                     the currently supported types is list in the usage of the function 
     *                                     querySet()
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetNull($prepared_query, $parameter, $type)
    {
        return ($this->querySet($prepared_query, $parameter, $type, "NULL", 1, ""));
    }

    // }}}
    // {{{ querySetText()

    /**
     * Set a parameter of a prepared query with a text value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             text value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetText($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "text", $this->getTextFieldValue($value)));
    }

    // }}}
    // {{{ querySetClob()

    /**
     * Set a parameter of a prepared query with a character large object value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param int         $value             handle of large object created with createLOB() function from which
     *                                     it will be read the data value that is meant to be assigned to
     *                                     specified parameter.
     * @param string    $field            name of the field of a INSERT or UPDATE query to which it will be
                                         assigned the value to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetCLob($prepared_query, $parameter, $value, $field)
    {
        return ($this->querySet($prepared_query, $parameter, "clob", $value, 0, $field));
    }

    // }}}
    // {{{ querySetClob()

    /**
     * Set a parameter of a prepared query with a binary large object value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param int         $value             handle of large object created with createLOB() function from which
     *                                     it will be read the data value that is meant to be assigned to
     *                                     specified parameter.
     * @param string    $field            name of the field of a INSERT or UPDATE query to which it will be
                                         assigned the value to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetBLob($prepared_query, $parameter, $value, $field)
    {
        return ($this->querySet($prepared_query, $parameter, "blob", $value, 0, $field));
    }

    // }}}
    // {{{ querySetInteger()

    /**
     * Set a parameter of a prepared query with a text value.
     * 
     * @param int     $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int     $parameter         order number of the parameter in the query statement. The order
     *                                 number of the first parameter is 1.
     * @param int     $value             an integer value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetInteger($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "integer", $this->getIntegerFieldValue($value)));
    }

    // }}}
    // {{{ querySetBoolean()

    /**
     * Set a parameter of a prepared query with a boolean value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param boolean     $value             boolean value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetBoolean($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "boolean", $this->getBooleanFieldValue($value)));
    }

    // }}}
    // {{{ querySetDate()

    /**
     * Set a parameter of a prepared query with a date value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             date value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetDate($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "date", $this->getDateFieldValue($value)));
    }

    // }}}
    // {{{ querySetTimestamp()

    /**
     * Set a parameter of a prepared query with a time stamp value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             time stamp value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetTimestamp($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "timestamp", $this->getTimestampFieldValue($value)));
    }

    // }}}
    // {{{ querySetTime()

    /**
     * Set a parameter of a prepared query with a time value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             time value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetTime($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "time", $this->getTimeFieldValue($value)));
    }

    // }}}
    // {{{ querySetFloat()

    /**
     * Set a parameter of a prepared query with a float value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             float value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetFloat($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "float", $this->getFloatFieldValue($value)));
    }

    // }}}
    // {{{ querySetDecimal()

    /**
     * Set a parameter of a prepared query with a decimal value.
     * 
     * @param int         $prepared_query argument is a handle that was returned by the function prepareQuery()
     * @param int         $parameter         order number of the parameter in the query statement. The order
     *                                     number of the first parameter is 1.
     * @param string     $value             decimal value that is meant to be assigned to specified parameter.
       * 
     * @access public
     * @see querySet()
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function querySetDecimal($prepared_query, $parameter, $value)
    {
        return ($this->querySet($prepared_query, $parameter, "decimal", $this->getDecimalFieldValue($value)));
    }

    // }}}
    // {{{ setResultTypes()

    /**
     * Define the list of types to be associated with the columns of a given result set.
     * 
     * This function may be called before invoking fetchInto(), fetchOne(), fetchRow(),
     * fetchCol() and fetchAll() so that the necessary data type conversions are performed
     * on the data to be retrieved by them. If this function is not called, the type of all
     * result set columns is assumed to be text, thus leading to not perform any conversions.
     * 
     * @param resource    $result    result identifier
     * @param string    $types    reference to an array variable that lists the data types to be
     *                             expected in the result set columns. If this array contains less
     *                             types than the number of columns that are returned in the result
     *                             set, the remaining columns are assumed to be of the type text.
     *                             Currently, the types clob and blob are not fully supported.
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function setResultTypes($result, &$types)
    {
        if (isset($this->result_types[$result])) {
            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                'Set result types: attempted to redefine the types of the columns of a result set');
        }
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        if ($columns<count($types)) {
            return $this->raiseError(DB_ERROR_SYNTAX, "", "", 
                'Set result types: it were specified more result types ('.count($types).') than result columns ('.$columns.')');
        }
        $valid_types = array(
            "text" =>      MDB_TYPE_TEXT,
            "boolean" =>   MDB_TYPE_BOOLEAN,
            "integer" =>   MDB_TYPE_INTEGER,
            "decimal" =>   MDB_TYPE_DECIMAL,
            "float" =>     MDB_TYPE_FLOAT,
            "date" =>      MDB_TYPE_DATE,
            "time" =>      MDB_TYPE_TIME,
            "timestamp" => MDB_TYPE_TIMESTAMP,
            "clob" =>      MDB_TYPE_CLOB,
            "blob" =>      MDB_TYPE_BLOB
        );
        for($column = 0; $column<count($types); $column++) {
            if (!isset($valid_types[$types[$column]])) {
                return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
                    'Set result types: '.$types[$column].' is not a supported column type');
            }
            $this->result_types[$result][$column] = $valid_types[$types[$column]];
        }
        for(; $column<$columns; $column++) {
            $this->result_types[$result][$column] = MDB_TYPE_TEXT;
        }
        return (DB_OK);
    }

    // }}}
    // {{{ affectedRows()
    /**
    * returns the affected rows of a query
    *
    * @return mixed DB_Error or number of rows
    *
    * @access public
    */
    function affectedRows()
    {
        if ($this->affected_rows == -1) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA);
        }
        return ($this->affected_rows);
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     * 
     * @param resource    $result        result identifier
     * @param array        $columns    reference to an associative array variable that will hold the
     *                                 names of columns. The indexes of the array are the column names
     *                                 mapped to lower case and the values are the respective numbers
     *                                 of the columns starting from 0. Some DBMS may not return any
     *                                 columns when the result set does not contain any rows.
     * 
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function getColumnNames($result, &$columns)
    {
        $columns = array();
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get column names: obtaining result column names is not implemented');
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     * 
     * @param resource    $result        result identifier
     *  
     * @access public
     *
     * @return mixed integer value with the number of columns, a DB error on failure
     */ 
    function numCols($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Number of columns: obtaining the number of result columns is not implemented');
    }

    // }}}
    // {{{ endOfResult()
    /**
    * check if the end of the result set has been reached
    * 
    * @param resource    $result result identifier
    *
    * @return mixed TRUE or FALSE on sucess, a DB error on failure
    *
    * @access public
    */
    function endOfResult($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'End of result: end of result method not implemented');
    }

    // }}}
    // {{{ setFetchMode()

    /**
     * Sets which fetch mode should be used by default on queries
     * on this connection.
     *
     * @param integer $fetchmode    DB_FETCHMODE_ORDERED or
     *                                DB_FETCHMODE_ASSOC, possibly bit-wise OR'ed with
     *                                DB_FETCHMODE_FLIPPED.
     *
     * @param string $object_class    The class of the object
     *                              to be returned by the fetch methods when
     *                              the DB_FETCHMODE_OBJECT mode is selected.
     *                              If no class is specified by default a cast
     *                              to object from the assoc array row will be done.
     *                              There is also the posibility to use and extend the
     *                              'DB_Row' class.
     *
     * @see DB_FETCHMODE_ORDERED
     * @see DB_FETCHMODE_ASSOC
     * @see DB_FETCHMODE_FLIPPED
     * @see DB_FETCHMODE_OBJECT
     * @see DB_Row::DB_Row()
     * 
     * @access public
     */
    function setFetchMode($fetchmode, $object_class = NULL)
    {
        switch ($fetchmode) {
            case DB_FETCHMODE_OBJECT:
                if ($object_class) {
                    $this->fetchmode_object_class = $object_class;
                }
            case DB_FETCHMODE_ORDERED:
            case DB_FETCHMODE_ASSOC:
                $this->fetchmode = $fetchmode;
                break;
            default:
                return $this->raiseError('invalid fetchmode mode');
        }
    }

    // }}}
    // {{{ fetch()
    /**
    * fetch value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed string on success, a DB error on failure
    *
    * @access public
    */
    function fetch($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Fetch: fetch result method not implemented');
    }

    // }}}
    // {{{ fetchLoabResult()
    /**
    * fetch a lob value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed string on success, a DB error on failure
    *
    * @access public
    */
    function fetchLobResult($result, $row, $field)
    {
        $lob = count($this->lobs) + 1;
        $this->lobs[$lob] = array(
            "Result" => $result,
            "Row" => $row,
            "Field" => $field,
            "Position" => 0
        );
        $character_lob = array(
            "Database" => $this,
            "Error" => "",
            "Type" => "resultlob",
            "ResultLOB" => $lob
        );
        if (!createLob($character_lob, $clob)) {
            return $this->raiseError(DB_ERROR, "", "", 
                'Fetch LOB result: '. $character_lob["Error"]);
        }
        return ($clob);
    }

    // }}}
    // {{{ retrieveLob()
    /**
    * fetch a float value from a result set
    * 
    * @param int    $lob handle to a lob created by the createLob() function
    *
    * @return mixed DB_Ok on success, a DB error on failure
    *
    * @access public
    */
    function retrieveLob($lob)
    {
        if (!isset($this->lobs[$lob])) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                'Fetch LOB result: it was not specified a valid lob');
        }
        if (!isset($this->lobs[$lob]["Value"])) {
            $this->lobs[$lob]["Value"] = $this->fetch($this->lobs[$lob]["Result"], $this->lobs[$lob]["Row"], $this->lobs[$lob]["Field"]);
        }
        return (DB_OK);
    }

    // }}}
    // {{{ endOfResultLob()
    /**
    * Determine whether it was reached the end of the large object and therefore there is
    * no more data to be read for the its input stream.
    * 
    * @param int    $lob handle to a lob created by the createLob() function
    *
    * @return mixed TRUE or FALSE on success, a DB error on failure
    *
    * @access public
    */
    function endOfResultLob($lob)
    {
        $result = $this->retrieveLob($lob);
        if (MDB::isError($result)) {
            return $result;
        }
        return ($this->lobs[$lob]["Position"] >= strlen($this->lobs[$lob]["Value"]));
    }

    // }}}
    // {{{ readResultLob()
    /**
    * Read data from large object input stream.
    * 
    * @param int    $lob    handle to a lob created by the createLob() function
    * @param blob    $data    reference to a variable that will hold data to be read from the large object input stream
    * @param int    $length    integer value that indicates the largest ammount of data to be read from the large object input stream.
    *
    * @return mixed length on success, a DB error on failure
    *
    * @access public
    */
    function readResultLob($lob, &$data, $length)
    {
        $result = $this->retrieveLob($lob);
        if (MDB::isError($result)) {
            return $result;
        }
        $length = min($length,strlen($this->lobs[$lob]["Value"])-$this->lobs[$lob]["Position"]);
        $data = substr($this->lobs[$lob]["Value"], $this->lobs[$lob]["Position"], $length);
        $this->lobs[$lob]["Position"] += $length;
        return ($length);
    }

    // }}}
    // {{{ destroyResultLob()
    /**
    * Free any resources allocated during the lifetime of the large object handler object.
    * 
    * @param int    $lob    handle to a lob created by the createLob() function
    *
    * @access public
    */
    function destroyResultLob($lob)
    {
        if (isset($this->lobs[$lob])) {
            $this->lobs[$lob] = "";
        }
    }

    // }}}
    // {{{ fetchClobResult()
    /**
    * fetch a clob value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure, a DB error on failure
    *
    * @access public
    */
    function fetchClobResult($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'fetch clob result method is not implemented');
    }

    // }}}
    // {{{ fetchBlobResult()
    /**
    * fetch a blob value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchBlobResult($result, $row, $field)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'fetch blob result method is not implemented');
    }

    // }}}
    // {{{ resultIsNull()
    /**
    * Determine whether the value of a query result located in given row and field is a NULL.
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed TRUE or FALSE on success, a DB error on failure
    *
    * @access public
    */
    function resultIsNull($result, $row, $field)
    {
        $res = $this->fetch($result, $row, $field);
        if(MDB::isError($res)) {
            return $res;
        }
        return (!isset($res));
    }

    // }}}
    // {{{ baseConvertResult()
    /**
    * general type conversion method
    * 
    * @param mixed    $value    refernce to a value to be converted
    * @param int    $type    constant that specifies which type to convert to
    *
    * @return object a DB error on failure
    *
    * @access private
    */
    function baseConvertResult(&$value, $type)
    {
        switch($type) {
            case MDB_TYPE_TEXT:
                return (DB_OK);
            case MDB_TYPE_INTEGER:
                $value = intval($value);
                return (DB_OK);
            case MDB_TYPE_BOOLEAN:
                $value = (strcmp($value, "Y") ? 0 : 1);
                return (DB_OK);
            case MDB_TYPE_DECIMAL:
                return (DB_OK);
            case MDB_TYPE_FLOAT:
                $value = doubleval($value);
                return (DB_OK);
            case MDB_TYPE_DATE:
            case MDB_TYPE_TIME:
            case MDB_TYPE_TIMESTAMP:
                return (DB_OK);
            case MDB_TYPE_CLOB:
            case MDB_TYPE_BLOB:
                $value = "";
                return $this->raiseError(DB_ERROR_INVALID, "", "", 
                    'BaseConvertResult: attempt to convert result value to an unsupported type '.$type);
            default:
                $value = "";
                return $this->raiseError(DB_ERROR_INVALID, "", "", 
                    'BaseConvertResult: attempt to convert result value to an unknown type '.$type);
        }
    }

    // }}}
    // {{{ convertResult()
    /**
    * convert a value to a RDBMS indepdenant MDB type
    * 
    * @param mixed    $value    refernce to a value to be converted
    * @param int    $type    constant that specifies which type to convert to
    *
    * @return object a DB error on failure
    *
    * @access public
    */
    function convertResult(&$value, $type)
    {
        return ($this->baseConvertResult($value, $type));
    }

    // }}}
    // {{{ convertResultRow()
    /**
    * convert a result row
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    *
    * @return mixed DB_Ok on success,  a DB error on failure
    *
    * @access public
    */
    function convertResultRow($result, &$row)
    {
        if (isset($this->result_types[$result]))
        {
            $columns = $this->numCols($result);
            if (MDB::isError($columns)) {
                return $columns;
            }
            for($column = 0; $column<$columns; $column++) {
                if (!isset($row[$column])) {
                    continue;
                }
                switch($type = $this->result_types[$result][$column]) {
                    case MDB_TYPE_TEXT:
                        break;
                    case MDB_TYPE_INTEGER:
                        $row[$column] = intval($row[$column]);
                        break;
                    default:
                        if (!$this->convertResult($row[$column], $type)) {
                            return $this->raiseError(DB_ERROR_INVALID, "", "", 
                                'convertResultRow: attempt to convert result value to an unknown type '.$type);
                        }
                        break;
                }
            }
        }
        return (DB_OK);
    }

    // }}}
    // {{{ fetchDateResult()
    /**
    * fetch a date value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchDateResult($result, $row, $field)
    {
        $value = $this->fetch($result,$row,$field);
        $this->convertResult($value, MDB_TYPE_DATE);
        return ($value);
    }

    // }}}
    // {{{ fetchTimestampResult()
    /**
    * fetch a timestamp value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchTimestampResult($result, $row, $field)
    {
        $value = $this->fetch($result,$row,$field);
        $this->convertResult($value, MDB_TYPE_TIMESTAMP);
        return ($value);
    }

    // }}}
    // {{{ fetchTimeResult()
    /**
    * fetch a time value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchTimeResult($result, $row, $field)
    {
        $value = $this->fetch($result, $row, $field);
        $this->convertResult($value, MDB_TYPE_TIME);
        return ($value);
    }

    // }}}
    // {{{ fetchBooleanResult()
    /**
    * fetch a boolean value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchBooleanResult($result, $row, $field)
    {
        $value = $this->fetch($result, $row, $field);
        $this->convertResult($value, MDB_TYPE_BOOLEAN);
        return ($value);
    }

    // }}}
    // {{{ fetchFloatResult()
    /**
    * fetch a float value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchFloatResult($result, $row, $field)
    {
        $value = $this->fetch($result, $row, $field);
        $this->convertResult($value, MDB_TYPE_FLOAT);
        return ($value);
    }

    // }}}
    // {{{ fetchDecimalResult()
    /**
    * fetch a decimal value from a result set
    * 
    * @param resource    $result result identifier
    * @param int    $row    number of the row where the data can be found
    * @param int    $field    field number where the data can be found 
    *
    * @return mixed content of the specified data cell, a DB error on failure
    *
    * @access public
    */
    function fetchDecimalResult($result, $row, $field)
    {
        $value = $this->fetch($result, $row, $field);
        $this->convertResult($value, MDB_TYPE_DECIMAL);
        return ($value);
    } 

    // }}}
    // {{{ numRows()
    /**
    * returns the number of rows in a result object
    * renamed for PEAR
    * used to be: NumberOfColumns
    *
    * @param object DB_Result the result object to check
    *
    * @return mixed DB_Error or the number of rows
    *
    * @access public
    */
    function numRows($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Num Rows: number of rows method not implemented");
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param $result result identifier
     *
     * @access public
     *
     * @return bool TRUE on success, FALSE if $result is invalid
     */
    function freeResult($result)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Free Result: free result method not implemented");
    }

    // }}}
    // {{{ errorNative()
    /**
    * returns an errormessage, provides by the database
    *
    * @return mixed DB_Error or message
    *
    * @access public
    */
    function errorNative()
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    unsigned
     *
     *                                        Boolean flag that indicates whether the field should
     *                                        be declared as unsigned integer if possible.
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getIntegerDeclaration($name, &$field)
    {
        // How should warnings be handled?
        if (isset($field["unsigned"])) {
            $this->warning = "unsigned integer field \"$name\" is being declared as signed integer";
        }
        return ("$name INT".(isset($field["default"]) ? " DEFAULT ".$field["default"] : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTextDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    length
     *
     *                                        Integer value that determines the maximum length of
     *                                         the text field. If this argument is missing the field
     *                                         should be declared to have the longest length allowed
     *                                         by the DBMS.
     *
     *                                    default
     *
     *                                        Text value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getTextDeclaration($name, &$field)
    {
        return ((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getCLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character large object type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    length
     *
     *                                        Integer value that determines the maximum length of
     *                                         the large object field. If this argument is missing
     *                                         the field should be declared to have the longest length
     *                                         allowed by the DBMS.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getCLOBDeclaration($name, &$field)
    {
        return ((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getBLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large object type
     * field to be used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    length
     *
     *                                        Integer value that determines the maximum length of
     *                                         the large object field. If this argument is missing
     *                                         the field should be declared to have the longest length
     *                                         allowed by the DBMS.
     * 
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getBLOBDeclaration($name, &$field)
    {
        return ((isset($field["length"]) ? "$name CHAR (".$field["length"].")" : "$name TEXT").(isset($field["default"]) ? " DEFAULT ".$this->getTextFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getBooleanDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an boolean type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Boolean value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getBooleanDeclaration($name, &$field)
    {
        return ("$name CHAR (1)".(isset($field["default"]) ? " DEFAULT ".$this->getBooleanFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an date type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Date value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getDateDeclaration($name, &$field)
    {
        return ("$name CHAR (".strlen("YYYY-MM-DD").")".(isset($field["default"]) ? " DEFAULT ".$this->getDateFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an timestamp type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Time stamp value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getTimestampDeclaration($name, &$field)
    {
        return ("$name CHAR (".strlen("YYYY-MM-DD HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->getTimestampFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an time type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Time value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getTimeDeclaration($name, &$field)
    {
        return ("$name CHAR (".strlen("HH:MM:SS").")".(isset($field["default"]) ? " DEFAULT ".$this->getTimeFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an float type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getFloatDeclaration($name, &$field)
    {
        return ("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->getFloatFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an decimal type field to be
     * used in statements like CREATE TABLE.
     * 
     * @param string    $name          name the field to be declared.
     * @param string    $field         associative array with the name of the properties of the
     *                                 field being declared as array indexes. Currently, the
     *                                 types of supported field properties are as follows:
     *
     *                                    default
     *
     *                                        Integer value to be used as default for this field.
     *
     *                                    notnull
     *
     *                                        Boolean flag that indicates whether this field is
     *                                        constrained to not be set to NULL.
     * 
     * @access public
     *
     * @return string    DBMS specific SQL code portion that should be used to declare the specified field.
     */
    function getDecimalDeclaration($name, &$field)
    {
        return ("$name TEXT ".(isset($field["default"]) ? " DEFAULT ".$this->getDecimalFieldValue($field["default"]) : "").(isset($field["notnull"]) ? " NOT NULL" : ""));
    }

    // }}}
    // {{{ getIntegerFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getIntegerFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "$value");
    }

    // }}}
    // {{{ getTextFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that already contains any DBMS specific escaped character sequences.
     */
    function getTextFieldValue($value)
    {
        $this->quote($value);
        return ("'$value'");
    }

    // }}}
    // {{{ getCLOBFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */    
    function getCLOBFieldValue($prepared_query, $parameter, $clob, &$value)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get CLOB field value: prepared queries with values of type "clob" are not yet supported');
    }

    // }}}
    // {{{ freeCLOBValue()

    /**
     * free a chracter large object
     * 
     * @param resource     $prepared_query query handle from prepare()
     * @param string    $blob             
     * @param string    $value             
     * @param string    $success         
     * 
     * @access private
     */
    function freeCLOBValue($prepared_query, $clob, &$value, $success)
    {
    }

    // }}}
    // {{{ getBLOBFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getBLOBFieldValue($prepared_query, $parameter, $blob, &$value)
    {
        return $this->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            'Get BLOB field value: prepared queries with values of type "blob" are not yet supported');
    }

    // }}}
    // {{{ freeBLOBValue()

    /**
     * free a binary large object
     * 
     * @param resource     $prepared_query query handle from prepare()
     * @param string    $blob             
     * @param string    $value             
     * @param string    $success         
     * 
     * @access private
     */
    function freeBLOBValue($prepared_query, $blob, &$value, $success)
    {
    }

    // }}}
    // {{{ getBooleanFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getBooleanFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : ($value ? "'Y'" : "'N'"));
    }

    // }}}
    // {{{ getDateFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getDateFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    // }}}
    // {{{ getTimestampFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getTimestampFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    // }}}
    // {{{ getTimeFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getTimeFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    // }}}
    // {{{ getFloatFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getFloatFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    // }}}
    // {{{ getDecimalFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getDecimalFieldValue($value)
    {
        return (!strcmp($value, "NULL") ? "NULL" : "'$value'");
    }

    // }}}
    // {{{ getFieldValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to compose query statements.
     * 
     * @param string    $type         type to which the value should be converted to
     * @param string    $value         text string value that is intended to be converted.
     * 
     * @access public
     *
     * @return string    text string that represents the given argument value in a DBMS specific format.
     */
    function getFieldValue($type, $value)
    {
        switch($type) {
            case "integer":
                return ($this->getIntegerFieldValue($value));
            case "text":
                return ($this->getTextFieldValue($value));
            case "boolean":
                return ($this->getBooleanFieldValue($value));
            case "date":
                return ($this->getDateFieldValue($value));
            case "timestamp":
                return ($this->getTimestampFieldValue($value));
            case "time":
                return ($this->getTimeFieldValue($value));
            case "float":
                return ($this->getFloatFieldValue($value));
            case "decimal":
                return ($this->getDecimalFieldValue($value));
        }
        return ("");
    }

    // }}}
    // {{{ support()

    /**
     * Tell whether a DB implementation or its backend extension
     * supports a given feature.
     *
     * @param string $feature name of the feature (see the MDB class doc)
     * @return bool whether this DB implementation supports $feature
     * @access public
     */
    function support($feature)
    {
        return (isset($this->supported[$feature]));
    }

    // }}}
    // {{{ nextId()
    /**
    * returns the next free id of a sequence
    * renamed for PEAR
    * used to be: getSequenceNextValue
    *
    * @param string  $name     name of the sequence
    * @param boolean $ondemand when true the seqence is
    *                          automatic created, if it
    *                          not exists
    *
    * @return mixed DB_Error or id
    */
    function nextId($name, $ondemand = FALSE)
    {
         return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Next Sequence: getting next sequence value not supported');
    }

    // }}}
    // {{{ currId()
    /**
    * returns the current id of a sequence
    * renamed for PEAR
    * used to be: getSequenceCurrentValue
    *
    * @param string  $name     name of the sequence
    *
    * @return mixed DB_Error or id
    */
    function currId($name)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Current Sequence: getting current sequence value not supported');
    }

    // }}}
    // {{{ baseFetchInto()

    /**
     * get the stucture of a field into an array
     * 
     * @param resource    $result    result identifier
     * @param array        $array     reference to an array where data from the row is stored
     * @param int        $rownum the row number to fetch
     * 
     * @access private
     *
     * @return mixed DB_OK on success, a DB error on failure
     */ 
    function baseFetchInto($result, &$array, $rownum)
    {
        if($rownum == NULL) {
            ++$this->highest_fetched_row[$result];
            $rownum = $this->highest_fetched_row[$result];
        } else {
            $this->highest_fetched_row[$result] = max($this->highest_fetched_row[$result], $row);
        }
        if ($rownum + 1 > $this->numRows($result)) {
            return NULL;
        }
        $columns = $this->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        for($array = array(), $column = 0; $column < $columns; $column++) {
            if (!$this->resultIsNull($result, $rownum, $column)) {
                $res = $this->fetch($result, $rownum, $column);
                if (MDB::isError($res)) {
                    if($res->getMessage() == '') {
                        if($this->autofree) {
                            $this->freeResult($result);
                        }
                        return NULL;
                    } else {
                        return $res;
                    }
                }
            }
            $array[$column] = $res;
        }
        if (isset($this->result_types[$result])) {
            if (!$this->convertResultRow($result, $array)) {
                return $this->raiseError();
            }
        }
        return (DB_OK);
    }

    // }}}
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     * renamed for PEAR
     * used to be fetchResultArray
     *
     * @param resource    $result     result identifier
     * @param array        $array         reference to an array where data from the row is stored
     * @param int        $fetchmode     how the array data should be indexed
     * @param int        $rownum        the row number to fetch
     * @access public
     *
     * @return int DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function fetchInto($result, &$array, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        return ($this->baseFetchInto($result, $array, $rownum));
    }

    // }}}
    // {{{ fetchOne()

    /**
     * Fetch and return a field of data (it uses fetchInto for that)
     * renamed for PEAR
     * used to be fetchResultField
     * 
     * @param resource    $result     result identifier
     * @param mixed        $value        reference to a variable where data from the field is stored
     * @param int        $fetchmode    how the array data should be indexed
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function fetchOne($result, &$value, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        if (MDB::isError($result)) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch field: it was not specified a valid result set');
        }
        if (MDB::isError($this->endOfResult($result))) {
            $res = $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch field: result set is empty');
        } else {
            $res = $this->fetchInto($result, $value, $fetchmode, NULL);
            $value = $value[0];
        }
        if(!$this->autofree || $value == NULL) {
            $this->freeResult($result);
        }
        return ($res);
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch and return a row of data (it uses fetchInto for that)
     * renamed for PEAR
     * used to be fetchResultRow
     * 
     * @param resource    $result     result identifier
     * @param array        $row         reference to an array where data from the row is stored
     * @param int        $fetchmode     how the array data should be indexed
     * @param int        $rownum        the row number to fetch
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function fetchRow($result, &$row, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        if (MDB::isError($result)) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch row: it was not specified a valid result set');
        }
        if (MDB::isError($this->endOfResult($result))) {
            $this->freeResult($result);
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch row: result set is empty');
        }
        $row = array();
        $res = $this->fetchInto($result, $row, $fetchmode, $rownum);
        if(!$this->autofree || $row == NULL) {
            $this->freeResult($result);
        }
        return ($res);
    }

    // }}}
    // {{{ fetchCol()

    /**
     * Fetch and return a column of data (it uses fetchInto for that)
     * renamed for PEAR
     * used to be fetchResultColumn
     * 
     * @param resource    $result     result identifier
     * @param array        $column        reference to an array where data from the row is stored
     * @param int        $fetchmode     how the array data should be indexed
     * @param int        $colnum        the row number to fetch
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function fetchCol($result, &$column, $fetchmode = DB_FETCHMODE_DEFAULT, $colnum = '0')
    {
        if (MDB::isError($result)) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch column: it was not specified a valid result set');
        }
        $temp = $column = array();
        while(DB_OK === $res = $this->fetchInto($result, $temp, $fetchmode, NULL)) {
            $column[] = $temp[$colnum];
        }
        if(!$this->autofree) {
            $this->freeResult($result);
        }
        if($res != NULL) {
            return ($res);
        }
        return (DB_OK);
    }

    // }}}
    // {{{ fetchAll()

    /**
     * Fetch and return a column of data (it uses fetchInto for that)
     * renamed for PEAR
     * used to be fetchResultAll
     * 
     * @param resource    $result     result identifier
     * @param array        $column        reference to an array where data from the row is stored
     * @param int        $fetchmode     how the array data should be indexed
     * @param boolean    $rekey     if  set to true the $all will have the first column as its first dimension
     * @param boolean    $force_array    used only when the query returns exactly two columns.  
     *                                 If true, the values of the returned array
     *                                 will be one-element arrays instead of scalars.
     * @param boolean    $group if true, the values of the returned array
     *                       is wrapped in another array.  If the same
     *                       key value (in the first column) repeats
     *                       itself, the values will be appended to
     *                       this array instead of overwriting the
     *                       existing values.
     *
     * @return mixed DB_OK on success, a DB error on failure
     * @see getAssoc()
     * 
     * @access public
     */
    function fetchAll($result, &$all, $fetchmode = DB_FETCHMODE_DEFAULT, $rekey = FALSE, $force_array = FALSE, $group = FALSE)
    {
        if (MDB::isError($result)) {
            return $this->raiseError(DB_ERROR_NEED_MORE_DATA, "", "", 
                    'Fetch All: it was not specified a valid result set');
        }
        if($rekey) {
            $cols = $this->numCols($result);
            if (MDB::isError($cols))
                return $cols;
            if ($cols < 2) {
                return $this->raiseError(DB_ERROR_TRUNCATED);
            }
        }
        $row = 0;
        $all = array();
        while (DB_OK === $res = $this->fetchInto($result, $array, $fetchmode, NULL)) {
            if ($rekey) {
                if ($fetchmode == DB_FETCHMODE_ASSOC) {
                    reset($array);
                    $key = current($array);
                    unset($array[key($array)]);
                } else {
                    $key = array_shift($array);
                }
                if (!$force_array && sizeof($array) == 1) {
                    $array = $array[0];
                }
                if ($group) {
                    $all[$key][$row] = $array;
                } else {
                    $all[$key] = $array;
                }
            } else {
                $all[$row] = $array;
            }
            $row++;
        }
        if(!$this->autofree) {
            $this->freeResult($result);
        }
        if($res != NULL) {
            return ($res);
        }
        return (DB_OK);
    }

    // }}}
    // {{{ queryOne()

    /**
     * Execute the specified query, fetch the value from the first column of the first
     * row of the result set into a given variable and then frees the result set.
     * 
     * @param string    $query    the SELECT query statement to be executed.
     * @param mixed     $field    reference to a variable into which the result set field is fetched.
     *                          If it is NULL this variable is unset.
     * @param string    $type   optional argument that specifies the expected datatype of the result
     *                          set field, so that an eventual conversion may be performed. The
     *                          default datatype is text, meaning that no conversion is performed
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function queryOne($query, &$field, $type = NULL)
    {
        if($type != NULL) {
            $types = array($type);
        }
        $result = $this->query($query, $types);
        if (MDB::isError($result)) {
            return $result;
        }
        return ($this->fetchOne($result, $field));
    }

    // }}}
    // {{{ queryRow()

    /**
     * Execute the specified query, fetch the value from the first column of the first
     * row of the result set into a given variable and then frees the result set.
     * 
     * @param string    $query    the SELECT query statement to be executed.
     * @param array        $row    reference to an array variable into which the result set row values
     *                             are fetched. The array positions are filled according to the position
     *                             of the result set columns starting from 0. Columns with NULL values
     *                             are not assigned.
     * @param array        $types    optional array argument that specifies a list of expected datatypes
     *                             of the result set columns, so that the eventual conversions may be
     *                             performed. The default list of datatypes is empty, meaning that no
     *                             conversion is performed.
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function queryRow($query, &$row, $types = NULL)
    {
        $result = $this->query($query, $types);
        if (MDB::isError($result)) {
            return $result;
        }
        return ($this->fetchRow($result, $row));
    }

    // }}}
    // {{{ queryCol()

    /**
     * Execute the specified query, fetch the value from the first column of the first
     * row of the result set into a given variable and then frees the result set.
     * 
     * @param string    $query    the SELECT query statement to be executed.
     * @param array     $column    reference to an array variable into which the result set column is
     *                             fetched. The rows on which the first column is NULL are ignored.
     *                             Therefore, do not rely on the count of entries of the array variable
     *                             to assume that it is the number of rows in the result set.
     * @param string    $type    optional argument that specifies the expected datatype of the result
     *                             set field, so that an eventual conversion may be performed. The
     *                             default datatype is text, meaning that no conversion is performed
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function queryCol($query, &$column, $type = NULL)
    {
        if($type != NULL) {
            $types = array($type);
        }
        $result = $this->query($query, $types);
        if (MDB::isError($result)) {
            return $result;
        }
        return ($this->fetchCol($result, $column));
    }

    // }}}
    // {{{ queryAll()

    /**
     * Execute the specified query, fetch the value from the first column of the first
     * row of the result set into a given variable and then frees the result set.
     * 
     * @param string    $query    the SELECT query statement to be executed.
     * @param array        $all    reference to a two dimension array variable into which the result
     *                             set rows and column values are fetched. The array positions are
     *                             filled according to the position of the result set columns and rows
     *                            starting from 0. Columns with NULL values are not assigned.
     * @param array        $types    optional array argument that specifies a list of expected datatypes
     *                             of the result set columns, so that the eventual conversions may be
     *                             performed. The default list of datatypes is empty, meaning that no
     *                             conversion is performed.
     *
     * @return mixed DB_OK on success, a DB error on failure
     * 
     * @access public
     */
    function queryAll($query, &$all, $types = NULL)
    {
        if (MDB::isError($result = $this->query($query, $types))) {
            return $result;
        }
        return ($this->fetchAll($result, $all));
    }

    // }}}
    // {{{ getOne()

    /**
     * Fetch the first column of the first row of data returned from
     * a query.  Takes care of doing the query and freeing the results
     * when finished.
     *
     * @param string $query the SQL query
     * 
     * @param string    $type string that contains the type of the column in the result set
     * 
     * @param array $params if supplied, prepare/execute will be used
     *        with this array as execute parameters
     * 
     * @param array    $param_types array that contains the types of the values defined in $params
     *
     * @return mixed DB_Error or the returned value of the query
     *
     * @access public
     */
    function &getOne($query, $type = NULL, $params = array(), $param_types = NULL)
    {
        if ($types != NULL) {
            $types = array($type);
        }
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepareQuery($query);
            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params, $param_types);
            $result = $this->executeQuery($prepared_query, $types);
        } else {
            $result = $this->query($query, $types);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchOne($result, $value, DB_FETCHMODE_ORDERED);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result)) {
                return $result;
            }
        }

        return $value;
    }

    // }}}
    // {{{ getRow()

    /**
     * Fetch the first row of data returned from a query.  Takes care
     * of doing the query and freeing the results when finished.
     *
     * @param string $query the SQL query
     * 
     * @param array    $types array that contains the types of the columns in the result set
     * 
     * @param array $params array if supplied, prepare/execute will be used
     *        with this array as execute parameters
     * 
     * @param array    $param_types array that contains the types of the values defined in $params
     * 
     * @param integer $fetchmode the fetch mode to use
     * 
     * @access public
     * @return array the first row of results as an array indexed from
     * 0, or a DB error code.
     */
    function &getRow($query, $types = NULL, $params = array(), $param_types = NULL, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        settype($params, 'array');
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepareQuery($query);
            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params, $param_types);
            $result = $this->executeQuery($prepared_query, $types);
        } else {
            $result = $this->query($query, $types);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchRow($result, $row, $fetchmode);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result)) {
                return $result;
            }
        }

        return $row;
    }

    // }}}
    // {{{ getCol()

    /**
     * Fetch a single column from a result set and return it as an
     * indexed array.
     *
     * @param string $query the SQL query
     * 
     * @param string    $type string that contains the type of the column in the result set
     * 
     * @param array $params array if supplied, prepare/execute will be used
     *        with this array as execute parameters
     * 
     * @param array    $param_types array that contains the types of the values defined in $params
     *
     * @param integer $fetchmode the fetch mode to use
     * 
     * @param mixed $colnum which column to return (integer [column number,
     * starting at 0] or string [column name])
     *
     * @access public
     *
     * @return array an indexed array with the data from the first
     * row at index 0, or a DB error code.
     */
    function &getCol($query, $type = NULL, $params = array(), $param_types = NULL, $fetchmode = DB_FETCHMODE_DEFAULT, $colnum = '0')
    {
        if ($type != NULl) {
            $types = array($type);
        }
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepareQuery($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params, $param_types);
            $result = $this->executeQuery($prepared_query, $types);
        } else {
            $result = $this->query($query, $types);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchCol($result, $col, $fetchmode, $colnum);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return $col;
    }

    // }}}
    // {{{ getAssoc()

    /**
     * Fetch the entire result set of a query and return it as an
     * associative array using the first column as the key.
     *
     * If the result set contains more than two columns, the value
     * will be an array of the values from column 2-n.  If the result
     * set contains only two columns, the returned value will be a
     * scalar with the value of the second column (unless forced to an
     * array with the $force_array parameter).  A DB error code is
     * returned on errors.  If the result set contains fewer than two
     * columns, a DB_ERROR_TRUNCATED error is returned.
     *
     * For example, if the table "mytable" contains:
     *
     *  ID      TEXT       DATE
     * --------------------------------
     *  1       'one'      944679408
     *  2       'two'      944679408
     *  3       'three'    944679408
     *
     * Then the call getAssoc('SELECT id,text FROM mytable') returns:
     *   array(
     *     '1' => 'one',
     *     '2' => 'two',
     *     '3' => 'three',
     *   )
     *
     * ...while the call getAssoc('SELECT id,text,date FROM mytable') returns:
     *   array(
     *     '1' => array('one', '944679408'),
     *     '2' => array('two', '944679408'),
     *     '3' => array('three', '944679408')
     *   )
     *
     * If the more than one row occurs with the same value in the
     * first column, the last row overwrites all previous ones by
     * default.  Use the $group parameter if you don't want to
     * overwrite like this.  Example:
     *
     * getAssoc('SELECT category,id,name FROM mytable', NULL, NULL
     *          DB_FETCHMODE_ASSOC, FALSE, TRUE) returns:
     *   array(
     *     '1' => array(array('id' => '4', 'name' => 'number four'),
     *                  array('id' => '6', 'name' => 'number six')
     *            ),
     *     '9' => array(array('id' => '4', 'name' => 'number four'),
     *                  array('id' => '6', 'name' => 'number six')
     *            )
     *   )
     *
     * Keep in mind that database functions in PHP usually return string
     * values for results regardless of the database's internal type.
     *
     * @param string $query the SQL query
     * 
     * @param array    $types array that contains the types of the columns in the result set
     *
     * @param array $params array if supplied, prepare/execute will be used
     *        with this array as execute parameters
     *
     * @param array    $param_types array that contains the types of the values defined in $params
     * 
     * @param boolean $force_array used only when the query returns
     * exactly two columns.  If true, the values of the returned array
     * will be one-element arrays instead of scalars.
     * 
     * @param boolean $group if true, the values of the returned array
     *                       is wrapped in another array.  If the same
     *                       key value (in the first column) repeats
     *                       itself, the values will be appended to
     *                       this array instead of overwriting the
     *                       existing values.
     *
     * @access public
     *
     * @return array associative array with results from the query.
     */
    function &getAssoc($query, $types = NULL, $params = array(), $param_types = NULL,
        $fetchmode = DB_FETCHMODE_ORDERED, $force_array = FALSE, $group = FALSE)
    {
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepareQuery($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params, $param_types);
            $result = $this->executeQuery($prepared_query, $types);
        } else {
            $result = $this->query($query, $types);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchAll($result, $all, $fetchmode, TRUE, $force_array, $group);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return $all;
    }

    // }}}
    // {{{ getAll()

    /**
     * Fetch all the rows returned from a query.
     *
     * @param string $query the SQL query
     * @param array    $types array that contains the types of the columns in the result set
     * @param array $params array if supplied, prepare/execute will be used
     *        with this array as execute parameters
     * @param array    $param_types array that contains the types of the values defined in $params
     * @param integer $fetchmode the fetch mode to use
     *
     * @access public
     * @return array an nested array, or a DB error
     */
    function &getAll($query, $types = NULL, $params = array(), $param_types = NULL, $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        settype($params, "array");
        if (sizeof($params) > 0) {
            $prepared_query = $this->prepareQuery($query);

            if (MDB::isError($prepared_query)) {
                return $prepared_query;
            }
            $this->querySetArray($prepared_query, $params, $param_types);
            $result = $this->executeQuery($prepared_query, $types);
        } else {
            $result = $this->query($query, $types);
        }

        if (MDB::isError($result)) {
            return $result;
        }
        
        $err = $this->fetchAll($result, $all, $fetchmode);
        if (MDB::isError($err)) {
            return $err;
        }
        if (isset($prepared_query)) {
            $result = $this->freePreparedQuery($prepared_query);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return $all;
    }

    // }}}
    // {{{ tableInfo()
    /**
    * returns meta data about the result set
    *
    * @param resource    $result    result identifier
    * @param mixed $mode depends on implementation
    *
    * @return array an nested array, or a DB error
    *
    * @access public
    */
    function tableInfo($result, $mode = NULL)
    {
        return $this->raiseError(DB_ERROR_NOT_CAPABLE);
    }
};

// Used by many drivers
if (!function_exists('array_change_key_case')) {
    define('CASE_UPPER', 1);
    define('CASE_LOWER', 0);
    function &array_change_key_case(&$array, $case) {
        $casefunc = ($case == CASE_LOWER) ? 'strtolower' : 'strtoupper';
        $ret = array();
        foreach ($array as $key => $value) {
            $ret[$casefunc($key)] = $value;
        }
        return $ret;
    }
}
?>
