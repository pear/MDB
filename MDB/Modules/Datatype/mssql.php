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
 * MDB MS SQL driver
 *
 * @package MDB
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB_Datatype_mssql extends MDB_Datatype_Common
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
        switch($type) {
            case MDB_TYPE_BOOLEAN:
                return ($value == '1') ? true : false;
            case MDB_TYPE_DATE:
                if (strlen($value) > 10) {
                    $value = substr($value,0,10);
                }
                return $value;
            case MDB_TYPE_TIME:
                if (strlen($value) > 8) {
                    $value = substr($value,11,8);
                }
                return $value;
            default:
                return $this->_baseConvertResult($db, $value,$type);
        }
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
        $type = isset($field['length']) ? 'VARCHAR ('.$field['length'].')' : 'TEXT';
        $default = isset($field['default']) ? ' DEFAULT TIME'.
            $this->getTextValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$default.$notnull;
    }

    // }}}
    // {{{ getCLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an character
     * large object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the
     *                        properties of the field being declared as array
     *                        indexes. Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       length
     *                        Integer value that determines the maximum length
     *                        of the large object field. If this argument is
     *                        missing the field should be declared to have the
     *                        longest length allowed by the DBMS.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field
     *                        is constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getCLOBDeclaration(&$db, $name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            if ($length <= 8000) {
                $type = "VARCHAR($length)";
            } else {
                $type = 'TEXT';
            }
        } else {
            $type = 'TEXT';
        }
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$default;
    }

    // }}}
    // {{{ getBLOBDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an binary large
     * object type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       length
     *                        Integer value that determines the maximum length
     *                        of the large object field. If this argument is
     *                        missing the field should be declared to have the
     *                        longest length allowed by the DBMS.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getBLOBDeclaration(&$db, $name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            if ($length <= 8000) {
                $type = "VARBINARY($length)";
            } else {
                $type = 'IMAGE';
            }
        } else {
            $type = 'IMAGE';
        }
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$default;
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
     *       notnull
     *           Boolean flag that indicates whether this field is constrained
     *           to not be set to null.
     * @return string DBMS specific SQL code portion that should be used to
     *       declare the specified field.
     * @access public
     */
    function getBooleanDeclaration(&$db, $name, $field)
    {
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getBooleanValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' BIT'.$default.$notnull;
    }

    // }}}
    // {{{ getFloatDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an float type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Integer value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getFloatDeclaration(&$db, $name, $field)
    {
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getFloatValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' FLOAT'.$default.$notnull;
    }

    // }}}
    // {{{ getDecimalDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an decimal type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Integer value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getDecimalDeclaration(&$db, $name, $field)
    {
        $type = 'DECIMAL(18,'.$db->decimal_places.')';
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getDecimalValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$default.$notnull;
    }

    // }}}
    // {{{ getCLOBValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param           $clob
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getCLOBValue(&$db, $clob)
    {
        if ($clob === null) {
            return 'NULL';
        }
        $value = "'";
        $data = null;
        while(!$this->endOfLOB($db, $clob)) {
            $result = $this->readLOB($db, $clob, $data, $db->options['lob_buffer_length']);
            if (MDB::isError($result)) {
                return $result;
            }
            $value .= $db->quote($data);
        }
        $value .= "'";
        return $value;
    }

    // }}}
    // {{{ freeCLOBValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $clob
     * @param string    $value
     * @access public
     */
    function freeCLOBValue(&$db, $clob)
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
     * @param           $blob
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getBLOBValue(&$db, $blob)
    {
        if ($blob === null) {
            return 'NULL';
        }
        $value = "0x";
        $data = null;
        while(!$this->endOfLOB($db, $blob)) {
        $result = $this->readLOB($db, $blob, $data, $db->options['lob_buffer_length']);
            if (MDB::isError($result)) {
                return $result;
            }
            $value .= bin2hex($data);
        }
        return $value;
    }

    // }}}
    // {{{ freeBLOBValue()

    /**
     * free a binary large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $blob
     * @param string    $value
     * @access public
     */
    function freeBLOBValue(&$db, $blob, $value)
    {
        unset($value);
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
        return ($value === null) ? 'NULL' : ($value ? 1 : 0);
    }

    // }}}
    // {{{ getFloatValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $value text string value that is intended to be converted.
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getFloatValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : $value;
    }

    // }}}
    // {{{ getDecimalValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $value text string value that is intended to be converted.
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getDecimalValue(&$db, $value)
    {
        return ($value === null) ? 'NULL' : $value;
    }
}

?>