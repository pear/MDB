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
// | Author: YOUR NAME <YOUR EMAIL>                                       |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'MDB/Modules/Manager/Common.php';

/**
 * MDB MSSQL driver for the management modules
 *
 * @package MDB
 * @category Database
 * @author  YOUR NAME <YOUR EMAIL>
 */
class MDB_Manager_mssql extends MDB_Manager_Common
{
    // }}}
    // {{{ createDatabase()

    /**
     * create a new database
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name of the database that should be created
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createDatabase(&$db, $name)
    {
        $DatabaseDevice = isset($db->options['database_device']) ? $db->options['database_device'] : 'DEFAULT';
        $DatabaseSize = isset($db->options['database_size']) ? "=".$db->options['database_size'] : '';
        return $db->standaloneQuery("CREATE DATABASE $name ON ".$DatabaseDevice.$DatabaseSize);
    }

    // }}}
    // {{{ dropDatabase()

    /**
     * drop an existing database
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name of the database that should be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function dropDatabase(&$db, $name)
    {
        return $db->standaloneQuery("DROP DATABASE $name");
    }

    // }}}
    // {{{ alterTable()

    /**
     * alter an existing table
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name         name of the table that is intended to be changed.
     * @param array $changes     associative array that contains the details of each type
     *                             of change that is intended to be performed. The types of
     *                             changes that are currently supported are defined as follows:
     *
     *                             name
     *
     *                                New name for the table.
     *
     *                            added_fields
     *
     *                                Associative array with the names of fields to be added as
     *                                 indexes of the array. The value of each entry of the array
     *                                 should be set to another associative array with the properties
     *                                 of the fields to be added. The properties of the fields should
     *                                 be the same as defined by the Metabase parser.
     *
     *                                Additionally, there should be an entry named Declaration that
     *                                 is expected to contain the portion of the field declaration already
     *                                 in DBMS specific SQL code as it is used in the CREATE TABLE statement.
     *
     *                            removed_fields
     *
     *                                Associative array with the names of fields to be removed as indexes
     *                                 of the array. Currently the values assigned to each entry are ignored.
     *                                 An empty array should be used for future compatibility.
     *
     *                            renamed_fields
     *
     *                                Associative array with the names of fields to be renamed as indexes
     *                                 of the array. The value of each entry of the array should be set to
     *                                 another associative array with the entry named name with the new
     *                                 field name and the entry named Declaration that is expected to contain
     *                                 the portion of the field declaration already in DBMS specific SQL code
     *                                 as it is used in the CREATE TABLE statement.
     *
     *                            changed_fields
     *
     *                                Associative array with the names of the fields to be changed as indexes
     *                                 of the array. Keep in mind that if it is intended to change either the
     *                                 name of a field and any other properties, the changed_fields array entries
     *                                 should have the new names of the fields as array indexes.
     *
     *                                The value of each entry of the array should be set to another associative
     *                                 array with the properties of the fields to that are meant to be changed as
     *                                 array entries. These entries should be assigned to the new values of the
     *                                 respective properties. The properties of the fields should be the same
     *                                 as defined by the Metabase parser.
     *
     *                                If the default property is meant to be added, removed or changed, there
     *                                 should also be an entry with index ChangedDefault assigned to 1. Similarly,
     *                                 if the notnull constraint is to be added or removed, there should also be
     *                                 an entry with index ChangedNotNull assigned to 1.
     *
     *                                Additionally, there should be an entry named Declaration that is expected
     *                                 to contain the portion of the field changed declaration already in DBMS
     *                                 specific SQL code as it is used in the CREATE TABLE statement.
     *                            Example
     *                                array(
     *                                    'name' => 'userlist',
     *                                    'added_fields' => array(
     *                                        'quota' => array(
     *                                            'type' => 'integer',
     *                                            'unsigned' => 1
     *                                            'declaration' => 'quota INT'
     *                                        )
     *                                    ),
     *                                    'removed_fields' => array(
     *                                        'file_limit' => array(),
     *                                        'time_limit' => array()
     *                                        ),
     *                                    'changed_fields' => array(
     *                                        'gender' => array(
     *                                            'default' => 'M',
     *                                            'change_default' => 1,
     *                                            'declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                        )
     *                                    ),
     *                                    'renamed_fields' => array(
     *                                        'sex' => array(
     *                                            'name' => 'gender',
     *                                            'declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                        )
     *                                    )
     *                                )
     *
     * @param boolean $check     indicates whether the function should just check if the DBMS driver
     *                             can perform the requested table alterations if the value is true or
     *                             actually perform them otherwise.
     * @access public
     *
      * @return mixed MDB_OK on success, a MDB error on failure
     */
    function alterTable(&$db, $name, $changes, $check)
    {
        if ($check) {
            for ($change = 0, reset($changes);
                $change < count($changes);
                next($changes), $change++)
            {
                switch (key($changes)) {
                    case 'added_fields':
                        break;
                    case 'removed_fields':
                    case 'name':
                    case 'renamed_fields':
                    case 'changed_fields':
                    default:
                        return $db->raiseError(MDB_ERROR_CANNOT_ALTER, null, null,
                            'Alter table: change type "'.key($changes).'" not yet supported');
                }
            }
            return MDB_OK;
        } else {
            if (isset($changes[$change = 'removed_fields'])
                || isset($changes[$change = 'name'])
                || isset($changes[$change = 'renamed_fields'])
                || isset($changes[$change = 'changed_fields']))
            {
                return $db->raiseError(MDB_ERROR_CANNOT_ALTER, null, null,
                    'Alter table: change type "'.$change.'" is not supported by the server"');
            }
            $query='';
            if (isset($changes['added_fields'])) {
                if (strcmp($query, '')) {
                    $query.= ', ';
                }
                $query.= 'ADD ';
                $fields = $changes['added_fields'];
                for ($field = 0, reset($fields);
                    $field < count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query.= ', ';
                    }
                    $query.= $fields[key($fields)]['declaration'];
                }
            }
            return strcmp($query, '') ? $db->query("ALTER TABLE $name $query") : MDB_OK;
        }
    }

    // }}}
    // {{{ createSequence()

    /**
     * create sequence
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $seq_name     name of the sequence to be created
     * @param string    $start         start value of the sequence; default is 1
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createSequence(&$db, $seq_name, $start = 1)
    {
        $sequence_name = $db->getSequenceName($seq_name);
        return $db->query("CREATE TABLE $sequence_name (sequence INT NOT NULL IDENTITY($start,1) PRIMARY KEY CLUSTERED)");
    }

    // }}}
    // {{{ dropSequence()

    /**
     * drop existing sequence
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $seq_name     name of the sequence to be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function dropSequence(&$db, $seq_name)
    {
        $sequence_name = $db->getSequenceName($seq_name);
        return $db->Query("DROP TABLE $sequence_name");
    }
}
?>