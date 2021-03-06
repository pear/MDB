<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Tomas V.V.Cox,                 |
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
// | Author: Manuel Lemos <mlemos@acm.org>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//

require 'MDB.php';
MDB::loadFile('Manager');
MDB::loadFile('metabase_wrapper');

/**
 * Part of Metabase test suite
 *
 * @package MDB
 * @category Database
 * @author  Manuel Lemos <mlemos@acm.org>>
 */

function Output($message)
{
    echo $message,"\n";
}

function Dump($output)
{
    echo $output;
}

$input_file = ($argc<2 ? "test.schema" : $argv[1]);
$variables = array(
    "create" => "1"
);
$arguments = array(
    "Type" => "mysql",
    "User" => "metapear",
    "Password" => "funky",
    "Debug" => "Output"
);
$manager = new metabase_manager_class;
$manager->debug = "Output";
$success = $manager->UpdateDatabase($input_file, $input_file.".before", $arguments, $variables);
if($success) {
    echo $manager->DumpDatabase(array(
        "Output" => "Dump",
        "EndOfLine" => "\n"
    ));
} else {
    echo "Error: ".$manager->error."\n";
}
if(count($manager->warnings) >0 ) {
    echo "WARNING:\n",implode($manager->warnings,"!\n"),"\n";
}
if($manager->database) {
    echo MetabaseDebugOutput($manager->database);
}

?>
