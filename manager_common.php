<?
if(!defined("MDB_MANAGER_DATABASE_INCLUDED"))
{
    define("MDB_MANAGER_DATABASE_INCLUDED",1);

/*
 * manager_common.php
 *
 * @(#) $Header$
 *
 */

class MDB_manager_database_class extends PEAR
{
    /* PRIVATE METHODS */

    function getField(&$db, &$field, $field_name, &$query)
    {
        if (!strcmp($field_name, "")) {
            //return($this->setError("Get field", "it was not specified a valid field name (\"$field_name\")"));
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
        return(1);
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
        return(1);
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
        if (!$db->getFieldList($db, $fields, $query_fields)) {
            // XXX needs more checking
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, "", "", 'unkown error');
        }
        return($db->query("CREATE TABLE $name ($query_fields)"));
    }

    function dropTable(&$db, $name)
    {
        return($db->query("DROP TABLE $name"));
    }

    function alterTable(&$db, $name, &$changes, $check)
    {
        return $db->raiseError(DB_ERROR_UNSUPPORTED, "", "", 
            "Alter table: database table alterations are not supported");
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
            if ($db->Support("IndexSorting") && isset($definition["FIELDS"][$field_name]["sorting"])) {
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
        return($db->query($query));
    }

    function dropIndex(&$db, $table, $name)
    {
        return($db->query("DROP INDEX $name"));
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