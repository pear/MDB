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

/*
 * Used by autoPrepare()
 */
define('MDB_AUTOQUERY_INSERT', 1);
define('MDB_AUTOQUERY_UPDATE', 2);

/**
 * MDB_Extended: class which adds several high level methods to MDB
 *
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com>
 */
class MDB_Extended
{
    // }}}
    // {{{ queryOne()

    /**
     * Execute the specified query, fetch the value from the first column of
     * the first row of the result set and then frees
     * the result set.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SELECT query statement to be executed.
     * @param string $type optional argument that specifies the expected
     *       datatype of the result set field, so that an eventual conversion
     *       may be performed. The default datatype is text, meaning that no
     *       conversion is performed
     * @return mixed field value on success, a MDB error on failure
     * @access public
     */
    function queryOne(&$db, $query, $type = null)
    {
        if ($type != null) {
            $type = array($type);
        }
        $result = $db->query($query, $type);
        if (MDB::isError($result)) {
            return $result;
        }
        $one = $db->fetchOne($result);

        if (!$db->options['autofree'] || $one != null) {
            $db->freeResult($result);
        }

        return $one;
    }

    // }}}
    // {{{ queryRow()

    /**
     * Execute the specified query, fetch the values from the first
     * row of the result set into an array and then frees
     * the result set.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SELECT query statement to be executed.
     * @param array $types optional array argument that specifies a list of
     *       expected datatypes of the result set columns, so that the eventual
     *       conversions may be performed. The default list of datatypes is
     *       empty, meaning that no conversion is performed.
     * @param int $fetchmode how the array data should be indexed
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function queryRow(&$db, $query, $types = null, $fetchmode = MDB_FETCHMODE_DEFAULT)
    {
        $result = $db->query($query, $types);
        if (MDB::isError($result)) {
            return $result;
        }
        $row = $db->fetchRow($result, $fetchmode);

        if (!$db->options['autofree'] || $row != null) {
            $db->freeResult($result);
        }

        return $row;
    }

    // }}}
    // {{{ queryCol()

    /**
     * Execute the specified query, fetch the value from the first column of
     * each row of the result set into an array and then frees the result set.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SELECT query statement to be executed.
     * @param string $type optional argument that specifies the expected
     *       datatype of the result set field, so that an eventual conversion
     *       may be performed. The default datatype is text, meaning that no
     *       conversion is performed
     * @param int $colnum the row number to fetch
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function queryCol(&$db, $query, $type = null, $colnum = 0)
    {
        if ($type != null) {
            $type = array($type);
        }
        $result = $db->query($query, $type);
        if (MDB::isError($result)) {
            return $result;
        }
        $col = $db->fetchCol($result, $colnum);

        if (!$db->options['autofree']) {
            $db->freeResult($result);
        }

        return $col;
    }

    // }}}
    // {{{ queryAll()

    /**
     * Execute the specified query, fetch all the rows of the result set into
     * a two dimensional array and then frees the result set.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SELECT query statement to be executed.
     * @param array $types optional array argument that specifies a list of
     *       expected datatypes of the result set columns, so that the eventual
     *       conversions may be performed. The default list of datatypes is
     *       empty, meaning that no conversion is performed.
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
     */
    function queryAll(&$db, $query, $types = null, $fetchmode = MDB_FETCHMODE_DEFAULT,
        $rekey = false, $force_array = false, $group = false)
    {
        if (MDB::isError($result = $db->query($query, $types))) {
            return $result;
        }
        $all = $db->fetchAll($result, $fetchmode, $rekey, $force_array, $group);

        if (!$db->options['autofree']) {
            $db->freeResult($result);
        }

        return $all;
    }

    // }}}
    // {{{ getOne()

    /**
     * Fetch the first column of the first row of data returned from
     * a query.  Takes care of doing the query and freeing the results
     * when finished.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SQL query
     * @param string $type string that contains the type of the column in the
     *       result set
     * @param array $params if supplied, prepare/execute will be used
     *       with this array as execute parameters
     * @param array $param_types array that contains the types of the values
     *       defined in $params
     * @return mixed MDB_Error or the returned value of the query
     * @access public
     */
    function getOne(&$db, $query, $type = null, $params = array(), $param_types = null)
    {
        settype($params, 'array');
        if (count($params) == 0) {
            return MDB_Extended::queryOne($db, $query, $type);
        }

        if ($type != null) {
            $type = array($type);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $result = MDB_Extended::execute($db, $prepared_query, $type, $params, $param_types);
        if (MDB::isError($result)) {
            return $result;
        }

        $row = $db->fetchRow($result, MDB_FETCHMODE_ORDERED);

        $db->freePreparedQuery($prepared_query);

        if (!$db->options['autofree'] || $row != null) {
            $db->freeResult($result);
        }

        return $row[0];
    }

    // }}}
    // {{{ getRow()

    /**
     * Fetch the first row of data returned from a query.  Takes care
     * of doing the query and freeing the results when finished.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SQL query
     * @param array $types array that contains the types of the columns in
     *       the result set
     * @param array $params array if supplied, prepare/execute will be used
     *       with this array as execute parameters
     * @param array $param_types array that contains the types of the values
     *       defined in $params
     * @param integer $fetchmode the fetch mode to use
     * @return array the first row of results as an array indexed from
     * 0, or a MDB error code.
     * @access public
     */
    function getRow(&$db, $query, $types = null, $params = array(),
        $param_types = null, $fetchmode = MDB_FETCHMODE_DEFAULT)
    {
        settype($params, 'array');
        if (count($params) == 0) {
            return MDB_Extended::queryRow($db, $query, $types, $fetchmode);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $result = MDB_Extended::execute($db, $prepared_query, $types, $params, $param_types);
        if (MDB::isError($result)) {
            return $result;
        }

        $row = $db->fetchRow($result, $fetchmode);

        $db->freePreparedQuery($prepared_query);

        if (!$db->options['autofree'] || $row != null) {
            $db->freeResult($result);
        }

        return $row;
    }

    // }}}
    // {{{ getCol()

    /**
     * Fetch a single column from a result set and return it as an
     * indexed array.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SQL query
     * @param string $type string that contains the type of the column in the
     *       result set
     * @param array $params array if supplied, prepare/execute will be used
     *       with this array as execute parameters
     * @param array $param_types array that contains the types of the values
     *       defined in $params
     * @param mixed $colnum which column to return (integer [column number,
     *       starting at 0] or string [column name])
     * @return array an indexed array with the data from the first
     * row at index 0, or a MDB error code.
     * @access public
     */
    function getCol(&$db, $query, $type = null, $params = array(),
        $param_types = null, $colnum = 0)
    {
        if ($type != null) {
            $type = array($type);
        }
        settype($params, 'array');
        if (count($params) > 0) {
            $result = MDB_Extended::queryCol($db, $query, $type, $colnum);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $result = MDB_Extended::execute($db, $prepared_query, $type, $params, $param_types);
        if (MDB::isError($result)) {
            return $result;
        }

        $col = $db->fetchCol($result, $colnum);

        $db->freePreparedQuery($prepared_query);

        if (!$db->options['autofree']) {
            $db->freeResult($result);
        }

        return $col;
    }

    // }}}
    // {{{ getAll()

    /**
     * Fetch all the rows returned from a query.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $query the SQL query
     * @param array $types array that contains the types of the columns in
     *       the result set
     * @param array $params array if supplied, prepare/execute will be used
     *       with this array as execute parameters
     * @param array $param_types array that contains the types of the values
     *       defined in $params
     * @param integer $fetchmode the fetch mode to use
     * @param boolean $rekey if set to true, the $all will have the first
     *       column as its first dimension
     * @param boolean $force_array used only when the query returns exactly
     *       two columns. If true, the values of the returned array will be
     *       one-element arrays instead of scalars.
     * @param boolean $group if true, the values of the returned array is
     *       wrapped in another array.  If the same key value (in the first
     *       column) repeats itself, the values will be appended to this array
     *       instead of overwriting the existing values.
     * @return array an nested array, or a MDB error
     * @access public
     */
    function getAll(&$db, $query, $types = null, $params = array(),
        $param_types = null, $fetchmode = MDB_FETCHMODE_DEFAULT,
        $rekey = false, $force_array = false, $group = false)
    {
        settype($params, 'array');
        if (count($params) > 0) {
            return MDB_Extended::queryAll($db, $query, $types, $fetchmode, $rekey, $force_array, $group);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $result = MDB_Extended::execute($db, $prepared_query, $types, $params, $param_types);
        if (MDB::isError($result)) {
            return $result;
        }

        $all = $db->fetchAll($result, $fetchmode, $rekey, $force_array, $group);

        $db->freePreparedQuery($prepared_query);

        if (!$db->options['autofree']) {
            $db->freeResult($result);
        }

        return $all;
    }

    // }}}
    // {{{ execute()

    /**
     * Executes a prepared SQL query
     * With execute() the generic query of prepare is assigned with the given
     * data array. The values of the array inserted into the query in the same
     * order like the array order
     *
     * @param object    &$db reference to driver MDB object
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
    function execute(&$db, $prepared_query, $types = null, $params = false, $param_types = null)
    {
        $db->setParamArray($prepared_query, $params, $param_types);

        return $db->executeQuery($prepared_query, $types);
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
     * @param object    &$db reference to driver MDB object
     * @param resource $prepared_query query handle from prepareQuery()
     * @param array $types array that contains the types of the columns in
     *        the result set
     * @param array $params numeric array containing the
     *        data to insert into the query
     * @param array $param_types array that contains the types of the values
     *        defined in $params
     * @return mixed a result handle or MDB_OK on success, a MDB error on failure
     * @access public
     * @see prepareQuery(), execute()
     */
    function executeMultiple(&$db, $prepared_query, $types = null, $params, $param_types = null)
    {
        for($i = 0, $j = count($params); $i < $j; $i++) {
            $result = MDB_Extended::execute($db, $prepared_query, $types, $params[$i], $param_types);
            if (MDB::isError($result)) {
                return $result;
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ autoPrepare()

    /**
     * Make automaticaly an insert or update query and call prepareQuery() with it
     *
     * @param object    &$db reference to driver MDB object
     * @param string $table name of the table
     * @param array $table_fields ordered array containing the fields names
     * @param int $mode type of query to make (MDB_AUTOQUERY_INSERT or MDB_AUTOQUERY_UPDATE)
     * @param string $where in case of update queries, this string will be put after the sql WHERE statement
     * @return resource handle for the query
     * @see buildManipSQL
     * @access public
     */
    function autoPrepare(&$db, $table, $table_fields, $mode = MDB_AUTOQUERY_INSERT, $where = false)
    {
        $query = MDB_Extended::buildManipSQL($db, $table, $table_fields, $mode, $where);
        return $db->prepareQuery($query);
    }

    // {{{
    // }}} autoExecute()

    /**
     * Make automaticaly an insert or update query and call prepareQuery() and execute() with it
     *
     * @param object    &$db reference to driver MDB object
     * @param string $table name of the table
     * @param array $fields_values assoc ($key=>$value) where $key is a field name and $value its value
     * @param array $types array that contains the types of the columns in
     *        the result set
     * @param int $mode type of query to make (MDB_AUTOQUERY_INSERT or MDB_AUTOQUERY_UPDATE)
     * @param array $param_types array that contains the types of the values
     *        defined in $params
     * @param string $where in case of update queries, this string will be put after the sql WHERE statement
     * @return mixed  a new MDB_Result or a MDB_Error when fail
     * @see buildManipSQL
     * @see autoPrepare
     * @access public
    */
    function autoExecute(&$db, $table, $fields_values,
        $types = null, $param_types = null, $mode = MDB_AUTOQUERY_INSERT, $where = false)
    {
        $prepared_query = MDB_Extended::autoPrepare($db, $table, array_keys($fields_values), $mode, $where);
        $ret = MDB_Extended::execute($db, $prepared_query, $types, array_values($fields_values), $param_types);
        $db->freePreparedQuery($sth);
        return $ret;
    }

    // {{{
    // }}} buildManipSQL()

    /**
     * Make automaticaly an sql query for prepareQuery()
     *
     * Example : buildManipSQL('table_sql', array('field1', 'field2', 'field3'), MDB_AUTOQUERY_INSERT)
     *           will return the string : INSERT INTO table_sql (field1,field2,field3) VALUES (?,?,?)
     * NB : - This belongs more to a SQL Builder class, but this is a simple facility
     *      - Be carefull ! If you don't give a $where param with an UPDATE query, all
     *        the records of the table will be updated !
     *
     * @param object    &$db reference to driver MDB object
     * @param string $table name of the table
     * @param array $table_fields ordered array containing the fields names
     * @param int $mode type of query to make (MDB_AUTOQUERY_INSERT or MDB_AUTOQUERY_UPDATE)
     * @param string $where in case of update queries, this string will be put after the sql WHERE statement
     * @return string sql query for prepareQuery()
     * @access public
     */
    function buildManipSQL(&$db, $table, $table_fields, $mode, $where = false)
    {
        if (count($table_fields) == 0) {
            $db->raiseError(MDB_ERROR_NEED_MORE_DATA);
        }
        $first = true;
        switch ($mode) {
            case MDB_AUTOQUERY_INSERT:
                $values = '';
                $names = '';
                while (list(, $value) = each($table_fields)) {
                    if ($first) {
                        $first = false;
                    } else {
                        $names .= ',';
                        $values .= ',';
                    }
                    $names .= $value;
                    $values .= '?';
                }
                return "INSERT INTO $table ($names) VALUES ($values)";
                break;
            case MDB_AUTOQUERY_UPDATE:
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
                if ($where) {
                    $sql .= " WHERE $where";
                }
                return $sql;
                break;
            default:
                $db->raiseError(MDB_ERROR_SYNTAX);
        }
    }
}
?>