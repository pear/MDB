<?php
 /*
 *
 * @(#) $Header$
 *
 */
    require_once("pear_wrapper.php");
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
    // $db = DB::connect($dsn, true);
    $db = DB::connect($dsn);
    // With DB::isError you can differentiate between an error or
    // a valid connection.
    //echo Var_Dump::display($db)."<br>";
    if (DB::isError($db)) {
        die ($db->getMessage());
    }

    // happy query
    $query ="SELECT * FROM test";
    echo "query for the following examples:".$query."<br>";
    echo "<br>field:<br>".$db->getOne($query)."<br>";

    // run the query and get a result handler
    $result = $db->query($query);
    echo "<br>tableInfo() ";
    Var_Dump::display($result->tableInfo());

    $result = $db->query($query);
    echo "<br>numCols() ";
    Var_Dump::display($result->numCols());
    $result->fetchInto(&$arr);
    echo "<br>fetchInto() ";
    Var_Dump::display($arr);
    echo "<br>free() ";
    Var_Dump::display($result->free());

    $result = $db->query($query);
    echo "<br>numRows() ";
    Var_Dump::display($result->numRows());
    echo "<br>fetchRow() ";
    Var_Dump::display($result->fetchRow());

    // lets create a sequence on demand
    echo "<br>get the next id using on demand:<br>";
    echo "<br>nextId:".$db->nextId("real_funky_id_2");
    echo "<br>dropSequence:".$db->dropSequence("real_funky_id_2");

    // lets create a sequence
    echo "<br>create a new seq with start 3 name real_funky_id<br>";
    $err = $db->createSequence("real_funky_id",3);
    if (DB::isError($err)) {
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