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

// $Id$

require_once 'MDB/Modules/Datatype/Common.php';

/**
 * MDB OCI8 driver
 * 
 * @package MDB
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com> 
 */
class MDB_Datatype_oci8 extends MDB_Datatype_Common
{
    // }}}
    // {{{ convertResult()

    /**
     * convert a value to a RDBMS indepdenant MDB type
     * 
     * @param object    &$db reference to driver MDB object
     * @param mixed $value value to be converted
     * @param int $type constant that specifies which type to convert to
     * @return mixed converted value
     * @access public 
     */
    function convertResult(&$db, $value, $type)
    {
        switch ($type) {
            case MDB_TYPE_DATE:
                return substr($value, 0, strlen('YYYY-MM-DD'));
            case MDB_TYPE_TIME:
                return substr($value, strlen('YYYY-MM-DD '), strlen('HH:MI:SS'));
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
                return 'INT';
            case 'text':
                return 'VARCHAR ('.(isset($field['length']) ? $field['length'] : (isset($db->options['default_text_field_length']) ? $db->options['default_text_field_length'] : 4000)).')';
            case 'boolean':
                return 'CHAR (1)';
            case 'date':
            case 'time':
            case 'timestamp':
                return 'DATE';
            case 'float':
                return 'NUMBER';
            case 'decimal':
                return 'NUMBER(*,'.$db->decimal_places.')';
        }
        return '';
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
     *        of the field being declared as array indexes. Id
     * ently, the types
     *        of supported field properties are as follows:
     * 
     *        unsigned
     *            Boolean flag that indicates whether the field should be
     *            declared as unsigned integer if possible.
     * 
     *        default
     *            Integer value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getIntegerDeclaration(&$db, $name, $field)
    {
        if (isset($field['unsigned'])) {
            $db->warning = "unsigned integer field \"$name\" is being declared as signed integer";
        }
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.$field['default'] : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the text
     *            field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        default
     *            Text value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTextDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getTextValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the large
     *            object field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getCLOBDeclaration(&$db, $name, $field)
    {
        return "$name CLOB".(isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        length
     *            Integer value that determines the maximum length of the large
     *            object field. If this argument is missing the field should be
     *            declared to have the longest length allowed by the DBMS.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getBLOBDeclaration(&$db, $name, $field)
    {
        return "$name BLOB".(isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Boolean value to be used as default for this field.
     * 
     *        notnullL
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getBooleanDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getBooleanValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Date value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getDateDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getDateValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Timestamp value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTimestampDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getTimestampValue($db, $field['default']) : '').(isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Time value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getTimeDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getTimeValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Float value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getFloatDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getFloatValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
     *        of the field being declared as array indexes. Currently, the types
     *        of supported field properties are as follows:
     * 
     *        default
     *            Decimal value to be used as default for this field.
     * 
     *        notnull
     *            Boolean flag that indicates whether this field is constrained
     *            to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *        declare the specified field.
     * @access public 
     */
    function getDecimalDeclaration(&$db, $name, $field)
    {
        return "$name ".$this->getTypeDeclaration($field).
            (isset($field['default']) ? ' DEFAULT '.
            $this->getDecimalValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getCLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param object    &$db reference to driver MDB object
     * @param  $clob 
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getCLOBValue(&$db, $clob)
    {
        if ($clob === null) {
            return 'NULL';
        }
        return 'EMPTY_CLOB()';
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
        unset($value);
    }

    // }}}
    // {{{ getBLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param object    &$db reference to driver MDB object
     * @param  $blob 
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getBLOBValue(&$db, $blob)
    {
        if ($blob === null) {
            return 'NULL';
        }
        return 'EMPTY_BLOB()';
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
        unset($value);
    }

    // }}}
    // {{{ getDateValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     * 
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getDateValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "TO_DATE('$value','YYYY-MM-DD')";
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
     *        a DBMS specific format.
     * @access public 
     */
    function getTimestampValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "TO_DATE('$value','YYYY-MM-DD HH24:MI:SS')";
    }

    // }}}
    // {{{ getTimeValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     *        compose query statements.
     * 
     * @param object    &$db reference to driver MDB object
     * @param string $value text string value that is intended to be converted.
     * @return string text string that represents the given argument value in
     *        a DBMS specific format.
     * @access public 
     */
    function getTimeValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : "TO_DATE('0001-01-01 $value','YYYY-MM-DD HH24:MI:SS')";
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
     *        a DBMS specific format.
     * @access public 
     */
    function getFloatValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : (float)$value;
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
     *        a DBMS specific format.
     * @access public 
     */
    function getDecimalValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : $value;
    }

    // }}}
    // {{{ _retrieveLOB()

    /**
     * retrieve LOB from the database
     * 
     * @param int $lob handle to a lob created by the createLOB() function
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access private 
     */
    function _retrieveLOB(&$db, $lob)
    {
        if (!isset($db->lobs[$lob])) {
            return $db->raiseError(MDB_ERROR, null, null,
                'Retrieve LOB: it was not specified a valid lob');
        }
        if (!isset($db->lobs[$lob]['loaded'])) {
            if (!is_object($db->lobs[$lob]['value'])) {
               return $db->raiseError(MDB_ERROR, null, null,
                   'Retrieve LOB: attemped to retrieve LOB from non existing or NULL column');
            }
            $db->lobs[$lob]['value'] = $db->lobs[$lob]['value']->load();
            $db->lobs[$lob]['loaded'] = true;
        }
        return MDB_OK;
    }
}

?>