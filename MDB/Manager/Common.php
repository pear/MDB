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
// MDB proxy class for RDBMS management methods.
//

if(!defined("MDB_MANAGER_DATABASE_INCLUDED"))
{
    define("MDB_MANAGER_DATABASE_INCLUDED",1);


class MDB_manager_database_class extends PEAR
{
    /* PRIVATE METHODS */

    function getField(&$db, &$field, $field_name, &$query)
    {
        if (!strcmp($field_name, "")) {
            return $db->raiseError(DB_ERROR_NOSUCHFIELD, "", "", "Get field: it was not specified a valid field name (\"$field_name\")");
        }
        switch($field["type"]) {
            case "integer":
                $query = $db->getIntegerDeclaration($field_name, $field);
                break;
            case "text":
                $query = $db->getTextDeclaration($field_name, $field);
                break;
            case "clob":
                $query = $db->getCLOBDeclaration($field_name, $field);
                break;
            case "blob":
                $query = $db->getBLOBDeclaration($field_name, $field);
                break;
            case "boolean":
                $query = $db->getBooleanDeclaration($field_name, $field);
                break;
            case "date":
                $query = $db->getDateDeclaration($field_name, $field);
                break;
            case "timestamp":
                $query = $db->getTimestampDeclaration($field_name, $field);
                break;
            case "time":
                $query = $db->getTimeDeclaration($field_name, $field);
                break;
            case "float":
                $query = $db->getFloatDeclaration($field_name, $field);
                break;
            case "decimal":
                $query = $db->getDecimalDeclaration($field_name, $field);
                break;
            default:
                return $db->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Get field: type \"".$field["type"]."\" is not yet supported");
                break;
        }
        return (DB_OK);
    }

    function getFieldList(&$db, &$fields, &$query_fields)
    {
        for($query_fields = "", reset($fields), $field_number = 0;
            $field_number < count($fields);
            $field_number++,next($fields))
        {
            if ($field_number>0) {
                $query_fields.= ", ";
            }
            $field_name = key($fields);
            $result = $this->getField(&$db, $fields[$field_name], $field_name, $query);
            if (MDB::isError($result)) {
                return $result;
            }
            $query_fields .= $query;
        }
        return (DB_OK);
    }
    
    /* PUBLIC METHODS */

    function createDatabase(&$db, $database)
    {
        return $db->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Create database: database creation is not supported");
    }

    function dropDatabase(&$db, $database)
    {
        return $db->raiseError(DB_ERROR_UNSUPPORTED, "", "", "Drop database: database dropping is not supported");
    }

    function createTable(&$db, $name, &$fields)
    {
        if (!isset($name) || !strcmp($name, "")) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, "", "", "no valid table name specified");
        }
        if (count($fields) == 0) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, "", "", 'no fields specified for table "'.$name.'"');
        }
        $query_fields = "";
        if (!$this->getFieldList($db, $fields, $query_fields)) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, "", "", 'unkown error');
        }
        return ($db->query("CREATE TABLE $name ($query_fields)"));
    }

    function dropTable(&$db, $name)
    {
        return ($db->query("DROP TABLE $name"));
    }

    function alterTable(&$db, $name, &$changes, $check)
    {
        return $db->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            "Alter table: database table alterations are not supported");
    }

    function listDatabases(&$db, &$dbs)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List Databses: list databases is not supported');
    }

    function listUsers(&$db, &$users)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List User: list user is not supported');
    }

    function listViews(&$db, &$views)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List View: list view is not supported');
    }

    function listFunctions(&$db, &$functions)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List Function: list function is not supported');
    }
    
    function listTables(&$db, &$tables)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List tables: list tables is not supported');
    }

    function listTableFields(&$db, $table, &$fields)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List table fields: list table fields is not supported');
    }

    function getTableFieldDefinition(&$db, $table, $field, $definition)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Get table field definition: table field definition is not supported');
    }

    function createIndex(&$db, $table, $name, $definition)
    {
        $query = "CREATE";
        if (isset($definition["unique"])) {
            $query .= " UNIQUE";
        }
        $query .= " INDEX $name ON $table (";
        for($field = 0,reset($definition["FIELDS"]); $field<count($definition["FIELDS"]); $field++,next($definition["FIELDS"])) {
            if ($field>0) {
                $query.= ", ";
            }
            $field_name = Key($definition["FIELDS"]);
            $query.= $field_name;
            if ($db->support("IndexSorting") && isset($definition["FIELDS"][$field_name]["sorting"])) {
                switch($definition["FIELDS"][$field_name]["sorting"]) {
                    case "ascending":
                        $query.= " ASC";
                        break;
                    case "descending":
                        $query.= " DESC";
                        break;
                }
            }
        }
        $query.= ")";
        return ($db->query($query));
    }

    function dropIndex(&$db, $table, $name)
    {
        return ($db->query("DROP INDEX $name"));
    }

    function createSequence(&$db, $name, $start)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Create Sequence: sequence creation not supported');
    }

    function dropSequence(&$db, $name)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'Drop Sequence: sequence dropping not supported');
    }

    function listSequences(&$db, &$sequences)
    {
        return $db->raiseError(DB_ERROR_NOT_CAPABLE, "", "", 
            'List sequences: List sequences is not supported');
    }

};
}
?>
