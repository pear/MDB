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
    var $valid_types = array(
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
                'setResultTypes: attempted to redefine the types of the columns of a result set');
        }

        $columns = count($types);
        for($column = 0; $column < $columns; $column++) {
            if (is_array($types[$column])) {
/*
                if (!isset($this->validateLOBArray($db, $types[$column])) {
                    return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                        'setResultTypes: ' . $types[$column]['type'] . ' is not a supported column type');
                }
*/
                $db->results[$result_value]['types'][$column] = $types[$column];
            } else {
                if (!isset($this->valid_types[$types[$column]])) {
                    return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
                        'setResultTypes: ' . $types[$column] . ' is not a supported column type');
                }
                $db->results[$result_value]['types'][$column] = $this->valid_types[$types[$column]];
            }
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
    function _baseConvertResult(&$db, $result, $value, $type)
    {
        if (is_array($type) && isset($type['type'])) {
            $lob = count($db->lobs) + 1;
            $db->lobs[$lob] = array(
                'value' => $value,
                'position' => 0
            );
            $dst_lob = array(
                'database' => &$db,
                'type' => 'resultlob',
                'resultLOB' => $lob
            );
            if (MDB::isError($lob = $this->createLOB($db, $dst_lob))) {
                return $db->raiseError(MDB_ERROR, null, null,
                    'Fetch LOB result: ' . $dst_lob['error']);
            }
            $type['LOB'] = $lob;
            $type['database'] = &$db;
            return $db->datatype->createLOB($db, $type);
        }
        switch ($type) {
            case MDB_TYPE_TEXT:
                return $value;
            case MDB_TYPE_INTEGER:
                return intval($value);
            case MDB_TYPE_BOOLEAN:
                return ($value == 'Y') ? true : false;
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
            case MDB_TYPE_BLOB:
                $lob = count($db->lobs) + 1;
                $db->lobs[$lob] = array(
                    'value' => $value,
                    'position' => 0
                );
                $dst_lob = array(
                    'database' => &$db,
                    'type' => 'resultlob',
                    'resultLOB' => $lob
                );
                if (MDB::isError($lob = $this->createLOB($db, $dst_lob))) {
                    return $db->raiseError(MDB_ERROR, null, null,
                        'Fetch LOB result: ' . $dst_lob['error']);
                }
                return $lob;
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
    function convertResult(&$db, $result, $value, $type)
    {
        return $this->_baseConvertResult($db, $result, $value, $type);
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
                if (!isset($column)
                   || !isset($db->results[$result_value]['types'][$current_column])
                ) {
                    continue;
                }
                switch ($type = $db->results[$result_value]['types'][$current_column]) {
                    case MDB_TYPE_TEXT:
                        break;
                    case MDB_TYPE_INTEGER:
                        $row[$key] = intval($row[$key]);
                        break;
                    default:
                        $value = $this->convertResult($db, $result, $row[$key], $type);
                        if (MDB::isError($value)) {
                            return $value;
                        }
                        $row[$key] = $value;
                        break;
                }
            }
        }
        return $row;
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
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getCLOBDeclaration()

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
    function getCLOBDeclaration(&$db, $name, $field)
    {
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getBLOBDeclaration()

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
    function getBLOBDeclaration(&$db, $name, $field)
    {
        return (isset($field['length']) ? "$name CHAR (" . $field['length'] . ')' : "$name TEXT") . (isset($field['default']) ? ' DEFAULT ' . $this->getTextValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name CHAR (1)" . (isset($field['default']) ? ' DEFAULT ' . $this->getBooleanValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name CHAR (" . strlen('YYYY-MM-DD') . ')' . (isset($field['default']) ? ' DEFAULT ' . $this->getDateValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name CHAR (" . strlen('YYYY-MM-DD HH:MM:SS') . ')' . (isset($field['default']) ? ' DEFAULT ' . $this->getTimestampValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name CHAR (" . strlen('HH:MM:SS') . ')' . (isset($field['default']) ? ' DEFAULT ' . $this->getTimeValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name TEXT " . (isset($field['default']) ? ' DEFAULT ' . $this->getFloatValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name TEXT " . (isset($field['default']) ? ' DEFAULT ' . $this->getDecimalValue($db, $field['default']) : '') . (isset($field['notnull']) ? ' NOT NULL' : '');
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
    // {{{ getCLOBValue()

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
    function getCLOBValue(&$db, $clob)
    {
        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Get CLOB field value: prepared queries with values of type "clob" are not yet supported');
    }

    // }}}
    // {{{ freeCLOBValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string $blob
     * @param string $value
     * @access public
     */
    function freeCLOBValue(&$db, $clob, &$value)
    {
    }

    // }}}
    // {{{ getBLOBValue()

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
    function getBLOBValue(&$db, $blob)
    {
        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null,
            'Get BLOB field value: prepared queries with values of type "blob" are not yet supported');
    }

    // }}}
    // {{{ freeBLOBValue()

    /**
     * free a binary large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string $blob
     * @param string $value
     * @access public
     */
    function freeBLOBValue(&$db, $blob, &$value)
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

    // }}}
    // {{{ createLOB()

    /**
     * Create a handler object of a specified class with functions to
     * retrieve data from a large object data stream.
     *
     * @param object    &$db reference to driver MDB object
     * @param array $arguments An associative array with parameters to create
     *                  the handler object. The array indexes are the names of
     *                  the parameters and the array values are the respective
     *                  parameter values.
     *
     *                  Some parameters are specific of the class of each type
     *                  of handler object that is created. The following
     *                  parameters are common to all handler object classes:
     *
     *                  type
     *
     *                      Name of the type of the built-in supported class
     *                      that will be used to create the handler object.
     *                      There are currently four built-in types of handler
     *                      object classes: data, resultlob, inputfile and
     *                      outputfile.
     *
     *                      The data handler class is the default class. It
     *                      simply reads data from a given data string.
     *
     *                      The resultlob handler class is meant to read data
     *                      from a large object retrieved from a query result.
     *                      This class is not used directly by applications.
     *
     *                      The inputfile handler class is meant to read data
     *                      from a file to use in prepared queries with large
     *                      object field parameters.
     *
     *                      The outputfile handler class is meant to write to
     *                      a file data from result columns with large object
     *                      fields. The functions to read from this type of
     *                      large object do not return any data. Instead, the
     *                      data is just written to the output file with the
     *                      data retrieved from a specified large object handle.
     *
     *                  class
     *
     *                      Name of the class of the handler object that will be
     *                      created if the Type argument is not specified. This
     *                      argument should be used when you need to specify a
     *                      custom handler class.
     *
     *                  database
     *
     *                      Database object as returned by MDB::connect.
     *                      This is an option argument needed by some handler
     *                      classes like resultlob.
     *
     *                  The following arguments are specific of the inputfile
     *                  handler class:
     *
     *                      file
     *
     *                          Integer handle value of a file already opened
     *                          for writing.
     *
     *                      file_name
     *
     *                          Name of a file to be opened for writing if the
     *                          File argument is not specified.
     *
     *                  The following arguments are specific of the outputfile
     *                  handler class:
     *
     *                      file
     *
     *                          Integer handle value of a file already opened
     *                          for writing.
     *
     *                      file_name
     *
     *                          Name of a file to be opened for writing if the
     *                          File argument is not specified.
     *
     *                      buffer_length
     *
     *                          Integer value that specifies the length of a
     *                          buffer that will be used to read from the
     *                          specified large object.
     *
     *                      LOB
     *
     *                          Integer handle value that specifies a large
     *                          object from which the data to be stored in the
     *                          output file will be written.
     *
     *                      result
     *
     *                          Integer handle value as returned by the function
     *                          MDB::query() or MDB::executeQuery() that specifies
     *                          the result set that contains the large object value
     *                          to be retrieved. If the LOB argument is specified,
     *                          this argument is ignored.
     *
     *                      row
     *
     *                          Integer value that specifies the number of the
     *                          row of the result set that contains the large
     *                          object value to be retrieved. If the LOB
     *                          argument is specified, this argument is ignored.
     *
     *                      field
     *
     *                          Integer or string value that specifies the
     *                          number or the name of the column of the result
     *                          set that contains the large object value to be
     *                          retrieved. If the LOB argument is specified,
     *                          this argument is ignored.
     *
     *                      binary
     *
     *                          Boolean value that specifies whether the large
     *                          object column to be retrieved is of binary type
     *                          (blob) or otherwise is of character type (clob).
     *                          If the LOB argument is specified, this argument
     *                          is ignored.
     *
     *                  The following argument is specific of the data
     *                  handler class:
     *
     *                  data
     *
     *                      String of data that will be returned by the class
     *                      when it requested with the readLOB() method.
     *
     *                  The following argument is specific of the resultlob
     *                  handler class:
     *
     *                      resultLOB
     *
     *                          Integer handle value of a large object result
     *                          row field.
     * @return integer handle value that should be passed as argument insubsequent
     * calls to functions that retrieve data from the large object input stream.
     * @access public
     */
    function createLOB(&$db, $arguments)
    {
        $result = MDB::loadClass('LOB');
        if (MDB::isError($result)) {
            return $result;
        }
        $class_name = 'MDB_LOB';
        if (isset($arguments['type'])) {
            switch ($arguments['type']) {
                case 'data':
                    break;
                case 'resultlob':
                    $class_name = 'MDB_LOB_Result';
                    break;
                case 'inputfile':
                    $class_name = 'MDB_LOB_Input_File';
                    break;
                case 'outputfile':
                    $class_name = 'MDB_LOB_Output_File';
                    break;
                default:
                    if (isset($arguments['error'])) {
                        $arguments['error'] = $arguments['type'] . ' is not a valid type of large object';
                    }
                    return $db->raiseError();
            }
        } else {
            if (isset($arguments['class'])) {
                $class = $arguments['class'];
            }
        }

        $lob = count($GLOBALS['_MDB_LOBs']) + 1;
        $GLOBALS['_MDB_LOBs'][$lob] =& new $class_name;
        if (isset($arguments['database'])) {
            $GLOBALS['_MDB_LOBs'][$lob]->database = &$arguments['database'];
        } else {
            $GLOBALS['_MDB_LOBs'][$lob]->database = &$db;
        }
        $result = $GLOBALS['_MDB_LOBs'][$lob]->create($arguments);
        if (MDB::isError($result)) {
            $GLOBALS['_MDB_LOBs'][$lob]->database->datatype->destroyLOB($GLOBALS['_MDB_LOBs'][$lob]->database, $lob);
            return $result;
        }
        return $lob;
    }

    // }}}
    // {{{ _retrieveLob()

    /**
     * retrieve LOB from the database
     * 
     * @param object    &$db reference to driver MDB object
     * @param int $lob handle to a lob created by the createLob() function
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access private 
     */
    function _retrieveLob($db, $lob)
    {
        if (!isset($db->lobs[$lob])) {
            return $db->raiseError(MDB_ERROR, null, null,
                'Retrieve LOB: it was not specified a valid lob');
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _readResultLOB()

    /**
     * Read data from large object input stream.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $lob handle to a lob created by the createLOB() function
     * @param blob $data reference to a variable that will hold data to be
     *       read from the large object input stream
     * @param int $length integer value that indicates the largest ammount of
     *       data to be read from the large object input stream.
     * @return mixed length on success, a MDB error on failure
     * @access private
     */
    function _readResultLOB(&$db, $lob, &$data, $length)
    {
        $lobresult = $this->_retrieveLob($db, $lob);
        if (MDB::isError($lobresult)) {
            return $lobresult;
        }
        $length = min($length, strlen($db->lobs[$lob]['value']) - $db->lobs[$lob]['position']);
        $data = substr($db->lobs[$lob]['value'], $db->lobs[$lob]['position'], $length);
        $db->lobs[$lob]['position'] += $length;
        return $length;
    }

    // }}}
    // {{{ readLOB()

    /**
     * Read data from large object input stream.
     *
     * @param object    &$db reference to driver MDB object
     * @param integer $lob argument handle that is returned by the
     *                          MDB::createLOB() method.
     * @param string $data reference to a variable that will hold data
     *                          to be read from the large object input stream
     * @param integer $length    value that indicates the largest ammount ofdata
     *                          to be read from the large object input stream.
     * @return mixed the effective number of bytes read from the large object
     *                      input stream on sucess or an MDB error object.
     * @access public
     * @see endOfLOB()
     */
    function readLOB(&$db, $lob, &$data, $length)
    {
        return $GLOBALS['_MDB_LOBs'][$lob]->readLOB($data, $length);
    }

    // }}}
    // {{{ _endOfResultLOB()

    /**
     * Determine whether it was reached the end of the large object and
     * therefore there is no more data to be read for the its input stream.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $lob handle to a lob created by the createLOB() function
     * @return mixed true or false on success, a MDB error on failure
     * @access private
     */
    function _endOfResultLOB(&$db, $lob)
    {
        $lobresult = $this->_retrieveLob($db, $lob);
        if (MDB::isError($lobresult)) {
            return $lobresult;
        }
        return $db->lobs[$lob]['position'] >= strlen($db->lobs[$lob]['value']);
    }

    // }}}
    // {{{ endOfLOB()

    /**
     * Determine whether it was reached the end of the large object and
     * therefore there is no more data to be read for the its input stream.
     *
     * @param object    &$db reference to driver MDB object
     * @param integer $lob argument handle that is returned by the
     *                          MDB::createLOB() method.
     * @access public
     * @return boolean flag that indicates whether it was reached the end of the large object input stream
     */
    function endOfLOB(&$db, $lob)
    {
        return $GLOBALS['_MDB_LOBs'][$lob]->endOfLOB();
    }

    // }}}
    // {{{ _destroyResultLOB()

    /**
     * Free any resources allocated during the lifetime of the large object
     * handler object.
     *
     * @param object    &$db reference to driver MDB object
     * @param int $lob handle to a lob created by the createLOB() function
     * @access private
     */
    function _destroyResultLOB(&$db, $lob)
    {
        if (isset($db->lobs[$lob])) {
            $db->lobs[$lob] = '';
        }
    }

    // }}}
    // {{{ destroyLOB()

    /**
     * Free any resources allocated during the lifetime of the large object
     * handler object.
     *
     * @param object    &$db reference to driver MDB object
     * @param integer $lob argument handle that is returned by the
     *                          MDB::createLOB() method.
     * @access public
     */
    function destroyLOB(&$db, $lob)
    {
        $GLOBALS['_MDB_LOBs'][$lob]->destroy();
        unset($GLOBALS['_MDB_LOBs'][$lob]);
    }
};

?>