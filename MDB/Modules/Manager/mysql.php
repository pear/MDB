<?
if(!defined("MDB_MANAGER_MYSQL_INCLUDED"))
{
    define("MDB_MANAGER_MYSQL_INCLUDED",1);

/*
 * manager_mysql.php
 *
 * @(#) $Header$
 *
 */

class MDB_manager_mysql_class extends MDB_manager_database_class
{
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

    function dropDatabase(&$db, $name)
    {
        if (MDB::isError($result = $db->connect())) {
            return $result;
        }
        if (!mysql_drop_db($name, $this->connection)) {
            return $db->mysqlRaiseError(DB_ERROR_CANNOT_DROP);
        }
        return (DB_OK);
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
            // XXX needs more checking
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, "", "", 'unkown error');
        }
        if (isset($db->supported["Transactions"])) {
            $query_fields .= ", dummy_primary_key INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (dummy_primary_key)";
        }
        return ($db->query("CREATE TABLE $name ($query_fields)".(isset($db->supported["Transactions"]) ? " TYPE = BDB" : "")));
    }

    function alterTable(&$db, $name, &$changes, $check)
    {
        if ($check) {
            for($change = 0,reset($changes);
                $change < count($changes);
                next($changes), $change++)
            {
                switch(Key($changes)) {
                    case "AddedFields":
                    case "RemovedFields":
                    case "ChangedFields":
                    case "RenamedFields":
                    case "name":
                        break;
                    default:
                        return $db->raiseError(DB_ERROR_CANNOT_ALTER, "", "", 
                            'Alter table: change type "'.Key($changes).'" not yet supported');
                }
            }
            return (DB_OK);
        } else {
            $query = (isset($changes["name"]) ? "RENAME AS ".$changes["name"] : "");
            if (isset($changes["AddedFields"]))    {
                $fields = $changes["AddedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $query .= "ADD ".$fields[key($fields)]["Declaration"];
                }
            }
            if (isset($changes["RemovedFields"])) {
                $fields = $changes["RemovedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $query .= "DROP ".Key($fields);
                }
            }
            $renamed_fields = array();
            if (isset($changes["RenamedFields"])) {
                $fields = $changes["RenamedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    $renamed_fields[$fields[key($fields)]["name"]] = key($fields);
                }
            }
            if (isset($changes["ChangedFields"])) {
                $fields = $changes["ChangedFields"];
                for($field = 0,reset($fields);
                    $field<count($fields);
                    next($fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    if (isset($renamed_fields[key($fields)])) {
                        $field_name = $renamed_fields[key($fields)];
                        unset($renamed_fields[key($fields)]);
                    } else {
                        $field_name = key($fields);
                    }
                    $query .= "CHANGE $field_name ".$fields[key($fields)]["Declaration"];
                }
            }
            if (count($renamed_fields))
            {
                for($field = 0,reset($renamed_fields);
                    $field<count($renamed_fields);
                    next($renamed_fields), $field++)
                {
                    if (strcmp($query, "")) {
                        $query .= ",";
                    }
                    $old_field_name = $renamed_fields[Key($renamed_fields)];
                    $query .= "CHANGE $old_field_name ".$changes["RenamedFields"][$old_field_name]["Declaration"];
                }
            }
            return ($db->query("ALTER TABLE $name $query"));
        }
    }

    function listDatabases(&$db, &$dbs)
    {
        $result = $db->queryCol("SHOW DATABASES", $dbs);
        if(MDB::isError($result)) {
            return $result;
        }
        return(1);
    }

    function listUsers(&$db, &$users)
    {
        $result = $db->queryCol("SELECT DISTINCT USER FROM USER", $users);
        if(MDB::isError($result)) {
            return $result;
        }
        return(1);
    }

    function listTables(&$db, &$tables)
    {
        $result = $db->queryCol("SHOW TABLES", $table_names);
        if(MDB::isError($result)) {
            return $result;
        }
        $prefix_length = strlen($db->sequence_prefix);
        for($tables = array(), $table = 0; $table < count($table_names); ++$table)
        {
            if (substr($table_names[$table], 0, $prefix_length) != $db->sequence_prefix)
                $tables[] = $table_names[$table];
        }
        return(1);
    }

    function listTableFields(&$db, $table, &$fields)
    {
        $result = $db->query("SHOW COLUMNS FROM $table");
        if(MDB::isError($result)) {
            return $result;
        }
        $result2 = $db->getColumnNames($result, $columns);
        if(MDB::isError($result2)) {
            $db->freeResult($result);
            return $result2;
        }
        if(!isset($columns["field"])) {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, "", "", 'List table fields: show columns does not return the table field names');
        }
        $field_column = $columns["field"];
        for($fields = array(), $field = 0; !$db->endOfResult($result); ++$field) {
            $field_name = $db->fetch($result, $field, $field_column);
            if ($field_name != $db->dummy_primary_key)
                $fields[] = $field_name;
        }
        $db->freeResult($result);
        return(1);
    }

    function getTableFieldDefinition(&$db, $table, $field, &$definition)
    {
        $field_name = strtolower($field);
        if ($field_name == $db->dummy_primary_key) {
            return $db->raiseError(DB_ERROR_MANAGER, "", "", 'List table fields: '.$db->dummy_primary_key.' is an hidden column');
        }
        $result = $db->query("SHOW COLUMNS FROM $table");
        if(MDB::isError($result)) {
            return $result;
        }
        $result2 = $db->getColumnNames($result, $columns);
        if(MDB::isError($result)) {
            $db->freeResult($result);
            return $result2;
        }
        if (!isset($columns[$column = "field"])
            && !isset($columns[$column = "type"]))
        {
            $db->freeResult($result);
            return $db->raiseError(DB_ERROR_MANAGER, "", "", 'List table fields: show columns does not return the column '.$column);
        }
        $field_column = $columns["field"];
        $type_column = $columns["type"];
        while (DB_OK === $res = $db->fetchInto($result, $row)) {
            if ($field_name == strtolower($row[$field_column])) {
                $db_type = strtolower($row[$type_column]);
                $db_type = strtok($db_type, "(), ");
                if ($db_type == "national") {
                    $db_type = strtok("(), ");
                }
                $length = strtok("(), ");
                $decimal = strtok("(), ");
                $type = array();
                switch($db_type) {
                    case "tinyint":
                    case "smallint":
                    case "mediumint":
                    case "int":
                    case "integer":
                    case "bigint":
                        $type[0] = "integer";
                        if($length == "1") {
                            $type[1] = "boolean";
                        }
                        break;

                    case "tinytext":
                    case "mediumtext":
                    case "longtext":
                    case "text":
                    case "char":
                    case "varchar":
                        $type[0] = "text";
                        if($decimal == "binary") {
                            $type[1] = "blob";
                        } elseif($length == "1") {
                            $type[1] = "boolean";
                        } elseif(strstr($db_type, "text"))
                            $type[1] = "clob";
                        break;

                    case "enum":
                    case "set":
                        $type[0] = "text";
                        $type[1] = "integer";
                        break;

                    case "date":
                        $type[0] = "date";
                        break;

                    case "datetime":
                    case "timestamp":
                        $type[0] = "timestamp";
                        break;

                    case "time":
                        $type[0] = "time";
                        break;

                    case "float":
                    case "double":
                    case "real":
                        $type[0] = "float";
                        break;

                    case "decimal":
                    case "numeric":
                        $type[0] = "decimal";
                        break;

                    case "tinyblob":
                    case "mediumblob":
                    case "longblob":
                    case "blob":
                        $type[0] = "blob";
                        break;

                    case "year":
                        $type[0] = "integer";
                        $type[1] = "date";
                        break;

                    default:
                        return $db->raiseError(DB_ERROR_MANAGER, "", "", 'List table fields: unknown database attribute type');
                }
                unset($notnull);
                if (isset($columns["null"])
                    && $row[$columns["null"]] != "YES")
                {
                    $notnull = 1;
                }
                unset($default);
                if (isset($columns["default"])
                    && isset($row[$columns["default"]]))
                {
                    $default=$row[$columns["default"]];
                }
                for($definition = array(), $datatype = 0; $datatype < count($type); $datatype++) {
                    $definition[$datatype] = array("type" => $type[$datatype]);
                    if(isset($notnull)) {
                        $definition[$datatype]["notnull"] = 1;
                    }
                    if(isset($default)) {
                        $definition[$datatype]["default"] = $default;
                    }
                    if(strlen($length)) {
                        $definition[$datatype]["length"] = $length;
                    }
                }
                $db->freeResult($result);
                return(DB_OK);
            }
        }
        if(!$db->autofree) {
            $db->freeResult($result);
        }
        if(MDB::IsError($res)) {
            return($res);
        }
        return $db->raiseError(DB_ERROR_MANAGER, "", "", 'List table fields: it was not specified an existing table column');
    }

    function createSequence(&$db, $name, $start)
    {
        $sequence_name = $db->sequence_prefix.$name;
        $res = $db->query("CREATE TABLE $sequence_name
            (sequence INT DEFAULT 0 NOT NULL AUTO_INCREMENT, PRIMARY KEY (sequence))");
        if (MDB::isError($res)) {
            return $res;
        }
        if ($start == 1) {
            return 1;
        }
        $res = $db->query("INSERT INTO $sequence_name (sequence) VALUES (".($start-1).")");
        if (!MDB::isError($res)) {
            return 1;
        }
        // Handle error
        $result = $db->query("DROP TABLE $sequence_name");
        if (MDB::isError($result)) {
            return $db->raiseError(DB_ERROR, "", "", 
                'Create sequence: could not drop inconsistent sequence table ('.
                $result->getMessage().' ('.$result->getUserinfo().'))');
        }
        return $db->raiseError(DB_ERROR, "", "", 
            'Create sequence: could not create sequence table ('.
            $res->getMessage().' ('.$res->getUserinfo().'))');
    }

    function dropSequence(&$db, $name)
    {
        $sequence_name = $db->sequence_prefix.$name;
        return ($db->query("DROP TABLE $sequence_name"));
    }

    function createIndex(&$db, $table, $name, $definition)
    {
        $query = "ALTER TABLE $table ADD ".(isset($definition["unique"]) ? "UNIQUE" : "INDEX")." $name (";
        for($field = 0,reset($definition["FIELDS"]);
            $field < count($definition["FIELDS"]);
            $field++, next($definition["FIELDS"]))
        {
            if ($field>0) {
                $query .= ",";
            }
            $query .= key($definition["FIELDS"]);
        }
        $query .= ")";
        return ($db->query($query));
    }

    function dropIndex(&$db, $table, $name)
    {
        return ($db->query("ALTER TABLE $table DROP INDEX $name"));
    }

    function listSequences(&$db, &$sequences)
    {
        $result = $db->queryCol("SHOW TABLES", $sequences);
        if(MDB::isError($result)) {
            return $result;
        }
        $prefix_length = strlen($db->sequence_prefix);
        for($sequences = array(),$sequence = 0;$sequence < count($sequences); ++$sequence) {
            if (substr($sequences[$sequence], 0, $prefix_length) == $db->sequence_prefix) {
                $sequences[] = substr($sequences[$sequence], $prefix_length);
            }
        }
        return(1);
    }

};
}
?>