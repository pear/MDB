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

require_once 'MDB/Modules/Manager/Common.php';

/**
 * MDB MySQL driver for the management modules
 *
 * @package MDB
 * @category Database
 * @access private
 * @author  Paul Cooper <pgc@ucecom.com>
 */
class MDB_Manager_pgsql extends MDB_Manager_common
{
    // {{{ createDatabase()

    /**
     * create a new database
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name of the database that should be created
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function createDatabase(&$db, $name)
    {
        return $db->_standaloneQuery("CREATE DATABASE $name");
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
     **/
    function dropDatabase(&$db, $name)
    {
        return $db->_standaloneQuery("DROP DATABASE $name");
    }

    // }}}
    // {{{ createTable()

    /**
     * create a new table
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name Name of the database that should be created
     * @param array $fields Associative array that contains the definition of each field of the new table
     *                         The indexes of the array entries are the names of the fields of the table an
     *                         the array entry values are associative arrays like those that are meant to be
     *                          passed with the field definitions to get[Type]Declaration() functions.
     *
     *                         Example
     *                         array(
     *
     *                             'id' => array(
     *                                 'type' => 'integer',
     *                                 'unsigned' => 1
     *                                 'notnull' => 1
     *                                 'default' => 0
     *                             ),
     *                             'name' => array(
     *                                 'type' => 'text',
     *                                 'length' => 12
     *                             ),
     *                             'password' => array(
     *                                 'type' => 'text',
     *                                 'length' => 12
     *                             )
     *                         );
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function createTable(&$db, $name, $fields)
    {
        if (!isset($name) || !strcmp($name, '')) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null, 'no valid table name specified');
        }
        if (count($fields) == 0) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null, 'no fields specified for table "'.$name.'"');
        }
        $query_fields = '';
        if (MDB::isError($query_fields = $this->getFieldDeclarationList($db, $fields))) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null, 'unkown error');
        }
        return $db->query("CREATE TABLE $name ($query_fields)");
    }

    // }}}
    // {{{ alterTable()

    /**
     * alter an existing table
     *
     * @param object    &$db reference to driver MDB object
     * @param string $name name of the table that is intended to be changed.
     * @param array $changes associative array that contains the details of each type
     *                              of change that is intended to be performed. The types of
     *                              changes that are currently supported are defined as follows:
     *
     *                              name
     *
     *                                 New name for the table.
     *
     *                             added_fields
     *
     *                                 Associative array with the names of fields to be added as
     *                                  indexes of the array. The value of each entry of the array
     *                                  should be set to another associative array with the properties
     *                                  of the fields to be added. The properties of the fields should
     *                                  be the same as defined by the Metabase parser.
     *
     *                                 Additionally, there should be an entry named Declaration that
     *                                  is expected to contain the portion of the field declaration already
     *                                  in DBMS specific SQL code as it is used in the CREATE TABLE statement.
     *
     *                             removed_fields
     *
     *                                 Associative array with the names of fields to be removed as indexes
     *                                  of the array. Currently the values assigned to each entry are ignored.
     *                                  An empty array should be used for future compatibility.
     *
     *                             renamed_fields
     *
     *                                 Associative array with the names of fields to be renamed as indexes
     *                                  of the array. The value of each entry of the array should be set to
     *                                  another associative array with the entry named name with the new
     *                                  field name and the entry named Declaration that is expected to contain
     *                                  the portion of the field declaration already in DBMS specific SQL code
     *                                  as it is used in the CREATE TABLE statement.
     *
     *                             changed_fields
     *
     *                                 Associative array with the names of the fields to be changed as indexes
     *                                  of the array. Keep in mind that if it is intended to change either the
     *                                  name of a field and any other properties, the changed_fields array entries
     *                                  should have the new names of the fields as array indexes.
     *
     *                                 The value of each entry of the array should be set to another associative
     *                                  array with the properties of the fields to that are meant to be changed as
     *                                  array entries. These entries should be assigned to the new values of the
     *                                  respective properties. The properties of the fields should be the same
     *                                  as defined by the Metabase parser.
     *
     *                                 If the default property is meant to be added, removed or changed, there
     *                                  should also be an entry with index ChangedDefault assigned to 1. Similarly,
     *                                  if the notnull constraint is to be added or removed, there should also be
     *                                  an entry with index ChangedNotNull assigned to 1.
     *
     *                                 Additionally, there should be an entry named Declaration that is expected
     *                                  to contain the portion of the field changed declaration already in DBMS
     *                                  specific SQL code as it is used in the CREATE TABLE statement.
     *                             Example
     *                                 array(
     *                                     'name' => 'userlist',
     *                                     'added_fields' => array(
     *                                         'quota' => array(
     *                                             'type' => 'integer',
     *                                             'unsigned' => 1
     *                                             'declaration' => 'quota INT'
     *                                         )
     *                                     ),
     *                                     'removed_fields' => array(
     *                                         'file_limit' => array(),
     *                                         'time_limit' => array()
     *                                         ),
     *                                     'changed_fields' => array(
     *                                         'gender' => array(
     *                                             'default' => 'M',
     *                                             'change_default' => 1,
     *                                             'declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                         )
     *                                     ),
     *                                     'renamed_fields' => array(
     *                                         'sex' => array(
     *                                             'name' => 'gender',
     *                                             'declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                         )
     *                                     )
     *                                 )
     * @param boolean $check indicates whether the function should just check if the DBMS driver
     *                              can perform the requested table alterations if the value is true or
     *                              actually perform them otherwise.
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function alterTable(&$db, $name, &$changes, $check)
    {
        if ($check) {
            for ($change = 0, reset($changes); $change < count($changes); next($changes), $change++) {
                switch (key($changes)) {
                    case 'added_fields':
                        break;
                    case 'removed_fields':
                        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'database server does not support dropping table columns');
                    case 'name':
                    case 'renamed_fields':
                    case 'changed_fields':
                    default:
                        return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'change type "'.key($changes).'\" not yet supported');
                }
            }
            return MDB_OK;
        } else {
            if (isSet($changes[$change = 'name']) || isSet($changes[$change = 'renamed_fields']) || isSet($changes[$change = 'changed_fields'])) {
                return $db->raiseError(MDB_ERROR_UNSUPPORTED, null, null, 'change type "'.$change.'" not yet supported');
            }
            $query = '';
            if (isSet($changes['added_fields'])) {
                $fields = $changes['added_fields'];
                for ($field = 0, reset($fields); $field < count($fields); next($fields), $field++) {
                    if (MDB::isError($result = $db->query("ALTER TABLE $name ADD ".$fields[key($fields)]['declaration']))) {
                        return $result;
                    }
                }
            }
            if (isSet($changes['removed_fields'])) {
                $fields = $changes['removed_fields'];
                for ($field = 0, reset($fields); $field < count($fields); next($fields), $field++) {
                    if (MDB::isError($result = $db->query("ALTER TABLE $name DROP ".key($fields)))) {
                        return $result;
                    }
                }
            }
            return MDB_OK;
        }
    }

    // }}}
    // {{{ listDatabases()

    /**
     * list all databases
     *
     * @param object    &$db reference to driver MDB object
     * @return mixed data array on success, a MDB error on failure
     * @access public
     **/
    function listDatabases(&$db)
    {
        $result = $db->query('SELECT datname FROM pg_database');
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result);
    }

    // }}}
    // {{{ listUsers()

    /**
     * list all users
     *
     * @param object    &$db reference to driver MDB object
     * @return mixed data array on success, a MDB error on failure
     * @access public
     **/
    function listUsers(&$db)
    {
        $result = $db->query('SELECT usename FROM pg_user');
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result);
    }

    // }}}
    // {{{ listTables()

    /**
     * list all tables in the current database
     *
     * @param object    &$db reference to driver MDB object
     * @return mixed data array on success, a MDB error on failure
     * @access public
     **/
    function listTables(&$db)
    {
        // gratuitously stolen from PEAR DB _getSpecialQuery in pgsql.php
        $sql = 'SELECT c.relname as "Name"
            FROM pg_class c, pg_user u
            WHERE c.relowner = u.usesysid AND c.relkind = \'r\'
            AND not exists (select 1 from pg_views where viewname = c.relname)
            AND c.relname !~ \'^pg_\'
            AND c.relname !~ \'^pga_\'
            UNION
            SELECT c.relname as "Name"
            FROM pg_class c
            WHERE c.relkind = \'r\'
            AND not exists (select 1 from pg_views where viewname = c.relname)
            AND not exists (select 1 from pg_user where usesysid = c.relowner)
            AND c.relname !~ \'^pg_\'
            AND c.relname !~ \'^pga_\'';
        $result = $db->query($sql);
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result);
    }

    // }}}
    // {{{ listTableFields()

    /**
     * list all fields in a tables in the current database
     *
     * @param object    &$db reference to driver MDB object
     * @param string $table name of table that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listTableFields(&$db, $table)
    {
        $result = $db->query("SELECT * FROM $table");
        if (MDB::isError($result)) {
            return $result;
        }
        $columns = $db->getColumnNames($result);
        if (MDB::isError($columns)) {
            $db->freeResult($columns);
        }
        return array_flip($columns);
    }

    // }}}
    // {{{ listViews()

    /**
     * list the views in the database
     *
     * @param object    &$db reference to driver MDB object
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function listViews(&$db)
    {
        // gratuitously stolen from PEAR DB _getSpecialQuery in pgsql.php
        $result = $db->query('SELECT viewname FROM pg_views');
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result);
    }

    // }}}
    // {{{ listTableIndexes()

    /**
     * list all indexes in a table
     *
     * @param object    &$db reference to driver MDB object
     * @param string    $table      name of table that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listTableIndexes(&$db, $table) {
        $result = $db->query("SELECT relname 
                                FROM pg_class WHERE oid IN
                                  (SELECR indexrelid FROM pg_index, pg_class 
                                   WHERE (pg_class.relname='$table') 
                                   AND (pg_class.oid=pg_index.indrelid))");
        return $db->fetchCol($result);
    }

    // }}}
    // {{{ createSequence()

    /**
     * create sequence
     *
     * @param object    &$db reference to driver MDB object
     * @param string $seq_name name of the sequence to be created
     * @param string $start start value of the sequence; default is 1
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function createSequence(&$db, $seq_name, $start)
    {
        $seqname = $db->getSequenceName($seq_name);
        return $db->query("CREATE SEQUENCE $seqname INCREMENT 1".($start < 1 ? " MINVALUE $start" : '')." START $start");
    }

    // }}}
    // {{{ dropSequence()

    /**
     * drop existing sequence
     *
     * @param object    &$db reference to driver MDB object
     * @param string $seq_name name of the sequence to be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     **/
    function dropSequence(&$db, $seq_name)
    {
        $seqname = $db->getSequenceName($seq_name);
        return $db->query("DROP SEQUENCE $seqname");
    }

    // }}}
    // {{{ listSequences()

    /**
     * list all sequences in the current database
     *
     * @param object    &$db reference to driver MDB object
     * @return mixed data array on success, a MDB error on failure
     * @access public
     **/
    function listSequences(&$db)
    {
        // gratuitously stolen and adapted from PEAR DB _getSpecialQuery in pgsql.php
        $sql = 'SELECT c.relname as "Name"
            FROM pg_class c, pg_user u
            WHERE c.relowner = u.usesysid AND c.relkind = \'S\'
            AND not exists (select 1 from pg_views where viewname = c.relname)
            AND c.relname !~ \'^pg_\'
            UNION
            SELECT c.relname as "Name"
            FROM pg_class c
            WHERE c.relkind = \'S\'
            AND not exists (select 1 from pg_views where viewname = c.relname)
            AND not exists (select 1 from pg_user where usesysid = c.relowner)
            AND c.relname !~ \'^pg_\'';

        $result = $db->query($sql);
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result);
    }
}
?>
