<<?php
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

        if (!$this->options['autofree'] || $one != null) {
            $this->freeResult($result);
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

        if (!$this->options['autofree'] || $row != null) {
            $this->freeResult($result);
        }

        return $row;
    }

    // }}}
    // {{{ queryCol()

    /**
     * Execute the specified query, fetch the value from the first column of
     * each row of the result set into an array and then frees the result set.
     *
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

        if (!$this->options['autofree']) {
            $this->freeResult($result);
        }

        return $col;
    }

    // }}}
    // {{{ queryAll()

    /**
     * Execute the specified query, fetch all the rows of the result set into
     * a two dimensional array and then frees the result set.
     *
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

        if (!$this->options['autofree']) {
            $this->freeResult($result);
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
        if ($type != null) {
            $type = array($type);
        }
        settype($params, 'array');
        if (count($params) == 0) {
            return $this->queryOne($db, $query, $type);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $db->setParamArray($prepared_query, $params, $param_types);
        $result = $db->executeQuery($prepared_query, $type);
        if (MDB::isError($result)) {
            return $result;
        }

        $row = $db->fetchRow($result, MDB_FETCHMODE_ORDERED);

        $db->freePreparedQuery($prepared_query);

        if (!$this->options['autofree'] || $row != null) {
            $this->freeResult($result);
        }

        return $row[0];
    }

    // }}}
    // {{{ getRow()

    /**
     * Fetch the first row of data returned from a query.  Takes care
     * of doing the query and freeing the results when finished.
     *
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
    function getRow(&$db, $query, $types = null, $params = array(), $param_types = null, $fetchmode = MDB_FETCHMODE_DEFAULT)
    {
        settype($params, 'array');
        if (count($params) > 0) {
            return $this->queryRow($db, $query, $types, $fetchmode);
        }
        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $db->setParamArray($prepared_query, $params, $param_types);
        $result = $db->executeQuery($prepared_query, $types);
        if (MDB::isError($result)) {
            return $result;
        }

        $row = $db->fetchRow($result, $fetchmode);

        $db->freePreparedQuery($prepared_query);

        if (!$this->options['autofree'] || $row != null) {
            $this->freeResult($result);
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
    function getCol(&$db, $query, $type = null, $params = array(), $param_types = null, $colnum = 0)
    {
        if ($type != null) {
            $type = array($type);
        }
        settype($params, 'array');
        if (count($params) > 0) {
            $result = $this->queryCol($db, $query, $type, $colnum);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $db->setParamArray($prepared_query, $params, $param_types);
        $result = $db->executeQuery($prepared_query, $type);
        if (MDB::isError($result)) {
            return $result;
        }

        $col = $db->fetchCol($result, $colnum);

        $db->freePreparedQuery($prepared_query);

        if (!$this->options['autofree']) {
            $this->freeResult($result);
        }

        return $col;
    }

    // }}}
    // {{{ getAll()

    /**
     * Fetch all the rows returned from a query.
     *
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
    function getAll(&$db, $query, $types = null, $params = array(), $param_types = null, $fetchmode = MDB_FETCHMODE_DEFAULT,
        $rekey = false, $force_array = false, $group = false)
    {
        settype($params, 'array');
        if (count($params) > 0) {
            return $this->queryAll($db, $query, $types, $fetchmode, $rekey, $force_array, $group);
        }

        $prepared_query = $db->prepareQuery($query);
        if (MDB::isError($prepared_query)) {
            return $prepared_query;
        }

        $db->setParamArray($prepared_query, $params, $param_types);
        $result = $db->executeQuery($prepared_query, $types);
        if (MDB::isError($result)) {
            return $result;
        }

        $all = $db->fetchAll($result, $fetchmode, $rekey, $force_array, $group);

        $db->freePreparedQuery($prepared_query);

        if (!$this->options['autofree']) {
            $this->freeResult($result);
        }

        return $all;
    }
}
?>