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

    function &connect($dsn, $options = false)
    {
        if (!is_array($options) && $options) {
            $$options["persistent"] = true;
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
              $level = E_USER_NOTICE, $debuginfo = null)
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
            $level = E_USER_NOTICE, $debuginfo = null)
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
    var $row_counter = null;

    var $limit_from  = null;

    var $limit_count = null;

    function DB_result(&$dbh, $result)
    {
        $this->dbh = &$dbh;
        $this->result = $result;
    }

    function fetchRow($fetchmode = DB_FETCHMODE_DEFAULT, $rownum = null)
    {
        $this->dbh->fetchInto($this->result, &$arr, $fetchmode, $rownum);
        if(is_array($arr)) {
            return $arr;
        }
        else {
            return null;
        }
    }

    function fetchInto(&$arr, $fetchmode = DB_FETCHMODE_DEFAULT, $rownum = null)
    {
        $this->dbh->fetchInto($this->result, &$arr, $fetchmode, $rownum);
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
        $this->result = false;
        return true;
    }

    function tableInfo($mode = null)
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
    }

    function connect($dsninfo, $persistent = false)
    {
        return $this->MDB_object->connect($dsninfo, $persistent);
    }

    function disconnect()
    {
        return $this->MDB_object->disconnect();
    }

    function quoteString($string)
    {
        $this->MDB_object->quoteString($string);
        return $string;
    }

    function quote($string)
    {
        $this->MDB_object->quote($string);
        return $string;
    }

    function provides($feature)
    {
        return $this->MDB_object->provides($feature);
    }

    function errorCode($nativecode)
    {
        return $this->MDB_object->errorCode($nativecode);
    }

    function errorMessage($dbcode)
    {
        return $this->MDB_object->errorMessage($dbcode);
    }

    function &raiseError($code = DB_ERROR, $mode = null, $options = null,
                         $userinfo = null, $nativecode = null)
    {
        return $this->MDB_object->raiseError($code = DB_ERROR, $mode, $options, $userinfo, $nativecode);
    }

    function setFetchMode($fetchmode, $object_class = null)
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
        return $this->MDB_object->prepare($query);
    }

    function execute($stmt, $data = false)
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
        $result = $this->MDB_object->query($query, $params);
        if (DB::isError($result) || $result === DB_OK) {
            return $result;
        } else {
            return new DB_result($this->MDB_object, $result);
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
                     $params = null,
                     $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        return $this->MDB_object->getRow($query, $params, $fetchmode);
    }


    function &getCol($query, $col = 0, $params = array())
    {
        return $this->MDB_object->getCol($query, $col, $params);
    }


    function &getAssoc($query, $force_array = false, $params = array(),
                       $fetchmode = DB_FETCHMODE_ORDERED, $group = false)
    {
        return $this->MDB_object->getAssoc($query, $force_array, $params, $fetchmode, $group);
    }

    function &getAll($query,
                     $params = null,
                     $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        return $this->MDB_object->getAll($query, $params, $fetchmode);
    }

    function autoCommit($onoff=false)
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

    function nextId($seq_name, $ondemand = true)
    {
        $return = $this->MDB_object->nextId($seq_name);
        if($ondemand && $return == 0)
        {
            $this->MDB_object->createSequence($seq_name, 1);
            $return = $this->MDB_object->nextId($seq_name);
        }
        return $return;
    }

    function createSequence($seq_name)
    {
        return $this->MDB_object->createSequence($seq_name, 1);
    }

    function dropSequence($seq_name)
    {
        return $this->MDB_object->dropSequence($seq_name);
    }

    function tableInfo($result, $mode = null)
    {
        return $this->MDB_object->tableInfo($result, $mode);
    }

    function getTables()
    {
        return $this->MDB_object->getTables();
    }

    function getListOf($type)
    {
        return $this->MDB_object->getListOf($type);
    }
}
?>