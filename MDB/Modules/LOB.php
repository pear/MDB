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
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
 * MDB Large Object (BLOB/CLOB) classes
 *
 * @package MDB
 * @category Database
 * @access private
 * @author  Lukas Smith <smith@backendmedia.com>
 */
class MDB_LOB
{
    var $db;
    var $lob;
    var $data = '';
    var $position = 0;

    function create(&$arguments)
    {
        if (isset($arguments['data'])) {
            $this->data = $arguments['data'];
        }
        return MDB_OK;
    }

    function destroy()
    {
        $this->data = '';
    }

    function endOfLOB()
    {
        return $this->position >= strlen($this->data);
    }

    function readLOB(&$data, $length)
    {
        $length = min($length, strlen($this->data) - $this->position);
        $data = substr($this->data, $this->position, $length);
        $this->position += $length;
        return $length;
    }
};

class MDB_LOB_Result extends MDB_LOB
{
    var $result_lob = 0;

    function create(&$arguments)
    {
        if (!isset($arguments['resultLOB'])) {
            return PEAR::raiseError(null, MDB_ERROR_NEED_MORE_DATA, null, null,
                'it was not specified a result LOB identifier',
                'MDB_Error', true);
        }
        $this->result_lob = $arguments['resultLOB'];
        return MDB_OK;
    }

    function destroy()
    {
        $this->db->datatype->_destroyResultLOB($this->db, $this->result_lob);
    }

    function endOfLOB()
    {
        return $this->db->datatype->_endOfResultLOB($this->db, $this->result_lob);
    }

    function readLOB(&$data, $length)
    {
        $read_length = $this->db->datatype->_readResultLOB($this->db, $this->result_lob, $data, $length);
        if (MDB::isError($read_length)) {
            return $read_length;
        }
        if ($read_length < 0) {
            return PEAR::raiseError(null, MDB_ERROR_INVALID, null, null,
                'data was read beyond end of data source',
                'MDB_Error', true);
        }
        return $read_length;
    }
};

class MDB_LOB_Input_File extends MDB_LOB
{
    var $file = 0;
    var $opened_file = 0;

    function create(&$arguments)
    {
        if (isset($arguments['file'])) {
            if (intval($arguments['file']) == 0) {
                return PEAR::raiseError(null, MDB_ERROR_INVALID, null, null,
                    'it was specified an invalid input file identifier',
                    'MDB_Error', true);
            }
            $this->file = $arguments['file'];
        }
        else
        {
            if (isset($arguments['file_name'])) {
                if ((!$this->file = fopen($arguments['file_name'], 'rb'))) {
                return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND, null, null,
                    'could not open specified input file ("'.$arguments['file_name'].'")',
                    'MDB_Error', true);
                }
                $this->opened_file = 1;
            } else {
                return PEAR::raiseError(null, MDB_ERROR_NEED_MORE_DATA, null, null,
                    'it was not specified the input file',
                    'MDB_Error', true);
            }
        }
        return MDB_OK;
    }

    function destroy()
    {
        if ($this->opened_file) {
            fclose($this->file);
            $this->file = 0;
            $this->opened_file = 0;
        }
    }

    function endOfLOB() {
        return feof($this->file);
    }

    function readLOB(&$data, $length)
    {
        if (gettype($data = @fread($this->file, $length))!= 'string') {
            return PEAR::raiseError(null, MDB_ERROR, null, null,
                'could not read from the input file',
                'MDB_Error', true);
        }
        return strlen($data);
    }
};

class MDB_LOB_Output_File extends MDB_LOB
{
    var $file = 0;
    var $opened_file = 0;
    var $input_lob = 0;
    var $opened_lob = 0;
    var $buffer_length = 8000;

    function create(&$arguments)
    {
        if (isset($arguments['buffer_fength'])) {
            if ($arguments['buffer_length'] <= 0) {
                return PEAR::raiseError(null, MDB_ERROR_INVALID, null, null,
                    'it was specified an invalid buffer length',
                    'MDB_Error', true);
            }
            $this->buffer_length = $arguments['buffer_length'];
        }
        if (isset($arguments['file'])) {
            if (intval($arguments['file']) == 0) {
                return PEAR::raiseError(null, MDB_ERROR_INVALID, null, null,
                    'it was specified an invalid output file identifier',
                    'MDB_Error', true);
            }
            $this->file = $arguments['file'];
        } else {
            if (isset($arguments['file_name'])) {
                if ((!$this->file = fopen($arguments['file_name'],'wb'))) {
                    return PEAR::raiseError(null, MDB_ERROR_NOT_FOUND, null, null,
                        'could not open specified output file ("'.$arguments['file_name'].'")',
                        'MDB_Error', true);
                }
                $this->opened_file = 1;
            } else {
                return PEAR::raiseError(null, MDB_ERROR_NEED_MORE_DATA, null, null,
                    'it was not specified the output file',
                    'MDB_Error', true);
            }
        }
        if (isset($arguments['LOB'])) {
            $this->input_lob = $arguments['LOB'];
            $this->opened_lob = 1;
        }
        return MDB_OK;
    }

    function destroy()
    {
        if ($this->opened_file) {
            fclose($this->file);
            $this->opened_file = 0;
            $this->file = 0;
        }
        if ($this->opened_lob) {
            $this->db->datatype->destroyLOB($this->db, $this->input_lob);
            $this->input_lob = 0;
            $this->opened_lob = 0;
        }
    }

    function endOfLOB()
    {
        return $this->db->datatype->endOfLOB($this->db, $this->input_lob);
    }

    function readLOB(&$data, $length) {
        $buffer_length = ($length == 0 ? $this->buffer_length : $length);
        $written_full = 0;
        do {
            for($written = 0;
                !$this->db->datatype->endOfLOB($this->db, $this->input_lob)
                && $written < $buffer_length;
                $written += $read)
            {
                $result = $this->db->datatype->readLOB($this->db, $this->input_lob, $buffer, $buffer_length);
                if (MDB::isError($result)) {
                    return $result;
                }
                $read = strlen($buffer);
                if (@fwrite($this->file, $buffer, $read)!= $read) {
                    return PEAR::raiseError(null, MDB_ERROR, null, null,
                        'could not write to the output file',
                        'MDB_Error', true);
                }
            }
            $written_full += $written;
        } while($length == 0 && !$this->db->datatype->endOfLOB($this->db, $this->input_lob));
        return $written_full;
    }
}
?>