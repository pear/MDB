<?php

require_once '../MDB.php';
require_once '../manager.php';
require_once '../date.php';
require_once 'PHPUnit/PHPUnit.php';

class MDB_Manager_TestCase extends PHPUnit_TestCase {
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

    // test the manager
    function testManager() {
        global $includemanager;
        if ($includemanager) {
            $manager = new MDB_manager;
            $input_file = 'metapear_test_db.schema';
            $output_file = $input_file . '.before'; 
            $database_variables = array();
            $result = $manager->updateDatabase($input_file, $input_file.".before", $dsn, $database_variables);
            $result = $manager->updateDatabase($input_file, $output_file, $this->dsn, $database_variables);
        
            $this->assertTrue(!MDB::isError($result), $result->toString());
        } else {
            $this->assertTrue(TRUE);
        }
    }


}

?>