<?
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
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+
// 
// $Id$
//
// MDB postgresql driver class for RDBMS management methods.
//

if(!defined("MDB_MANAGER_PGSQL_INCLUDED"))
{
    define("MDB_MANAGER_PGSQL_INCLUDED",1);

class MDB_manager_pgsql_class extends MDB_manager_database_class
{
    function createDatabase(&$db, $name)
    {
        return($db->_standaloneQuery("CREATE DATABASE $name"));
    }

    function dropDatabase(&$db, $name)
    {
        return($db->_standaloneQuery("DROP DATABASE $name"));
    }

    function createTable(&$db, $name, $fields) 
    {
        if (!isset($name) || !strcmp($name, '')) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'no valid table name specified');
        }
        if (count($fields) == 0) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'no fields specified for table "' . $name . '"');                
        }
        $query_fields = "";
        if (!$this->getFieldList($db, $fields, $query_fields)) {
            return $db->raiseError(DB_ERROR_CANNOT_CREATE, '', '', 'unkown error');
        }

        return ($db->query("CREATE TABLE $name ($query_fields)"));

    }

    function alterTable($name, &$changes, $check)
    {
        if($check) {
            for($change=0, Reset($changes); $change < count($changes); Next($changes), $change++) {
                switch(Key($changes)) {
                case "AddedFields":
                    break;
                case "RemovedFields":
                    return($this->SetError("Alter table", "database server does not support dropping table columns"));
                case "name":
                case "RenamedFields":
                case "ChangedFields":
                default:
                    return($this->SetError("Alter table", "change type \"" . Key($changes) . "\" not yet supported"));
                }
            }
            return (DB_OK);
        } else {
            if(IsSet($changes[$change="name"])
               || IsSet($changes[$change="RenamedFields"])
               || IsSet($changes[$change="ChangedFields"]))
                return($this->SetError("Alter table", "change type \"$change\" not yet supported"));
            $query = "";
            if(IsSet($changes["AddedFields"])) {
                $fields = $changes["AddedFields"];
                for($field = 0, Reset($fields); $field < count($fields); Next($fields), $field++) {
                    if(!$this->Query("ALTER TABLE $name ADD " . $fields[Key($fields)]["Declaration"])) {
                        return(0);
                    }
                }
            }
            if(IsSet($changes["RemovedFields"])) {
                $fields = $changes["RemovedFields"];
                for($field = 0, Reset($fields); $field < count($fields); Next($fields), $field++) {
                    if(!$this->Query("ALTER TABLE $name DROP " . Key($fields))) {
                        return(0);
                    }
                }
            }
            return (DB_OK);
        }
    }

    // }}}
    // {{{ listDatabases()
    /**
     * list the databases in the database
     *
     * @param $db (reference) MDB object database object
     * @param $dbs (reference) array where database names will be stored
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
    function listDatabases(&$db, &$dbs)
    {
        $result = $db->query('SELECT datname FROM pg_database');
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result, $dbs);
    }

    // }}}
    // {{{ listUsers()
    /**
     * list the users in the database
     *
     * @param $db (reference) MDB object database object
     * @param $users (reference) array where usernames will be stored
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
    function listUsers(&$db, &$users)
    {
        $result = $db->query('SELECT usename FROM pg_user');
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result, $users);
    }

    // }}}
    // {{{ listTables()
    /**
     * list the tables in the database
     *
     * @param $db (reference) MDB object database object
     * @param $tables (reference) array where table names will be stored
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
    function listTables(&$db, &$tables)
    {
        // gratuitously stolen from PEAR DB _getSpecialQuery in pgsql.php
        $sql = "SELECT c.relname as \"Name\"
                FROM pg_class c, pg_user u
                WHERE c.relowner = u.usesysid AND c.relkind = 'r'
                AND not exists (select 1 from pg_views where viewname = c.relname)
                AND c.relname !~ '^pg_'
                UNION
                SELECT c.relname as \"Name\"
                FROM pg_class c
                WHERE c.relkind = 'r'
                AND not exists (select 1 from pg_views where viewname = c.relname)
                AND not exists (select 1 from pg_user where usesysid = c.relowner)
                AND c.relname !~ '^pg_'";
        $result = $db->query($sql);
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result, $tables);
    }

    // }}}
    // {{{ listViews()
    /**
     * list the tables in the database
     *
     * @param $db (reference) MDB object database object
     * @param $views (reference) array where view names will be stored
     * 
     * @return mixed DB_OK on success, a DB error on failure
     */
    function listViewss(&$db, &$tables)
    {
        // gratuitously stolen from PEAR DB _getSpecialQuery in pgsql.php
        $result = $db->query("SELECT viewname FROM pg_views");
        if (MDB::isError($result)) {
            return $result;
        }
        return $db->fetchCol($result, $viewss);
    }

    function createSequence(&$db, $name, $start)
    {
        return($db->query("CREATE SEQUENCE $name INCREMENT 1" . ($start < 1 ? " MINVALUE $start" : "")." START $start"));
    }

    function dropSequence(&$db, $name)
    {
        return($db->query("DROP SEQUENCE $name"));
    }

};

}
?> 