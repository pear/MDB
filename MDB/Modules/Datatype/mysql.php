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
class MDB_Datatype_mysql extends MDB_Datatype_Common
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
        return $this->_baseConvertResult($db, $value, $type);
    }

    // }}}
    // {{{ getIntegerDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an integer type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       unsigned
     *                        Boolean flag that indicates whether the field
     *                        should be declared as unsigned integer if
     *                        possible.
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
    function getIntegerDeclaration(&$db, $name, $field)
    {
        $unsigned = isset($field['unsigned']) ? ' UNSIGNED' : '';
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getIntegerValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' INT'.$unsigned.$default.$notnull;
       ;
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
            if ($length <= 255) {
                $type = 'TINYTEXT';
            } else {
                if ($length <= 65535) {
                    $type = 'TEXT';
                } else {
                    if ($length <= 16777215) {
                        $type = 'MEDIUMTEXT';
                    } else {
                        $type = 'LONGTEXT';
                    }
                }
            }
        } else {
            $type = 'LONGTEXT';
        }
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$notnull;
        
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
            if ($length <= 255) {
                $type = 'TINYBLOB';
            } else {
                if ($length <= 65535) {
                    $type = 'BLOB';
                } else {
                    if ($length <= 16777215) {
                        $type = 'MEDIUMBLOB';
                    } else {
                        $type = 'LONGBLOB';
                    }
                }
            }
        }
        else {
            $type = 'LONGBLOB';
        }
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$notnull;
    }

    // }}}
    // {{{ getDateDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an date type
     * field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field properties
     *                        are as follows:
     *
     *                       default
     *                        Date value to be used as default for this field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getDateDeclaration(&$db, $name, $field)
    {
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getDateValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' DATE'.$default.$notnull;
    }

    // }}}
    // {{{ getTimestampDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an timestamp
     * type field to be used in statements like CREATE TABLE.
     *
     * @param object    &$db reference to driver MDB object
     * @param string  $name   name the field to be declared.
     * @param string  $field  associative array with the name of the properties
     *                        of the field being declared as array indexes.
     *                        Currently, the types of supported field
     *                        properties are as follows:
     *
     *                       default
     *                        Time stamp value to be used as default for this
     *                        field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getTimestampDeclaration(&$db, $name, $field)
    {
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getTimestampValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' DATETIME'.$default.$notnull;
    }

    // }}}
    // {{{ getTimeDeclaration()

    /**
     * Obtain DBMS specific SQL code portion needed to declare an time type
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
     *                        Time value to be used as default for this field.
     *
     *                       notnull
     *                        Boolean flag that indicates whether this field is
     *                        constrained to not be set to null.
     * @return string  DBMS specific SQL code portion that should be used to
     *                 declare the specified field.
     * @access public
     */
    function getTimeDeclaration(&$db, $name, $field)
    {
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getTimeValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' TIME'.$default.$notnull;
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
        $type = 'DOUBLE'.($db->fixed_float ? '('.($db->fixed_float + 2).','.$db->fixed_float.')' : '');
        $default = isset($field['default']) ? ' DEFAULT '.
            $this->getFloatValue($db, $field['default']) : '';
        $notnull = isset($field['notnull']) ? ' NOT NULL' : '';
        return $name.' '.$type.$default.$notnull;
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
     * @access public
     */
    function freeCLOBValue(&$db, $clob)
    {
        unset($db->lobs[$clob]);
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
        $value = "'";
        $data = null;
        while(!$this->endOfLOB($db, $blob)) {
        $result = $this->readLOB($db, $blob, $data, $db->options['lob_buffer_length']);
            if (MDB::isError($result)) {
                return $result;
            }
            $value .= addslashes($data);
        }
        $value .= "'";
        return $value;
    }

    // }}}
    // {{{ freeBLOBValue()

    /**
     * free a binary large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $blob
     * @access public
     */
    function freeBLOBValue(&$db, $blob)
    {
        unset($db->lobs[$blob]);
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
        return ($value === null) ? 'NULL' : (float)$value;
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
