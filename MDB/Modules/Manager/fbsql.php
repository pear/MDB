<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2002 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith, Frank M. Kromann                       |
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

require_once 'MDB/Modules/Manager/Common.php';

/**
 * MDB FrontBase driver for the management modules
 *
 * @package MDB
 * @category Database
 * @access private
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author  Frank M. Kromann <frank@kromann.info>
 */
class MDB_Manager_fbsql extends MDB_Manager_Common
{
    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_Manager_fbsql($db_index)
    {
        $this->MDB_Manager_Common($db_index);
    }

    // }}}
    // {{{ createDatabase()

    /**
     * create a new database
     *
     * @param string $name name of the database that should be created
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createDatabase($name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        if (MDB::isError($result = $db->connect())) {
            return $result;
        }
        if (!fbsql_create_db($name, $db->connection)) {
            return $db->fbsqlRaiseError();
        }

        return MDB_OK;
    }

    // }}}
    // {{{ dropDatabase()

    /**
     * drop an existing database
     *
     * @param string $name name of the database that should be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function dropDatabase($name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        if (MDB::isError($result = $db->connect())) {
            return $result;
        }
        if (!fbsql_drop_db($name, $db->connection)) {
            return $db->fbsqlRaiseError();
        }
        return MDB_OK;
    }

    // }}}
    // {{{ createTable()

    /**
     * create a new table
     *
     * @param string $name     Name of the database that should be created
     * @param array $fields Associative array that contains the definition of each field of the new table
     *                        The indexes of the array entries are the names of the fields of the table an
     *                        the array entry values are associative arrays like those that are meant to be
     *                         passed with the field definitions to get[Type]Declaration() functions.
     *
     *                        Example
     *                        array(
     *
     *                            'id' => array(
     *                                'type' => 'integer',
     *                                'unsigned' => 1
     *                                'notnull' => 1
     *                                'default' => 0
     *                            ),
     *                            'name' => array(
     *                                'type' => 'text',
     *                                'length' => 12
     *                            ),
     *                            'password' => array(
     *                                'type' => 'text',
     *                                'length' => 12
     *                            )
     *                        );
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createTable($name, $fields)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        if (!isset($name) || !strcmp($name, '')) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null,
                'createTable: no valid table name specified');
        }
        if (count($fields) == 0) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null,
                'createTable: no fields specified for table "'.$name.'"');
        }
        if (MDB::isError($query_fields = $this->getFieldDeclarationList($fields))) {
            return $db->raiseError(MDB_ERROR_CANNOT_CREATE, null, null,
                'createTable: unkown error');
        }
        $query = "CREATE TABLE $name ($query_fields)";

        return $db->query($query);
    }

    // }}}
    // {{{ alterTable()

    /**
     * alter an existing table
     *
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
    function alterTable($name, $changes, $check)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        if ($check) {
            for($change = 0,reset($changes);
                $change < count($changes);
                next($changes), $change++)
            {
                switch(key($changes)) {
                    case 'added_fields':
                    case 'removed_fields':
                    case 'changed_fields':
                    case 'renamed_fields':
                    case 'name':
                        break;
                    default:
                        return $db->raiseError(MDB_ERROR_CANNOT_ALTER, null, null,
                            'alterTable: change type "'.key($changes).'" not yet supported');
                }
            }
            return MDB_OK;
        } else {
            $query = (isset($changes['name']) ? 'RENAME AS '.$changes['name'] : '');
            if (isset($changes['added_fields'])) {
                $fields = $changes['added_fields'];
                for($field = 0, reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    $query .= 'ADD '.$fields[key($fields)]['declaration'];
                }
            }
            if (isset($changes['removed_fields'])) {
                $fields = $changes['removed_fields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    $query .= 'DROP '.key($fields);
                }
            }
            $renamed_fields = array();
            if (isset($changes['renamed_fields'])) {
                $fields = $changes['renamed_fields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    $renamed_fields[$fields[key($fields)]['name']] = key($fields);
                }
            }
            if (isset($changes['changed_fields'])) {
                $fields = $changes['changed_fields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    if (isset($renamed_fields[key($fields)])) {
                        $field_name = $renamed_fields[key($fields)];
                        unset($renamed_fields[key($fields)]);
                    } else {
                        $field_name = key($fields);
                    }
                    $query .= "CHANGE $field_name ".$fields[key($fields)]['declaration'];
                }
            }
            if (count($renamed_fields))
            {
                for($field = 0,reset($renamed_fields);
                    $field<count($renamed_fields);
                    next($renamed_fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    $old_field_name = $renamed_fields[key($renamed_fields)];
                    $query .= "CHANGE $old_field_name ".$changes['renamed_fields'][$old_field_name]['declaration'];
                }
            }
            return $db->query("ALTER TABLE $name $query");
        }
    }

    // }}}
    // {{{ listDatabases()

    /**
     * list all databases
     *
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listDatabases()
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query('SHOW DATABASES', null, false);
        if (MDB::isError($result)) {
            return $result;
        }
        $databases = $db->fetchCol($result);
        $db->freeResult($result);
        return $databases;
    }

    // }}}
    // {{{ listUsers()

    /**
     * list all users
     *
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listUsers()
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query('SELECT DISTINCT USER FROM USER', null, false);
        if (MDB::isError($result)) {
            return $result;
        }
        $users = $db->fetchCol($result);
        $db->freeResult($result);
        return $users;
    }

    // }}}
    // {{{ listTables()

    /**
     * list all tables in the current database
     *
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listTables()
    {
        
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query('SHOW TABLES', null, false);
        if (MDB::isError($result)) {
            return $result;
        }
        $table_names= $db->fetchCol($result);
        $db->freeResult($result);
        if (MDB::isError($table_names)) {
            return $table_names;
        }
        for($i = 0, $j = count($table_names), $tables = array(); $i < $j; ++$i)
        {
            if (!$this->_isSequenceName($table_names[$i]))
                $tables[] = $table_names[$i];
        }
        return $tables;
    }

    // }}}
    // {{{ listTableFields()

    /**
     * list all fields in a tables in the current database
     *
     * @param string $table name of table that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listTableFields($table)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query("SHOW COLUMNS FROM $table", null, false);
        if (MDB::isError($result)) {
            return $result;
        }
        $fields = $db->fetchCol($result);
        $db->freeResult($fields);
        if (MDB::isError($fields)) {
            return $fields;
        }
        if (is_array($fields)) {
            return $fields;
        }
        return array();
    }

    // }}}
    // {{{ createIndex()

    /**
     * get the stucture of a field into an array
     *
     * @param string    $table         name of the table on which the index is to be created
     * @param string    $name         name of the index to be created
     * @param array     $definition        associative array that defines properties of the index to be created.
     *                                 Currently, only one property named FIELDS is supported. This property
     *                                 is also an associative with the names of the index fields as array
     *                                 indexes. Each entry of this array is set to another type of associative
     *                                 array that specifies properties of the index that are specific to
     *                                 each field.
     *
     *                                Currently, only the sorting property is supported. It should be used
     *                                 to define the sorting direction of the index. It may be set to either
     *                                 ascending or descending.
     *
     *                                Not all DBMS support index sorting direction configuration. The DBMS
     *                                 drivers of those that do not support it ignore this property. Use the
     *                                 function support() to determine whether the DBMS driver can manage indexes.

     *                                 Example
     *                                    array(
     *                                        'fields' => array(
     *                                            'user_name' => array(
     *                                                'sorting' => 'ascending'
     *                                            ),
     *                                            'last_login' => array()
     *                                        )
     *                                    )
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createIndex($table, $name, $definition)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $query = "ALTER TABLE $table ADD ".(isset($definition['unique']) ? 'UNIQUE' : 'INDEX')." $name (";
        for($field = 0, reset($definition['fields']);
            $field < count($definition['fields']);
            $field++, next($definition['fields']))
        {
            if ($field > 0) {
                $query .= ',';
            }
            $query .= key($definition['fields']);
        }
        $query .= ')';
        return $db->query($query);
    }

    // }}}
    // {{{ dropIndex()

    /**
     * drop existing index
     *
     * @param string    $table         name of table that should be used in method
     * @param string    $name         name of the index to be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function dropIndex($table, $name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        return $db->query("ALTER TABLE $table DROP INDEX $name");
    }

    // }}}
    // {{{ listTableIndexes()

    /**
     * list all indexes in a table
     *
     * @param string    $table      name of table that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listTableIndexes($table)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        if (MDB::isError($result = $db->query("SHOW INDEX FROM $table", 'text', false))) {
            return $result;
        }
        $indexes_all = $db->fetchCol($result, 'Key_name');
        $db->freeResult($result);
        for($found = $indexes = array(), $index = 0, $indexes_all_cnt = count($indexes_all);
            $index < $indexes_all_cnt;
            $index++)
        {
            if ($indexes_all[$index] != 'PRIMARY'
                && !isset($found[$indexes_all[$index]]))
            {
                $indexes[] = $indexes_all[$index];
                $found[$indexes_all[$index]] = 1;
            }
        }
        return $indexes;
    }

    // }}}
    // {{{ createSequence()

    /**
     * create sequence
     *
     * @param string    $seq_name     name of the sequence to be created
     * @param string    $start         start value of the sequence; default is 1
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function createSequence($seq_name, $start = 1)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $sequence_name = $db->getSequenceName($seq_name);
        $res = $db->query("CREATE TABLE $sequence_name
            (sequence INTEGER UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY(sequence))");
        if (MDB::isError($res)) {
            return $res;
        }
        if ($start == 1) {
            return MDB_OK;
        }
        $res = $db->query("INSERT INTO $sequence_name (sequence) VALUES (".($start-1).')');
        if (!MDB::isError($res)) {
            return MDB_OK;
        }
        // Handle error
        $result = $db->query("DROP TABLE $sequence_name");
        if (MDB::isError($result)) {
            return $db->raiseError(MDB_ERROR, null, null,
                'createSequence: could not drop inconsistent sequence table ('.
                $result->getMessage().' ('.$result->getUserinfo().'))');
        }
        return $db->raiseError(MDB_ERROR, null, null,
            'createSequence: could not create sequence table ('.
            $res->getMessage().' ('.$res->getUserinfo().'))');
    }

    // }}}
    // {{{ dropSequence()

    /**
     * drop existing sequence
     *
     * @param string    $seq_name     name of the sequence to be dropped
     * @return mixed MDB_OK on success, a MDB error on failure
     * @access public
     */
    function dropSequence($seq_name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $sequence_name = $db->getSequenceName($seq_name);
        return $db->query("DROP TABLE $sequence_name");
    }

    // }}}
    // {{{ listSequences()

    /**
     * list all sequences in the current database
     *
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function listSequences()
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query('SHOW TABLES', 'text', false);
        if (MDB::isError($result)) {
            return $result;
        }
        $table_names = $db->fetchCol($result);
        $db->freeResult($result);
        if (MDB::isError($table_names)) {
            return $table_names;
        }
        for($i = 0, $j = count($table_names), $sequences = array(); $i < $j; ++$i)
        {
            if ($sqn = $this->_isSequenceName($table_names[$i]))
                $sequences[] = $sqn;
        }
        return $sequences;
    }

    // }}}
}
?>
