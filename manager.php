<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Lukas Smith <smith@dybnet.de>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//
// MDB XML Schema Manager
//

require_once("MDB.php");
require_once("parser.php");
require_once("xml_parser.php");

/**
* The database manager is a class that provides a set of database 
* management services like installing, altering and dumping the data 
* structures of databases.
*
* @package MDB
* @author  Lukas Smith <smith@dybnet.de>
*/ 

class MDB_manager extends PEAR
{
    var $fail_on_invalid_names = 1;
    var $error = "";
    var $warnings = array();
    var $database;
    var $database_definition = array(
        "name" => "",
        "create" => 0,
        "TABLES" => array()
    );

    function setupDatabase($dsninfo, $options = FALSE)
    {
        if (isset($options["debug"])) {
            $this->debug = $options["debug"];
        }

        $this->database = MDB::connect($dsninfo, $options);
        if (MDB::isError($this->database))
            return $this->database;

        if (!isset($options["debug"])) {
            $this->database->captureDebugOutput(1);
        }
        return(1);
    }

    function closeSetup()
    {
        if (is_object($this->database) && !MDB::isError($this->database)) {
            $this->database->close();
        }
    }

    function getField(&$field, $field_name, $declaration, &$query)
    {
        if (!strcmp($field_name, "")) {
            return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                'no valid field name ("'.$field_name.'") specified', 'MDB_Error', TRUE);
        }
        switch($field["type"]) {
            case "integer":
                if ($declaration) {
                    $query = $this->database->getIntegerDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "text":
                if ($declaration) {
                    $query = $this->database->getTextDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "clob":
                if ($declaration) {
                    $query = $this->database->getClobDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "blob":
                if ($declaration) {
                    $query = $this->database->getBlobDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "boolean":
                if ($declaration) {
                    $query = $this->database->getBooleanDeclaration(field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "date":
                if ($declaration) {
                    $query = $this->database->getDateDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "timestamp":
                if ($declaration) {
                    $query = $this->database->getTimestampDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "time":
                if ($declaration) {
                    $query = $this->database->getTimeDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "float":
                if ($declaration) {
                    $query = $this->database->getFloatDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            case "decimal":
                if ($declaration) {
                    $query = $this->database->getDecimalDeclaration($field_name, $field);
                } else {
                    $query = $field_name;
                }
                break;
            default:
                return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                    'type "'.$field["type"].'" is not yet supported', 'MDB_Error', TRUE);
        }
        return 1;
    }

    function getFieldList($fields, $declaration, &$query_fields)
    {
        for($query_fields = "", reset($fields), $field_number = 0;
            $field_number < count($fields);
            $field_number++, next($fields))
        {
            if ($field_number>0) {
                $query_fields .= ", ";
            }
            $field_name = key($fields);
            $result = $this->getField($fields[$field_name], $field_name, $declaration, $query);
            if (MDB::isError($result)) {
                return $result;
            }
            $query_fields .= $query;
        }
        return 1;
    }

    function getFields($table, &$fields)
    {
        return($this->getFieldList($this->database_definition["TABLES"][$table]["FIELDS"], 0, $fields));
    }

    function createTable($table_name, $table)
    {
        $this->database->debug("Create table: ".$table_name);
        
        $result = $this->database->createTable($table_name, $table["FIELDS"]);
        if (MDB::isError($result)) {
            return $result;
        }
        $success = 1;
        $error = "";
        if (isset($table["initialization"]))    {
            $instructions = $table["initialization"];
            for(reset($instructions), $instruction = 0;
                $success && $instruction < count($instructions);
                $instruction++, next($instructions))
            {
                switch($instructions[$instruction]["type"]) {
                    case "insert":
                        $fields = $instructions[$instruction]["FIELDS"];
                        for($query_fields = $query_values = "", reset($fields), $field_number = 0;
                            $field_number < count($fields);
                            $field_number++, next($fields))
                        {
                            if ($field_number>0) {
                                $query_fields .= ",";
                                $query_values .= ",";
                            }
                            $field_name = key($fields);
                            $field = $table["FIELDS"][$field_name];
                            $result = $this->getField($field, $field_name, 0, $query);
                            if (MDB::isError($result)) {
                                return $result;
                            }
                            $query_fields .= $query;
                            $query_values .= "?";
                        }
                        $result = $prepared_query = $this->database->prepareQuery(
                            "INSERT INTO $table_name ($query_fields) VALUES ($query_values)");
                        if (!MDB::isError($prepared_query))
                        {
                            for($lobs = array(), reset($fields), $field_number = 0;
                                $field_number < count($fields);
                                $field_number++, next($fields))
                            {
                                $field_name = key($fields);
                                $field = $table["FIELDS"][$field_name];
                                $res = $this->getField($field, $field_name, 0, $query);
                                if (MDB::isError($res)) {
                                    return $res;
                                }
                                switch($field["type"]) {
                                    case "integer":
                                        $result = $this->database->querySetInteger($prepared_query,
                                            $field_number+1,intval($fields[$field_name]));
                                        break;
                                    case "text":
                                        $result = $this->database->querySetText($prepared_query, 
                                            $field_number+1, $fields[$field_name]);
                                        break;
                                    case "clob":
                                        $lob_definition = array(
                                            "Database" =>$this,
                                            "Error" =>"",
                                            "Data" =>$fields[$field_name]
                                        );
                                        $lob = count($lobs);
                                        if (!($success = createLOB($lob_definition, $lobs[$lob])))
                                        {
                                            $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                                                $lob_definition["Error"], 'MDB_Error', TRUE);
                                            break;
                                        }
                                        $result = $this->database->querySetCLOB($prepared_query, 
                                            $field_number+1, $lobs[$lob], $field_name);
                                        break;
                                    case "blob":
                                        $lob_definition = array(
                                            "Database" =>$this,
                                            "Error" =>"",
                                            "Data" =>$fields[$field_name]
                                        );
                                        $lob = count($lobs);
                                        if (!($success = createLOB($lob_definition, $lobs[$lob]))) {
                                            $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                                                $lob_definition["Error"], 'MDB_Error', TRUE);
                                            break;
                                        }
                                        $result = $this->database->querySetBLOB($prepared_query, 
                                            $field_number+1, $lobs[$lob], $field_name);
                                        break;
                                    case "boolean":
                                        $result = $this->database->querySetBoolean($prepared_query, 
                                            $field_number+1,intval($fields[$field_name]));
                                        break;
                                    case "date":
                                        $result = $this->database->querySetDate($prepared_query, 
                                            $field_number+1, $fields[$field_name]);
                                        break;
                                    case "timestamp":
                                        $result = $this->database->querySetTimestamp($prepared_query, 
                                            $field_number+1, $fields[$field_name]);
                                        break;
                                    case "time":
                                        $result = $this->database->querySetTime($prepared_query, 
                                            $field_number+1, $fields[$field_name]);
                                        break;
                                    case "float":
                                        $result = $this->database->querySetFloat($prepared_query, 
                                            $field_number+1,doubleval($fields[$field_name]));
                                        break;
                                    case "decimal":
                                        $result = $this->database->querySetDecimal($prepared_query, 
                                            $field_number+1, $fields[$field_name]);
                                        break;
                                    default:
                                        $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                                            'type "'.$field["type"].'" is not yet supported', 'MDB_Error', TRUE);
                                        break;
                                }
                                if (MDB::isError($result)) {
                                    break;
                                }
                            }
                            if (!MDB::isError($result)) {
                                $result = $this->database->executeQuery($prepared_query);
                            }
                            for($lob = 0; $lob < count($lobs); $lob++) {
                                destroyLOB($lobs[$lob]);
                            }
                            $this->database->freePreparedQuery($prepared_query);
                        }
                        break;
                }
            }
        };
        if (!MDB::isError($result) && isset($table["INDEXES"])) {
            if (!$this->database->support("Indexes")) {
                return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                    'indexes are not supported', 'MDB_Error', TRUE);
            }
            $indexes = $table["INDEXES"];
            for($index = 0, reset($indexes);
                $index < count($indexes);
                next($indexes), $index++)
            {
                $result = $this->database->createIndex($table_name,key($indexes), $indexes[key($indexes)]);
                if (MDB::isError($result)) {
                    break;
                }
            }
        }
        if (MDB::isError($result)) {
            $res = $this->database->dropTable($table_name);
            if (MDB::isError($res)) {
                $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                    'could not drop the table ('
                    .$result->getMessage().' ('.$result->getUserinfo(),'))',
                    'MDB_Error', TRUE);
            }
        }
        return $result;
    }

    function dropTable($table_name)
    {
        return $this->database->dropTable($table_name);
    }

    function createSequence($sequence_name, $sequence, $created_on_table)
    {
        if (!$this->database->support("Sequences")) {
            return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                'sequences are not supported', 'MDB_Error', TRUE);
        }
        $this->database->debug("Create sequence: ".$sequence_name);
        if (!isset($sequence_name) || !strcmp($sequence_name, "")) {
            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                'no valid sequence name specified', 'MDB_Error', TRUE);
        }
        $start = $sequence["start"];
        if (isset($sequence["on"]) && !$created_on_table) {
            $table = $sequence["on"]["table"];
            $field = $sequence["on"]["field"];
            if ($this->database->support("Summaryfunctions")) {
                $field = "MAX($field)";
            }
            $result = $this->database->query("SELECT $field FROM $table");
            if (MDB::isError($result)) {
                return $result;
            }
            if (($rows = $this->database->numRows($result))) {
                for($row = 0; $row < $rows; $row++)    {
                    if (!$this->database->resultIsNull($result, $row, 0)
                        && ($value = $this->database->fetch($result, $row, 0) + 1) > $start)
                    
                    {
                        $start = $value;
                    }
                }
            }
            $this->database->freeResult($result);
        }
        $result = $this->database->createSequence($sequence_name, $start);
        
        return $result;
    }

    function dropSequence($sequence_name)
    {
        return $this->database->dropSequence($sequence_name);
    }

    
    /**
     * Create a database space within which may be created database objects 
     * like tables, indexes and sequences. The implementation of this function 
     * is highly DBMS specific and may require special permissions to run 
     * successfully. Consult the documentation or the DBMS drivers that you 
     * use to be aware of eventual configuration requirements.
     *
     * @access public
     *
     * @return mixed true on success, or a MDB error object
     */
     
    function createDatabase()
    {
        if (!isset($this->database_definition["name"])
            || !strcmp($this->database_definition["name"], ""))
        {
            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                'no valid database name specified', 'MDB_Error', TRUE);
        }
        $create = (isset($this->database_definition["create"]) && $this->database_definition["create"]);
        if ($create) {
            $this->database->debug("Create database: ".$this->database_definition["name"]);
            $result = $this->database->createDatabase($this->database_definition["name"]);
            if (MDB::isError($result)) {
                $this->database->debug("Create database error ");
                return $result;
            }
        }
        $previous_database_name = $this->database->setDatabase($this->database_definition["name"]);
        if (($support_transactions = $this->database->support("Transactions")) 
            && MDB::isError($result = $this->database->autoCommit(FALSE)))
        {
            return $result;
        }

        $created_objects = 0;
        for($error = "", reset($this->database_definition["TABLES"]), $table = 0;
            $table < count($this->database_definition["TABLES"]);
            next($this->database_definition["TABLES"]), $table++)
        {
            $table_name = key($this->database_definition["TABLES"]);
            
            $result = $this->createTable($table_name, $this->database_definition["TABLES"][$table_name]);
            if (MDB::isError($result)) {
                break;
            }
            $created_objects++;
        }
        if (!MDB::isError($result) 
            && isset($this->database_definition["SEQUENCES"]))
        {
            for($error = "", reset($this->database_definition["SEQUENCES"]), $sequence = 0;
                $sequence < count($this->database_definition["SEQUENCES"]);
                next($this->database_definition["SEQUENCES"]), $sequence++)
            {
                $sequence_name = key($this->database_definition["SEQUENCES"]);
                
                $result = $this->createSequence($sequence_name, 
                    $this->database_definition["SEQUENCES"][$sequence_name], 1);
                
                if (MDB::isError($result)) {
                    break;
                }
                $created_objects++;
            }
        }

        if (MDB::isError($result)) {
            if ($created_objects) {
                if ($support_transactions) {
                    $res = $this->database->rollback();
                    if (MDB::isError($res)) 
                        $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                            'Could not rollback the partially created database alterations ('
                            .$result->getMessage().' ('.$result->getUserinfo(),'))',
                            'MDB_Error', TRUE);
                } else {
                    $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                        'the database was only partially created ('
                        .$result->getMessage().' ('.$result->getUserinfo(),'))',
                        'MDB_Error', TRUE);
                }
            }
        } else {
            if ($support_transactions) {
                $res = $this->database->autoCommit(TRUE);
                if (MDB::isError($res)) 
                    $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                        'Could not end transaction after successfully created the database ('
                        .$result->getMessage().' ('.$result->getUserinfo(),'))',
                        'MDB_Error', TRUE);
            }
        }
        
        $this->database->setDatabase($previous_database_name);
        
        if (MDB::isError($result)
            && $create
            && MDB::isError($res = $this->database->dropDatabase($this->database_definition["name"])))
        {
            $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                'Could not drop the created database after unsuccessful creation attempt ('
                .$result->getMessage().' ('.$result->getUserinfo(),'))',
                'MDB_Error', TRUE);
        }
        
        return $result;
    }

    function addDefinitionChange(&$changes, $definition, $item, $change)
    {
        if (!isset($changes[$definition][$item])) {
            $changes[$definition][$item] = array();
        }
        for($change_number = 0, reset($change);
            $change_number < count($change);
            next($change), $change_number++)
        {
            $name = key($change);
            if (!strcmp(gettype($change[$name]), "array")) {
                if (!isset($changes[$definition][$item][$name])) {
                    $changes[$definition][$item][$name] = array();
                }
                $change_parts = $change[$name];
                for($change_part = 0, reset($change_parts);
                    $change_part < count($change_parts);
                    next($change_parts), $change_part++)
                {
                    $changes[$definition][$item][$name][key($change_parts)] = $change_parts[key($change_parts)];
                }
            } else {
                $changes[$definition][$item][key($change)] = $change[key($change)];
            }
        }
    }

    function compareDefinitions(&$previous_definition, &$changes)
    {
        $changes = array();
        for($defined_tables = array(), reset($this->database_definition["TABLES"]), $table = 0;
            $table < count($this->database_definition["TABLES"]);
            next($this->database_definition["TABLES"]), $table++)
        {
            $table_name = key($this->database_definition["TABLES"]);
            $was_table_name = $this->database_definition["TABLES"][$table_name]["was"];
            if (isset($previous_definition["TABLES"][$table_name])
                && isset($previous_definition["TABLES"][$table_name]["was"])
                && !strcmp($previous_definition["TABLES"][$table_name]["was"], $was_table_name))
            {
                $was_table_name = $table_name;
            }
            if (isset($previous_definition["TABLES"][$was_table_name])) {
                if (strcmp($was_table_name, $table_name)) {
                    $this->addDefinitionChange($changes, "TABLES", $was_table_name,array("name" =>$table_name));
                    $this->database->debug("Renamed table '$was_table_name' to '$table_name'");
                }
                if (isset($defined_tables[$was_table_name])) {
                    return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                        'the table "'.$was_table_name.'" was specified as base of more than of table of the database',
                        'MDB_Error', TRUE);
                }
                $defined_tables[$was_table_name] = 1;

                $fields = $this->database_definition["TABLES"][$table_name]["FIELDS"];
                $previous_fields = $previous_definition["TABLES"][$was_table_name]["FIELDS"];
                for($defined_fields = array(), reset($fields), $field = 0;
                    $field < count($fields);
                    next($fields), $field++)
                {
                    $field_name = key($fields);
                    $was_field_name = $fields[$field_name]["was"];
                    if (isset($previous_fields[$field_name])
                        && isset($previous_fields[$field_name]["was"])
                        && !strcmp($previous_fields[$field_name]["was"], $was_field_name))
                    {
                        $was_field_name = $field_name;
                    }
                    if (isset($previous_fields[$was_field_name])) {
                        if (strcmp($was_field_name, $field_name)) {
                            $field_declaration = $fields[$field_name];
                            $result = $this->getField($field_declaration, $field_name, 1, $query);
                            if (MDB::isError($result)) {
                                return $result;
                            }
                            $this->addDefinitionChange($changes, "TABLES", $was_table_name,
                                array("RenamedFields" =>array($was_field_name =>array(
                                    "name" =>$field_name,
                                    "Declaration" =>$query
                                )))
                            );
                            $this->database->debug("Renamed field '$was_field_name' to '$field_name' in table '$table_name'");
                        }
                        if (isset($defined_fields[$was_field_name])) {
                            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                                'the field "'.$was_table_name.'" was specified as base of more than one field of table',
                                'MDB_Error', TRUE);
                        }
                        $defined_fields[$was_field_name] = 1;
                        $change = array();
                        if (!strcmp($fields[$field_name]["type"], $previous_fields[$was_field_name]["type"])) {
                            switch($fields[$field_name]["type"]) {
                                case "integer":
                                    $previous_unsigned = isset($previous_fields[$was_field_name]["unsigned"]);
                                    $unsigned = isset($fields[$field_name]["unsigned"]);
                                    if (strcmp($previous_unsigned, $unsigned)) {
                                        $change["unsigned"] = $unsigned;
                                        $this->database->debug("Changed field '$field_name' type from '".($previous_unsigned ? "unsigned " : "").$previous_fields[$was_field_name]["type"]."' to '".($unsigned ? "unsigned " : "").$fields[$field_name]["type"]."' in table '$table_name'");
                                    }
                                    break;
                                case "text":
                                case "clob":
                                case "blob":
                                    $previous_length = (isset($previous_fields[$was_field_name]["length"]) ? $previous_fields[$was_field_name]["length"] : 0);
                                    $length = (isset($fields[$field_name]["length"]) ? $fields[$field_name]["length"] : 0);
                                    if (strcmp($previous_length, $length)) {
                                        $change["length"] = $length;
                                        $this->database->debug("Changed field '$field_name' length from '".$previous_fields[$was_field_name]["type"].($previous_length == 0 ? " no length" : "($previous_length)")."' to '".$fields[$field_name]["type"].($length == 0 ? " no length" : "($length)")."' in table '$table_name'");
                                    }
                                    break;
                                case "date":
                                case "timestamp":
                                case "time":
                                case "boolean":
                                case "float":
                                case "decimal":
                                    break;
                                default:
                                    return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                                        'type "'.$fields[$field_name]["type"].'" is not yet supported',
                                        'MDB_Error', TRUE);
                            }

                            $previous_notnull = isset($previous_fields[$was_field_name]["notnull"]);
                            $notnull = isset($fields[$field_name]["notnull"]);
                            if ($previous_notnull != $notnull) {
                                $change["ChangedNotNull"] = 1;
                                if ($notnull) {
                                    $change["notnull"] = isset($fields[$field_name]["notnull"]);
                                }
                                $this->database->debug("Changed field '$field_name' notnull from $previous_notnull to $notnull in table '$table_name'");
                            }

                            $previous_default = isset($previous_fields[$was_field_name]["default"]);
                            $default = isset($fields[$field_name]["default"]);
                            if (strcmp($previous_default, $default)) {
                                $change["ChangedDefault"] = 1;
                                if ($default) {
                                    $change["default"] = $fields[$field_name]["default"];
                                }
                                $this->database->debug("Changed field '$field_name' default from ".($previous_default ? "'".$previous_fields[$was_field_name]["default"]."'" : "NULL")." TO ".($default ? "'".$fields[$field_name]["default"]."'" : "NULL")." IN TABLE '$table_name'");
                            } else {
                                if ($default
                                    && strcmp($previous_fields[$was_field_name]["default"], $fields[$field_name]["default"]))
                                {
                                    $change["ChangedDefault"] = 1;
                                    $change["default"] = $fields[$field_name]["default"];
                                    $this->database->debug("Changed field '$field_name' default from '".$previous_fields[$was_field_name]["default"]."' to '".$fields[$field_name]["default"]."' in table '$table_name'");
                                }
                            }
                        } else {
                            $change["type"] = $fields[$field_name]["type"];
                            $this->database->debug("Changed field '$field_name' type from '".$previous_fields[$was_field_name]["type"]."' to '".$fields[$field_name]["type"]."' in table '$table_name'");
                        }
                        if (count($change)) {
                            $field_declaration = $fields[$field_name];
                            $result = $this->getField($field_declaration, $field_name, 1, $query);
                            if (MDB::isError($result)) {
                                return $result;
                            }
                            $change["Declaration"] = $query;
                            $change["Definition"] = $field_declaration;
                            $this->addDefinitionChange($changes, "TABLES", $was_table_name,array("ChangedFields" =>array($field_name =>$change)));
                        }
                    } else {
                        if (strcmp($field_name, $was_field_name)) {
                            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                                'it was specified a previous field name ("'
                                .$was_field_name.'") for field "'.$field_name.'" of table "'
                                .$table_name.'" that does not exist',
                                'MDB_Error', TRUE);
                        }
                        $field_declaration = $fields[$field_name];
                        $result = $this->getField($field_declaration, $field_name, 1, $query);
                        if (MDB::isError($result)) {
                            return $result;
                        }
                        $field_declaration["Declaration"] = $query;
                        $this->addDefinitionChange($changes, "TABLES", $table_name,array("AddedFields" =>array($field_name =>$field_declaration)));
                        $this->database->debug("Added field '$field_name' to table '$table_name'");
                    }
                }
                for(reset($previous_fields), $field = 0;
                    $field < count($previous_fields);
                    next($previous_fields), $field++)
                {
                    $field_name = key($previous_fields);
                    if (!isset($defined_fields[$field_name])) {
                        $this->addDefinitionChange($changes, "TABLES", $table_name,array("RemovedFields" =>array($field_name =>array())));
                        $this->database->debug("Removed field '$field_name' from table '$table_name'");
                    }
                }

                $indexes = (isset($this->database_definition["TABLES"][$table_name]["INDEXES"]) ? $this->database_definition["TABLES"][$table_name]["INDEXES"] : array());
                $previous_indexes = (isset($previous_definition["TABLES"][$was_table_name]["INDEXES"]) ? $previous_definition["TABLES"][$was_table_name]["INDEXES"] : array());
                for($defined_indexes = array(), reset($indexes), $index = 0;
                    $index < count($indexes);
                    next($indexes), $index++)
                {
                    $index_name = key($indexes);
                    $was_index_name = $indexes[$index_name]["was"];
                    if (isset($previous_indexes[$index_name])
                        && isset($previous_indexes[$index_name]["was"])
                        && !strcmp($previous_indexes[$index_name]["was"], $was_index_name))
                    {
                        $was_index_name = $index_name;
                    }
                    if (isset($previous_indexes[$was_index_name])) {
                        $change = array();

                        if (strcmp($was_index_name, $index_name)) {
                            $change["name"] = $was_index_name;
                            $this->database->debug("Changed index '$was_index_name' name to '$index_name' in table '$table_name'");
                        }
                        if (isset($defined_indexes[$was_index_name])) {
                            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                                'the index "'.$was_index_name.'" was specified as base of'
                                .' more than one index of table "'.$table_name.'"',
                                'MDB_Error', TRUE);
                        }
                        $defined_indexes[$was_index_name] = 1;

                        $previous_unique = isset($previous_indexes[$was_index_name]["unique"]);
                        $unique = isset($indexes[$index_name]["unique"]);
                        if ($previous_unique != $unique) {
                            $change["ChangedUnique"] = 1;
                            if ($unique) {
                                $change["unique"] = $unique;
                            }
                            $this->database->debug("Changed index '$index_name' unique from $previous_unique to $unique in table '$table_name'");
                        }

                        $fields = $indexes[$index_name]["FIELDS"];
                        $previous_fields = $previous_indexes[$was_index_name]["FIELDS"];
                        for($defined_fields = array(), reset($fields), $field = 0;
                            $field < count($fields);
                            next($fields), $field++)
                        {
                            $field_name = key($fields);
                            if (isset($previous_fields[$field_name])) {
                                $defined_fields[$field_name] = 1;
                                $sorting = (isset($fields[$field_name]["sorting"]) ? $fields[$field_name]["sorting"] : "");
                                $previous_sorting = (isset($previous_fields[$field_name]["sorting"]) ? $previous_fields[$field_name]["sorting"] : "");
                                if (strcmp($sorting, $previous_sorting)) {
                                    $this->database->debug("Changed index field '$field_name' sorting default from '$previous_sorting' to '$sorting' in table '$table_name'");
                                    $change["ChangedFields"] = 1;
                                }
                            } else {
                                $change["ChangedFields"] = 1;
                                $this->database->debug("Added field '$field_name' to index '$index_name' of table '$table_name'");
                            }
                        }
                        for(reset($previous_fields), $field = 0;
                            $field < count($previous_fields);
                            next($previous_fields), $field++)
                        {
                            $field_name = key($previous_fields);
                            if (!isset($defined_fields[$field_name])) {
                                $change["ChangedFields"] = 1;
                                $this->database->debug("Removed field '$field_name' from index '$index_name' of table '$table_name'");
                            }
                        }

                        if (count($change)) {
                            $this->addDefinitionChange($changes, "INDEXES", $table_name,array("ChangedIndexes" =>array($index_name =>$change)));
                        }
                    } else {
                        if (strcmp($index_name, $was_index_name)) {
                            return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                                'it was specified a previous index name ("'.$was_index_name
                                .') for index "'.$index_name.'" of table "'.$table_name.'" that does not exist',
                                'MDB_Error', TRUE);
                        }
                        $this->addDefinitionChange($changes, "INDEXES", $table_name,array("AddedIndexes" =>array($index_name =>$indexes[$index_name])));
                        $this->database->debug("Added index '$index_name' to table '$table_name'");
                    }
                }
                for(reset($previous_indexes), $index = 0;
                    $index < count($previous_indexes);
                    next($previous_indexes), $index++)
                {
                    $index_name = key($previous_indexes);
                    if (!isset($defined_indexes[$index_name])) {
                        $this->addDefinitionChange($changes, "INDEXES", $table_name,array("RemovedIndexes" =>array($index_name =>1)));
                        $this->database->debug("Removed index '$index_name' from table '$table_name'");
                    }
                }
            } else {
                if (strcmp($table_name, $was_table_name)) {
                    return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                        'it was specified a previous table name ("'
                        .$was_table_name.'") for table "'.$table_name.'" that does not exist',
                        'MDB_Error', TRUE);
                }
                $this->addDefinitionChange($changes, "TABLES", $table_name,array("Add" =>1));
                $this->database->debug("Added table '$table_name'");
            }
        }
        for(reset($previous_definition["TABLES"]), $table = 0;
            $table < count($previous_definition["TABLES"]);
            next($previous_definition["TABLES"]), $table++)
        {
            $table_name = key($previous_definition["TABLES"]);
            if (!isset($defined_tables[$table_name])) {
                $this->addDefinitionChange($changes, "TABLES", $table_name,array("Remove" =>1));
                $this->database->debug("Removed table '$table_name'");
            }
        }
        if (isset($this->database_definition["SEQUENCES"])) {
            for($defined_sequences = array(), reset($this->database_definition["SEQUENCES"]), $sequence = 0;
                $sequence < count($this->database_definition["SEQUENCES"]);
                next($this->database_definition["SEQUENCES"]), $sequence++)
            {
                $sequence_name = key($this->database_definition["SEQUENCES"]);
                $was_sequence_name = $this->database_definition["SEQUENCES"][$sequence_name]["was"];
                if (isset($previous_definition["SEQUENCES"][$sequence_name])
                    && isset($previous_definition["SEQUENCES"][$sequence_name]["was"])
                    && !strcmp($previous_definition["SEQUENCES"][$sequence_name]["was"], $was_sequence_name))
                {
                    $was_sequence_name = $sequence_name;
                }
                if (isset($previous_definition["SEQUENCES"][$was_sequence_name])) {
                    if (strcmp($was_sequence_name, $sequence_name)) {
                        $this->addDefinitionChange($changes, "SEQUENCES", $was_sequence_name,array("name" =>$sequence_name));
                        $this->database->debug("Renamed sequence '$was_sequence_name' to '$sequence_name'");
                    }
                    if (isset($defined_sequences[$was_sequence_name])) {
                        return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                            'the sequence "'.$was_sequence_name.'" was specified as base'
                            .' of more than of sequence of the database',
                            'MDB_Error', TRUE);
                    }
                    $defined_sequences[$was_sequence_name] = 1;
                    $change = array();
                    if (strcmp($this->database_definition["SEQUENCES"][$sequence_name]["start"], $previous_definition["SEQUENCES"][$was_sequence_name]["start"])) {
                        $change["start"] = $this->database_definition["SEQUENCES"][$sequence_name]["start"];
                        $this->database->debug("Changed sequence '$sequence_name' start from '".$previous_definition["SEQUENCES"][$was_sequence_name]["start"]."' to '".$this->database_definition["SEQUENCES"][$sequence_name]["start"]."'");
                    }
                    if (strcmp($this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"], $previous_definition["SEQUENCES"][$was_sequence_name]["on"]["table"])
                        || strcmp($this->database_definition["SEQUENCES"][$sequence_name]["on"]["field"], $previous_definition["SEQUENCES"][$was_sequence_name]["on"]["field"]))
                    {
                        $change["on"] = $this->database_definition["SEQUENCES"][$sequence_name]["on"];
                        $this->database->debug("Changed sequence '$sequence_name' on table field from '".$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["table"].".".$previous_definition["SEQUENCES"][$was_sequence_name]["on"]["field"]."' to '".$this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"].".".$this->database_definition["SEQUENCES"][$sequence_name]["on"]["field"]."'");
                    }
                    if (count($change)) {
                        $this->addDefinitionChange($changes, "SEQUENCES", $was_sequence_name,array("Change" =>array($sequence_name =>array($change))));
                    }
                } else {
                    if (strcmp($sequence_name, $was_sequence_name)) {
                        return PEAR::raiseError(NULL, DB_ERROR_INVALID, NULL, NULL, 
                            'it was specified a previous sequence name ("'.$was_sequence_name
                            .'") for sequence "'.$sequence_name.'" that does not exist',
                            'MDB_Error', TRUE);
                    }
                    $this->addDefinitionChange($changes, "SEQUENCES", $sequence_name,array("Add" =>1));
                    $this->database->debug("Added sequence '$sequence_name'");
                }
            }
        }
        if (isset($previous_definition["SEQUENCES"])) {
            for(reset($previous_definition["SEQUENCES"]), $sequence = 0;
                $sequence < count($previous_definition["SEQUENCES"]);
                next($previous_definition["SEQUENCES"]), $sequence++)
            {
                $sequence_name = key($previous_definition["SEQUENCES"]);
                if (!isset($defined_sequences[$sequence_name])) {
                    $this->addDefinitionChange($changes, "SEQUENCES", $sequence_name,array("Remove" =>1));
                    $this->database->debug("Removed sequence '$sequence_name'");
                }
            }
        }
        return 1;
    }

    /**
     * Execute the necessary actions to implement the requested changes 
     * in a database structure.
     *
     * @param array &$previous_definition an associative array that contains 
     * the definition of the database structure before applying the requested 
     * changes. The definition of this array may be built separately, but 
     * usually it is built by the Parse method the Metabase parser class.
     *
     * @param array &$changes an associative array that contains the definition of 
     * the changes that are meant to be applied to the database structure.
     *
     * @access public
     * 
     * @return mixed true on success, or a MDB error object
     */

    function alterDatabase(&$previous_definition, &$changes)
    {
        if (isset($changes["TABLES"])) {
            for($change = 0, reset($changes["TABLES"]);
                $change < count($changes["TABLES"]);
                next($changes["TABLES"]), $change++)
            {
                $table_name = key($changes["TABLES"]);
                if (isset($changes["TABLES"][$table_name]["Add"])
                    || isset($changes["TABLES"][$table_name]["Remove"]))
                {
                    continue;
                }
                $result = $this->database->alterTable($table_name, $changes["TABLES"][$table_name], 1);
                if (MDB::isError($result)) {
                    return $result;
                }
            }
        }
        if (isset($changes["SEQUENCES"])) {
            if (!$this->database->support("Sequences")) {
                return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                    'sequences are not supported', 'MDB_Error', TRUE);
            }
            for($change = 0, reset($changes["SEQUENCES"]);
                $change < count($changes["SEQUENCES"]);
                next($changes["SEQUENCES"]), $change++)
            {
                $sequence_name = key($changes["SEQUENCES"]);
                if (isset($changes["SEQUENCES"][$sequence_name]["Add"])
                    || isset($changes["SEQUENCES"][$sequence_name]["Remove"])
                    || isset($changes["SEQUENCES"][$sequence_name]["Change"]))
                {
                    continue;
                }
                return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                    'some sequences changes are not yet supported', 'MDB_Error', TRUE);
            }
        }
        if (isset($changes["INDEXES"]))    {
            if (!$this->database->support("Indexes")) {
                return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                    'indexes are not supported', 'MDB_Error', TRUE);
            }
            for($change = 0, reset($changes["INDEXES"]);
                $change < count($changes["INDEXES"]);
                next($changes["INDEXES"]), $change++)
            {
                $table_name = key($changes["INDEXES"]);
                $table_changes = count($changes["INDEXES"][$table_name]);
                if (isset($changes["INDEXES"][$table_name]["AddedIndexes"])) {
                    $table_changes--;
                }
                if (isset($changes["INDEXES"][$table_name]["RemovedIndexes"])) {
                    $table_changes--;
                }
                if (isset($changes["INDEXES"][$table_name]["ChangedIndexes"])) {
                    $table_changes--;
                }
                if ($table_changes) {
                    return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                        'index alteration not yet supported', 'MDB_Error', TRUE);
                }
            }
        }
        
        $previous_database_name = $this->database->setDatabase($this->database_definition["name"]);
        if (($support_transactions = $this->database->support("Transactions"))
            && MDB::isError($result = $this->database->autoCommit(FALSE))) {
            return $result;
        }
        $error = "";
        $alterations = 0;
        if (isset($changes["INDEXES"])) {
            for($change = 0, reset($changes["INDEXES"]);
                $change < count($changes["INDEXES"]);
                next($changes["INDEXES"]), $change++)
            {
                $table_name = key($changes["INDEXES"]);
                if (isset($changes["INDEXES"][$table_name]["RemovedIndexes"])) {
                    $indexes = $changes["INDEXES"][$table_name]["RemovedIndexes"];
                    for($index = 0, reset($indexes);
                        $index < count($indexes);
                        next($indexes), $index++)
                    {
                        $result = $this->database->dropIndex($table_name,key($indexes));
                        if (MDB::isError($result)) {
                            break;
                        }
                        $alterations++;
                    }
                }
                if (!MDB::isError($result)
                    && isset($changes["INDEXES"][$table_name]["ChangedIndexes"]))
                {
                    $indexes = $changes["INDEXES"][$table_name]["ChangedIndexes"];
                    for($index = 0, reset($indexes);
                        $index < count($indexes);
                        next($indexes), $index++)
                    {
                        $name = key($indexes);
                        $was_name = (isset($indexes[$name]["name"]) ? $indexes[$name]["name"] : $name);
                        $result = $this->database->dropIndex($table_name, $was_name);
                        if (MDB::isError($result)) {
                            break;
                        }
                        $alterations++;
                    }
                }
                if (MDB::isError($result)) {
                    break;
                };
            }
        }
        if (!MDB::isError($result)
            && isset($changes["TABLES"]))
        {
            for($change = 0, reset($changes["TABLES"]);
                $change < count($changes["TABLES"]);
                next($changes["TABLES"]), $change++)
            {
                $table_name = key($changes["TABLES"]);
                if (isset($changes["TABLES"][$table_name]["Remove"])) {
                    $result = $this->dropTable($table_name);
                    if (!MDB::isError($result)) {
                        $alterations++;
                    }
                } else {
                    if (!isset($changes["TABLES"][$table_name]["Add"])) {
                        $result = $this->database->alterTable($table_name, $changes["TABLES"][$table_name], 0);
                        if (!MDB::isError($result)) {
                            $alterations++;
                        }
                    }
                }
                if (MDB::isError($result)) {
                    break;
                }
            }
            for($change = 0, reset($changes["TABLES"]);
                $change < count($changes["TABLES"]);
                next($changes["TABLES"]), $change++)
            {
                $table_name = key($changes["TABLES"]);
                if (isset($changes["TABLES"][$table_name]["Add"])) {
                    $result = $this->createTable($table_name, $this->database_definition["TABLES"][$table_name]);
                    if (!MDB::isError($result)) {
                        $alterations++;
                    }
                }
                if (MDB::isError($result)) {
                    break;
                }
            }
        }
        if (!MDB::isError($result)
            && isset($changes["SEQUENCES"]))
        {
            for($change = 0, reset($changes["SEQUENCES"]);
                $change < count($changes["SEQUENCES"]);
                next($changes["SEQUENCES"]), $change++)
            {
                $sequence_name = key($changes["SEQUENCES"]);
                if (isset($changes["SEQUENCES"][$sequence_name]["Add"])) {
                    $created_on_table = 0;
                    if (isset($this->database_definition["SEQUENCES"][$sequence_name]["on"])) {
                        $table = $this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
                        if (isset($changes["TABLES"])
                            && isset($changes["TABLES"][$table_name])
                            && isset($changes["TABLES"][$table_name]["Add"]))
                        {
                            $created_on_table = 1;
                        }
                    }
                    
                    $result = $this->createSequence($sequence_name, 
                        $this->database_definition["SEQUENCES"][$sequence_name], $created_on_table);
                    if (!MDB::isError($result)) {
                        $alterations++;
                    }
                } else {
                    if (isset($changes["SEQUENCES"][$sequence_name]["Remove"])) {
                        if (!strcmp($error = $this->dropSequence($sequence_name), "")) {
                            $alterations++;
                        }
                    } else {
                        if (isset($changes["SEQUENCES"][$sequence_name]["Change"])) {
                            $created_on_table = 0;
                            if (isset($this->database_definition["SEQUENCES"][$sequence_name]["on"])) {
                                $table = $this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
                                if (isset($changes["TABLES"])
                                    && isset($changes["TABLES"][$table_name])
                                    && isset($changes["TABLES"][$table_name]["Add"]))
                                {
                                    $created_on_table = 1;
                                }
                            }
                            if (!MDB::isError($result = $this->dropSequence(
                                    $this->database_definition["SEQUENCES"][$sequence_name]["was"]), "")
                                && !MDB::isError($result = $this->createSequence(
                                    $sequence_name, $this->database_definition["SEQUENCES"][$sequence_name], $created_on_table), ""))
                            {
                                $alterations++;
                            }
                        } else {
                            return PEAR::raiseError(NULL, DB_ERROR_UNSUPPORTED, NULL, NULL, 
                                'changing sequences is not yet supported', 'MDB_Error', TRUE);
                        }
                    }
                }
                if (MDB::isError($result)) {
                    break;
                }
            }
        }
        if (!MDB::isError($result)
            && isset($changes["INDEXES"]))
        {
            for($change = 0, reset($changes["INDEXES"]);
                $change < count($changes["INDEXES"]);
                next($changes["INDEXES"]), $change++)
            {
                $table_name = key($changes["INDEXES"]);
                if (isset($changes["INDEXES"][$table_name]["ChangedIndexes"])) {
                    $indexes = $changes["INDEXES"][$table_name]["ChangedIndexes"];
                    for($index = 0, reset($indexes);
                        $index < count($indexes);
                        next($indexes), $index++)
                    {
                        $result = $this->database->createIndex($table_name, 
                            key($indexes), 
                            $this->database_definition["TABLES"][$table_name]["INDEXES"][key($indexes)]);
                        if (MDB::isError($result)) {
                            break;
                        }
                        $alterations++;
                    }
                }
                if (!MDB::isError($result)
                    && isset($changes["INDEXES"][$table_name]["AddedIndexes"]))
                {
                    $indexes = $changes["INDEXES"][$table_name]["AddedIndexes"];
                    for($index = 0, reset($indexes);
                        $index < count($indexes);
                        next($indexes), $index++)
                    {
                        $result = $this->database->createIndex($table_name, 
                            key($indexes), 
                            $this->database_definition["TABLES"][$table_name]["INDEXES"][key($indexes)]);
                        if (MDB::isError($result)) {
                            break;
                        }
                        $alterations++;
                    }
                }
                if (MDB::isError($result)) {
                    break;
                }
            }
        }
        if ($alterations
            && MDB::isError($result))
        {
            if ($support_transactions) {
                $res = $this->database->rollback();
                if (MDB::isError($res)) 
                    $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                        'Could not rollback the partially created database alterations ('
                        .$result->getMessage().' ('.$result->getUserinfo(),'))',
                        'MDB_Error', TRUE);
            } else {
                $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                    'the requested database alterations were only partially implemented ('
                    .$result->getMessage().' ('.$result->getUserinfo(),'))',
                    'MDB_Error', TRUE);
            }
        }
        if ($support_transactions) {
            $result = $this->database->autoCommit(TRUE);
            if (MDB::isError($result)) {
                $result = PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                    'Could not end transaction after successfully implemented the requested database alterations ('
                    .$result->getMessage().' ('.$result->getUserinfo(),'))',
                    'MDB_Error', TRUE);
            }
        }
        $this->database->setDatabase($previous_database_name);
        return $result;
    }

    function escapeSpecialCharacters($string)
    {
        if (gettype($string) != "string") {
            $string = strval($string);
        }
        for($escaped = "", $character = 0;
            $character < strlen($string);
            $character++)
        {
            switch($string[$character]) {
                case "\"":
                case ">":
                case "<":
                    $code = Ord($string[$character]);
                    break;
                default:
                    $code = Ord($string[$character]);
                    if ($code < 32 || $code>127) {
                        break;
                    }
                    $escaped .= $string[$character];
                    continue 2;
            }
            $escaped .= "&#$code;";
        }
        return($escaped);
    }

    function dumpSequence($sequence_name, $output, $eol, $dump_definition)
    {
        $sequence_definition = $this->database_definition["SEQUENCES"][$sequence_name];
        if ($dump_definition) {
            $start = $sequence_definition["start"];
        } else {
            if ($this->database->support("currId")) {
                $result = $this->database->currId($sequence_name, $start);
                if (MDB::isError($result)) {
                    return $result;
                }
                $start++;
            } else {
                $result = $this->database->nextId($sequence_name, $start);
                if (MDB::isError($result)) {
                    return $result;
                }
                // XXX needs more checking
                $this->warnings[] = "database does not support getting current sequence value, the sequence value was incremented";
            }
        }
        $output("$eol <sequence>$eol  <name>$sequence_name</name>$eol  <start>$start</start>$eol");
        if (isset($sequence_definition["on"])) {
            $output("  <on>$eol   <table>".$sequence_definition["on"]["table"]."</table>$eol   <field>".$sequence_definition["on"]["field"]."</field>$eol  </on>$eol");
        }
        $output(" </sequence>$eol");
        return(1);
    }


    /**
     * Dump a previously parsed database structure in the Metabase schema 
     * XML based format suitable for the Metabase parser. This function 
     * may optionally dump the database definition with initialization 
     * commands that specify the data that is currently present in the tables.
     *
     * @param array $arguments an associative array that takes pairs of tag 
     * names and values that define dump options. 
     *
     * @access public
     *
     * @return mixed true on success, or a MDB error object
     */

    function dumpDatabase($arguments)
    {
        $fp = 0;
        if (isset($arguments["Output_Mode"]) && $arguments["Output_Mode"] == 'file')
        {
            $fp = fopen($arguments["Output_File"], "w");
        }
        elseif (!isset($arguments["Output"])) {
            return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                'no valid output function specified', 'MDB_Error', TRUE);
        }
        
        $output = $arguments["Output"];
        $eol = (isset($arguments["EndOfLine"]) ? $arguments["EndOfLine"] : "\n");
        $dump_definition = isset($arguments["Definition"]);
        $sequences = array();
        if (isset($this->database_definition["SEQUENCES"])) {
            for($error = "", reset($this->database_definition["SEQUENCES"]), $sequence = 0;
                $sequence < count($this->database_definition["SEQUENCES"]);
                next($this->database_definition["SEQUENCES"]), $sequence++)
            {
                $sequence_name = key($this->database_definition["SEQUENCES"]);
                if (isset($this->database_definition["SEQUENCES"][$sequence_name]["on"])) {
                    $table = $this->database_definition["SEQUENCES"][$sequence_name]["on"]["table"];
                } else {
                    $table = "";
                }
                $sequences[$table][] = $sequence_name;
            }
        }
        $previous_database_name = (strcmp($this->database_definition["name"], "") ? $this->database->setDatabase($this->database_definition["name"]) : "");
        $buffer = ('<?xml version="1.0" encoding="ISO-8859-1" ?>'.$eol);
        $buffer .= ("<database>$eol$eol <name>".$this->database_definition["name"]."</name>$eol <create>".$this->database_definition["create"]."</create>$eol");
        
        if ($fp) {
            fwrite($fp, $buffer);
        } else {
            $output($buffer);
        }

        for($error = "", reset($this->database_definition["TABLES"]), $table = 0;
            $table < count($this->database_definition["TABLES"]);
            next($this->database_definition["TABLES"]), $table++)
        {
            $table_name = key($this->database_definition["TABLES"]);
            $buffer = ("$eol <table>$eol$eol  <name>$table_name</name>$eol");
            $buffer .= ("$eol  <declaration>$eol");
            $fields = $this->database_definition["TABLES"][$table_name]["FIELDS"];
            for(reset($fields), $field_number = 0;
                $field_number < count($fields);
                $field_number++, next($fields))
            {
                $field_name = key($fields);
                $field = $fields[$field_name];
                if (!isset($field["type"])) {
                    return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                        'it was not specified the type of the field "'.$field_name.'" of the table "'.$table_name, 'MDB_Error', TRUE);
                }
                $buffer .=("$eol   <field>$eol    <name>$field_name</name>$eol    <type>".$field["type"]."</type>$eol");
                switch($field["type"]) {
                    case "integer":
                        if (isset($field["unsigned"])) {
                            $buffer .=("    <unsigned>1</unsigned>$eol");
                        }
                        break;
                    case "text":
                    case "clob":
                    case "blob":
                        if (isset($field["length"])) {
                            $buffer .=("    <length>".$field["length"]."</length>$eol");
                        }
                        break;
                    case "boolean":
                    case "date":
                    case "timestamp":
                    case "time":
                    case "float":
                    case "decimal":
                        break;
                    default:
                        return("type \"".$field["type"]."\" is not yet supported");
                }
                if (isset($field["notnull"])) {
                    $buffer .=("    <notnull>1</notnull>$eol");
                }
                if (isset($field["default"])) {
                    $buffer .=("    <default>".$this->escapeSpecialCharacters($field["default"])."</default>$eol");
                }
                $buffer .=("   </field>$eol");
            }

            if (isset($this->database_definition["TABLES"][$table_name]["INDEXES"])) {
                $indexes = $this->database_definition["TABLES"][$table_name]["INDEXES"];
                for(reset($indexes), $index_number = 0;
                    $index_number < count($indexes);
                    $index_number++, next($indexes))
                {
                    $index_name = key($indexes);
                    $index = $indexes[$index_name];
                    $buffer .=("$eol   <index>$eol    <name>$index_name</name>$eol");
                    if (isset($indexes[$index_name]["unique"])) {
                        $buffer .=("    <unique>1</unique>$eol");
                    }
                    for(reset($index["FIELDS"]), $field_number = 0;
                        $field_number < count($index["FIELDS"]);
                        $field_number++, next($index["FIELDS"]))
                    {
                        $field_name = key($index["FIELDS"]);
                        $field = $index["FIELDS"][$field_name];
                        $buffer .=("    <field>$eol     <name>$field_name</name>$eol");
                        if (isset($field["sorting"])) {
                            $buffer .=("     <sorting>".$field["sorting"]."</sorting>$eol");
                        }
                        $buffer .=("   </field>$eol");
                    }
                    $buffer .=("   </index>$eol");
                }
            }
            $buffer .= ("$eol  </declaration>$eol");

            if ($fp) {
                fwrite($fp, $buffer);
            } else {
                $output($buffer);
            }

            if ($dump_definition) {
                if (isset($this->database_definition["TABLES"][$table_name]["initialization"])) {
                    $buffer = ("$eol  <initialization>$eol");
                    $instructions = $this->database_definition["TABLES"][$table_name]["initialization"];
                    for(reset($instructions), $instruction = 0; 
                        $instruction < count($instructions);
                        $instruction++, next($instructions))
                    {
                        switch($instructions[$instruction]["type"]) {
                            case "insert":
                                $buffer .= ("$eol   <insert>$eol");
                                $fields = $instructions[$instruction]["FIELDS"];
                                for(reset($fields), $field_number = 0;
                                    $field_number < count($fields);
                                    $field_number++, next($fields))
                                {
                                    $field_name = key($fields);
                                    $buffer .= ("$eol    <field>$eol     <name>$field_name</name>$eol     <value>".$this->escapeSpecialCharacters($fields[$field_name])."</value>$eol   </field>$eol");
                                }
                                $buffer .= ("$eol   </insert>$eol");
                                break;
                        }
                    }
                    $buffer .= ("$eol  </initialization>$eol");
                }
            } else {
                if (count($this->database_definition["TABLES"][$table_name]["FIELDS"]) == 0) {
                    return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                        'the definition of the table "'.$table_name.'" does not contain any fields', 'MDB_Error', TRUE);
                }
                $result = $this->getFields($table_name, $query_fields);
                if (MDB::isError($result)) {
                    return $result;
                }
                
                $result = $this->database->query("SELECT $query_fields FROM $table_name");
                if (MDB::isError($result)) {
                    return $result;
                }
                
                $rows = $this->database->numRows($result);

                if ($rows>0) {
                    $buffer = ("$eol  <initialization>$eol");
                    
                    if ($fp) {
                        fwrite($fp, $buffer);
                    } else {
                        $output($buffer);
                    }

                    for($row = 0; $row < $rows; $row++) {
                        $buffer = ("$eol   <insert>$eol");
                        $this->database->fetchInto($result, $values, DB_FETCHMODE_ASSOC);
                        foreach($fields as $field_name => $field) {
                                $buffer .= ("$eol   <field>$eol     <name>$field_name</name>$eol     <value>");
                                $buffer .= $this->escapeSpecialCharacters($values[$field_name]);
                                $buffer .= ("</value>$eol   </field>$eol");
                        }
                        $buffer .= ("$eol   </insert>$eol");
                        
                        if ($fp)
                            fwrite($fp, $buffer);
                        else
                            $output($buffer);
                    }
                    $buffer = ("$eol  </initialization>$eol");
                    if ($fp) {
                        fwrite($fp, $buffer);
                    } else {
                        $output($buffer);
                    }
                }

                $buffer = '';
                $this->database->freeResult($result);
            }
            $buffer .= ("$eol </table>$eol");
            if ($fp) {
                fwrite($fp, $buffer);
            } else {
                $output($buffer);
            }

            if (isset($sequences[$table_name])) {
                for($sequence = 0;
                    $sequence < count($sequences[$table_name]);
                    $sequence++)
                {
                    $result = $this->dumpSequence($sequences[$table_name][$sequence], $output, $eol, $dump_definition);
                    if (MDB::isError($result)) {
                        return $result;
                    }
                }
            }
        }
        if (isset($sequences[""])) {
            for($sequence = 0;
                $sequence < count($sequences[""]);
                $sequence++)
            {
                $result = $this->dumpSequence($sequences[""][$sequence], $output, $eol, $dump_definition);
                if (MDB::isError($result)) {
                    return $result;
                }
            }
        }
        $buffer = ("$eol</database>$eol");

        if ($fp) {
            fwrite($fp, $buffer);
            fclose($fp);
        } else {
            $output($buffer);
        }

        if (strcmp($previous_database_name, "")) {
            $this->database->setDatabase($previous_database_name);
        }
        return($error);
    }


    /**
     * Parse a database definition file by creating a Metabase schema format 
     * parser object and passing the file contents as parser input data stream.
     *
     * @param string $input_file the path of the database schema file.
     * 
     * @param array &$database_definition reference to an associative array that 
     * will hold the information about the database schema structure as return 
     * by the parser object.
     *
     * @param array &$variables an associative array that the defines the text 
     * string values that are meant to be used to replace the variables that are 
     * used in the schema description. 
     *
     * @param bool $fail_on_invalid_names (optional) make function fail on invalid 
     * names 
     *
     * @access public
     * 
     * @return mixed true on success, or a MDB error object
     */

    function parseDatabaseDefinitionFile($input_file, &$database_definition, &$variables, $fail_on_invalid_names = 1)
    {
        if (!($file = fopen($input_file, "r"))) {
            return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                'Could not open input file "'.$input_file.'"', 'MDB_Error', TRUE);
        }
        $parser = new MDB_parser;
        $parser->variables = $variables;
        $parser->fail_on_invalid_names = $fail_on_invalid_names;
        if (strcmp($error = $parser->parseStream($file), "")) {
            $error .= " Line ".$parser->error_line." column ".$parser->error_column." Byte index ".$parser->error_byte_index;
            return PEAR::raiseError(NULL, DB_ERROR_MANAGER_PARSE, NULL, NULL, 
                $error, 'MDB_Error', TRUE);
        } else {
            $database_definition = $parser->database;
        }
        fclose($file);
        
        if (MDB::isError($error))
            return $error;
        
        return 1;
    }

    /**
     * Dump the changes between two database definitions.
     *
     * @param array &$changes an associative array that specifies the list 
     * of database definitions changes as returned by the CompareDefinitions 
     * manager class function.
     *
     * @access public
     * 
     * @return mixed true on success, or a MDB error object
     */
     
    function dumpDatabaseChanges(&$changes)
    {
        if (isset($changes["TABLES"])) {
            foreach($changes["TABLES"] as $table_name => $table)
            {
                $this->database->debug("$table_name:");
                if (isset($table["Add"])) {
                    $this->database->debug("\tAdded table '$table_name'");
                } elseif (isset($table["Remove"])) {
                    $this->database->debug("\tRemoved table '$table_name'");
                } else {
                    if (isset($table["name"])) {
                        $this->database->debug("\tRenamed table '$table_name' to '".$table["name"]."'");
                    }
                    if (isset($table["AddedFields"])) {
                        foreach($table["AddedFields"] as $field_name => $field) {
                            $this->database->debug("\tAdded field '".$field_name."'");
                        }
                    }
                    if (isset($table["RemovedFields"])) {
                        foreach($table["RemovedFields"] as $field_name => $field) {
                            $this->database->debug("\tRemoved field '".$field_name."'");
                        }
                    }
                    if (isset($table["RenamedFields"])) {
                        foreach($table["RenamedFields"] as $field_name => $field) {
                            $this->database->debug("\tRenamed field '".$field_name."' to '".$field["name"]."'");
                        }
                    }
                    if (isset($table["ChangedFields"])) {
                        foreach($table["ChangedFields"] as $field_name => $field) {
                            if (isset($field["type"])) {
                                $this->database->debug(
                                    "\tChanged field '$field_name' type to '".$field["type"]."'");
                            }
                            if (isset($field["unsigned"]))
                            {
                                $this->database->debug(
                                    "\tChanged field '$field_name' type to '".
                                    ($field["unsigned"] ? "" : "not ")."unsigned'");
                            }
                            if (isset($field["length"]))
                            {
                                $this->database->debug(
                                    "\tChanged field '$field_name' length to '".
                                    ($field["length"] == 0 ? "no length" : $field["length"])."'");
                            }
                            if (isset($field["ChangedDefault"]))
                            {
                                $this->database->debug(
                                    "\tChanged field '$field_name' default to ".
                                    (isset($field["default"]) ? "'".$field["default"]."'" : "NULL"));
                            }
                            if (isset($field["ChangedNotNull"]))
                            {
                                $this->database->debug(
                                   "\tChanged field '$field_name' notnull to ".(isset($field["notnull"]) ? "'1'" : "0"));
                            }
                        }
                    }
                }
            }
        }
        if (isset($changes["SEQUENCES"])) {
            foreach($changes["SEQUENCES"] as $sequence_name => $sequence) 
            {
                $this->database->debug("$sequence_name:");
                if (isset($sequence["Add"])) {
                    $this->database->debug("\tAdded sequence '$sequence_name'");
                } elseif (isset($sequence["Remove"])) {
                    $this->database->debug("\tRemoved sequence '$sequence_name'");
                } else {
                    if (isset($sequence["name"])) {
                        $this->database->debug("\tRenamed sequence '$sequence_name' to '".$sequence["name"]."'");
                    }
                    if (isset($sequence["Change"])) {
                        foreach($sequence["Change"] as $sequence_name => $sequence) {
                            if (isset($sequence["start"])) {
                                $this->database->debug(
                                    "\tChanged sequence '$sequence_name' start to '".$sequence["start"]."'");
                            }
                        }
                    }
                }
            }
        }
        if (isset($changes["INDEXES"])) {
            foreach($changes["INDEXES"] as $table_name => $table)
            {
                $this->database->debug("$table_name:");
                if (isset($table["AddedIndexes"])) {
                    foreach($table["AddedIndexes"] as $index_name => $index) {
                        $this->database->debug("\tAdded index '".$index_name."' of table '$table_name'");
                    }
                }
                if (isset($table["RemovedIndexes"])) {
                    foreach($table["RemovedIndexes"] as $index_name => $index) {
                        $this->database->debug("\tRemoved index '".$index_name."' of table '$table_name'");
                    }
                }
                if (isset($table["ChangedIndexes"])) {
                    foreach($table["ChangedIndexes"] as $index_name => $index) {
                        if (isset($index["name"])) {
                            $this->database->debug(
                                "\tRenamed index '".$index_name."' to '".$index["name"]."' on table '$table_name'");
                        }
                        if (isset($index["ChangedUnique"])) {
                            $this->database->debug(
                                "\tChanged index '".$index_name."' unique to '".
                                isset($index["unique"])."' on table '$table_name'");
                        }
                        if (isset($index["ChangedFields"])) {
                            $this->database->debug("\tChanged index '".$index_name."' on table '$table_name'");
                        }
                    }
                }
            }
        }
    }


    /**
     * Compare the correspondent files of two versions of a database schema 
     * definition: the previously installed and the one that defines the schema 
     * that is meant to update the database.
     * If the specified previous definition file does not exist, this function 
     * will create the database from the definition specified in the current 
     * schema file.
     * If both files exist, the function assumes that the database was previously 
     * installed based on the previous schema file and will update it by just 
     * applying the changes.
     * If this function succeeds, the contents of the current schema file are 
     * copied to replace the previous schema file contents. Any subsequent schema 
     * changes should only be done on the file specified by the $current_schema_file 
     * to let this function make a consistent evaluation of the exact changes that 
     * need to be applied.
     *
     * @param string $current_schema_file name of the updated database schema 
     * definition file.
     *
     * @param string $previous_schema_file name the previously installed database 
     * schema definition file.
     * 
     * @param mixed &$dsninfo "data source name", see the MDB::parseDSN method
     * for a description of the dsn format. Can also be specified as
     * an array of the format returned by MDB::parseDSN.
     *
     * @param array &$variables an associative array that is passed to the argument 
     * of the same name to the ParseDatabaseDefinitionFile function. (there third 
     * param)
     *
     * @param array $options (optional) an associative array that is passed to the 
     * argument of the same name to the setupDatabase function. (there second param)
     *
     * @access public
     * 
     * @return mixed true on success, or a MDB error object
     */
    function updateDatabase($current_schema_file, $previous_schema_file, &$dsninfo, &$variables, $options = FALSE)
    {
        $result = $this->parseDatabaseDefinitionFile($current_schema_file, 
            $this->database_definition, $variables, $this->fail_on_invalid_names);
        if (MDB::isError($result)) {
            return $result;
        }
        $result = $this->setupDatabase($dsninfo, $options);
        if (MDB::isError($result)) {
            return $result;
        }
        $copy = 0;
        if (file_exists($previous_schema_file)) {
            $result = $this->parseDatabaseDefinitionFile($previous_schema_file, $database_definition, $variables, 0);
            if (MDB::isError($result)) {
                return $result;
            }
            
            $result = $this->compareDefinitions($database_definition, $changes);
            if (MDB::isError($result)) {
                return $result;
            }
            
            if (count($changes)) {
                $result = $this->alterDatabase($database_definition, $changes);
                if (MDB::isError($result)) {
                    return $result;
                }
                
                $copy = 1;
                $result = $this->dumpDatabaseChanges($changes);
                if (MDB::isError($result)) {
                    return $result;
                }
            }
        } else {
            $result = $this->createDatabase();
            if (MDB::isError($result)) {
                return $result;
            }
            $copy = 1;
        }
        
        if ($copy && !copy($current_schema_file, $previous_schema_file))
        {
            return PEAR::raiseError(NULL, DB_ERROR_MANAGER, NULL, NULL, 
                'Could not copy the new database definition file to the current file', 'MDB_Error', TRUE);
        }
        
        return(1);
    }


    /**
     * Parse a database schema definition file and dump the respective structure 
     * and contents.
     * 
     * @param string $schema_file path of the database schema file.
     *
     * @param mixed &$setup_arguments an associative array that takes pairs of tag names and values 
     * that define the setup arguments that are passed to the 
     * MDB_manager::SetupDatabase function.
     *
     * @param array &$dump_arguments an associative array that takes pairs of tag names and values 
     * that define dump options as defined for the MDB_manager::DumpDatabase 
     * function.
     *
     * @param array &$variables an associative array that the defines the text string values 
     * that are meant to be used to replace the variables that are used in the 
     * schema description as defined for the 
     * MDB_manager::ParseDatabaseDefinitionFile function.
     *
     * @access public
     * 
     * @return mixed true on success, or a MDB error object
     */

    function dumpDatabaseContents($schema_file, &$setup_arguments, &$dump_arguments, &$variables)
    {
        $result = $this->parseDatabaseDefinitionFile($schema_file, 
            $database_definition, $variables, $this->fail_on_invalid_names);
        if (MDB::isError($result)) {
            return $result;
        }

        $this->database_definition = $database_definition;
        
        $result = $this->setupDatabase($setup_arguments);
        if (MDB::isError($result)) {
            return $result;
        }
        
        return($this->dumpDatabase($dump_arguments));
    }

    function getDefinitionFromDatabase()
    {
        $database = $this->database->database_name;
        if (strlen($database) == 0) {
            return("it was not specified a valid database name");
        }
        $this->database_definition = array(
            "name" => $database,
            "create" => 1,
            "TABLES" => array()
        );

        if (!$this->database->listTables($tables)) {
            return($this->database->error());
        }
        for($table = 0; $table < count($tables); $table++) {
            $table_name = $tables[$table];
            if (!$this->database->listTableFields($table_name, $fields)) {
                return($this->database->error());
            }
            $this->database_definition["TABLES"][$table_name] = array("FIELDS" => array());
            for($field = 0; $field < count($fields); $field++)
            {
                $field_name = $fields[$field];
                if (!$this->database->getTableFieldDefinition($table_name, $field_name, $definition)) {
                    return($this->database->error());
                }
                $this->database_definition["TABLES"][$table_name]["FIELDS"][$field_name] = $definition[0];
            }
        }
        return("");
    }
};
?>