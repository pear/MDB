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

require_once('MDB/Modules/Reverse/Common.php');

/**
 * MDB PostGreSQL driver for the schema reverse engineering module
 *
 * @package MDB
 * @category Database
 * @author  Paul Cooper <pgc@ucecom.com>
 */
class MDB_Reverse_pgsql extends MDB_Reverse_common
{
    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function MDB_Reverse_pgsql($db_index)
    {
        $this->MDB_Reverse_Common($db_index);
    }

    // }}} 
    // {{{ getTableFieldDefinition()

    /**
     * get the stucture of a field into an array
     *
     * @param string    $table         name of table that should be used in method
     * @param string    $field_name     name of field that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function getTableFieldDefinition($table, $field_name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query("SELECT 
                    attnum,attname,typname,attlen,attnotnull,
                    atttypmod,usename,usesysid,pg_class.oid,relpages,
                    reltuples,relhaspkey,relhasrules,relacl,adsrc
                    FROM pg_class,pg_user,pg_type,
                         pg_attribute left outer join pg_attrdef on
                         pg_attribute.attrelid=pg_attrdef.adrelid 
                    WHERE (pg_class.relname='$table') 
                        and (pg_class.oid=pg_attribute.attrelid) 
                        and (pg_class.relowner=pg_user.usesysid) 
                        and (pg_attribute.atttypid=pg_type.oid)
                        and attnum > 0
                        and attname = '$field_name'
                        ORDER BY attnum
                        ", null, false);
        if (MDB::isError($result)) {
            return $result;
        }
        $columns = $db->fetchRow($result, MDB_FETCHMODE_ASSOC);
        $db->freeResult($result);
        $field_column = $columns['attname'];
        $type_column = $columns['typname'];
        $db_type = preg_replace('/\d/','', strtolower($type_column) );
        $length = $columns['attlen'];
        if ($length == -1) {
            $length = $columns['atttypmod']-4;
        }
        //$decimal = strtok('(), '); = eh?
        $type = array();
        switch($db_type) {
            case 'int':
                $type[0] = 'integer';
                if ($length == '1') {
                    $type[1] = 'boolean';
                }
                break;
            case 'text':
            case 'char':
            case 'varchar':
            case 'bpchar':
                $type[0] = 'text';
                
                if ($length == '1') {
                    $type[1] = 'boolean';
                } elseif (strstr($db_type, 'text'))
                    $type[1] = 'clob';
                break;
/*                        
            case 'enum':
                preg_match_all('/\'.+\'/U',$row[$type_column], $matches);
                $length = 0;
                if (is_array($matches)) {
                    foreach($matches[0] as $value) {
                        $length = max($length, strlen($value)-2);
                    }
                }
                unset($decimal);
            case 'set':
                $type[0] = 'text';
                $type[1] = 'integer';
                break;
*/
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
            case 'money':
            case 'numeric':
                $type[0] = 'decimal';
                break;
            case 'oid':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
                $type[0] = 'blob';
                $type[1] = 'text';
                break;
            case 'year':
                $type[0] = 'integer';
                $type[1] = 'date';
                break;
            default:
                return $db->raiseError(MDB_ERROR, null, null,
                    'getTableFieldDefinition: unknown database attribute type');
        }
         
        if ($columns['attnotnull'] == 'f') {
            $notnull = 1;
        }
        
        if (!preg_match("/nextval\('([^']+)'/",$columns['adsrc']))  {
            $default = substr($columns['adsrc'],1,-1);
        }
        $definition = array();
        for($field_choices = array(), $datatype = 0; $datatype < count($type); $datatype++) {
            $field_choices[$datatype] = array('type' => $type[$datatype]);
            if (isset($notnull)) {
                $field_choices[$datatype]['notnull'] = 1;
            }
            if (isset($default)) {
                $field_choices[$datatype]['default'] = $default;
            }
            if ($type[$datatype] != 'boolean'
                && $type[$datatype] != 'time'
                && $type[$datatype] != 'date'
                && $type[$datatype] != 'timestamp')
            {
                if (strlen($length)) {
                    $field_choices[$datatype]['length'] = $length;
                }
            }
        }
        $definition[0] = $field_choices;
        if (preg_match("/nextval\('([^']+)'/",$columns['adsrc'],$nextvals))  {
             
            $implicit_sequence = array();
            $implicit_sequence['on'] = array();
            $implicit_sequence['on']['table'] = $table;
            $implicit_sequence['on']['field'] = $field_name;
            $definition[1]['name'] = $nextvals[1];
            $definition[1]['definition'] = $implicit_sequence;
        }
 
        // check that its not just a unique field
        if (MDB::isError($result = $db->query("SELECT 
                oid,indexrelid,indrelid,indkey,indisunique,indisprimary 
                FROm pg_index, pg_class 
                WHERE (pg_class.relname='$table') 
                    AND (pg_class.oid=pg_index.indrelid)", null, false))) {
            return $result;
        }
        if (MDB::isError($indexes = $db->fetchAll($result, MDB_FETCHMODE_ASSOC))) {
            return $indexes;
        }
        $db->freeResult($result);
        $indkeys = explode(' ',$indexes['indkey']);
        if (in_array($columns['attnum'],$indkeys)) {
            if (MDB::isError($result = $db->query("SELECT 
                    relname FROM pg_class WHERE oid={$columns['indexrelid']}", null, false))
            ) {
                return $result;
            }
            if (MDB::isError($indexname = $db->fetchAll($result))) {
                return $indexname;
            }
            $db->freeResult($result);
            $is_primary = ($indexes['isdisprimary'] == 't') ;
            $is_unique = ($indexes['isdisunique'] == 't') ;
        
            $implicit_index = array();
            $implicit_index['unique'] = 1;
            $implicit_index['fields'][$field_name] = $indexname['relname'];
            $definition[2]['name'] = $field_name;
            $definition[2]['definition'] = $implicit_index;
        }
        return $definition;
    }


    // }}}
    // {{{ getTableIndexDefinition()
    /**
     * get the stucture of an index into an array
     *
     * @param string    $table      name of table that should be used in method
     * @param string    $index_name name of index that should be used in method
     * @return mixed data array on success, a MDB error on failure
     * @access public
     */
    function getTableIndexDefinition($table, $index_name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $result = $db->query("SELECT * from pg_index, pg_class 
                                WHERE (pg_class.relname='$index_name') 
                                AND (pg_class.oid=pg_index.indexrelid)", null, false);
        $row = $db->fetchAll($result, MDB_FETCHMODE_ASSOC);
        if ($row[0]['relname'] != $index_name) {
            return $db->raiseError(MDB_ERROR, null, null,
                'getTableIndexDefinition: it was not specified an existing table index');
        }

        $db->loadModule('manager');
        $columns = $db->manager->listTableFields($table);

        $definition = array();
        if ($row[0]['indisunique'] == 't') {
            $definition[$index_name]['unique'] = 1;
        }

        $index_column_numbers = explode(' ', $row[0]['indkey']);

        foreach ($index_column_numbers as $number) {
            $definition['fields'][$columns[($number - 1)]] = array('sorting' => 'ascending');
        }
        return $definition;
    }

    // }}}
    // {{{ tableInfo()

    /**
     * returns meta data about the result set
     *
     * @param  mixed $resource PostgreSQL result identifier or table name
     * @param mixed $mode depends on implementation
     * @return array an nested array, or a MDB error
     * @access public
     */
    function tableInfo($result, $mode = null)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $count = 0;
        $id = 0;
        $res = array();
        
        /**
         * depending on $mode, metadata returns the following values:
         *
         * - mode is false (default):
         * $result[]:
         *    [0]['table']  table name
         *    [0]['name']   field name
         *    [0]['type']   field type
         *    [0]['len']    field length
         *    [0]['flags']  field flags
         *
         * - mode is MDB_TABLEINFO_ORDER
         * $result[]:
         *    ['num_fields'] number of metadata records
         *    [0]['table']  table name
         *    [0]['name']   field name
         *    [0]['type']   field type
         *    [0]['len']    field length
         *    [0]['flags']  field flags
         *    ['order'][field name]  index of field named 'field name'
         *    The last one is used, if you have a field name, but no index.
         *    Test:  if (isset($result['meta']['myfield'])) { ...
         *
         * - mode is MDB_TABLEINFO_ORDERTABLE
         *     the same as above. but additionally
         *    ['ordertable'][table name][field name] index of field
         *       named 'field name'
         *
         *       this is, because if you have fields from different
         *       tables with the same field name * they override each
         *       other with MDB_TABLEINFO_ORDER
         *
         *       you can combine MDB_TABLEINFO_ORDER and
         *       MDB_TABLEINFO_ORDERTABLE with MDB_TABLEINFO_ORDER |
         *       MDB_TABLEINFO_ORDERTABLE * or with MDB_TABLEINFO_FULL
         **/
        
        // if $result is a string, then we want information about a
        // table without a resultset
        if (is_string($result)) {
            $id = pg_exec($db->connection, "SELECT * FROM $result LIMIT 0");
            if (empty($id)) {
                return $db->pgsqlRaiseError();
            }
        } else { // else we want information about a resultset
            $id = $result;
            if (empty($id)) {
                return $db->pgsqlRaiseError();
            }
        }
        
        $count = @pg_numfields($id);
        
        // made this IF due to performance (one if is faster than $count if's)
        if (empty($mode)) {
            for ($i = 0; $i < $count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name'] = @pg_fieldname ($id, $i);
                $res[$i]['type'] = @pg_fieldtype ($id, $i);
                $res[$i]['len'] = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldflags($id, $i, $result) : '';
            }
        } else { // full
            $res['num_fields'] = $count;
            
            for ($i = 0; $i < $count; $i++) {
                $res[$i]['table'] = (is_string($result)) ? $result : '';
                $res[$i]['name'] = @pg_fieldname ($id, $i);
                $res[$i]['type'] = @pg_fieldtype ($id, $i);
                $res[$i]['len'] = @pg_fieldsize ($id, $i);
                $res[$i]['flags'] = (is_string($result)) ? $this->_pgFieldFlags($id, $i, $result) : '';
                if ($mode & MDB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & MDB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }
        
        // free the result only if we were called on a table
        if (is_string($result) && is_resource($id)) {
            @pg_freeresult($id);
        }
        return $res;
    }

    // }}}
    // {{{ _pgFieldFlags()

    /**
     * Flags of a Field
     *
     * @param int $resource PostgreSQL result identifier
     * @param int $num_field the field number
     * @return string The flags of the field ('not_null', 'default_xx', 'primary_key',
     *                 'unique' and 'multiple_key' are supported)
     * @access private
     **/
    function _pgFieldFlags($resource, $num_field, $table_name)
    {
        $db =& $GLOBALS['_MDB_databases'][$this->db_index];
        $field_name = @pg_fieldname($resource, $num_field);
        
        $result = pg_exec($db->connection, "SELECT f.attnotnull, f.atthasdef
            FROM pg_attribute f, pg_class tab, pg_type typ
            WHERE tab.relname = typ.typname
            AND typ.typrelid = f.attrelid
            AND f.attname = '$field_name'
            AND tab.relname = '$table_name'");
        if (pg_numrows($result) > 0) {
            $row = pg_fetch_row($result, 0);
            $flags = ($row[0] == 't') ? 'not_null ' : '';
            
            if ($row[1] == 't') {
                $result = pg_exec($db->connection, "SELECT a.adsrc
                    FROM pg_attribute f, pg_class tab, pg_type typ, pg_attrdef a
                    WHERE tab.relname = typ.typname AND typ.typrelid = f.attrelid
                    AND f.attrelid = a.adrelid AND f.attname = '$field_name'
                    AND tab.relname = '$table_name'");
                $row = pg_fetch_row($result, 0);
                $num = str_replace('\'', '', $row[0]);
                
                $flags .= "default_$num ";
            }
        }
        $result = pg_exec($db->connection, "SELECT i.indisunique, i.indisprimary, i.indkey
            FROM pg_attribute f, pg_class tab, pg_type typ, pg_index i
            WHERE tab.relname = typ.typname
            AND typ.typrelid = f.attrelid
            AND f.attrelid = i.indrelid
            AND f.attname = '$field_name'
            AND tab.relname = '$table_name'");
        $count = pg_numrows($result);
        
        for ($i = 0; $i < $count ; $i++) {
            $row = pg_fetch_row($result, $i);
            $keys = explode(' ', $row[2]);
            
            if (in_array($num_field + 1, $keys)) {
                $flags .= ($row[0] == 't') ? 'unique ' : '';
                $flags .= ($row[1] == 't') ? 'primary ' : '';
                if (count($keys) > 1)
                    $flags .= 'multiple_key ';
            }
        }
        
        return trim($flags);
    }
}
?>