<?php
 /*
 *
 * @(#) $Header$
 *
 */
    require_once("MDB.php");
    require_once("Var_Dump.php");

    // just for kicks you can mess up this part to see some pear error handling
    $user = 'metapear';
    $pass = 'funky';
    //$pass = "";
    $host = 'localhost';
    $db_name = 'metapear_test_db';
    // Data Source Name: This is the universal connection string
    $dsn = "mysql://$user:$pass@$host/$db_name";
    // MDB::connect will return a Pear DB object on success
    // or a Pear DB Error object on error
    // You can also set to TRUE the second param
    // if you want a persistent connection:
    // $db = MDB::connect($dsn, true);
    $db = MDB::connect($dsn);
    // With MDB::isError you can differentiate between an error or
    // a valid connection.
    if (MDB::isError($db)) {
        die ($db->getMessage());
    }
    // happy query
    $query ="SELECT * FROM test";
    echo "query for the following examples:".$query."<br>";
    // run the query and get a result handler
    $result = $db->Query($query);
    // lets just get row:0 column:0 and free the result
    $db->FetchField($result, $field);
    echo "<br>field:<br>".$field."<br>";
    // run the query and get a result handler
    $result = $db->Query($query);
    // lets just get row:0 and free the result
    $db->FetchRow($result, $array);
    echo "<br>row:<br>";
    echo Var_Dump::display($array)."<br>";
    // run the query and get a result handler
    $result = $db->Query($query);
    // lets just get column:0 and free the result
    $db->FetchColumn($result, $array);
    echo "<br>column:<br>";
    echo Var_Dump::display($array)."<br>";
    // run the query and get a result handler
    $result = $db->Query($query);
    echo "tableInfo:<br>";
    echo Var_Dump::display($db->tableInfo($result))."<br>";
    // lets just get everything and free the result
    $result = $db->Query($query);
    $db->FetchAll($result, &$array);
    echo "<br>all:<br>";
    echo Var_Dump::display($array)."<br>";
    // save some time with this function
    // lets just get all and free the result
    $db->QueryAll($query, $array);
    echo "<br>all with just one call:<br>";
    echo Var_Dump::display($array)."<br>";
    // run the query with the offset 1 and count 1 and get a result handler
    unset($result);
    $result = $db->limitQuery($query, 1, 1);
    // lets just get everything but with an associative array and free the result
    $db->FetchAll($result, $array, DB_FETCHMODE_ASSOC);
    echo "<br>associative array with offset 1 and count 1:<br>";
    echo Var_Dump::display($array)."<br>";
    // lets create a sequence
    echo "<br>create a new seq with start 3 name real_funky_id<br>";
    $err = $db->createSequence("real_funky_id",3);
    if (MDB::isError($err)) {
        echo "<br>could not create sequence again<br>";
    }
    echo "<br>get the next id:<br>";
    echo $db->nextId("real_funky_id");
    echo "<br>affected rows:<br>";
    echo $db->affectedRows()."<br>";
    // lets try an prepare execute combo
    $alldata = array(  array(1, 'one', 'un'),
                       array(2, 'two', 'deus'),
                       array(3, 'three', 'trois'),
                       array(4, 'four', 'quatre'));
    $prepared_query = $db->prepare("INSERT INTO numbers VALUES(?,?,?)");
    echo "validate prepared query:<br>".$db->ValidatePreparedQuery($prepared_query)."<br>";
    foreach ($alldata as $row) {
        echo "running execute<br>";
        $db->execute($prepared_query, $row);
    }
    // lets try an prepare execute combo
    $alldata = array(  array(5, 'five', 'cinq'),
                       array(6, 'six', 'six'),
                       array(7, 'seven', 'sept'),
                       array(8, 'eight', 'huit'));
    $prepared_query = $db->prepare("INSERT INTO numbers VALUES(?,?,?)");
    $db->executeMultiple($prepared_query, $alldata);
    echo "running executeMultiple<br>";
    $array = array(4);
    echo "<br>see getOne in action:<br>".$db->getOne("SELECT trans_en FROM numbers WHERE number = ?",$array)."<br>";
    // You can disconnect from the database with:
    echo "<br>see getRow in action:<br>";
    echo Var_Dump::display($db->getRow("SELECT * FROM numbers WHERE number = ?",$array))."<br>";
    echo "<br>see getCol in action:<br>";
    echo Var_Dump::display($db->getCol("SELECT * FROM numbers",$array))."<br>";
    echo "<br>see getAssoc in action:<br>";
    echo Var_Dump::display($db->getAssoc("SELECT * FROM test", false, "", DB_FETCHMODE_ASSOC))."<br>";
    echo "tableInfo on a string:<br>";
    echo Var_Dump::display($db->tableInfo("numbers"))."<br>";
    $db->disconnect();
    
?>