<html>
<body>
<?php
// $Id$
//
// MDB reverse engineering of xml schemas script.
//

    if($submit && $file != '') {
        require_once('manager.php');
        require_once('Var_Dump.php');

        $dsn = "$type://$user:$pass@$host/$name";

        $manager = new MDB_manager;
        $err = $manager->connect($dsn);
        if(MDB::isError($err)) {
            $error = $err->getMessage();
        } else {
            $manager->dumpDatabase(
                array(
                    'Output_Mode' => 'file',
                    'Output' => $file
                ),
                MDB_MANAGER_DUMP_STRUCTURE
            );
            $warnings = $manager->getWarnings();
            if(count($warnings) > 0) {
                Var_Dump::display($warnings);
            }
            Var_Dump::display($manager->database_definition);
            $manager->disconnect();
        }
    }
    
    if (!$submit || $error) {
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
            <input type="submit" name="submit" value="ok" />
        ');
    }
?>
</form>
</body>
</html>
