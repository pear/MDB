<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Paul Cooper                    |
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

/*
 This is a small test suite for MDB using PHPUnit
 */

// BC hack to define PATH_SEPARATOR for version of PHP prior 4.3
if(!defined('PATH_SEPARATOR')) {
    if(defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == "\\") {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}
ini_set('include_path', '..'.PATH_SEPARATOR.ini_get('include_path'));

require_once('PHPUnit.php');
require_once('test_setup.php');
require_once('testUtils.php');
require_once('MDB.php');
require_once('HTML_TestListener.php');

PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handle_pear_error');
function handle_pear_error ($error_obj)
{
    print '<pre><b>PEAR-Error</b><br />';
    echo $error_obj->getMessage().': '.$error_obj->getUserinfo();
    print '</pre>';
}

// you may need to uncomment the line and modify the multiplier as you see fit
set_time_limit(60*count($dbarray));

MDB::loadFile('Manager');
MDB::loadFile('Date');

foreach ($testcases as $testcase) {
    include_once($testcase.'.php');
}

$database = 'driver_test';

$testmethods = isset($_POST['testmethods']) ? $_POST['testmethods'] : NULL;

if (!is_array($testmethods)) {
    foreach ($testcases as $testcase) {
        $testmethods[$testcase] = array_flip(getTests($testcase));
    }
}

?>
<html>
<head>
<title>MDB Tests</title>
<link href="tests.css" rel="stylesheet" type="text/css">
</head>
<body>
<?php

foreach ($dbarray as $db) {
    $dsn = $db['dsn'];
    $options = $db['options'];
    $options['optimize'] = 'portability';

    $display_dsn = $dsn['phptype'] . "://" . $dsn['username'] . ":" . $dsn['password'] . "@" . $dsn['hostspec'] . "/" . $database;
    echo "<div class=\"test\">\n";
    echo "<div class=\"title\">Testing $display_dsn</div>\n";

    $suite = new PHPUnit_TestSuite();

    foreach ($testcases as $testcase) {
        if (isset($testmethods[$testcase]) && is_array($testmethods[$testcase])) {
            $methods = array_keys($testmethods[$testcase]);
            foreach ($methods as $method) {
                $suite->addTest(new $testcase($method));
            }
        }
    }

    $result = new PHPUnit_TestResult;
    $result->addListener(new HTML_TestListener);
    $suite->run($result);
    echo "\n</div>\n";
}
?>
</body>
</html>
