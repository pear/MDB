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

/**
* MDB reverse engineering of xml schemas script.
*
* @package MDB
* @author  Lukas Smith <smith@dybnet.de>
 */

    echo ('
<html>
<body>
    ');

    if($submit && $file != '') {
        require_once('manager.php');
        require_once('Var_Dump.php');

        $dsn = "$type://$user:$pass@$host/$name";

        $manager = new MDB_manager;
        $err = $manager->connect($dsn);
        if(MDB::isError($err)) {
            $error = $err->getMessage();
        } else {
            if($action) {
                set_time_limit(0);
            }
            if($action == 'dump') {
                Var_Dump::display($manager->dumpDatabase(
                    array(
                        'Output_Mode' => 'file',
                        'Output' => $file
                    ),
                    MDB_MANAGER_DUMP_ALL
                ));
            } else if($action == 'create') {
                Var_Dump::display($manager->updateDatabase($file));
            } else {
                $error = 'no action selected';
            }
            $warnings = $manager->getWarnings();
            if(count($warnings) > 0) {
                Var_Dump::display($warnings);
            }
            Var_Dump::display($manager->database_definition);
            $manager->disconnect();
        }
    }
    
    if (!isset($submit) || isset($error)) {
        if ($error) {
            echo $error.'<br>';
        }
        echo ('
            <form action="reverse_engineer_xml_schema.php">
            Database Type:
            <select name="type">
                <option value="mysql"');
                if($type == 'mysql') {echo ('selected');}
                echo ('>MySQL</option>
            </select>
            <br />
            Username:
            <input type="text" name="user" value="'.$user.'" />
            <br />
            Password:
            <input type="text" name="pass" value="'.$pass.'" />
            <br />
            Host:
            <input type="text" name="host" value="'.$host.'" />
            <br />
            Databasename:
            <input type="text" name="name" value="'.$name.'" />
            <br />
            Filename:
            <input type="text" name="file" value="'.$file.'" />
            <br />
            Dump:
            <input type="radio" name="action" value="dump" />
            <br />
            Create:
            <input type="radio" name="action" value="create" />
            <br />
            <input type="submit" name="submit" value="ok" />
        ');
    }

    echo ('
</form>
</body>
</html>
    ');
?>
