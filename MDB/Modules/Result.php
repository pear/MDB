<?php
// vim: set et ts=4 sw=4 fdm=marker:
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
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//

// {{{ class MDB_Result
/**
 * This class implements a wrapper for a MDB result set.
 * A new instance of this class will be returned by the MDB implementation
 * after processing a query that returns data.
 *
 * @package  MDB
 * @author Stig Bakken <ssb@php.net>
 */

class MDB_result
{
    // {{{ properties

    var $dbh;
    var $result;

    // }}}
    // {{{ constructor
    /**
     * MDB_result constructor.
     * @param resource &$dbh   MDB object reference
     * @param resource $result  result resource id
     * @param array    $options assoc array with optional result options
     */

    function MDB_result(&$dbh, $result)
    {
        $this->dbh = &$dbh;
        $this->result = $result;
        $this->autofree    = $dbh->options['autofree'];
        $this->fetchmode   = $dbh->fetchmode;
    }

    // }}}
    // {{{ fetchRow()
    /**
     * Fetch and return a row of data (it uses driver->fetchInto for that)
     * @param int $fetchmode format of fetched row
     * @param int $rownum    the row number to fetch
     *
     * @return  array a row of data, NULL on no more rows or PEAR_Error on error
     *
     * @access public
     */
    function &fetchRow($fetchmode = MDB_FETCHMODE_DEFAULT, $rownum = null)
    {
        if ($fetchmode === MDB_FETCHMODE_DEFAULT) {
            $fetchmode = $this->fetchmode;
        }

        $this->row_counter++;
        $row = $this->dbh->fetchRow($this->result, $fetchmode, $rownum);
        if ($row == null && $this->autofree) {
            $this->freeResult();
        }
        return $row;
    }

    // }}}
    // {{{ numCols()
    /**
     * Get the the number of columns in a result set.
     *
     * @return int the number of columns, or a MDB error
     *
     * @access public
     */
    function numCols()
    {
        return $this->dbh->numCols($this->result);
    }

    // }}}
    // {{{ numRows()
    /**
     * Get the number of rows in a result set.
     *
     * @return int the number of rows, or a MDB error
     *
     * @access public
     */
    function numRows()
    {
        return $this->dbh->numRows($this->result);
    }

    // }}}
    // {{{ nextResult()
    /**
     * Get the next result if a batch of queries was executed.
     *
     * @return bool true if a new result is available or false if not.
     *
     * @access public
     */
    function nextResult()
    {
        return $this->dbh->nextResult($this->result);
    }

    // }}}
    // {{{ free()
    /**
     * Frees the resources allocated for this result set.
     * @return  int error code
     *
     * @access public
     */
    function freeResult()
    {
        $err = $this->dbh->freeResult($this->result);
        if(MDB::isError($err)) {
            return $err;
        }
        $this->result = false;
        return true;
    }

    // }}}
    // {{{ tableInfo()
    /**
    * @deprecated
    */
    function tableInfo($mode = null)
    {
        return $this->dbh->tableInfo($this->result, $mode);
    }

    // }}}
    // {{{ getRowCounter()
    /**
    * returns the actual row number
    * @return integer
    */
    function getRowCounter()
    {
        return $this->row_counter;
    }
    // }}}
}

?>