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
// | Author: Lukas Smith <smith@dybnet.de>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
 * The main 'MDB' class is simply a container class with some static
 * methods for creating DB objects as well as some utility functions
 * common to all parts of DB.
 *
 * The object model of DB is as follows (indentation means inheritance):
 *
 * MDB           The main DB class.  This is simply a utility class
 *               with some 'static' methods for creating DB objects as
 *               well as common utility functions for other DB classes.
 *
 * MDB_common    The base for each DB implementation.  Provides default
 * |             implementations (in OO lingo virtual methods) for
 * |             the actual DB implementations as well as a bunch of
 * |             query utility functions.
 * |
 * +-MDB_driver_mysql   The DB implementation for MySQL.  Inherits MDB_Common.
 *               When calling MDB::factory or MDB::connect for MySQL
 *               connections, the object returned is an instance of this
 *               class.
 *
 * @package  MDB
 * @author   Lukas Smith <smith@dybnet.de>
 */

require_once 'PEAR.php';

/**
 * The method mapErrorCode in each DB_dbtype implementation maps
 * native error codes to one of these.
 *
 * If you add an error code here, make sure you also add a textual
 * version of it in MDB::errorMessage().
 */

define('DB_OK',                         1);
define('DB_ERROR',                     -1);
define('DB_ERROR_SYNTAX',              -2);
define('DB_ERROR_CONSTRAINT',          -3);
define('DB_ERROR_NOT_FOUND',           -4);
define('DB_ERROR_ALREADY_EXISTS',      -5);
define('DB_ERROR_UNSUPPORTED',         -6);
define('DB_ERROR_MISMATCH',            -7);
define('DB_ERROR_INVALID',             -8);
define('DB_ERROR_NOT_CAPABLE',         -9);
define('DB_ERROR_TRUNCATED',          -10);
define('DB_ERROR_INVALID_NUMBER',     -11);
define('DB_ERROR_INVALID_DATE',       -12);
define('DB_ERROR_DIVZERO',            -13);
define('DB_ERROR_NODBSELECTED',       -14);
define('DB_ERROR_CANNOT_CREATE',      -15);
define('DB_ERROR_CANNOT_DELETE',      -16);
define('DB_ERROR_CANNOT_DROP',        -17);
define('DB_ERROR_NOSUCHTABLE',        -18);
define('DB_ERROR_NOSUCHFIELD',        -19);
define('DB_ERROR_NEED_MORE_DATA',     -20);
define('DB_ERROR_NOT_LOCKED',         -21);
define('DB_ERROR_VALUE_COUNT_ON_ROW', -22);
define('DB_ERROR_INVALID_DSN',        -23);
define('DB_ERROR_CONNECT_FAILED',     -24);
define('DB_ERROR_EXTENSION_NOT_FOUND',-25);
define('DB_ERROR_NOSUCHDB',           -26);
define('DB_ERROR_ACCESS_VIOLATION',   -27);
define('DB_ERROR_CANNOT_REPLACE',     -28);
define('DB_ERROR_CANNOT_ALTER',       -29);
define('DB_ERROR_MANAGER',            -30);
define('DB_ERROR_MANAGER_PARSE',      -31);
define('DB_ERROR_LOADEXTENSION',      -32);

/**
 * These constants are used when storing information about prepared
 * statements (using the 'prepare' method in DB_dbtype).
 *
 * The prepare/execute model in DB is mostly borrowed from the ODBC
 * extension, in a query the '?' character means a scalar parameter.
 * There are two extensions though, a "&" character means an opaque
 * parameter.  An opaque parameter is simply a file name, the real
 * data are in that file (useful for putting uploaded files into your
 * database and such). The "!" char means a parameter that must be
 * left as it is.
 * They modify the quote behavior:
 * DB_PARAM_SCALAR (?) => 'original string quoted'
 * DB_PARAM_OPAQUE (&) => 'string from file quoted'
 * DB_PARAM_MISC   (!) => original string
 */

define('DB_PARAM_SCALAR', 1);
define('DB_PARAM_OPAQUE', 2);
define('DB_PARAM_MISC',   3);

/**
 * These constants define different ways of returning binary data
 * from queries.  Again, this model has been borrowed from the ODBC
 * extension.
 *
 * DB_BINMODE_PASSTHRU  sends the data directly through to the browser
 *                      when data is fetched from the database.
 *
 * DB_BINMODE_RETURN    lets you return data as usual.
 *
 * DB_BINMODE_CONVERT   returns data as well, only it is converted to
 *                      hex format, for example the string '123'
 *                      would become '313233'.
 */

define('DB_BINMODE_PASSTHRU', 1);
define('DB_BINMODE_RETURN',   2);
define('DB_BINMODE_CONVERT',  3);

/**
 * This is a special constant that tells DB the user hasn't specified
 * any particular get mode, so the default should be used.
 */

define('DB_FETCHMODE_DEFAULT',  0);

/**
 * Column data indexed by numbers, ordered from 0 and up
 */

define('DB_FETCHMODE_ORDERED',  1);

/**
 * Column data indexed by column names
 */

define('DB_FETCHMODE_ASSOC',    2);

/**
 * Column data as object properties
 */

define('DB_FETCHMODE_OBJECT',   3);

/**
 * For multi-dimensional results: normally the first level of arrays
 * is the row number, and the second level indexed by column number or name.
 * DB_FETCHMODE_FLIPPED switches this order, so the first level of arrays
 * is the column name, and the second level the row number.
 */

define('DB_FETCHMODE_FLIPPED',  4);

/* for compatibility */

define('DB_GETMODE_ORDERED', DB_FETCHMODE_ORDERED);
define('DB_GETMODE_ASSOC',   DB_FETCHMODE_ASSOC);
define('DB_GETMODE_FLIPPED', DB_FETCHMODE_FLIPPED);

/**
 * These are constants for the tableInfo-function
 * they are bitwised or'ed. so if there are more constants to be defined
 * in the future, adjust DB_TABLEINFO_FULL accordingly
 */

define('DB_TABLEINFO_ORDER',      1);
define('DB_TABLEINFO_ORDERTABLE', 2);
define('DB_TABLEINFO_FULL',       3);

/*
 * Used by autoPrepare()
 */
define('DB_AUTOQUERY_INSERT', 1);
define('DB_AUTOQUERY_UPDATE', 2);

class MDB
{
    /**
     * Create a new DB connection object for the specified database
     * type
     *
     * @access  public
     *
     * @param   string  $type   database type, for example 'mysql'
     *
     * @return  mixed   a newly created MDB object, or a MDB error code on error
     */
    function &factory($type)
    {
        @include_once("${type}.php");

        $classname = "MDB_driver_${type}";

        if (!class_exists($classname)) {
            return PEAR::raiseError(NULL, DB_ERROR_NOT_FOUND,
                                    NULL, NULL, NULL, 'MDB_Error', TRUE);
        }
        $db =& new $class_name($dsninfo, $options);
        return $db;
    }

    /**
     * Create a new MDB connection object and connect to the specified
     * database
     *
     * IMPORTANT: In order for MDB to work properly it is necessary that
     * you make sure that you work with a reference of the original
     * object instead of a copy (this is a PHP4 quirk).
     *
     * For example:
     *     $mdb =& MDB::connect($dsn);
     *          ^^
     * And not:
     *     $mdb = MDB::connect($dsn);
     *          ^^
     *
     * @access  public
     *
     * @param   mixed   $dsn      'data source name', see the MDB::parseDSN
     *                            method for a description of the dsn format.
     *                            Can also be specified as an array of the
     *                            format returned by MDB::parseDSN.
     *
     * @param   mixed   $options  An associative array of option names and
     *                            their values.
     *
     * @return  mixed   a newly created MDB connection object, or a MDB
     *                  error object on error
     *
     * @see     MDB::parseDSN
     */
    function &connect($dsn, $options = FALSE)
    {
        if (is_array($dsn)) {
            $dsninfo = $dsn;
        } else {
            $dsninfo = MDB::parseDSN($dsn);
        }

        switch(isset($dsninfo['phptype']) ? $dsninfo['phptype'] : '') {
            case 'mysql';
                $include    = 'mysql.php';
                $class_name = 'MDB_driver_mysql';
                $included   = 'MYSQL_INCLUDED';
                break;
            case 'pgsql';
                $include    = 'pgsql.php';
                $class_name = 'MDB_driver_pgsql';
                $included   = 'PGSQL_INCLUDED';
                break;
            default:
                $included = (isset($options['includedconstant']) ?
                                   $options['includedconstant'] : '');
                if (!isset($options['include'])
                    || !strcmp($include = $options['includepath'],''))
                {
                    if (isset($options['includepath'])) {
                        return PEAR::raiseError(NULL, DB_ERROR_INVALID_DSN,
                            NULL, NULL,
                            'no valid DBMS driver include path specified',
                            'MDB_Error', TRUE);
                    } else {
                        return PEAR::raiseError(NULL, DB_ERROR_INVALID_DSN,
                            NULL, NULL, 'no existing DBMS driver specified',
                            'MDB_Error', TRUE);
                    }
                }
                if (!isset($options['classname'])
                    || !strcmp($class_name = $options['classname'],''))
                {
                    return PEAR::raiseError(NULL, DB_ERROR_INVALID_DSN,
                        NULL, NULL, 'no existing DBMS driver specified',
                        'MDB_Error', TRUE);
                }
        }
        $include_path = (isset($options['includepath']) ?
                               $options['includepath'] : dirname(__FILE__));
        if (!strcmp($included,'') || !defined($included))
        {
            $length = strlen($include_path);
            $separator = '';
            if($length) {
                $directory_separator = (defined('DIRECTORY_SEPARATOR') ?
                                                DIRECTORY_SEPARATOR : '/');
                if ($include_path[$length-1] != $directory_separator)
                    $separator = $directory_separator;
            }

            if(!file_exists($include_path.$separator.$include)) {
                $directory = 0;
                if (!strcmp($include_path,'')
                    || ($directory = @opendir($include_path)))
                {
                    if ($directory) {
                        closedir($directory);
                    }
                    return PEAR::raiseError(NULL, DB_ERROR_INVALID_DSN,
                        NULL, NULL, 'no existing DBMS driver specified',
                        'MDB_Error', TRUE);
                } else {
                    return PEAR::raiseError(NULL, DB_ERROR_INVALID_DSN,
                        NULL, NULL, 'no valid DBMS driver include path specified'
                        , 'MDB_Error', TRUE);
                }
            }
            @include_once($include_path.$separator.$include);
        }
        if (!class_exists($class_name)) {
            return PEAR::raiseError(NULL, DB_ERROR_NOT_FOUND,
                NULL, NULL, NULL, 'MDB_Error', TRUE);
        }
        $db =& new $class_name($dsninfo, $options);
        return $db;
    }

    /**
     * Return the MDB API version
     *
     * @access public
     * @return int     the MDB API version number
     */
    function apiVersion()
    {
        return 3;
    }

    /**
     * Tell whether a result code from a MDB method is an error
     *
     * @access public
     *
     * @param   int       $value  result code
     *
     * @return  boolean   whether $value is an MDB_Error
     */
    function isError($value)
    {
        return (is_object($value) &&
                (get_class($value) == 'mdb_error' ||
                 is_subclass_of($value, 'mdb_error')));
    }

    /**
     * Tell whether a query is a data manipulation query (insert,
     * update or delete) or a data definition query (create, drop,
     * alter, grant, revoke).
     *
     * @access  public
     *
     * @param   string   $query the query
     *
     * @return  boolean  whether $query is a data manipulation query
     */
    function isManip($query)
    {
        $manips = 'INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|
                  ALTER|GRANT|REVOKE|LOCK|UNLOCK|ROLLBACK|COMMIT';
        if (preg_match('/^\s*"?('.$manips.')\s+/i', $query)) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Return a textual error message for a MDB error code
     *
     * @param   int     $value error code
     *
     * @return  string  error message, or false if the error code was
     *                  not recognized
     */
    function errorMessage($value)
    {
        static $errorMessages;
        if (!isset($errorMessages)) {
            $errorMessages = array(
                DB_ERROR                    => 'unknown error',
                DB_ERROR_ALREADY_EXISTS     => 'already exists',
                DB_ERROR_CANNOT_CREATE      => 'can not create',
                DB_ERROR_CANNOT_ALTER       => 'can not alter',
                DB_ERROR_CANNOT_REPLACE     => 'can not replace',
                DB_ERROR_CANNOT_DELETE      => 'can not delete',
                DB_ERROR_CANNOT_DROP        => 'can not drop',
                DB_ERROR_CONSTRAINT         => 'constraint violation',
                DB_ERROR_DIVZERO            => 'division by zero',
                DB_ERROR_INVALID            => 'invalid',
                DB_ERROR_INVALID_DATE       => 'invalid date or time',
                DB_ERROR_INVALID_NUMBER     => 'invalid number',
                DB_ERROR_MISMATCH           => 'mismatch',
                DB_ERROR_NODBSELECTED       => 'no database selected',
                DB_ERROR_NOSUCHFIELD        => 'no such field',
                DB_ERROR_NOSUCHTABLE        => 'no such table',
                DB_ERROR_NOT_CAPABLE        => 'DB backend not capable',
                DB_ERROR_NOT_FOUND          => 'not found',
                DB_ERROR_NOT_LOCKED         => 'not locked',
                DB_ERROR_SYNTAX             => 'syntax error',
                DB_ERROR_UNSUPPORTED        => 'not supported',
                DB_ERROR_VALUE_COUNT_ON_ROW => 'value count on row',
                DB_ERROR_INVALID_DSN        => 'invalid DSN',
                DB_ERROR_CONNECT_FAILED     => 'connect failed',
                DB_OK                       => 'no error',
                DB_ERROR_NEED_MORE_DATA     => 'insufficient data supplied',
                DB_ERROR_EXTENSION_NOT_FOUND=> 'extension not found',
                DB_ERROR_NOSUCHDB           => 'no such database',
                DB_ERROR_ACCESS_VIOLATION   => 'insufficient permissions',
                DB_ERROR_MANAGER            => 'MDB_manager error',
                DB_ERROR_MANAGER_PARSE      => 'MDB_manager schema parse error',
                DB_ERROR_LOADEXTENSION      => 'Error while including on demand extension'
            );
        }

        if (MDB::isError($value)) {
            $value = $value->getCode();
        }

        return isset($errorMessages[$value]) ?
                     $errorMessages[$value] : $errorMessages[DB_ERROR];
    }

    /**
     * Parse a data source name
     *
     * A array with the following keys will be returned:
     *  phptype: Database backend used in PHP (mysql, odbc etc.)
     *  dbsyntax: Database used with regards to SQL syntax etc.
     *  protocol: Communication protocol to use (tcp, unix etc.)
     *  hostspec: Host specification (hostname[:port])
     *  database: Database to use on the DBMS server
     *  username: User name for login
     *  password: Password for login
     *
     * The format of the supplied DSN is in its fullest form:
     *
     *  phptype(dbsyntax)://username:password@protocol+hostspec/database
     *
     * Most variations are allowed:
     *
     *  phptype://username:password@protocol+hostspec:110//usr/db_file.db
     *  phptype://username:password@hostspec/database_name
     *  phptype://username:password@hostspec
     *  phptype://username@hostspec
     *  phptype://hostspec/database
     *  phptype://hostspec
     *  phptype(dbsyntax)
     *  phptype
     *
     * @param   string  $dsn Data Source Name to be parsed
     *
     * @return  array   an associative array
     *
     * @author Tomas V.V.Cox <cox@idecnet.com>
     */
    function parseDSN($dsn)
    {
        if (is_array($dsn)) {
            return $dsn;
        }

        $parsed = array(
            'phptype'  => FALSE,
            'dbsyntax' => FALSE,
            'username' => FALSE,
            'password' => FALSE,
            'protocol' => FALSE,
            'hostspec' => FALSE,
            'port'     => FALSE,
            'socket'   => FALSE,
            'database' => FALSE
        );

        // Find phptype and dbsyntax
        if (($pos = strpos($dsn, '://')) !== FALSE) {
            $str = substr($dsn, 0, $pos);
            $dsn = substr($dsn, $pos + 3);
        } else {
            $str = $dsn;
            $dsn = NULL;
        }

        // Get phptype and dbsyntax
        // $str => phptype(dbsyntax)
        if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
            $parsed['phptype']  = $arr[1];
            $parsed['dbsyntax'] = (empty($arr[2])) ? $arr[1] : $arr[2];
        } else {
            $parsed['phptype']  = $str;
            $parsed['dbsyntax'] = $str;
        }

        if (empty($dsn)) {
            return $parsed;
        }

        // Get (if found): username and password
        // $dsn => username:password@protocol+hostspec/database
        if (($at = strrpos($dsn,'@')) !== FALSE) {
            $str = substr($dsn, 0, $at);
            $dsn = substr($dsn, $at + 1);
            if (($pos = strpos($str, ':')) !== FALSE) {
                $parsed['username'] = urldecode(substr($str, 0, $pos));
                $parsed['password'] = urldecode(substr($str, $pos + 1));
            } else {
                $parsed['username'] = urldecode($str);
            }
        }

        // Find protocol and hostspec

        // $dsn => proto(proto_opts)/database
        if (preg_match('|^([^(]+)\((.*?)\)/?(.*?)$|', $dsn, $match)) {
            $proto       = $match[1];
            $proto_opts  = (!empty($match[2])) ? $match[2] : FALSE;
            $dsn         = $match[3];

        // $dsn => protocol+hostspec/database (old format)
        } else {
            if (strpos($dsn, '+') !== FALSE) {
                list($proto, $dsn) = explode('+', $dsn, 2);
            }
            if (strpos($dsn, '/') !== FALSE) {
                list($proto_opts, $dsn) = explode('/', $dsn, 2);
            } else {
                $proto_opts = $dsn;
                $dsn = NULL;
            }
        }

        // process the different protocol options
        $parsed['protocol'] = (!empty($proto)) ? $proto : 'tcp';
        $proto_opts = urldecode($proto_opts);
        if ($parsed['protocol'] == 'tcp') {
            if (strpos($proto_opts, ':') !== FALSE) {
                list($parsed['hostspec'], $parsed['port']) =
                                                     explode(':', $proto_opts);
            } else {
                $parsed['hostspec'] = $proto_opts;
            }
        } elseif ($parsed['protocol'] == 'unix') {
            $parsed['socket'] = $proto_opts;
        }

        // Get dabase if any
        // $dsn => database
        if (!empty($dsn)) {
            // /database
            if (($pos = strpos($dsn, '?')) === FALSE) {
                $parsed['database'] = $dsn;
            // /database?param1=value1&param2=value2
            } else {
                $parsed['database'] = substr($dsn, 0, $pos);
                $dsn = substr($dsn, $pos + 1);
                if (strpos($dsn, '&') !== FALSE) {
                    $opts = explode('&', $dsn);
                } else { // database?param1=value1
                    $opts = array($dsn);
                }
                foreach ($opts as $opt) {
                    list($key, $value) = explode('=', $opt);
                    if (!isset($parsed[$key])) { // don't allow params overwrite
                        $parsed[$key] = urldecode($value);
                    }
                }
            }
        }

        return $parsed;
    }

    /**
     * Load a PHP database extension if it is not loaded already.
     *
     * @access  public
     *
     * @param   string  $name the base name of the extension (without the .so
     *                        or .dll suffix)
     *
     * @return  boolean true if the extension was already or successfully
     *                  loaded, false if it could not be loaded
     */
    function assertExtension($name)
    {
        if (!extension_loaded($name)) {
            $dlext = OS_WINDOWS ? '.dll' : '.so';
            @dl($name . $dlext);
        }
        return extension_loaded($name);
    }
}

/**
 * MDB_Error implements a class for reporting portable database error
 * messages.
 *
 * @package MDB
 * @author  Stig Bakken <ssb@fast.no>
 */
class MDB_Error extends PEAR_Error
{
    /**
     * DB_Error constructor.
     *
     * @param mixed   $code      MDB error code, or string with error message.
     * @param integer $mode      what "error mode" to operate in
     * @param integer $level     what error level to use for
     *                           $mode & PEAR_ERROR_TRIGGER
     * @param smixed  $debuginfo additional debug info, such as the last query
     */

    function MDB_Error($code = MDB_ERROR, $mode = PEAR_ERROR_RETURN,
              $level = E_USER_NOTICE, $debuginfo = NULL)
    {
        if (is_int($code)) {
            $this->PEAR_Error('DB Error: ' . MDB::errorMessage($code), $code,
                              $mode, $level, $debuginfo);
        } else {
            $this->PEAR_Error("DB Error: $code", DB_ERROR, $mode, $level,
                              $debuginfo);
        }
    }
}
?>