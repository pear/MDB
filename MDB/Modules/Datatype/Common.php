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

define('MDB_TYPE_TEXT'      , 0);
define('MDB_TYPE_BOOLEAN'   , 1);
define('MDB_TYPE_INTEGER'   , 2);
define('MDB_TYPE_DECIMAL'   , 3);
define('MDB_TYPE_FLOAT'     , 4);
define('MDB_TYPE_DATE'      , 5);
define('MDB_TYPE_TIME'      , 6);
define('MDB_TYPE_TIMESTAMP' , 7);
define('MDB_TYPE_CLOB'      , 8);
define('MDB_TYPE_BLOB'      , 9);

/**
 * MDB_Common: Base class that is extended by each MDB driver
 *
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com>
 */
class MDB_Datatype_Common
{
    // }}}
    // {{{ setParamText()

    /**
     * Set a parameter of a prepared query with a text value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value text value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamText(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'text', $this->getTextValue($value));
    }

    // }}}
    // {{{ setParamClob()

    /**
     * Set a parameter of a prepared query with a character large object value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param int $value handle of large object created with createLOB()
     *       function from which it will be read the data value that is meant
     *       to be assigned to specified parameter.
     * @param string $field name of the field of a INSERT or UPDATE query to
     *       which it will be assigned the value to specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamClob(&$db, $prepared_query, $parameter, $value, $field)
    {
        return $this->setParam($prepared_query, $parameter, 'clob', $value, 0, $field);
    }

    // }}}
    // {{{ setParamBlob()

    /**
     * Set a parameter of a prepared query with a binary large object value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param int $value handle of large object created with createLOB()
     *       function from which it will be read the data value that is meant
     *       to be assigned to specified parameter.
     * @param string $field name of the field of a INSERT or UPDATE query to
     *       which it will be assigned the value to specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamBlob(&$db, $prepared_query, $parameter, $value, $field)
    {
        return $this->setParam($prepared_query, $parameter, 'blob', $value, 0, $field);
    }

    // }}}
    // {{{ setParamInteger()

    /**
     * Set a parameter of a prepared query with a text value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param int $value an integer value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamInteger(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'integer', $this->getIntegerValue($value));
    }

    // }}}
    // {{{ setParamBoolean()

    /**
     * Set a parameter of a prepared query with a boolean value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param boolean $value boolean value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamBoolean(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'boolean', $this->getBooleanValue($value));
    }

    // }}}
    // {{{ setParamDate()

    /**
     * Set a parameter of a prepared query with a date value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value date value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamDate(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'date', $this->getDateValue($value));
    }

    // }}}
    // {{{ setParamTimestamp()

    /**
     * Set a parameter of a prepared query with a time stamp value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value time stamp value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamTimestamp(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'timestamp', $this->getTimestampValue($value));
    }

    // }}}
    // {{{ setParamTime()

    /**
     * Set a parameter of a prepared query with a time value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value time value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamTime(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'time', $this->getTimeValue($value));
    }

    // }}}
    // {{{ setParamFloat()

    /**
     * Set a parameter of a prepared query with a float value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value float value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamFloat(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'float', $this->getFloatValue($value));
    }

    // }}}
    // {{{ setParamDecimal()

    /**
     * Set a parameter of a prepared query with a decimal value.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $prepared_query argument is a handle that was returned by
     *       the function prepareQuery()
     * @param int $parameter order number of the parameter in the query
     *       statement. The order number of the first parameter is 1.
     * @param string $value decimal value that is meant to be assigned to
     *       specified parameter.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     * @see setParam()
     */
    function setParamDecimal(&$db, $prepared_query, $parameter, $value)
    {
        return $this->setParam($prepared_query, $parameter, 'decimal', $this->getDecimalValue($value));
    }

    // }}}
    // {{{ setResultTypes()

    /**
     * Define the list of types to be associated with the columns of a given
     * result set.
     *
     * This function may be called before invoking fetchInto(), fetchOne(),
     * fetchRow(), fetchCol() and fetchAll() so that the necessary data type
     * conversions are performed on the data to be retrieved by them. If this
     * function is not called, the type of all result set columns is assumed
     * to be text, thus leading to not perform any conversions.
     *
     * @param object    &$db reference to driver MDB object
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
    function setResultTypes(&$db, $result, $types)
    {
        $result_value = intval($result);
        if (isset($db->results[$result_value]['types'])) {
            return $db->raiseError(MDB_ERROR_INVALID, null, null,
                'Set result types: attempted to redefine the types of the columns of a result set');
        }
        $columns = $db->numCols($result);
        if (MDB::isError($columns)) {
            return $columns;
        }
        if ($columns < count($types)) {
            return $db->raiseError(MDB_ERROR_SYNTAX, null, null,
                'Set result types: it were specified more result types (' . count($types) . ') than result columns (' . $columns . ')');
        }
        $valid_types = array(
            'text'      => MDB_TYPE_TEXT,
            'boolean'   => MDB_TYPE_BOOLEAN,
            'integer'   => MDB_TYPE_INTEGER,
            'decimal'   => MDB_TYPE_DECIMAL,
            'float'     => MDB_TYPE_FLOAT,
            'date'      => MDB_TYPE_DATE,
            'time'      => MDB_TYPE_TIME,
            'timestamp' => MDB_TYPE_TIMESTAMP,
            'clob'      => MDB_TYPE_CLOB,
            'blob'      => MDB_TYPE_BLOB
        );
        for($column = 0; $column < count($types); $column++) {
            if (!isset($valid_types[$types[$column]])) {
                return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                    'Set result types: ' . $types[$column] . ' is not a supported column type');
            }
            $db->results[$result_value]['types'][$column] = $valid_types[$types[$column]];
        }
        while ($column < $columns) {
            $db->results[$result_value]['types'][$column] = MDB_TYPE_TEXT;
            $column++;
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _baseConvertResult()

    /**
     * general type conversion method
     *
     * @param object    &$db reference to driver MDB object
     * @param mixed $value refernce to a value to be converted
     * @param int $type constant that specifies which type to convert to
     * @return object a MDB error on failure
     * @access private
     */
    function _baseConvertResult(&$db, $value, $type)
    {
        switch ($type) {
            case MDB_TYPE_TEXT:
                return $value;
            case MDB_TYPE_BLOB:
                return $value;
            case MDB_TYPE_CLOB:
                return $value;
            case MDB_TYPE_INTEGER:
                return intval($value);
            case MDB_TYPE_BOOLEAN:
                return strcmp($value, 'Y') ? false : true;
            case MDB_TYPE_DECIMAL:
                return $value;
            case MDB_TYPE_FLOAT:
                return doubleval($value);
            case MDB_TYPE_DATE:
                return $value;
            case MDB_TYPE_TIME:
                return $value;
            case MDB_TYPE_TIMESTAMP:
                return $value;
            case MDB_TYPE_CLOB:
                return $value;
            case MDB_TYPE_BLOB:
                return $db->raiseError(MDB_ERROR_INVALID, null, null,
                    'BaseConvertResult: attempt to convert result value to an unsupported type ' . $type);
            default:
                return $db->raiseError(MDB_ERROR_INVALID, null, null,
                    'BaseConvertResult: attempt to convert result value to an unknown type ' . $type);
        }
    }

    // }}}
    // {{{ convertResult()

    /**
     * convert a value to a RDBMS indepdenant MDB type
     *
     * @param object    &$db reference to driver MDB object
     * @param mixed $value value to be converted
     * @param int $type constant that specifies which type to convert to
     * @return mixed converted value or a MDB error on failure
     * @access public
     */
    function convertResult(&$db, $value, $type)
    {
        return $this->_baseConvertResult($value, $type);
    }

    // }}}
    // {{{ convertResultRow()

    /**
     * convert a result row
     *
     * @param object    &$db reference to driver MDB object
     * @param resource $result result identifier
     * @param array $row array with data
     * @return mixed MDB_OK on success,  a MDB error on failure
     * @access public
     */
    function convertResultRow(&$db, $result, $row)
    {
        $result_value = intval($result);
        if (isset($db->results[$result_value]['types'])) {
            $current_column = -1;
            foreach($row as $key => $column) {
                ++$current_column;
                if (!isset($db->results[$result_value]['types'][$current_column])
                   ||!isset($column)
                ) {
                    continue;
                }
                switch ($type = $db->results[$result_value]['types'][$current_column]) {
                    case MDB_TYPE_TEXT:
                    case MDB_TYPE_BLOB:
                    case MDB_TYPE_CLOB:
                        break;
                    case MDB_TYPE_INTEGER:
                        $row[$key] = intval($row[$key]);
                        break;
                    default:
                        $value = $this->convertResult($row[$key], $type);
                        if (MDB::isError($value)) {
                            return $value;
                        }
                        $row[$key] = $value;
                        break;
                }
            }
        }
        return ($row);
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       unsigned
     *           Boolean flag that indicates whether the field should be
     *           declared as unsigned integer if possible.
     *
     *       default
     *           Integer value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getIntegerDeclaration(&$db, $name, $field)
    {
        if (isset($field['unsigned'])) {
            $db->warnings[] = "unsigned integer field \"$name\" is being
                declared as signed integer";
        }
        return "$name INT" . (isset($field['default']) ? ' DEFAULT ' . $field['default'] : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getTextDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       length
     *           Integer value that determines the maximum length of the text
     *           field. If this argument is missing the field should be
     *           declared to have the longest length allowed by the DBMS.
     *
     *       default
     *           Text value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getTextDeclaration(&$db, $name, $field)
    {
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getClobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       length
     *           Integer value that determines the maximum length of the large
     *           object field. If this argument is missing the field should be
     *           declared to have the longest length allowed by the DBMS.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getClobDeclaration(&$db, $name, $field)
    {
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getBlobDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       length
     *           Integer value that determines the maximum length of the large
     *           object field. If this argument is missing the field should be
     *           declared to have the longest length allowed by the DBMS.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getBlobDeclaration(&$db, $name, $field)
    {
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getBooleanDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a boolean type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Boolean value to be used as default for this field.
     *
     *       notnullL
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getBooleanDeclaration(&$db, $name, $field)
    {
        return "$name CHAR (1)" . (isset($field['default']) ? ' DEFAULT ' . $this->getBooleanValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a date type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Date value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getDateDeclaration(&$db, $name, $field)
    {
        return "$name CHAR (" . strlen("YYYY-MM-DD") . ")" . (isset($field['default']) ? ' DEFAULT ' . $this->getDateValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a timestamp
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Timestamp value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getTimestampDeclaration(&$db, $name, $field)
    {
        return "$name CHAR (" . strlen("YYYY-MM-DD HH:MM:SS") . ")" . (isset($field['default']) ? ' DEFAULT ' . $this->getTimestampValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a time
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Time value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getTimeDeclaration(&$db, $name, $field)
    {
        return "$name CHAR (" . strlen("HH:MM:SS") . ")" . (isset($field['default']) ? ' DEFAULT ' . $this->getTimeValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a float type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Float value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getFloatDeclaration(&$db, $name, $field)
    {
        return "$name TEXT " . (isset($field['default']) ? ' DEFAULT ' . $this->getFloatValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a decimal type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name the field to be declared.
     * @param string $field associative array with the name of the properties
     *       of the field being declared as array indexes. Currently, the types
     *       of supported field properties are as follows:
     *
     *       default
     *           Decimal value to be used as default for this field.
     *
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getDecimalDeclaration(&$db, $name, $field)
    {
        return "$name TEXT " . (isset($field['default']) ? ' DEFAULT ' . $this->getDecimalValue($field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getIntegerValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getIntegerValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : (int)$value;
    }

    // }}}
    // {{{ getTextValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that already contains any DBMS specific
     *       escaped character sequences.
     * @access public
     */
    function getTextValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'".$db->quote($value)."'";
    }

    // }}}
    // {{{ getClobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param resource $prepared_query query handle from prepare()
     * @param  $parameter
     * @param  $clob
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getClobValue(&$db, $prepared_query, $parameter, $clob)
    {
        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Get CLOB field value: prepared queries with values of type "clob" are not yet supported');
    }

    // }}}
    // {{{ freeClobValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param resource $prepared_query query handle from prepare()
     * @param string $blob
     * @param string $value
     * @access public
     */
    function freeClobValue(&$db, $prepared_query, $clob, &$value)
    {
    }

    // }}}
    // {{{ getBlobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param resource $prepared_query query handle from prepare()
     * @param  $parameter
     * @param  $blob
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getBlobValue(&$db, $prepared_query, $parameter, $blob)
    {
        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Get BLOB field value: prepared queries with values of type "blob" are not yet supported');
    }

    // }}}
    // {{{ freeBlobValue()

    /**
     * free a binary large object
     *
     * @param object    &$db reference to driver MDB object
     * @param resource $prepared_query query handle from prepare()
     * @param string $blob
     * @param string $value
     * @access public
     */
    function freeBlobValue(&$db, $prepared_query, $blob, &$value)
    {
    }

    // }}}
    // {{{ getBooleanValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getBooleanValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : ($value ? "'Y'" : "'N'");
    }

    // }}}
    // {{{ getDateValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getDateValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'$value'";
    }

    // }}}
    // {{{ getTimestampValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getTimestampValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'$value'";
    }

    // }}}
    // {{{ getTimeValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     *       compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getTimeValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'$value'";
    }

    // }}}
    // {{{ getFloatValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getFloatValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'$value'";
    }

    // }}}
    // {{{ getDecimalValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *       a DBMS specific format.
     * @access public
     */
    function getDecimalValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "'$value'";
    }
};

?>