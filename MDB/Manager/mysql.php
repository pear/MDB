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
// | Author: Lukas Smith <smith@dybnet.de>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
* MDB MySQL driver for the management extensions
*
* @package MDB
* @author  Lukas Smith <smith@dybnet.de>
*/

if(!defined('MDB_MANAGER_MYSQL_INCLUDED'))
{
    define('MDB_MANAGER_MYSQL_INCLUDED',1);

class MDB_manager_mysql_class extends MDB_manager_common
{
    var $verified_table_types = array();

    // }}}
    // {{{ _verifyTransactionalTableType()
    /**
     * verify that chosen transactional table hanlder is available in the database
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string $table_type name of the table handler
     *
     * @access private
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function _verifyTransactionalTableType(&$db, $table_type)
    {
        switch(strtoupper($table_type)) {
            case 'BERKELEYDB':
            case 'BDB':
                $check = 'have_bdb';
                break;
            case 'INNODB':
                $check = 'have_innobase';
                break;
            case 'GEMINI':
                $check = 'have_gemini';
                break;
            case 'HEAP':
            case 'ISAM':
            case 'MERGE':
            case 'MRG_MYISAM':
            case 'MYISAM':
            case '':
                return(DB_OK);
            default:
                return($db->raiseError(DB_ERROR_UNSUPPORTED, '', '', 'Verify transactional table',$table_type.' is not a supported table type'));
        }
        if(!$db->connect()) {
            return($db->raiseError());
        }
        if(isset($this->verified_table_types[$table_type])
            && $this->verified_table_types[$table_type] == $db->connection)
        {
            return(DB_OK);
        }
        if(MDB::isError($has = $db->queryAll("SHOW VARIABLES LIKE '$check'", NULL, DB_FETCHMODE_ORDERED))) {
            return($db->raiseError());
        }
        if(count($has) == 0) {
            return($db->raiseError(DB_ERROR_UNSUPPORTED, '', '', 'Verify transactional table',"could not tell if ".$table_type.' is a supported table type'));
        }
        if(strcmp($has[0][1], 'YES')) {
            return($db->raiseError(DB_ERROR_UNSUPPORTED, '', '', 'Verify transactional table',$table_type.' is not a supported table type by this MySQL database server'));
        }
        $this->verified_table_types[$table_type] = $db->connection;
        return (DB_OK);
    }

    // }}}
    // {{{ createDatabase()

    /**
     * create a new database
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string $name name of the database that should be created
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function createDatabase(&$db, $name)
    {
        if (MDB::isError($result = $db->connect())) {
            return $result;
        }
        if (!mysql_create_db($name, $db->connection)) {
            return $db->mysqlRaiseError(DB_ERROR_CANNOT_CREATE);
        }

        return (DB_OK);
    }

    // }}}
    // {{{ dropDatabase()

    /**
     * drop an existing database
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string $name name of the database that should be dropped
     *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function dropDatabase(&$db, $name)
    {
        if (MDB::isError($result = $db->connect())) {
            return $result;
        }
        if (!mysql_drop_db($name, $db->connection)) {
            return $db->mysqlRaiseError(DB_ERROR_CANNOT_DROP);
        }
        return (DB_OK);
    }

    // }}}
    // {{{ createTable()

    /**
     * create a new table
     *
     * @param $dbs (reference) array where database names will be stored
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
       *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function createTable(&$db, $name, &$fields)
    {
        if (!isset($name) || !strcmp($name, '')) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'no valid table name specified');
        }
        if (count($fields) == 0) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'no fields specified for table "'.$name.'"');
        }
        if(MDB::isError($verify = $this->_verifyTransactionalTableType($db, $db->default_table_type))) {
            return($verify);
        }
        if (MDB::isError($query_fields = $this->getFieldDeclarationList($db, $fields))) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'unkown error');
        }
        if (isset($db->supported['Transactions'])
            && $db->default_table_type=='BDB')
        {
            $query_fields .= ', dummy_primary_key INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (dummy_primary_key)';
        }
        $query = "CREATE TABLE $name ($query_fields)".(strlen($db->default_table_type) ? ' TYPE='.$db->default_table_type : '');

        return ($db->query($query));
    }

    // }}}
    // {{{ alterTable()

    /**
     * alter an existing table
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string $name         name of the table that is intended to be changed.
     * @param array $changes     associative array that contains the details of each type
     *                             of change that is intended to be performed. The types of
     *                             changes that are currently supported are defined as follows:
     *
     *                             name
     *
     *                                New name for the table.
     *
     *                            AddedFields
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
     *                            RemovedFields
     *
     *                                Associative array with the names of fields to be removed as indexes
     *                                 of the array. Currently the values assigned to each entry are ignored.
     *                                 An empty array should be used for future compatibility.
     *
     *                            RenamedFields
     *
     *                                Associative array with the names of fields to be renamed as indexes
     *                                 of the array. The value of each entry of the array should be set to
     *                                 another associative array with the entry named name with the new
     *                                 field name and the entry named Declaration that is expected to contain
     *                                 the portion of the field declaration already in DBMS specific SQL code
     *                                 as it is used in the CREATE TABLE statement.
     *
     *                            ChangedFields
     *
     *                                Associative array with the names of the fields to be changed as indexes
     *                                 of the array. Keep in mind that if it is intended to change either the
     *                                 name of a field and any other properties, the ChangedFields array entries
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
     *                                    'AddedFields' => array(
     *                                        'quota' => array(
     *                                            'type' => 'integer',
     *                                            'unsigned' => 1
     *                                            'Declaration' => 'quota INT'
     *                                        )
     *                                    ),
     *                                    'RemovedFields' => array(
     *                                        'file_limit' => array(),
     *                                        'time_limit' => array()
     *                                        ),
     *                                    'ChangedFields' => array(
     *                                        'gender' => array(
     *                                            'default' => 'M',
     *                                            'ChangeDefault' => 1,
     *                                            'Declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                        )
     *                                    ),
     *                                    'RenamedFields' => array(
     *                                        'sex' => array(
     *                                            'name' => 'gender',
     *                                            'Declaration' => "gender CHAR(1) DEFAULT 'M'"
     *                                        )
     *                                    )
     *                                )
     *
     * @param boolean $check     indicates whether the function should just check if the DBMS driver
     *                             can perform the requested table alterations if the value is true or
     *                             actually perform them otherwise.
     * @access public
     *
      * @return mixed DB_OK on success, a DB error on failure
     */
    function alterTable(&$db, $name, &$changes, $check)
    {
        if ($check) {
            for($change = 0,reset($changes);
                $change < count($changes);
                next($changes), $change++)
            {
                switch(Key($changes)) {
                    case 'AddedFields':
                    case 'RemovedFields':
                    case 'ChangedFields':
                    case 'RenamedFields':
                    case 'name':
                        break;
                    default:
                        return $db->raiseError(DB_ERROR_CANNOT_ALTER, '', '',
                            'Alter table: change type "'.Key($changes).'" not yet supported');
                }
            }
            return (DB_OK);
        } else {
            $query = (isset($changes['name']) ? 'RENAME AS '.$changes['name'] : '');
            if (isset($changes['AddedFields']))    {
                $fields = $changes['AddedFields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    $query .= 'ADD '.$fields[key($fields)]['Declaration'];
                }
            }
            if (isset($changes['RemovedFields'])) {
                $fields = $changes['RemovedFields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, '')) {
                        $query .= ',';
                    }
                    $query .= 'DROP '.Key($fields);
                }
            }
            $renamed_fields = array();
            if (isset($changes['RenamedFields'])) {
                $fields = $changes['RenamedFields'];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    $renamed_fields[$fields[key($fields)]['name']] = key($fields);
                }
            }
            if (isset($changes['ChangedFields'])) {
                $fields = $changes['ChangedFields'];
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
                    $query .= "CHANGE $field_name ".$fields[key($fields)]['Declaration'];
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
                    $old_field_name = $renamed_fields[Key($renamed_fields)];
                    $query .= "CHANGE $old_field_name ".$changes['RenamedFields'][$old_field_name]['Declaration'];
                }
            }
            return ($db->query("ALTER TABLE $name $query"));
        }
    }

    // }}}
    // {{{ listDatabases()

    /**
     * list all databases
     *
     * @param $dbs (reference) array where database names will be stored
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listDatabases(&$db)
    {
        $result = $db->queryCol('SHOW DATABASES', NULL, DB_FETCHMODE_ORDERED);
        if(MDB::isError($result)) {
            return $result;
        }
        return ($result);
    }

    // }}}
    // {{{ listUsers()

    /**
     * list all users
     *
     * @param $dbs (reference) array where database names will be stored
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listUsers(&$db)
    {
        $result = $db->queryCol('SELECT DISTINCT USER FROM USER', NULL, DB_FETCHMODE_ORDERED);
        if(MDB::isError($result)) {
            return $result;
        }
        return ($result);
    }

    // }}}
    // {{{ listTables()

    /**
     * list all tables in the current database
     *
     * @param $dbs (reference) array where database names will be stored
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listTables(&$db)
    {
        $result = $db->queryCol('SHOW TABLES', NULL, DB_FETCHMODE_ORDERED);
        if(MDB::isError($result)) {
            return $result;
        }
        for($i = 0, $j = count($result), $tables = array(); $i < $j; ++$i)
        {
            if (!$this->_isSequenceName(&$db, $result[$i]))
                $tables[] = $result[$i];
        }
        return ($tables);
    }

    // }}}
    // {{{ listTableFields()

    /**
     * list all fields in a tables in the current database
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string $table name of table that should be used in method
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listTableFields(&$db, $table)
    {
        $result = $db->query("SHOW COLUMNS FROM $table");
        if(MDB::isError($result)) {
            return $result;
        }
        $columns = $db->getColumnNames($result);
        if(MDB::isError($columns)) {
            $db->freeResult($columns);
            return $columns;
        }
        if(!isset($columns['field'])) {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'List table fields: show columns does not return the table field names');
        }
        $field_column = $columns['field'];
        for($fields = array(), $field = 0; !$db->endOfResult($result); ++$field) {
            $field_name = $db->fetch($result, $field, $field_column);
            if ($field_name != $db->dummy_primary_key)
                $fields[] = $field_name;
        }
        $db->freeResult($result);
        return ($fields);
    }

    // }}}
    // {{{ getTableFieldDefinition()

    /**
     * get the stucture of a field into an array
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $table         name of table that should be used in method
     * @param string    $fields     name of field that should be used in method
      *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function getTableFieldDefinition(&$db, $table, $field)
    {
        $field_name = strtolower($field);
        if ($field_name == $db->dummy_primary_key) {
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Gist table field definiton: '.$db->dummy_primary_key.' is an hidden column');
        }
        $result = $db->query("SHOW COLUMNS FROM $table");
        if(MDB::isError($result)) {
            return $result;
        }
        $columns = $db->getColumnNames($result);
        if(MDB::isError($columns)) {
            $db->freeResult($columns);
            return $columns;
        }
        if (!isset($columns[$column = 'field'])
            || !isset($columns[$column = 'type']))
        {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get table field definition: show columns does not return the column '.$column);
        }
        $field_column = $columns['field'];
        $type_column = $columns['type'];
        while (is_array($row = $db->fetchInto($result))) {
            if ($field_name == strtolower($row[$field_column])) {
                $db_type = strtolower($row[$type_column]);
                $db_type = strtok($db_type, '(), ');
                if ($db_type == 'national') {
                    $db_type = strtok('(), ');
                }
                $length = strtok('(), ');
                $decimal = strtok('(), ');
                $type = array();
                switch($db_type) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'integer':
                    case 'bigint':
                        $type[0] = 'integer';
                        if($length == '1') {
                            $type[1] = 'boolean';
                        }
                        break;

                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                    case 'text':
                    case 'char':
                    case 'varchar':
                        $type[0] = 'text';
                        if($decimal == 'binary') {
                            $type[1] = 'blob';
                        } elseif($length == '1') {
                            $type[1] = 'boolean';
                        } elseif(strstr($db_type, 'text'))
                            $type[1] = 'clob';
                        break;

                    case 'enum':
                    case 'set':
                        $type[0] = 'text';
                        $type[1] = 'integer';
                        break;

                    case 'date':
                        $type[0] = 'date';
                        break;

                    case 'datetime':
                    case 'timestamp':
                        $type[0] = 'timestamp';
                        break;

                    case 'time':
                        $type[0] = 'time';
                        break;

                    case 'float':
                    case 'double':
                    case 'real':
                        $type[0] = 'float';
                        break;

                    case 'decimal':
                    case 'numeric':
                        $type[0] = 'decimal';
                        break;

                    case 'tinyblob':
                    case 'mediumblob':
                    case 'longblob':
                    case 'blob':
                        $type[0] = 'blob';
                        break;

                    case 'year':
                        $type[0] = 'integer';
                        $type[1] = 'date';
                        break;

                    default:
                        return $db->raiseError(DB_ERROR_MANAGER, '', '', 'List table fields: unknown database attribute type');
                }
                unset($notnull);
                if (isset($columns['null'])
                    && $row[$columns['null']] != 'YES')
                {
                    $notnull = 1;
                }
                unset($default);
                if (isset($columns['default'])
                    && isset($row[$columns['default']]))
                {
                    $default=$row[$columns['default']];
                }
                for($definition = array(), $datatype = 0; $datatype < count($type); $datatype++) {
                    $definition[$datatype] = array('type' => $type[$datatype]);
                    if(isset($notnull)) {
                        $definition[$datatype]['notnull'] = 1;
                    }
                    if(isset($default)) {
                        $definition[$datatype]['default'] = $default;
                    }
                    if(strlen($length)) {
                        $definition[$datatype]['length'] = $length;
                    }
                }
                $db->freeResult($result);
                return ($definition);
            }
        }
        if(!$db->options['autofree']) {
            $db->freeResult($result);
        }
        if(MDB::isError($row)) {
            return($row);
        }
        return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get table field definition: it was not specified an existing table column');
    }

    // }}}
    // {{{ createIndex()

    /**
     * get the stucture of a field into an array
     *
     * @param $dbs (reference) array where database names will be stored
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
     *                                        'FIELDS' => array(
     *                                            'user_name' => array(
     *                                                'sorting' => 'ascending'
     *                                            ),
     *                                            'last_login' => array()
     *                                        )
     *                                    )
       *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function createIndex(&$db, $table, $name, $definition)
    {
        $query = "ALTER TABLE $table ADD ".(isset($definition['unique']) ? 'UNIQUE' : 'INDEX')." $name (";
        for($field = 0,reset($definition['FIELDS']);
            $field < count($definition['FIELDS']);
            $field++, next($definition['FIELDS']))
        {
            if ($field>0) {
                $query .= ',';
            }
            $query .= key($definition['FIELDS']);
        }
        $query .= ')';
        return ($db->query($query));
    }

    // }}}
    // {{{ dropIndex()

    /**
     * drop existing index
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $table         name of table that should be used in method
     * @param string    $name         name of the index to be dropped
      *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function dropIndex(&$db, $table, $name)
    {
        return ($db->query("ALTER TABLE $table DROP INDEX $name"));
    }

    // }}}
    // {{{ listTableIndexes()

    /**
     * list all indexes in a table
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $table      name of table that should be used in method
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listTableIndexes(&$db, $table)
    {
        if(MDB::isError($result = $db->query("SHOW INDEX FROM $table"))) {
            return($result);
        }
        if(MDB::isError($columns = $db->getColumnNames($result)))
        {
            $db->freeResult($result);
            return($columns);
        }
        if(!isset($columns['key_name']))
        {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'List table indexes: show index does not return the table index names');
        }
        $indexes_all = $db->fetchCol($result, DB_FETCHMODE_ORDERED, $columns['key_name']);
        for($found = $indexes = array(), $index = 0; $index < count($indexes_all); $index++)
        {
            if ($indexes_all[$index] != 'PRIMARY'
                && !isset($found[$indexes_all[$index]]))
            {
                $indexes[] = $indexes_all[$index];
                $found[$indexes_all[$index]] = 1;
            }
        }
        $db->freeResult($result);
        return($indexes);
    }

    // }}}
    // {{{ getTableIndexDefinition()

    /**
     * get the stucture of an index into an array
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $table      name of table that should be used in method
     * @param string    $index      name of index that should be used in method
      *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function getTableIndexDefinition(&$db, $table, $index)
    {
        $index_name = strtolower($index);
        if($index_name == 'PRIMARY') {
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get table index definition: PRIMARY is an hidden index');
        }
        if(MDB::isError($result = $db->query("SHOW INDEX FROM $table"))) {
            return($result);
        }
        if(MDB::isError($columns = $db->getColumnNames($result)))
        {
            $db->freeResult($result);
            return($columns);
        }
        if(!isset($columns['non_unique'])
            || !isset($columns['key_name'])
            || !isset($columns['column_name'])
            || !isset($columns['collation']))
        {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get table index definition: show index does not return the column '.$column);
        }
        $non_unique_column = $columns['non_unique'];
        $key_name_column = $columns['key_name'];
        $column_name_column = $columns['column_name'];
        $collation_column = $columns['collation'];
        $definition = array();
        while (is_array($row = $db->fetchInto($result))) {
            $key_name = $row[$key_name_column];
            if(!strcmp($index_name, $key_name)) {
                if(!$row[$non_unique_column]) {
                    $definition['unique'] = 1;
                }
                $column_name = $row[$column_name_column];
                $definition['FIELDS'][$column_name] = array();
                if(isset($row[$collation_column])) {
                    $definition['FIELDS'][$column_name]['sorting']=($row[$collation_column]=='A' ? 'ascending' : 'descending');
                }
            }
        }
        $db->freeResult($result);
        if (!isset($definition['FIELDS'])) {
            return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get table index definition: it was not specified an existing table index');
        }
        return ($definition);
    }

    // }}}
    // {{{ createSequence()

    /**
     * create sequence
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $seq_name     name of the sequence to be created
     * @param string    $start         start value of the sequence; default is 1
      *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function createSequence(&$db, $seq_name, $start)
    {
        if(MDB::isError($verify = $this->_verifyTransactionalTableType($db,$db->default_table_type))) {
            return($verify);
        }
        $sequence_name = $db->getSequenceName($seq_name);
        $res = $db->query("CREATE TABLE $sequence_name
            (sequence INT DEFAULT 0 NOT NULL AUTO_INCREMENT, PRIMARY KEY (sequence))");
        if (MDB::isError($res)) {
            return $res;
        }
        if ($start == 1) {
            return DB_OK;
        }
        $res = $db->query("INSERT INTO $sequence_name (sequence) VALUES (".($start-1).')');
        if (!MDB::isError($res)) {
            return DB_OK;
        }
        // Handle error
        $result = $db->query("DROP TABLE $sequence_name");
        if (MDB::isError($result)) {
            return $db->raiseError(DB_ERROR, '', '',
                'Create sequence: could not drop inconsistent sequence table ('.
                $result->getMessage().' ('.$result->getUserinfo().'))');
        }
        return $db->raiseError(DB_ERROR, '', '',
            'Create sequence: could not create sequence table ('.
            $res->getMessage().' ('.$res->getUserinfo().'))');
    }

    // }}}
    // {{{ dropSequence()

    /**
     * drop existing sequence
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $seq_name     name of the sequence to be dropped
      *
     * @access public
     *
     * @return mixed DB_OK on success, a DB error on failure
     */
    function dropSequence(&$db, $seq_name)
    {
        $sequence_name = $db->getSequenceName($seq_name);
        return ($db->query("DROP TABLE $sequence_name"));
    }

    // }}}
    // {{{ listSequences()

    /**
     * list all sequences in the current database
     *
     * @param $dbs (reference) array where database names will be stored
     *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function listSequences(&$db)
    {
        $result = $db->queryCol('SHOW TABLES', NULL, DB_FETCHMODE_ORDERED);
        if(MDB::isError($result)) {
            return $result;
        }
        for($i = 0, $j = count($result), $sequences = array(); $i < $j; ++$i)
        {
            if ($sqn = $db->_isSequenceName(&$db, $result[$i]))
                $sequences[] = $sqn;
        }
        return ($sequences);
    }

    // }}}
    // {{{ getSequenceDefinition()

    /**
     * get the stucture of a sequence into an array
     *
     * @param $dbs (reference) array where database names will be stored
     * @param string    $sequence   name of sequence that should be used in method
      *
     * @access public
     *
     * @return mixed data array on success, a DB error on failure
     */
    function getSequenceDefinition(&$db, $sequence)
    {
        if(MDB::isError($table_names = $db->queryCol('SHOW TABLES', NULL, DB_FETCHMODE_ORDERED))) {
            return($table_names);
        }
        for($i = 0, $j = count($table_names); $i < $j; $i++) {
            if ($sqn = $db->_isSequenceName($db, $table_names[$i])) {
                $start = $db->currId($sqn);
                if (MDB::isError($start)) {
                    return ($start);
                }
                if ($db->support('CurrId')) {
                    $start++;
                } else {
                    $db->warnings[] = 'database does not support getting current sequence value, the sequence value was incremented';
                }
                $definition = array('start' => $start);
                return($definition);
            }
        }
        return $db->raiseError(DB_ERROR_MANAGER, '', '', 'Get sequence definition: it was not specified an existing sequence');
    }
}

}
?>
