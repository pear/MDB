<?php
// $Id$
//
// MDB test script.
//

// BC hack to define PATH_SEPARATOR for version of PHP prior 4.3
if (!defined('PATH_SEPARATOR')) {
    if (defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == "\\") {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}
ini_set('include_path', '..'.PATH_SEPARATOR.ini_get('include_path'));

    // MDB.php doesnt have to be included since manager.php does that
    // manager.php is only necessary for handling xml schema files
    require_once 'MDB.php';
    // only including this to output result data
    require_once 'Var_Dump.php';

    PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handle_pear_error');
    function handle_pear_error ($error_obj)
    {
        print '<pre><b>PEAR-Error</b><br />';
        echo $error_obj->getMessage().': '.$error_obj->getUserinfo();
        print '</pre>';
    }

    // just for kicks you can mess up this part to see some pear error handling
    $user = 'metapear';
    $pass = 'funky';
    //$pass = '';
    $host = 'localhost';
    $db_name = 'metapear_test_db';
    if (isset($_GET['db_type'])) {
        $db_type = $_GET['db_type'];
    } else {
        $db_type = 'mysql';
    }
    echo($db_type.'<br>');

    // Data Source Name: This is the universal connection string
    $dsn['username'] = $user;
    $dsn['password'] = $pass;
    $dsn['hostspec'] = $host;
    $dsn['phptype'] = $db_type;
    // MDB::connect will return a Pear DB object on success
    // or a Pear MDB error object on error
    // You can also set to true the second param
    // if you want a persistent connection:
    // $db = MDB::connect($dsn, true);
    // you can alternatively build a dsn here
   //$dsn = "$db_type://$user:$pass@$host/$db_name";
    Var_Dump::display($dsn);
    $db =& MDB::connect($dsn);
    // With MDB::isError you can differentiate between an error or
    // a valid connection.
    if (MDB::isError($db)) {
        die (__LINE__.$db->getMessage());
    }
/*
    MDB::loadFile('Manager');
    $manager =& new MDB_Manager;
    $input_file = 'metapear_test_db.schema';
    // you can either pass a dsn string, a dsn array or an exisiting db connection
    $manager->connect($db);
    // lets create the database using 'metapear_test_db.schema'
    // if you have allready run this script you should have 'metapear_test_db.schema.before'
    // in that case MDB will just compare the two schemas and make any necessary modifications to the existing DB
    echo(Var_Dump::display($manager->updateDatabase($input_file, $input_file.'.before')).'<br>');
    echo('updating database from xml schema file<br>');
*/
    echo('switching to database: '.$db_name.'<br>');
    $db->setDatabase($db_name);
    // happy query
    $query ='SELECT * FROM test';
    echo('query for the following examples:'.$query.'<br>');
    // run the query and get a result handler
    $result = $db->query($query);
    // lets just get row:0 column:0 and free the result
    $field = $db->fetchOne($result);
    $db->freeResult($result);
    echo('<br>field:<br>'.$field.'<br>');
    // run the query and get a result handler
    $result = $db->query($query);
    // lets just get row:0 and free the result
    $array = $db->fetchRow($result);
    $db->freeResult($result);
    echo('<br>row:<br>');
    echo(Var_Dump::display($array).'<br>');
    // run the query and get a result handler
    $result = $db->query($query, null, 'Result');
    // lets just get row:0 and free the result
    $array = $result->fetchRow();
    $result->freeResult();
    echo('<br>row from object:<br>');
    echo(Var_Dump::display($array).'<br>');
    // run the query and get a result handler
    echo('<br>setting new result mode with setResultMode:<br>');
    $db->setResultMode('Result');
    $result = $db->query($query, null, true);
    // lets just get row:0 and free the result
    $array = $result->fetchRow();
    $result->freeResult();
    echo('<br>row from object defined with default result mode:<br>');
    echo(Var_Dump::display($array).'<br>');
    // run the query and get a result handler
    $result = $db->query($query, null, false);
    // lets just get column:0 and free the result
    $array = $db->fetchCol($result);
    $db->freeResult($result);
    echo('<br>column overriding default result mode with false:<br>');
    echo(Var_Dump::display($array).'<br>');
    echo '<br>unset default result class<br>';
    $db->setResultMode(false);
    // run the query and get a result handler
    $result = $db->query($query);
    // lets just get column:0 and free the result
    $array = $db->fetchCol($result, MDB_FETCHMODE_DEFAULT, 2);
    $db->freeResult($result);
    echo('<br>get column #2 (counting from 0):<br>');
    echo(Var_Dump::display($array).'<br>');
    // run the query and get a result handler
    $result = $db->query($query);
    echo('tableInfo:<br>');
    echo(Var_Dump::display($db->tableInfo($result)).'<br>');
    $types = array('integer', 'text', 'timestamp');
    $db->setResultTypes($result, $types);
    $array = $db->fetchAll($result, MDB_FETCHMODE_FLIPPED);
    $db->freeResult($result);
    echo('<br>all with result set flipped:<br>');
    echo(Var_Dump::display($array).'<br>');
    // save some time with this function
    // lets just get all and free the result
    Var_Dump::display($db->loadModule('extended'));
    $array = $db->extended->queryAll($query);
    $db->freeResult($result);
    echo('<br>all with just one call:<br>');
    echo(Var_Dump::display($array).'<br>');
    // run the query with the offset 1 and count 1 and get a result handler
    $result = $db->extended->limitQuery($query, null, 1, 1);
    // lets just get everything but with an associative array and free the result
    $array = $db->fetchAll($result, MDB_FETCHMODE_ASSOC);
    $db->freeResult($result);
    echo('<br>associative array with offset 1 and count 1:<br>');
    echo(Var_Dump::display($array).'<br>');
    // lets create a sequence
    echo(Var_Dump::display($db->loadModule('manager')));
    echo('<br>create a new seq with start 3 name real_funky_id<br>');
    $err = $db->manager->createSequence('real_funky_id', 3);
    if (MDB::isError($err)) {
            echo('<br>could not create sequence again<br>');
    }
    echo('<br>get the next id:<br>');
    $value = $db->nextId('real_funky_id');
    echo($value.'<br>');
    // lets try an prepare execute combo
    $alldata = array(
                     array(1, 'one', 'un'),
                     array(2, 'two', 'deux'),
                     array(3, 'three', 'trois'),
                     array(4, 'four', 'quatre')
    );
    $prepared_query = $db->prepare('INSERT INTO numbers VALUES(?,?,?)');
    foreach ($alldata as $row) {
            echo('running execute<br>');
            $db->extended->executeParams($prepared_query, null, $row, array('integer', 'text', 'text'));
    }
    // lets try an prepare execute combo
    $alldata = array(
                     array(5, 'five', 'cinq'),
                     array(6, 'six', 'six'),
                     array(7, 'seven', 'sept'),
                     array(8, 'eight', 'huit')
    );
    $prepared_query = $db->prepare('INSERT INTO numbers VALUES(?,?,?)');
    echo('running executeMultiple<br>');
    echo(Var_Dump::display($db->extended->executeMultiple($prepared_query, null, $alldata, array('integer', 'text', 'text'))).'<br>');
    $array = array(4);
    echo('<br>see getOne in action:<br>');
    echo(Var_Dump::display($db->extended->getOne('SELECT trans_en FROM numbers WHERE number = ?','text',$array)).'<br>');
    $db->setFetchmode(MDB_FETCHMODE_ASSOC);
    echo('<br>default fetchmode ist now MDB_FETCHMODE_ASSOC<br>');
    echo('<br>see getRow in action:<br>');
    echo(Var_Dump::display($db->extended->getRow('SELECT * FROM numbers WHERE number = ?',array('integer','text','text'),$array)));
    echo('default fetchmode ist now MDB_FETCHMODE_ORDERED<br>');
    $db->setFetchmode(MDB_FETCHMODE_ORDERED);
    echo('<br>see getCol in action:<br>');
    echo(Var_Dump::display($db->extended->getCol('SELECT * FROM numbers','text', null, null, 1)).'<br>');
    echo('<br>see getAll in action:<br>');
    echo(Var_Dump::display($db->extended->getAll('SELECT * FROM test',array('integer','text','text'))).'<br>');
    echo('<br>see getAssoc in action:<br>');
    echo(Var_Dump::display($db->extended->getAll('SELECT * FROM test',array('integer','text','text'), null, null, MDB_FETCHMODE_ASSOC)).'<br>');
    echo('tableInfo on a string:<br>');
    echo(Var_Dump::display($db->tableInfo('numbers')).'<br>');
    echo('<br>just a simple update query:<br>');
    echo(Var_Dump::display($db->query('UPDATE numbers set trans_en ='.$db->getValue('integer', 0))).'<br>');
    echo('<br>affected rows:<br>');
    echo($db->affectedRows().'<br>');
    // subselect test
    $sub_select = $db->subSelect('SELECT test_name from test WHERE test_name = '.$db->getValue('text', 'gummihuhn'), 'text');
    echo(Var_Dump::display($sub_select).'<br>');
    $query_with_subselect = 'SELECT * FROM test WHERE test_name IN ('.$sub_select.')';
    // run the query and get a result handler
    echo($query_with_subselect.'<br>');
    $result = $db->query($query_with_subselect);
    $array = $db->fetchAll($result);
    $db->freeResult($result);
    echo('<br>all with subselect:<br>');
    echo('<br>drop index (will fail if the index was never created):<br>');
    echo(Var_Dump::display($db->manager->dropIndex('test', 'test_id_index')).'<br>');
    $index_def = array(
        'fields' => array(
            'test_id' => array(
                'sorting' => 'ascending'
            )
        )
    );
    echo('<br>create index:<br>');
    echo(Var_Dump::display($db->manager->createIndex('test', 'test_id_index', $index_def)).'<br>');
/*
    if ($db_type == 'mysql') {
        $manager->captureDebugOutput(true);
        $manager->database->setOption('log_line_break', '<br>');
        // ok now lets create a new xml schema file from the existing DB
        // we will not use the 'metapear_test_db.schema' for this
        // this feature is especially interesting for people that have an existing Db and want to move to MDB's xml schema management
        // you can also try MDB_MANAGER_DUMP_ALL and MDB_MANAGER_DUMP_CONTENT
        echo(Var_Dump::display($manager->dumpDatabase(
            array(
                'Output_Mode' => 'file',
                'Output' => $db_name.'2.schema'
            ),
            MDB_MANAGER_DUMP_STRUCTURE
        )).'<br>');
        if ($manager->options['debug']) {
            echo($manager->debugOutput().'<br>');
        }
        // this is the database definition as an array
        echo(Var_Dump::display($manager->database_definition).'<br>');
    }
*/
    echo('<br>just a simple delete query:<br>');
    echo(Var_Dump::display($db->query('DELETE FROM numbers')).'<br>');
    // You can disconnect from the database with:
    $db->disconnect()
?>
