<?php
// vim: set et ts=4 sw=4 fdm=marker:
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
//

require_once 'MDB/Modules/Datatype/Common.php';

/**
 * MDB MySQL driver
 *
 * @package MDB
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB_Datatype_ibase extends MDB_Datatype_Common
{
    // }}}
    // {{{ convertResult()

    /**
     * convert a value to a RDBMS indepdenant MDB type
     *
     * @param object    &$db reference to driver MDB object
     * @param mixed  $value   value to be converted
     * @param int    $type    constant that specifies which type to convert to
     * @return mixed converted value
     * @access public
     */
    function convertResult(&$db, $value, $type)
    {
        switch ($type) {
            case MDB_TYPE_DECIMAL:
                return sprintf('%.'.$db->decimal_places.'f', doubleval($value)/$db->decimal_factor);
            case MDB_TYPE_TIMESTAMP:
                return substr($value, 0, strlen('YYYY-MM-DD HH:MM:SS'));
            default:
                return $this->_baseConvertResult($db, $value, $type);
        }
    }

    // }}}
    // {{{ getTypeDeclaration()

    /**
     * Obtain DBMS specific native datatype as a string
     * 
     * @param object    &$db reference to driver MDB object
     * @param string $field associative array with the name of the properties
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     * @return string with the correct RDBMS native type
     * @access public 
     */
    function getTypeDeclaration(&$db, $field)
    {
        switch ($field['type']) {
            case 'integer':
                return 'INTEGER';
            case 'text':
                return 'VARCHAR ('.(isset($field['length']) ? $field['length'] : (isset($db->options['default_text_field_length']) ? $db->options['default_text_field_length'] : 4000)).')';
            case 'clob':
                return 'BLOB SUB_TYPE 1';
            case 'blob':
                return 'BLOB SUB_TYPE 0';
            case 'boolean':
                return 'CHAR (1)';
            case 'date':
                return 'DATE';
            case 'time':
                return 'TIME';
            case 'timestamp':
                return 'TIMESTAMP';
            case 'float':
                return 'DOUBLE PRECISION';
            case 'decimal':
                return 'DECIMAL(18,'.$this->decimal_places .')';
        }
        return '';
    }

    // }}}
    // {{{ getTextDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an text type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getTextDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['default']) ? ' DEFAULT '.$this->getTextValue($db, $field['default']) : '')
               .(IsSet($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getCLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the large
     *          object field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getCLOBDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getBLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the large
     *          object field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getBLOBDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a date type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Date value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getDateDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['default']) ? ' DEFAULT "'.$field['default'].'"' : '')
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a time
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Time value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getTimeDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['default']) ? ' DEFAULT "'.$field['default'].'"' : '')
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a float type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Float value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getFloatDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['default']) ? ' DEFAULT '.$this->getFloatValue($db, $field['default']) : '')
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare a decimal type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name   name the field to be declared.
     * @param string $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      default
     *          Decimal value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *      declare the specified field.
     * @access public
     */
    function getDecimalDeclaration(&$db, $name, $field)
    {
        return $name.' '.$this->getTypeDeclaration($field)
               .(isset($field['default']) ? ' DEFAULT '
               .$this->getDecimalValue($db, $field['default']) : '')
               .(isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ _getLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $lob
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     * @access private
     */
    function _getLOBValue(&$db, $lob)
    {
        if (MDB::isError($connect = $db->connect())) {
            return $connect;
        }
        $prepared_query = $lob['prepared_query'];
        $parameter = $lob['parameter'];
        $success = 1;   // REMOVE ME
        $value   = '';  // DEAL WITH ME
        if (!$db->transaction_id = ibase_trans(IBASE_COMMITTED, $db->connection)) {
            return $db->raiseError(MDB_ERROR, '', '',
                '_getLOBValue: Could not start a new transaction: '.ibase_errmsg());
        }

        if (($lo = ibase_blob_create($db->auto_commit ? $db->connection : $db->transaction_id))) {
            while (!$this->endOfLOB($db, $lob)) {
                $result = $this->readLOB($db, $lob, $data, $db->options['lob_buffer_length']);
                if (MDB::isError($result)) {
                    $success = 0;
                    break;
                }
                if (!ibase_blob_add($lo, $data)) {
                    $result = $db->raiseError(MDB_ERROR, null, null,
                        '_getLOBValue - Could not add data to a large object: ' . ibase_errmsg());
                    $success = 0;
                    break;
                }
            }
            if (MDB::isError($result)) {
                ibase_blob_cancel($lo);
            } else {
                $value = ibase_blob_close($lo);
            }
        } else {
            $result = $db->raiseError(MDB_ERROR, null, null,
                'Get LOB field value: ' . pg_ErrorMessage($db->connection));
        }
        if (!isset($db->query_parameters[$prepared_query])) {
            $db->query_parameters[$prepared_query]       = array(0, '');
            $db->query_parameter_values[$prepared_query] = array();
        }
        $query_parameter = count($db->query_parameters[$prepared_query]);
        $db->query_parameters[$prepared_query][$query_parameter] = $value;
        $db->query_parameter_values[$prepared_query][$parameter] = $query_parameter;
        $value = '?';

        if (!$db->auto_commit) {
            $db->commit();
        }
        return $value;
    }

    // }}}
    // {{{ getCLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param           $clob
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     * @access public
     */
    function getCLOBValue(&$db, $clob)
    {
        if ($clob === null) {
            return 'NULL';
        }
        return $this->_getLOBValue($db, $clob);
    }

    // }}}
    // {{{ freeLOBValue()

    /**
     * free a large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string $lob
     * @param string $value
     * @access public
     */
    function freeLOBValue(&$db, $lob,&$value)
    {
        $prepared_query;
        $query_parameter = $this->query_parameter_values[$prepared_query][$lob];

        unset($this->query_parameters[$prepared_query][$query_parameter]);
        unset($this->query_parameter_values[$prepared_query][$lob]);
        if (count($this->query_parameter_values[$prepared_query]) == 0) {
            unset($this->query_parameters[$prepared_query]);
            unset($this->query_parameter_values[$prepared_query]);
        }
        unset($value);
    }

    // }}}
    // {{{ freeCLOBValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string $clob
     * @param string $value
     * @access public
     */
    function freeCLOBValue(&$db, $clob, &$value)
    {
        $this->freeLOBValue($db, $clob, $value);
    }

    // }}}
    // {{{ getBLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param           $blob
     * @return string text string that represents the given argument value in
     *      a DBMS specific format.
     * @access public
     */
    function getBLOBValue(&$db, $blob)
    {
        if ($blob === null) {
            return 'NULL';
        }
        return $this->_getLOBValue($db, $blob);
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
        $this->freeLOBValue($db, $clob, $value);
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
     *      a DBMS specific format.
     * @access public
     */
    function getFloatValue(&$db, $value)
    {
        return (($value === null) ? 'NULL' : $value);
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
     *      a DBMS specific format.
     * @access public
     */
    function getDecimalValue(&$db, $value)
    {
        return (($value === null) ? 'NULL' : strval(round($value*$db->decimal_factor)));
    }
}

?>