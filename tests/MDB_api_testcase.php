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

class MDB_Api_TestCase extends PHPUnit_TestCase {
    //contains the dsn of the database we are testing
    var $dsn;
    //contains the MDB object of the db once we have connected
    var $db;
    // contains field names from the test table
    var $fields;
    // contains the types of the fields from the test table
    var $types;

    function MDB_Api_Test($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        global $dsn, $options, $database;
        $this->dsn = $dsn;
        $this->database = $database;
        $this->db =& MDB::connect($dsn, $options);
        if (MDB::isError($this->db)) {
            print_r($this->db);
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
    }

    function tearDown() {
        unset($this->dsn);
        if (!MDB::isError($this->db)) {
            $this->db->disconnect();
        }
        unset($this->db);
    }

    function methodExists($name) {
        if (array_key_exists(strtolower($name), array_flip(get_class_methods($this->db)))) {
            return TRUE;
        }
        $this->assertTrue(FALSE, 'method '. $name . ' not implemented in ' . get_class($this->db));
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
        if (!$this->methodExists('getOption')) {
            return;
        }
        $atc = $this->db->getOption('persistent');
        $this->assertEquals($atc, $this->db->options['persistent']);
    }

    function testSetOption() {
        if (!$this->methodExists($this->db, 'setOption')) {
            return;
        }
        $option = $this->db->getOption('persistent');
        $this->db->setOption('persistent', !$option);
        $this->assertEquals(!$option, $this->db->getOption('persistent'));
        $this->db->setOption('persistent', $option);
    }

    function testGetTextValue() {
        if (!$this->methodExists($this->db, 'getTextValue')) {
            return;
        }
        $text = "Mr O'Leary";
        $text = $this->db->getTextValue($text);
        $this->assertEquals("'Mr O\'Leary'", $text);
    }

    function testLoadExtension() {
        $this->assertTrue(FALSE, 'Test stub: please fill in');
    }

    function testLoadManager() {
        if (!$this->methodExists($this->db, 'loadManager')) {
            return;
        }
        $this->assertTrue(!MDB::isError($this->db->loadManager("Create database")));
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
        if (!$this->methodExists($this->db, 'query')) {
            return;
        }
        $result = $this->standardQuery();
        $this->assertTrue(is_resource($result), 'query: $result returned is not a resource');
    }

    function testFetch() {
        if (!$this->methodExists($this->db, 'fetch')) {
            return;
        }
        $result = $this->standardQuery();
        $this->assertNotNull($this->db->fetch($result, 0, 0));
    }

    function testNumCols() { 
        if (!$this->methodExists($this->db, 'numCols')) {
            return;
        }
        $result = $this->standardQuery();
        $this->assertTrue((!MDB::isError($this->db->numCols($result))) && ($this->db->numCols($result) > 0));
    }
}

?>