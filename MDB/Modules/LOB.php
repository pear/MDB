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
// MDB LOB classes.
//

if(!defined("MDB_LOB_INCLUDED"))
{
    define("MDB_LOB_INCLUDED",1);

$lobs = array();

class MDB_lob extends PEAR
{
    var $error = "";
    var $database;
    var $lob;
    var $data = "";
    var $position = 0;

    function create(&$arguments)
    {
        if(isset($arguments["Data"])) {
            $this->data = $arguments["Data"];
        }
        return (DB_OK);
    }

    function destroy()
    {
        $this->data = "";
    }

    function endOfLob()
    {
        return($this->position >= strlen($this->data));
    }

    function readLob(&$data, $length)
    {
        $length = min($length,strlen($this->data)-$this->position);
        $data = substr($this->data, $this->position, $length);
        $this->position += $length;
        return($length);
    }
};

class MDB_lob_result extends MDB_lob
{
    var $result_lob = 0;

    function create(&$arguments)
    {
        if(!isset($arguments["ResultLOB"])) {
            $this->error = "it was not specified a result Lob identifier";
            return(0);
        }
        $this->result_lob = $arguments["ResultLOB"];
        return (DB_OK);
    }

    function destroy()
    {
        $this->database->destroyResultLob($this->result_lob);
    }

    function endOfLob()
    {
        return($this->database->endOfResultLob($this->result_lob));
    }

    function readLob(&$data, $length)
    {
        if(($read_length = $this->database->readResultLob($this->result_lob, $data, $length)) < 0) {
            $this->error = $this->database->error();
        }
        return($read_length);
    }
};

class MDB_lob_input_file extends MDB_lob
{
    var $file = 0;
    var $opened_file = 0;

    function create(&$arguments)
    {
        if(isset($arguments["File"])) {
            if(intval($arguments["File"]) == 0) {
                $this->error = "it was specified an invalid input file identifier";
                return(0);
            }
            $this->file = $arguments["File"];
        }
        else
        {
            if(isset($arguments["FileName"])) {
                if((!$this->file = fopen($arguments["FileName"],"r"))) {
                    $this->error = "could not open specified input file (\"".$arguments["FileName"]."\")";
                    return(0);
                }
                $this->opened_file = 1;
            } else {
                $this->error = "it was not specified the input file";
                return(0);
            }
        }        
        return (DB_OK);
    }

    function destroy()
    {
        if($this->opened_file) {
            fclose($this->file);
            $this->file = 0;
            $this->opened_file = 0;
        }
    }

    function endOfLob() {
        return(feof($this->file));
    }

    function readLob(&$data, $length)
    {
        if(GetType($data = @fread($this->file, $length))!= "string") {
            $this->error = "could not read from the input file";
            return(-1);
        }
        return(strlen($data));
    }
};

class MDB_lob_output_file extends MDB_lob
{
    var $file = 0;
    var $opened_file = 0;
    var $input_lob = 0;
    var $opened_lob = 0;
    var $buffer_length = 8000;

    function create(&$arguments)
    {
        global $lobs;
        if(isset($arguments["BufferLength"])) {
            if($arguments["BufferLength"] <= 0) {
                $this->error = "it was specified an invalid buffer length";
                return(0);
            }
            $this->buffer_length = $arguments["BufferLength"];
        }
        if(isset($arguments["File"])) {
            if(intval($arguments["File"]) == 0) {
                $this->error = "it was specified an invalid output file identifier";
                return(0);
            }
            $this->file = $arguments["File"];
        } else {
            if(isset($arguments["FileName"])) {
                if((!$this->file = fopen($arguments["FileName"],"w"))) {
                    $this->error = "could not open specified output file (\"".$arguments["FileName"]."\")";
                    return(0);
                }
                $this->opened_file = 1;
            } else {
                $this->error = "it was not specified the output file";
                return(0);
            }
        }
        if(isset($arguments["LOB"])) {
            if(!isset($lobs[$arguments["LOB"]])) {
                $this->destroy();
                $this->error = "it was specified an invalid input large object identifier";
                return(0);
            }
            $this->input_lob = $arguments["LOB"];
        } else {
            if($this->database
                && isset($arguments["Result"])
                && isset($arguments["Row"])
                && isset($arguments["Field"])
                && isset($arguments["Binary"]))
            {
                if($arguments["Binary"]) {
                    $this->input_lob = $this->database->fetchBLobResult($arguments["Result"],
                        $arguments["Row"], $arguments["Field"]);
                } else {
                    $this->input_lob = $this->database->fetchClobResult($arguments["Result"],
                        $arguments["Row"], $arguments["Field"]);
                }
                if($this->input_lob == 0) {
                    $this->destroy();
                    $this->error = "could not fetch the input result large object";
                    return(0);
                }
                $this->opened_lob = 1;
            } else {
                $this->destroy();
                $this->error = "it was not specified the input large object identifier";
                return(0);
            }
        }
        return (DB_OK);
    }

    function destroy()
    {
        if($this->opened_file) {
            fclose($this->file);
            $this->opened_file = 0;
            $this->file = 0;
        }
        if($this->opened_lob) {
            destroyLob($this->input_lob);
            $this->input_lob = 0;
            $this->opened_lob = 0;
        }
    }

    function endOfLob()
    {
        return(endOfLob($this->input_lob));
    }

    function readLob(&$data, $length) {
        $buffer_length = ($length == 0 ? $this->buffer_length : $length);
        for($written = 0;
        !endOfLob($this->input_lob) && $written<$buffer_length;
        $written += $read) {
            if(readLob($this->input_lob, $buffer, $buffer_length) == -1) {
                $this->error = lob($this->input_lob);
                return(-1);
            }
            $read = strlen($buffer);
            if(@fwrite($this->file, $buffer, $read)!= $read) {
                $this->error = "could not write to the output file";
                return(-1);
            }
        }
        return($written);
    }
};

function createLOB(&$arguments, &$lob)
{
    global $lobs;

    $lob = count($lobs)+1;
    $class_name = "MDB_lob";
    if(isset($arguments["Type"])) {
        switch($arguments["Type"]) {
            case "resultlob":
                $class_name = "MDB_lob_result";
                break;
            case "inputfile":
                $class_name = "MDB_lob_input_file";
                break;
            case "outputfile":
                $class_name = "MDB_lob_output_file";
                break;
            default:
                if(isset($arguments["Error"])) {
                    $arguments["Error"] = $arguments["Type"]." is not a valid type of large object";
                }
                return(0);
        }
    } else {
        if(isset($arguments["Class"])) {
            $class = $arguments["Class"];
        }
    }
    $lobs[$lob] = new $class_name;
    $lobs[$lob]->lob = $lob;

    if(isset($arguments["Database"])) {
        $lobs[$lob]->database = $arguments["Database"];
    }

    if($lobs[$lob]->create($arguments)) {
        return (DB_OK);
    }
    if(isset($arguments["Error"])) {
        $arguments["Error"] = $lobs[$lob]->error;
    }
    destroyLob($lob);
    return(0);
}

function destroyLob($lob)
{
    global $lobs;

    $lobs[$lob]->destroy();
    unset($lobs[$lob]);
    $lobs[$lob] = "";
}

function endOfLob($lob)
{
    global $lobs;

    return($lobs[$lob]->endOfLob());
}

function readLob($lob, &$data, $length)
{
    global $lobs;

    return($lobs[$lob]->readLob($data, $length));
}

function lobError($lob)
{
    global $lobs;

    return($lobs[$lob]->error);
}

}
?>
