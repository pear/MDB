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
 * MDB FrontbaseSQL driver
 *
 * @package MDB
 * @category Database
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB_Datatype_fbsql extends MDB_Datatype_Common
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
            case MDB_TYPE_DECIMAL:
                return sprintf('%.'.$db->decimal_places.'f', doubleval($value)/$db->decimal_factor);
            default:
                return $this->_baseConvertResult($db, $value, $type);
        }
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
        return "$name INT".
                (isset($field['unsigned']) ? ' UNSIGNED' : '').
                (isset($field['default']) ? ' DEFAULT '.$field['default'] : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
    }

    // }}}
    // {{{ getClobDeclaration()

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
    function getClobDeclaration(&$db, $name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            $type = "VARCHAR($length)";
        } else {
            $type = 'VARCHAR(32768)';
        }
        return "$name $type".
                 (isset($field['notnull']) ? ' NOT NULL' : '');
    }

    // }}}
    // {{{ getBlobDeclaration()

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
    function getBlobDeclaration(&$db, $name, $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            $type = "BLOB($length)";
        }
        else {
            $type = 'BLOB(32768)';
        }
        return "$name $type".
                (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name DATE".
                (isset($field['default']) ? " DEFAULT DATE'".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
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
        return "$name DATETIME".
                (isset($field['default']) ? " DEFAULT TIMESTAMP'".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
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
        return "$name TIME".
                (isset($field['default']) ? " DEFAULT TIME'".$field['default']."'" : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
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
        if (isset($db->options['fixedfloat'])) {
            $db->fixed_float = $db->options['fixedfloat'];
        } else {
            if ($db->connection == 0) {
                // XXX needs more checking
                $db->connect();
            }
        }
        return "$name DOUBLE".
                ($db->fixed_float ?
                 '('.($db->fixed_float + 2).','.$db->fixed_float.')' : '').
                (isset($field['default']) ?
                 ' DEFAULT '.$this->getFloatValue($field['default']) : '').
                (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
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
        return "$name REAL".
                (isset($field['default']) ?
                 ' DEFAULT '.$this->getDecimalValue($field['default']) : '').
                 (isset($field['notnull']) ? ' NOT NULL' : '')
               ;
    }

    // }}}
    // {{{ getClobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $clob
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getClobValue(&$db, $prepared_query, $parameter, $clob)
    {
        $value = "'";
        while(!$this->endOfLob($clob)) {
            if (MDB::isError($result = $this->readLob($clob, $data, $db->options['lob_buffer_length']))) {
                return $result;
            }
            $value .= $db->quote($data);
        }
        $value .= "'";
        return $value;
    }

    // }}}
    // {{{ freeClobValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $clob
     * @return MDB_OK
     * @access public
     */
    function freeClobValue(&$db, $prepared_query, $clob)
    {
        unset($this->lobs[$clob]);
        return MDB_OK;
    }

    // }}}
    // {{{ getBlobValue()

    /**
     * Convert a text value into a DBMS specific format that is suitable to
     * compose query statements.
     *
     * @param object    &$db reference to driver MDB object
     * @param resource  $prepared_query query handle from prepare()
     * @param           $parameter
     * @param           $blob
     * @return string  text string that represents the given argument value in
     *                 a DBMS specific format.
     * @access public
     */
    function getBlobValue(&$db, $prepared_query, $parameter, $blob)
    {
        $value = "'";
        while(!$this->endOfLob($blob)) {
            if (MDB::isError($result = $this->readLob($blob, $data, $db->options['lob_buffer_length']))) {
                return $result;
            }
            $value .= addslashes($data);
        }
        $value .= "'";
        return $value;
    }

    // }}}
    // {{{ freeBlobValue()

    /**
     * free a binary large object
     *
     * @param object    &$db reference to driver MDB object
     * @param resource  $prepared_query query handle from prepare()
     * @param string    $blob
     * @return MDB_OK
     * @access public
     */
    function freeBlobValue(&$db, $prepared_query, $blob)
    {
        unset($this->lobs[$blob]);
        return MDB_OK;
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
        return ($value === null) ? 'NULL' : strval(round(doubleval($value)*$db->decimal_factor));
    }
}

?>
