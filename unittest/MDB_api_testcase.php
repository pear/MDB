<?php

require_once '../manager.php';
require_once '../date.php';
require_once 'PHPUnit/PHPUnit.php';

class MDB_Api_TestCase extends PHPUnit_TestCase {
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

    //test stuff in common.php
    function testConnect() {
        $db = MDB::connect($this->dsn);
        $this->assertTrue(!MDB::isError($db), 'Connect failed bailing out');
        if (MDB::isError($this->db)) {
            exit;
        }
    }

    function testGetOption() {
        if ($this->methodExists($this->db, 'getOption')) {
            $atc = $this->db->getOption('persistent');
            print_r($atc);
            $this->assertEquals($atc, $this->db->options['persistent']);
        }
    }

    function testSetOption() {
        if ($this->methodExists($this->db, 'setOption')) {
            $option = $this->db->getOption('persistent');
            $this->db->setOption('persistent', !$option);
            $this->assertEquals(!$option, $this->db->getOption('persistent'));
            $this->db->setOption('persistent', $option);
        }
    }

    function testGetTextValue() {
        if ($this->methodExists($this->db, 'getTextValue')) {
            $text = "Mr O'Leary";
            $text = $this->db->getTextValue($text);
            $this->assertEquals("'Mr O\'Leary'", $text);
        }
    }
    
    function testLoadExtension() {
        $this->assertTrue(FALSE, 'Test stub: please fill in');
    }

    function testLoadManager() {
        if ($this->methodExists($this->db, 'loadManager')) {
            $this->assertTrue(!MDB::isError($this->db->loadManager("Create database")));
        }
    }


    // test of the driver

    // helper function so that we don't have to write out a query a million times
    function standardQuery() {
        $query ="SELECT * FROM users";
        // run the query and get a result handler
        if (!MDB::isError($this->db)) {
            return $this->db->query($query);
        }
        return FALSE;
    }


    function testQuery() {
        if ($this->methodExists($this->db, 'query')) {
            $result = $this->standardQuery();
            $this->assertTrue(is_resource($result), 'query: $result returned is not a resource');        
        }
    }

    function testFetch() {
        if ($this->methodExists($this->db, 'fetch')) {
            $result = $this->standardQuery();
            $this->assertNotNull($this->db->fetch($result, 0, 0));
        }
    }

    function testNumCols() { 
        if ($this->methodExists($this->db, 'numCols')) {
            $result = $this->standardQuery();
            $this->assertTrue((!MDB::isError($this->db->numCols($result))) && ($this->db->numCols($result) > 0));
        }
    }
}

?>