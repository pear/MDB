<?php
 /*
 *
 * @(#) $Header$
 *
 */
 
require_once "MDB.php";

class DB
{
    function &factory($type)
    {
        return MDB::factory($type);
    }

    function &connect($dsn, $options = FALSE)
    {
        if (!is_array($options) && $options) {
            $$options["persistent"] = TRUE;
        }
        $db = MDB::connect($dsn, $options);
        if(PEAR::isError($db))
        {
            return $db;
        }
        $obj =& new MDB_PEAR_PROXY($db);
        return $obj;
    }

    function apiVersion()
    {
        return MDB::apiVersion();
    }

    function isError($value)
    {
        return MDB::isError($value);
    }

    function isManip($query)
    {
        return MDB::isManip($query);
    }

    function isWarning($value)
    {
        return MDB::isWarning($value);
    }

    function errorMessage($value)
    {
        return MDB::errorMessage($value);
    }

    function parseDSN($dsn)
    {
        return MDB::parseDSN($dsn);
    }

    function assertExtension($name)
    {
        return MDB::assertExtension($name);
    }
}

class DB_Error extends PEAR_Error
{
    function DB_Error($code = DB_ERROR, $mode = PEAR_ERROR_RETURN,
              $level = E_USER_NOTICE, $debuginfo = NULL)
    {
        if (is_int($code)) {
            $this->PEAR_Error('DB Error: ' . DB::errorMessage($code), $code, $mode, $level, $debuginfo);
        } else {
            $this->PEAR_Error("DB Error: $code", DB_ERROR, $mode, $level, $debuginfo);
        }
    }
}

class DB_Warning extends PEAR_Error
{
    function DB_Warning($code = DB_WARNING, $mode = PEAR_ERROR_RETURN,
            $level = E_USER_NOTICE, $debuginfo = NULL)
    {
        if (is_int($code)) {
            $this->PEAR_Error('DB Warning: ' . DB::errorMessage($code), $code, $mode, $level, $debuginfo);
        } else {
            $this->PEAR_Error("DB Warning: $code", 0, $mode, $level, $debuginfo);
        }
    }
}

class DB_result
{
    var $dbh;
    var $result;
    var $row_counter = NULL;

    var $limit_from  = NULL;

    var $limit_count = NULL;

    function DB_result(&$dbh, $result)
    {
        $this->dbh = &$dbh;
        $this->result = $result;
    }

    function fetchRow($fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        $this->dbh->fetchInto($this->result, &$arr, $fetchmode, $rownum);
        if(is_array($arr)) {
            return $arr;
        }
        else {
            return NULL;
        }
    }

    function fetchInto(&$arr, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = NULL)
    {
        return $this->dbh->fetchInto($this->result, &$arr, $fetchmode, $rownum);
    }

    function numCols()
    {
        return $this->dbh->numCols($this->result);
    }

    function numRows()
    {
        return $this->dbh->numRows($this->result);
    }

    function nextResult()
    {
        return $this->dbh->nextResult($this->result);
    }

    function free()
    {
        $err = $this->dbh->freeResult($this->result);
        if(DB::isError($err)) {
            return $err;
        }
        $this->result = FALSE;
        return TRUE;
    }

    function tableInfo($mode = NULL)
    {
        return $this->dbh->tableInfo($this->result, $mode);
    }

    function getRowCounter()
    {
        return $this->dbh->highest_fetched_row[$this->result];
    }
}

class DB_row
{
    function DB_row(&$arr)
    {
        for (reset($arr); $key = key($arr); next($arr)) {
            $this->$key = &$arr[$key];
        }
    }
}

class MDB_PEAR_PROXY
{
    var $MDB_object;
    
    function MDB_PEAR_PROXY($MDB_object)
    {
        $this->MDB_object = $MDB_object;
        $this->MDB_object->sequence_prefix = "_seq_";;
    }

    function connect($dsninfo, $persistent = FALSE)
    {
        return $this->MDB_object->connect($dsninfo, $persistent);
    }

    function disconnect()
    {
        return $this->MDB_object->disconnect();
    }

    function quoteString($string)
    {
        $string = $this->quote($string);
        if ($string{0} == "'") {
            return substr($string, 1, -1);
        }
        return $string;
    }

    function quote($string)
    {
        $this->MDB_object->quote($string);
        return $string;
    }

    function provides($feature)
    {
        return $this->MDB_object->support($feature);
    }

    function errorCode($nativecode)
    {
        return $this->MDB_object->errorCode($nativecode);
    }

    function errorMessage($dbcode)
    {
        return $this->MDB_object->errorMessage($dbcode);
    }

    function &raiseError($code = DB_ERROR, $mode = NULL, $options = NULL,
                         $userinfo = NULL, $nativecode = NULL)
    {
        return $this->MDB_object->raiseError($code = DB_ERROR, $mode, $options, $userinfo, $nativecode);
    }

    function setFetchMode($fetchmode, $object_class = NULL)
    {
        return $this->MDB_object->setFetchMode($fetchmode, $object_class);
    }

    function setOption($option, $value)
    {
        return $this->MDB_object->setOption($option, $value);
    }

    function getOption($option)
    {
        return $this->MDB_object->getOption($option);
    }

    function prepare($query)
    {
        return $this->MDB_object->prepareQuery($query);
    }

    function execute($stmt, $data = FALSE)
    {
        $result = $this->MDB_object->execute($stmt, $data);
        if (DB::isError($result) || $result === DB_OK) {
            return $result;
        } else {
            return new DB_result($this->MDB_object, $result);
        }
    }

    function executeMultiple( $stmt, &$data )
    {
        return $this->MDB_object->executeMultiple( $stmt, &$data );
    }

    function &query($query, $params = array()) {
        if (sizeof($params) > 0) {
            $sth = $this->MDB_object->prepare($query);
            if (MDB::isError($sth)) {
                return $sth;
            }
            return $this->MDB_object->execute($sth, $params);
        } else {
            $result = $this->MDB_object->query($query);
            if (MDB::isError($result) || $result === DB_OK) {
                return $result;
            } else {
                return new DB_result($this->MDB_object, $result);
            }
        }
    }

    function simpleQuery($query) {
        return $this->MDB_object->query($query);
    }

    function limitQuery($query, $from, $count)
    {
        $result = $this->MDB_object->limitQuery($query, $from, $count);
        if (DB::isError($result) || $result === DB_OK) {
            return $result;
        } else {
            return new DB_result($this->MDB_object, $result);
        }
    }

    function &getOne($query, $params = array())
    {
        return $this->MDB_object->getOne($query, $params);
    }

    function &getRow($query,
                     $params = NULL,
                     $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        return $this->MDB_object->getRow($query, $params, $fetchmode);
    }

    function &getCol($query, $col = 0, $params = array())
    {
        return $this->MDB_object->getCol($query, $col, $params);
    }

    function &getAssoc($query, $force_array = FALSE, $params = array(),
                       $fetchmode = DB_FETCHMODE_ORDERED, $group = FALSE)
    {
        return $this->MDB_object->getAssoc($query, $force_array, $params, $fetchmode, $group);
    }

    function &getAll($query,
                     $params = NULL,
                     $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        return $this->MDB_object->getAll($query, $params, $fetchmode);
    }

    function autoCommit($onoff = FALSE)
    {
        return $this->MDB_object->autoCommit($onoff);
    }

    function commit()
    {
        return $this->MDB_object->commit();
    }

    function rollback()
    {
        return $this->MDB_object->rollback();
    }

    function numRows($result)
    {
        return $this->MDB_object->numRows($result);
    }

    function affectedRows()
    {
        return $this->MDB_object->affectedRows();
    }

    function errorNative()
    {
        return $this->MDB_object->errorNative();
    }

    function nextId($seq_name, $ondemand = TRUE)
    {
        return $this->MDB_object->nextId($seq_name, $ondemand);
    }

    function createSequence($seq_name)
    {
        return $this->MDB_object->createSequence($seq_name, 1);
    }

    function dropSequence($seq_name)
    {
        return $this->MDB_object->dropSequence($seq_name);
    }

    function tableInfo($result, $mode = NULL)
    {
        return $this->MDB_object->tableInfo($result, $mode);
    }

    function getTables()
    {
        return $this->getListOf('tables');
    }

    function getListOf($type)
    {
        switch ($type) {
            case 'tables':
                $this->MDB_object->listTables($result);
                break;
            case 'views':
                $this->MDB_object->listViews($result);
                break;
            case 'users':
                $this->MDB_object->listUsers($result);
                break;
            case 'functions':
                $this->MDB_object->listFunctions($result);
                break;
            case 'databases':
                $this->MDB_object->listDatabases($result);
                break;
            default:
                return $this->raiseError(DB_ERROR_UNSUPPORTED);
        }
        return $result;
    }
}
?>