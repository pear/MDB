<?php

require_once '../manager.php';
require_once '../date.php';
require_once 'PHPUnit/PHPUnit.php';

class MDB_Usage_TestCase extends PHPUnit_TestCase {
    //contains the dsn of the database we are testing
    var $dsn;
    //contains the MDB object of the db once we have connected
    var $db;
    // contains field names from the test table
    var $fields;
    // contains the types of the fields from the test table
    var $types;

    function MDB_Test($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        global $dsn;
        $this->dsn = $dsn;
        $this->db = MDB::connect($dsn);
        $this->fields = array('user_name',
                        'user_password',
                        'subscribed',
                        'user_id',
                        'quota',
                        'weight',
                        'access_date',
                        'access_time',
                        'approved'
                        );

        $this->types = array('text',
                       'text',
                       'boolean',
                       'text',
                       'decimal',
                       'float',
                       'date',
                       'time',
                       'timestamp'
                       );
    }

    function tearDown() {
        unset($this->dsn);
        if (!MDB::isError($this->db)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    function methodExists(&$class, $name) {
        if (array_key_exists(strtolower($name), array_flip(get_class_methods($class)))) {
            return TRUE;
        }
        $this->assertTrue(FALSE, 'method '. $name . ' not implemented in ' . get_class($class));
        return FALSE;
    }

    function insertTestValues($prepared_query, &$data) {
        for ($i = 0; $i < count($this->fields); $i++) {
            $func = 'setParam' . $this->types[$i];
            $this->db->$func($prepared_query, ($i + 1), $data[$this->fields[$i]]);
        }
    }

    function verifyFetchedValues(&$result, $rownum, &$data) {
        for ($i = 0; $i < count($this->fields); $i++) {
            if ($this->types[$i] == 'text') {
                $func = 'fetch';
            } else {
                $func = 'fetch' . $this->types[$i];
            }
            if ($this->types[$i] == 'float') {
                $delta = 0.000000000001;
            } else {
                $delta = 0;
            }
            $value = $this->db->$func($result, $rownum, $i);
            $field = $this->fields[$i];
            $this->assertEquals($value, $data[$field], "the value retrieved for field \"$field\" ($value) doesn't match what was stored ($data[$field]) . $func", $delta);
        }
    }

    function testStorage() {
        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
        $row = 1234;
        $data = array();
        $data["user_name"] = "user_$row";
        $data["user_password"] = "somepassword";
        $data["subscribed"] = $row % 2;
        $data["user_id"] = $row;
        $data["quota"] = strval($row/100);
        $data["weight"] = sqrt($row);
        $data["access_date"] = MDB_date::mdbToday();
        $data["access_time"] = MDB_date::mdbTime();
        $data["approved"] = MDB_date::mdbNow();

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $this->insertTestValues($prepared_query, $data);

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error executing prepared query' . $result->getMessage());
        }

        $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users');

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users' . $result->getMessage());
        }
  
        $this->verifyFetchedValues($result, 0, $data);

        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
        
    }

    function testBulkFetch() {
        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }

        $data = array();
        $total_rows = 5;
        for ($row = 0; $row < $total_rows; $row++) {
            $data[$row]["user_name"] = "user_$row";
            $data[$row]["user_password"] = "somepassword";
            $data[$row]["subscribed"] = $row % 2;
            $data[$row]["user_id"] = $row;
            $data[$row]["quota"] = sprintf("%.2f",strval(1+($row+1)/100));
            $data[$row]["weight"] = sqrt($row);
            $data[$row]["access_date"] = MDB_date::mdbToday();
            $data[$row]["access_time"] = MDB_date::mdbTime();
            $data[$row]["approved"] = MDB_date::mdbNow();

            $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $this->insertTestValues($prepared_query, $data[$row]);

            $result = $this->db->executeQuery($prepared_query);
            
            $this->db->freePreparedQuery($prepared_query);

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query' . $result->getMessage());
            }
        }

        $total_fields =  count($this->fields);
        for ($i = 0; $i < $total_fields; $i++) {
            $field = $this->fields[$i];
            for ($row = 0; $row < $total_rows; $row++) {
                $value = $this->db->queryOne('SELECT ' . $field . ' FROM users WHERE user_id=' . $row, $this->types[$i]);
                //                print_r($value);
                if (MDB::isError($value)) {
                    $this->assertTrue(FALSE, 'Error fetching row ' . $row . ' for field ' . $field . ' of type ' . $this->types[$i]);  
                } else {
                    $this->assertEquals(strval(rtrim($value)), strval($data[$row][$field]), 'the query field ' . $field . ' of type ' . $this->types[$i] . ' for row ' . $row . ' was returned in ' . $value . ' unlike ' . $data[$row][$field] . ' as expected'); 
                }
            }
        }

        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
    }

    function testPreparedQueries() {
        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }

        $question_value = $this->db->getTextValue("Does this work?");

        $prepared_query = $this->db->prepareQuery("INSERT INTO users (user_name, user_password, user_id) VALUES (?, $question_value, 1)");

        $this->db->setParamText($prepared_query, 1, "Sure!");

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $error = $result->getMessage();
        }

        $this->assertTrue(!MDB::isError($result), 'Could not execute prepared query with a text value with a question mark. Error: ');

        $question_value = $this->db->getTextValue("Wouldn't it be great if this worked too?");

        $prepared_query = $this->db->prepareQuery("INSERT INTO users (user_name, user_password, user_id) VALUES (?, $question_value, 2)");

        $this->db->setParamText($prepared_query, 1, "Sure!");

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $error = $result->getMessage();
        }

        $this->assertTrue(!MDB::isError($result), 'Could not execute prepared query with a text value with a quote character before a question mark. Error: ');

        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
        
    }


}

?>