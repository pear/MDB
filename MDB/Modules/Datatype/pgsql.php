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
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'MDB/Modules/Datatype/Common.php';

/**
 * MDB PostGreSQL driver
 *
 * @package MDB
 * @category Database
 * @author  Paul Cooper <pgc@ucecom.com>
 */

class MDB_Datatype_pgsql extends MDB_Datatype_Common
{
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
        switch ($type) {
            case MDB_TYPE_BOOLEAN:
                return $value == 't' ? true : false;
            case MDB_TYPE_DECIMAL:
                return sprintf('%.'.$db->decimal_places.'f',doubleval($value)/$db->decimal_factor);
            case MDB_TYPE_FLOAT:
                return doubleval($value);
            case MDB_TYPE_DATE:
                return $value;
            case MDB_TYPE_TIME:
                return $value;
            case MDB_TYPE_TIMESTAMP:
                return substr($value, 0, strlen('YYYY-MM-DD HH:MM:SS'));
            default:
                return $this->_baseConvertResult($db, $value, $type);
        }
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
        return (isset($field['length']) ? "$name VARCHAR (" . $field['length'] . ')' : "$name TEXT").
            (isset($field['default']) ? " DEFAULT '" . $field['default'] . "'" : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name OID".(isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name OID".(isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name BOOLEAN" . (isset($field['default']) ? ' DEFAULT '.
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
        return $name.' DATE'.(isset($field['default']) ? ' DEFAULT \''.$field['default'] . "'" : '').(isset($field['notnull']) ? ' NOT NULL' : '');
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
        return $name.' TIME'.(isset($field['default']) ? ' DEFAULT \''.$field['default'].'\'' : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
        return "$name FLOAT8 ".(isset($field['default']) ? ' DEFAULT '.
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
        return "$name INT8 ".(isset($field['default']) ? ' DEFAULT '.
            $this->getDecimalValue($db, $field['default']) : '').
            (isset($field['notnull']) ? ' NOT NULL' : '');
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
        $connect = $db->connect();
        if (MDB::isError($connect)) {
            return $connect;
        }
        $prepared_query = $lob['prepared_query'];
        $parameter = $lob['parameter'];
        if ($db->auto_commit && !@pg_Exec($db->connection, 'BEGIN')) {
            return $db->raiseError(MDB_ERROR, null, null,
                '_getLOBValue: error starting transaction');
        }
        if (($lo = pg_locreate($db->connection))) {
            if (($handle = pg_loopen($db->connection, $lo, 'w'))) {
                while (!$this->endOfLOB($db, $lob)) {
                    $result = $this->readLOB($db, $lob, $data, $db->options['lob_buffer_length']);
                    if (MDB::isError($result)) {
                        break;
                    }
                    if (!pg_lowrite($handle, $data)) {
                        $result = $db->raiseError(MDB_ERROR, null, null,
                            'Get LOB field value: ' . pg_ErrorMessage($db->connection));
                        break;
                    }
                }
                pg_loclose($handle);
                if (!MDB::isError($result)) {
                    $value = strval($lo);
                }
            } else {
                $result = $db->raiseError(MDB_ERROR, null, null,
                    'Get LOB field value: ' .  pg_ErrorMessage($db->connection));
            }
            if (MDB::isError($result)) {
                $result = pg_lounlink($db->connection, $lo);
            }
        } else {
            $result = $db->raiseError(MDB_ERROR, null, null,
                'Get LOB field value: ' . pg_ErrorMessage($db->connection));
        }
        if ($db->auto_commit) {
            @pg_Exec($db->connection, 'END');
        }
        if (MDB::isError($result)) {
            return $result;
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
    // {{{ freeCLOBValue()

    /**
     * free a character large object
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $clob
     * @access public
     */
    function freeCLOBValue(&$db, $clob, &$value)
    {
#        pg_lounlink($db->connection, intval($value));
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
     * @param string    $blob
     * @access public
     */
    function freeBLOBValue(&$db, $blob, &$value)
    {
#        pg_lounlink($db->connection, intval($value));
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
        return ($value === null) ? 'NULL' : ($value ? "'t'" : "'f'");
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
        return ($value === null) ? 'NULL' : $value;
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
        return ($value === null) ? 'NULL' : strval(round($value*$db->decimal_factor));
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
            return $db->raiseError(MDB_ERROR_INVALID, null, null,
                'Retrieve LOB: did not specified a valid lob');
        }
        if (!isset($db->lobs[$lob]['handle'])) {
            if ($db->auto_commit) {
                if (!pg_exec($db->connection, 'BEGIN')) {
                    return $db->raiseError(MDB_ERROR,  null, null,
                        'Retrieve LOB: ' . pg_ErrorMessage($db->connection));
                }
                $db->lobs[$lob]['in_transaction'] = 1;
            }
            $db->lobs[$lob]['handle'] =
                pg_loopen($db->connection, $db->lobs[$lob]['value'], 'r');
            if (!$db->lobs[$lob]['handle']) {
                if (isset($db->lobs[$lob]['in_transaction'])) {
                    pg_Exec($db->connection, 'END');
                    unset($db->lobs[$lob]['in_transaction']);
                }
                unset($db->lobs[$lob]['value']);
                return $db->raiseError(MDB_ERROR, null, null,
                    'Retrieve LOB: ' . pg_ErrorMessage($db->connection));
            }
        }
        return MDB_OK;
    }

    // }}}
    // {{{ _endOfResultLOB()

    /**
     * Determine whether it was reached the end of the large object and
     * therefore there is no more data to be read for the its input stream.
     *
     * @param int    $lob handle to a lob created by the createLOB() function
     * @return mixed true or false on success, a MDB error on failure
     * @access private
     */
    function _endOfResultLOB(&$db, $lob)
    {
        $lobresult = $this->_retrieveLOB($db, $lob);
        if (MDB::isError($lobresult)) {
            return $lobresult;
        }
        return isset($db->lobs[$lob]['end_of_LOB']);
    }

    // }}}
    // {{{ _readResultLOB()

    /**
     * Read data from large object input stream.
     *
     * @param int $lob handle to a lob created by the createLOB() function
     * @param blob $data reference to a variable that will hold data to be
     *      read from the large object input stream
     * @param int $length integer value that indicates the largest ammount of
     *      data to be read from the large object input stream.
     * @return mixed length on success, a MDB error on failure
     * @access private
     */
    function _readResultLOB(&$db, $lob, &$data, $length)
    {
        $lobresult = $this->_retrieveLOB($db, $lob);
        if (MDB::isError($lobresult)) {
            return $lobresult;
        }
        $data = pg_loread($db->lobs[$lob]['handle'], $length);
        if (gettype($data) != 'string') {
            $db->raiseError(MDB_ERROR, null, null,
                'Read Result LOB: ' . pg_ErrorMessage($db->connection));
        }
        if (($length = strlen($data)) == 0) {
            $db->lobs[$lob]['end_of_LOB'] = 1;
        }
        return $length;
    }

    // }}}
    // {{{ _destroyResultLOB()

    /**
     * Free any resources allocated during the lifetime of the large object
     * handler object.
     *
     * @param int $lob handle to a lob created by the createLOB() function
     * @access private
     */
    function _destroyResultLOB(&$db, $lob)
    {
        if (isset($db->lobs[$lob])) {
            if (isset($db->lobs[$lob]['value'])) {
                pg_loclose($db->lobs[$lob]['handle']);
                if (isset($db->lobs[$lob]['in_transaction'])) {
                    pg_Exec($db->connection, 'END');
                }
            }
            $db->lobs[$lob] = '';
        }
    }
}

?>