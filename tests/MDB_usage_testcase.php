<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2002 Manuel Lemos, Paul Cooper                    |
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
// | Author: Paul Cooper <pgc@ucecom.com>                                 |
// +----------------------------------------------------------------------+
//
// $Id$

class MDB_Usage_TestCase extends PHPUnit_TestCase {
    //contains the dsn of the database we are testing
    var $dsn;
    //contains the MDB object of the db once we have connected
    var $db;
    // contains field names from the test table
    var $fields;
    // contains the types of the fields from the test table
    var $types;

    function MDB_Usage_TestCase($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        global $dsn, $options, $database;
        $this->dsn = $dsn;
        $this->database = $database;
        $this->db =& MDB::connect($dsn, $options);
        if (MDB::isError($this->db)) {
            $this->assertTrue(FALSE, 'Could not connect to database in setUp');
            exit;
        }
        $this->db->setDatabase($this->database);
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
        $this->clearTables();
    }

    function tearDown() {
        $this->clearTables();
        unset($this->dsn);
        if (!MDB::isError($this->db)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    function methodExists($name) {
        if (array_key_exists(strtolower($name), array_flip(get_class_methods($this->db)))) {
            return(TRUE);
        }
        $this->assertTrue(FALSE, 'method '. $name.' not implemented in '.get_class($this->db));
        return(FALSE);
    }

    function clearTables() {
        if (MDB::isError($this->db->query('DELETE FROM users'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
        if (MDB::isError($this->db->query('DELETE FROM files'))) {
            $this->assertTrue(FALSE, 'Error deleting from table users');
        }
    }

    function supported($feature) {
        if (!$this->db->support($feature)) {
            $this->assertTrue(FALSE, 'This database does not support'.$feature);
            return(FALSE);
        }
        return(TRUE);
    }

    function insertTestValues($prepared_query, &$data) {
        for ($i = 0; $i < count($this->fields); $i++) {
            $func = 'setParam'.$this->types[$i];
            $this->db->$func($prepared_query, ($i + 1), $data[$this->fields[$i]]);
        }
    }

    function verifyFetchedValues(&$result, $rownum, &$data) {
        for ($i = 0; $i < count($this->fields); $i++) {
            if ($this->types[$i] == 'text') {
                $func = 'fetch';
            } else {
                $func = 'fetch'.$this->types[$i];
            }
            if ($this->types[$i] == 'float') {
                $delta = 0.0000000001;
            } else {
                $delta = 0;
            }
            $value = $this->db->$func($result, $rownum, $i);

            $field = $this->fields[$i];
            $this->assertEquals($value, $data[$field], "the value retrieved for field \"$field\" ($value) using $func() doesn't match what was stored ($data[$field]).$func", $delta);
        }
    }

    /**
     * Test typed data storage and retrieval
     *
     * This tests typed data storage and retrieval by executing a single 
     * prepared query and then selecting the data back from the database
     * and comparing the results
     */
    function testStorage() {
        $row = 1234;
        $data = array();
        $data['user_name'] = "user_$row";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = $row % 2;
        $data['user_id'] = $row;
        $data['quota'] = strval($row/100);
        $data['weight'] = sqrt($row);
        $data['access_date'] = MDB_Date::mdbToday();
        $data['access_time'] = MDB_Date::mdbTime();
        $data['approved'] = MDB_Date::mdbNow();

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $this->insertTestValues($prepared_query, $data);

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
        }

        $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users');

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }
  
        $this->verifyFetchedValues($result, 0, $data);
    }

    /**
     * Test bulk fetch
     *
     * This test bulk fetching of result data by using a prepared query to 
     * insert an number of rows of data and then retrieving the data columns
     * one by one
     */ 
    function testBulkFetch() {
        $data = array();
        $total_rows = 5;

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        for ($row = 0; $row < $total_rows; $row++) {
            $data[$row]['user_name'] = "user_$row";
            $data[$row]['user_password'] = 'somepassword';
            $data[$row]['subscribed'] = $row % 2;
            $data[$row]['user_id'] = $row;
            $data[$row]['quota'] = sprintf("%.2f",strval(1+($row+1)/100));
            $data[$row]['weight'] = sqrt($row);
            $data[$row]['access_date'] = MDB_Date::mdbToday();
            $data[$row]['access_time'] = MDB_Date::mdbTime();
            $data[$row]['approved'] = MDB_Date::mdbNow();

            $this->insertTestValues($prepared_query, $data[$row]);

            $result = $this->db->executeQuery($prepared_query);
            
            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
            }
        }

        $this->db->freePreparedQuery($prepared_query);

        $total_fields =  count($this->fields);
        for ($i = 0; $i < $total_fields; $i++) {
            $field = $this->fields[$i];
            for ($row = 0; $row < $total_rows; $row++) {
                $value = $this->db->queryOne('SELECT '.$field.' FROM users WHERE user_id='.$row, $this->types[$i]);
                if (MDB::isError($value)) {
                    $this->assertTrue(FALSE, 'Error fetching row '.$row.' for field '.$field.' of type '.$this->types[$i]);  
                } else {
                    $this->assertEquals(strval(rtrim($value)), strval($data[$row][$field]), 'the query field '.$field.' of type '.$this->types[$i].' for row '.$row.' was returned in '.$value.' unlike '.$data[$row][$field].' as expected'); 
                }
            }
        }
    }

    /**
     * Test prepared queries
     *
     * Tests prepared queries, making sure they correctly deal with ?, !, and '
     */
    function testPreparedQueries() {
        $question_value = $this->db->getTextValue('Does this work?');

        $prepared_query = $this->db->prepareQuery("INSERT INTO users (user_name, user_password, user_id) VALUES (?, $question_value, 1)");

        $this->db->setParamText($prepared_query, 1, 'Sure!');

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $error = $result->getMessage();
        }

        $this->assertTrue(!MDB::isError($result), 'Could not execute prepared query with a text value with a question mark. Error: ');

        $question_value = $this->db->getTextValue("Wouldn't it be great if this worked too?");

        $prepared_query = $this->db->prepareQuery("INSERT INTO users (user_name, user_password, user_id) VALUES (?, $question_value, 2)");

        $this->db->setParamText($prepared_query, 1, 'For Sure!');

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $error = $result->getMessage();
        }

        $this->assertTrue(!MDB::isError($result), 'Could not execute prepared query with a text value with a quote character before a question mark. Error: ');

    }

    /**
     * Test retrieval of result metadata
     *
     * This tests the result metadata by executing a prepared_query and
     * select the data, and checking the result contains the correct
     * number of columns and that the column names are in the correct order
     */
    function testMetadata() {
        $row = 1234;
        $data = array();
        $data['user_name'] = "user_$row";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = $row % 2;
        $data['user_id'] = $row;
        $data['quota'] = strval($row/100);
        $data['weight'] = sqrt($row);
        $data['access_date'] = MDB_Date::mdbToday();
        $data['access_time'] = MDB_Date::mdbTime();
        $data['approved'] = MDB_Date::mdbNow();

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $this->insertTestValues($prepared_query, $data);

        $result = $this->db->executeQuery($prepared_query);

        $this->db->freePreparedQuery($prepared_query);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
        }

        $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users');

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }
  
        $numcols = $this->db->numCols($result);

        $this->assertEquals($numcols, count($this->fields), "The query result returned a number of $numcols columns unlike ".count($this->fields) .' as expected');

        $column_names = $this->db->getColumnNames($result);
        for ($column = 0; $column < $numcols; $column++) {
            $this->assertEquals($column_names[$this->fields[$column]], $column, "The query result column \"".$this->fields[$column]."\" was returned in position ".$column_names[$this->fields[$column]]." unlike $column as expected");
        }
        
    }

    /**
     * Test storage and retrieval of nulls
     * 
     * This tests null storage and retrieval by successively inserting, 
     * selecting, and testing a number of null / not null values
     */
    function testNulls() {
        $test_values = array(
                             array('test', FALSE),
                             array('NULL', FALSE),
                             array('null', FALSE),
                             array('', FALSE),
                             array(NULL, TRUE)
                             );

        for ($test_value = 0; $test_value <= count($test_values); $test_value++) {
            if ($test_value == count($test_values)) {
                $value = 'NULL';
                $is_null = TRUE;
            } else {
                $value = $this->db->getTextValue($test_values[$test_value][0]);
                $is_null = $test_values[$test_value][1];
            }

            $this->clearTables();

            $result = $this->db->query("INSERT INTO users (user_name,user_password,user_id) VALUES ($value,$value,0)");

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing insert query'.$result->getMessage());
            }

            $result = $this->db->query('SELECT user_name,user_password FROM users');

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing select query'.$result->getMessage());
            }

            $this->assertTrue(!$this->db->endOfResult($result), 'The query result seems to have reached the end of result earlier than expected');

            if ($is_null) {
                $error_message = 'A query result column is not NULL unlike what was expected';
            } else {
                $error_message = "A query result column is NULL even though it was expected to be \"".$test_values[$test_value][0]."\"";
            }
            
            $this->assertTrue(($this->db->resultIsNull($result, 0, 0) == $is_null), $error_message);

            $this->assertTrue(($this->db->resultIsNull($result, 0, 1) == $is_null), $error_message);

            $this->assertTrue($this->db->endOfResult($result), 'the query result did not seem to have reached the end of result as expected after testing only if columns are NULLs');

        }
    }

    /**
     * Tests escaping of text values with special characters
     *
     */
    function testEscapeSequences() {
        $test_strings = array(
                            "'",
                            "\"",
                            "\\",
                            "%",
                            "_",
                            "''",
                            "\"\"",
                            "\\\\",
                            "\\'\\'",
                            "\\\"\\\""
                            );

        for($string = 0; $string < count($test_strings); $string++) {
            $this->clearTables();

            $value = $this->db->getTextValue($test_strings[$string]);

            $result = $this->db->query("INSERT INTO users (user_name,user_password,user_id) VALUES ($value,$value,0)");

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing insert query'.$result->getMessage());
            }

            $result = $this->db->query('SELECT user_name,user_password FROM users');

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing select query'.$result->getMessage());
            }

            $this->assertTrue(!$this->db->endOfResult($result), 'The query result seems to have reached the end of result earlier than expected');

            $value = $this->db->fetch($result, 0, 'user_name');

            $this->assertEquals(rtrim($value), $test_strings[$string], "the value retrieved for field \"user_name\" (\"$value\") doesn't match what was stored (".$test_strings[$string].')');

        }
    }

    /**
     * Test paged queries
     *
     * Test the use of setSelectedRowRange to return paged queries
     */
    function testRanges() {
        if (!$this->supported('SelectRowRanges')) {
            return;
        }
        
        $data = array();
        $total_rows = 5;

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        for ($row = 0; $row < $total_rows; $row++) {
            $data[$row]['user_name'] = "user_$row";
            $data[$row]['user_password'] = 'somepassword';
            $data[$row]['subscribed'] = $row % 2;
            $data[$row]['user_id'] = $row;
            $data[$row]['quota'] = sprintf("%.2f",strval(1+($row+1)/100));
            $data[$row]['weight'] = sqrt($row);
            $data[$row]['access_date'] = MDB_Date::mdbToday();
            $data[$row]['access_time'] = MDB_Date::mdbTime();
            $data[$row]['approved'] = MDB_Date::mdbNow();

            $this->insertTestValues($prepared_query, $data[$row]);

            $result = $this->db->executeQuery($prepared_query);
            
            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
            }
        }

        $this->db->freePreparedQuery($prepared_query);
        
        for ($rows = 2, $start_row = 0; $start_row < $total_rows; $start_row += $rows) {

            $this->db->setSelectedRowRange($start_row, $rows);

            $result = $this->db->query('SELECT user_name,user_password,subscribed,user_id,quota,weight,access_date,access_time,approved FROM users ORDER BY user_id');

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing select query'.$result->getMessage());
            }

            for ($row = 0; $row < $rows && ($row + $start_row < $total_rows); $row++) {
                $this->verifyFetchedValues($result, $row, $data[$row + $start_row]);
            }
        }

        $this->assertTrue($this->db->endOfResult($result), "The query result did not seem to have reached the end of result as expected starting row $start_row after fetching upto row $row");

        $this->db->freeResult($result);

        for ($rows = 2, $start_row = 0; $start_row < $total_rows; $start_row += $rows) {

            $this->db->setSelectedRowRange($start_row, $rows);

            $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users ORDER BY user_id');

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing select query'.$result->getMessage());
            }

            $result_rows = $this->db->numRows($result);

            $this->assertTrue(($result_rows <= $rows), 'expected a result of no more than $rows but the returned number of rows is $result_rows');
            
            for ($row = 0; $row < $result_rows; $row++) {
                $this->assertTrue(!$this->db->endOfResult($result), 'The query result seem to have reached the end of result at row $row that is before $result_rows as expected');

                $this->verifyFetchedValues($result, $row, $data[$row + $start_row]);

            }
        }

        $this->assertTrue($this->db->endOfResult($result), 'the query result did not seem to have reached the end of result as expected');

        $this->db->freeResult($result);
    }

    /**
     * Test the handling of sequences
     */
    function testSequences() {
        if (!$this->supported('Sequences')) {
            return;
        }

        for ($start_value = 1; $start_value < 4; $start_value++) {
            $sequence_name = "test_sequence_$start_value";

            $this->db->dropSequence($sequence_name);

            $result = $this->db->createSequence($sequence_name, $start_value);
            $this->assertTrue(!MDB::isError($result), "Error creating sequence $sequence_name with start value $start_value");

            for ($sequence_value = $start_value; $sequence_value < ($start_value + 4); $sequence_value++) {
                $value = $this->db->nextId($sequence_name, FALSE);
                
                $this->assertEquals($sequence_value, $value, "The returned sequence value is $value and not $sequence_value as expected with sequence start value with $start_value");

            }

            $result = $this->db->dropSequence($sequence_name);

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, "Error dropping sequence $sequence_name : ".$result->getMessage());
            }

        }

        // Test ondemand creation of sequences
        $sequence_name = 'test_ondemand';

        $this->db->dropSequence($sequence_name);

        for ($sequence_value = 1; $sequence_value < 4; $sequence_value++) {
            $value = $this->db->nextId($sequence_name);
            
            $this->assertEquals($sequence_value, $value, "Error in ondemand sequences. The returned sequence value is $value and not $sequence_value as expected");
            
        }
        
        $result = $this->db->dropSequence($sequence_name);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, "Error dropping sequence $sequence_name : ".$result->getMessage());
        }
    }


    /**
     * Test replace query
     *
     * The replace method emulates the replace query of mysql
     */
    function testReplace() {
        if (!$this->supported('Replace')) {
            return;
        }

        $row = 1234;
        $data = array();
        $data['user_name'] = "user_$row";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = $row % 2;
        $data['user_id'] = $row;
        $data['quota'] = strval($row/100);
        $data['weight'] = sqrt($row);
        $data['access_date'] = MDB_Date::mdbToday();
        $data['access_time'] = MDB_Date::mdbTime();
        $data['approved'] = MDB_Date::mdbNow();

        $fields = array(
                      'user_name' => array(
                                           'Value' => "user_$row",
                                           'Type' => 'text'
                                         ),
                      'user_password' => array(
                                               'Value' => $data['user_password'],
                                               'Type' => 'text'
                                             ),
                      'subscribed' => array(
                                            'Value' => $data['subscribed'],
                                            'Type' => 'boolean'
                                          ),
                      'user_id' => array(
                                         'Value' => $data['user_id'],
                                         'Type' => 'integer',
                                         'Key' => 1
                                       ),
                      'quota' => array(
                                       'Value' => $data['quota'],
                                       'Type' => 'decimal'
                                     ),
                      'weight' => array(
                                        'Value' => $data['weight'],
                                        'Type' => 'float'
                                      ),
                      'access_date' => array(
                                             'Value' => $data['access_date'],
                                             'Type' => 'date'
                                           ),
                      'access_time' => array(
                                             'Value' => $data['access_time'],
                                             'Type' => 'time'
                                           ),
                      'approved' => array(
                                          'Value' => $data['approved'],
                                          'Type' => 'timestamp'
                                        )
                      );

        $support_affected_rows = $this->db->support('AffectedRows');

        $result = $this->db->replace('users', $fields);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Replace failed');
        }

        if ($support_affected_rows) {
            $affected_rows = $this->db->affectedRows();

            $this->assertEquals($affected_rows, 1, "replacing a row in an empty table returned $affected_rows unlike 1 as expected");
        }

        $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users');

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }
  
        $this->verifyFetchedValues($result, 0, $data);

        $row = 4321;
        $fields['user_name']['Value'] = $data['user_name'] = "user_$row";
        $fields['user_password']['Value'] = $data['user_password'] = 'somepassword';
        $fields['subscribed']['Value'] = $data['subscribed'] = $row % 2;
        $fields['quota']['Value'] = $data['quota'] = strval($row/100);
        $fields['weight']['Value'] = $data['weight'] = sqrt($row);
        $fields['access_date']['Value'] = $data['access_date'] = MDB_Date::mdbToday();
        $fields['access_time']['Value'] = $data['access_time'] = MDB_Date::mdbTime();
        $fields['approved']['Value'] = $data['approved'] = MDB_Date::mdbNow();

        $result = $this->db->replace('users', $fields);

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Replace failed');
        }

        if ($support_affected_rows) {
            $affected_rows = $this->db->affectedRows();

            $this->assertEquals($affected_rows, 2, "replacing a row in an empty table returned $affected_rows unlike 2 as expected");
        }

        $result = $this->db->query('SELECT user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved FROM users');

        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }
  
        $this->verifyFetchedValues($result, 0, $data);

        $this->assertTrue($this->db->endOfResult($result), 'the query result did not seem to have reached the end of result as expected');

        $this->db->freeResult($result);
    }

    /**
     * Test affected rows methods
     */
    function testAffectedRows() {
        if (!$this->supported('AffectedRows')) {
            return;
        }

        $data = array();
        $total_rows = 7;

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        for ($row = 0; $row < $total_rows; $row++) {
            $data[$row]['user_name'] = "user_$row";
            $data[$row]['user_password'] = 'somepassword';
            $data[$row]['subscribed'] = $row % 2;
            $data[$row]['user_id'] = $row;
            $data[$row]['quota'] = sprintf("%.2f",strval(1+($row+1)/100));
            $data[$row]['weight'] = sqrt($row);
            $data[$row]['access_date'] = MDB_Date::mdbToday();
            $data[$row]['access_time'] = MDB_Date::mdbTime();
            $data[$row]['approved'] = MDB_Date::mdbNow();

            $this->insertTestValues($prepared_query, $data[$row]);

            $result = $this->db->executeQuery($prepared_query);

            $affected_rows = $this->db->affectedRows();

            $this->assertEquals($affected_rows, 1, "Inserting the row $row return($affected_rows affected row count instead of 1 as expected)"); 

            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
            }
        }

        $this->db->freePreparedQuery($prepared_query);

        $prepared_query = $this->db->prepareQuery('UPDATE users SET user_password=? WHERE user_id < ?');

        for ($row = 0; $row < $total_rows; $row++) {
            $this->db->setParamText($prepared_query, 1, "another_password_$row");
            $this->db->setParamInteger($prepared_query, 2, $row);

            $result = $this->db->executeQuery($prepared_query);
            
            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
            }

            $affected_rows = $this->db->affectedRows();

            $this->assertEquals($affected_rows, $row, "Updating the $row rows return($affected_rows affected row count)"); 

        }

        $this->db->freePreparedQuery($prepared_query);

        $prepared_query = $this->db->prepareQuery('DELETE FROM users WHERE user_id >= ?');

        for ($row = $total_rows; $total_rows; $total_rows = $row) {
            $this->db->setParamInteger($prepared_query, 1, $row = intval($total_rows / 2));

            $result = $this->db->executeQuery($prepared_query);
            
            if (MDB::isError($result)) {
                $this->assertTrue(FALSE, 'Error executing prepared query'.$result->getMessage());
            }

            $affected_rows = $this->db->affectedRows();

            $this->assertEquals($affected_rows, ($total_rows - $row), 'Deleting '.($total_rows - $row)." rows returned $affected_rows affected row count"); 

        }

        $this->db->freePreparedQuery($prepared_query);
    }

    /**
     * Testing transaction support
     */
    function testTransactions() {
        if (!$this->supported('Transactions')) {
            return;
        }

        $this->db->autoCommit(0);

        $row = 0;
        $data = array();
        $data['user_name'] = "user_$row";
        $data['user_password'] = 'somepassword';
        $data['subscribed'] = $row % 2;
        $data['user_id'] = $row;
        $data['quota'] = strval($row/100);
        $data['weight'] = sqrt($row);
        $data['access_date'] = MDB_Date::mdbToday();
        $data['access_time'] = MDB_Date::mdbTime();
        $data['approved'] = MDB_Date::mdbNow();

        $prepared_query = $this->db->prepareQuery('INSERT INTO users (user_name, user_password, subscribed, user_id, quota, weight, access_date, access_time, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->executeQuery($prepared_query);
        $this->db->rollback();

        $result = $this->db->query('SELECT * FROM users');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }

        $this->assertTrue($this->db->endOfResult($result), 'Transaction rollback did not revert the row that was inserted');
        $this->db->freeResult($result);

        $this->insertTestValues($prepared_query, $data);
        $result = $this->db->executeQuery($prepared_query);
        $this->db->commit();

        $result = $this->db->query('SELECT * FROM users');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }

        $this->assertTrue(!$this->db->endOfResult($result), 'Transaction commit did not make permanent the row that was inserted');
        $this->db->freeResult($result);

        $result = $this->db->query('DELETE FROM users');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error deleting from users'.$result->getMessage());
            $this->db->rollback();
        }

        $autocommit = $this->db->autocommit(1);
        $this->assertTrue(!MDB::isError($autocommit), 'Error autocommiting transactions');

        $this->db->freePreparedQuery($prepared_query);

        $result = $this->db->query('SELECT * FROM users');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from users'.$result->getMessage());
        }

        $this->assertTrue($this->db->endOfResult($result), 'Transaction end with implicit commit when re-enabling auto-commit did not make permanent the rows that were deleted');
        $this->db->freeResult($result);
    }

    /**
     * Testing LOB storage
     */

    function testLobStorage() {
        if (!$this->supported('LOBs')) {
            return;
        }

        $prepared_query = $this->db->prepareQuery('INSERT INTO files (document,picture) VALUES (?,?)');

        $character_lob = array(
                              'Database' => $this->db,
                              'Error' => '',
                              'Data' => ''
                              );
        for ($code = 32; $code <= 127; $code++) {
            $character_lob['Data'] .= chr($code);
        }
        $binary_lob = array(
                            'Database' => $this->db,
                            'Error' => '',
                            'Data' => ''
                            );
        for ($code = 0; $code <= 255; $code++) {
            $binary_lob['Data'] .= chr($code);
        }

        $clob = $this->db->createLob($character_lob);

        $this->assertTrue(!MDB::isError($clob), 'Error creating character LOB: '.$character_lob['Error']);

        $blob = $this->db->createLob($binary_lob);
        
        $this->assertTrue(!MDB::isError($blob), 'Error creating binary LOB: '.$binary_lob['Error']);

        $this->db->setParamClob($prepared_query, 1, $clob, 'document');
        $this->db->setParamBlob($prepared_query, 2, $blob, 'picture');

        $result = $this->db->executeQuery($prepared_query);
        
        $this->assertTrue(!MDB::isError($result), 'Error executing prepared query');
        
        $this->db->destroyLob($blob);
        $this->db->destroyLob($clob);
        $this->db->freePreparedQuery($prepared_query);

        $result = $this->db->query('SELECT document, picture FROM files');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from files'.$result->getMessage());
        }

        $this->assertTrue(!$this->db->endOfResult($result), 'The query result seem to have reached the end of result too soon.');
        
        $clob = $this->db->fetchClob($result, 0, 'document');

        if (!MDB::isError($clob)) {
            for ($value = ''; !$this->db->endOfLob($clob);) {
                $this->assertTrue(($this->db->readLob($clob, $data, 8000) >= 0), 'Could not read CLOB');
                $value .= $data;
            }

            $this->db->destroyLob($clob);

            $this->assertEquals($value, $character_lob['Data'], "Retrieved character LOB value (\"".$value."\") is different from what was stored (\"".$character_lob['Data']."\")");
        } else {
            $this->assertTrue(FALSE, 'Error retrieving CLOB result');
        }

        $blob = $this->db->fetchBlob($result, 0, 'picture');

        if (!MDB::isError($blob)) {
            for ($value = ''; !$this->db->endOfLob($clob);) {
                $this->assertTrue(($this->db->readLob($blob, $data, 8000) >= 0), 'Could not read BLOB');
                $value .= $data;
            }

            $this->db->destroyLob($blob);

            $this->assertEquals($value, $binary_lob['Data'], "Retrieved binary LOB value (\"".$value."\") is different from what was stored (\"".$binary_lob['Data']."\")");
        } else {
            $this->assertTrue(FALSE, 'Error retrieving CLOB result');
        }

        $this->db->freeResult($result);
    }

    /**
     * Test for lob storage from and to files
     */

    function testLobFiles() {
        if (!$this->supported('LOBs')) {
            return;
        }

        $prepared_query = $this->db->prepareQuery('INSERT INTO files (document,picture) VALUES (?,?)');
        
        $character_data_file = 'character_data';
        if (($file = fopen($character_data_file, 'w'))) {
            for ($character_data = '', $code = 32; $code <= 127; $code++) {
                $character_data .= chr($code);
            }
            $character_lob = array(
                                   'Type' => 'inputfile',
                                   'Database' => $this->db,
                                   'Error' => '',
                                   'FileName' => $character_data_file
                                   );
            $this->assertTrue((fwrite($file, $character_data, strlen($character_data)) == strlen($character_data)), 'Error creating clob file to read from');
            fclose($file);
        }
        
        $binary_data_file = 'binary_data';
        if (($file = fopen($binary_data_file, 'wb'))) {
            for($binary_data = '', $code = 0; $code <= 255; $code++) {
                    $binary_data .= chr($code);
            }
            $binary_lob = array(
                                'Type' => 'inputfile',
                                'Database' => $this->db,
                                'Error' => '',
                                'FileName' => $binary_data_file
                                );
            $this->assertTrue((fwrite($file, $binary_data, strlen($binary_data)) == strlen($binary_data)), 'Error creating blob file to read from');
            fclose($file);
        }

        $clob = $this->db->createLob($character_lob);
        $this->assertTrue(!MDB::isError($clob), 'Error creating clob');

        $blob = $this->db->createLob($binary_lob);
        $this->assertTrue(!MDB::isError($blob), 'Error creating blob');

        $this->db->setParamCLOB($prepared_query, 1, $clob, 'document');
        $this->db->setParamBLOB($prepared_query, 2, $blob, 'picture');

        $result = $this->db->executeQuery($prepared_query);
        $this->assertTrue(!MDB::isError($result), 'Error executing prepared query - inserting LOB from files');

        $this->db->destroyLOB($blob);
        $this->db->destroyLOB($clob);

        $this->db->freePreparedQuery($prepared_query);

        $result = $this->db->query('SELECT document, picture FROM files');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from files'.$result->getMessage());
        }

        $this->assertTrue(!$this->db->endOfResult($result), 'The query result seem to have reached the end of result too soon.');

        $character_lob = array(
                             'Type' => 'outputfile',
                             'Database' => $this->db,
                             'Result' => $result,
                             'Row' => 0,
                             'Field' => 'document',
                             'Binary' => 0,
                             'Error' => '',
                             'FileName' => $character_data_file
                             );

        $clob = $this->db->createLOB($character_lob);

        if (!MDB::isError($clob)) {
            $this->assertTrue(($this->db->readLOB($clob, $data, 0) >= 0), 'Error reading CLOB ');
            $this->db->destroyLOB($clob);

            $this->assertTrue(($file = fopen($character_data_file, 'r')), "Error opening character data file: $character_data_file");
            $this->assertEquals(getType($value = fread($file, filesize($character_data_file))), 'string', "Could not read from character LOB file: $character_data_file");
            fclose($file);

            $this->assertEquals($value, $character_data, "retrieved character LOB value (\"".$value."\") is different from what was stored (\"".$character_data."\")");
        } else {
            $this->assertTrue(FALSE, 'Error creating character LOB in a file');
        }

        $binary_lob = array(
                            'Type' => 'outputfile',
                            'Database' => $this->db,
                            'Result' => $result,
                            'Row' => 0,
                            'Field' => 'picture',
                            'Binary' => 1,
                            'Error' => '',
                            'FileName' => $binary_data_file
                            );

        $blob = $this->db->createLOB($binary_lob);

        if (!MDB::isError($blob)) {
            $this->assertTrue(($this->db->readLOB($blob, $data, 0) >= 0), 'Error reading BLOB ');
            $this->db->destroyLOB($blob);

            $this->assertTrue(($file = fopen($binary_data_file, 'rb')), "Error opening binary data file: $binary_data_file");
            $this->assertEquals(getType($value = fread($file, filesize($binary_data_file))), 'string', "Could not read from binary LOB file: $binary_data_file");
            fclose($file);

            $this->assertEquals($value, $binary_data, "retrieved binary LOB value (\"".$value."\") is different from what was stored (\"".$binary_data."\")");
        } else {
            $this->assertTrue(FALSE, 'Error creating binary LOB in a file');
        }

        $this->db->freeResult($result);
    }

    /**
     * Test handling of lob nulls
     */

    function testLobNulls() {
        if (!$this->supported('LOBs')) {
            return;
        }
        
        $prepared_query = $this->db->prepareQuery('INSERT INTO files (document,picture) VALUES (?,?)');
  
        $this->db->setParamNull($prepared_query, 1, 'clob');
        $this->db->setParamNull($prepared_query, 2, 'blob');

        $result = $this->db->executeQuery($prepared_query);
        $this->assertTrue(!MDB::isError($result), 'Error executing prepared query - inserting NULL lobs');

        $this->db->freePreparedQuery($prepared_query);

        $result = $this->db->query('SELECT document, picture FROM files');
        if (MDB::isError($result)) {
            $this->assertTrue(FALSE, 'Error selecting from files'.$result->getMessage());
        }

        $this->assertTrue(!$this->db->endOfResult($result), 'The query result seem to have reached the end of result too soon.');

        $this->assertTrue($this->db->resultIsNull($result, 0, 'document'), 'A query result large object column is not NULL unlike what was expected (document)');
        $this->assertTrue($this->db->resultIsNull($result, 0, 'picture'), 'A query result large object column is not NULL unlike what was expected (picture)');

        $this->db->freeResult($result);
    }
}

?>