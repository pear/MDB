<?php

/*
 This is a small (at least to start with) test suite
*/

require_once 'PHPUnit/PHPUnit.php';
require_once 'HTML/IT.php';
require_once 'test_setup.php';

$tpl = new IntegratedTemplate('./templates');

$tpl->loadTemplatefile('results.tpl', true, true);

foreach ($testarray as $test) {
    include_once $test . '.php';
}

foreach ($dsnarray as $dsn) {
    foreach ($testarray as $test) {
        $tpl->setCurrentBlock('test');
        $tpl->setVariable('testtitle', 'Performing ' . $test); 

        $suite = new PHPUnit_TestSuite($test);
        $result = PHPUnit::run($suite);

        $tpl->setVariable('testresult', nl2br($result->toString()));
        $tpl->parseCurrentBlock('test');
    }

    $tpl->setCurrentBlock('dsn');
    $tpl->setVariable('title', 'Testing ' . $dsn);
    $tpl->parseCurrentBlock('dsn');
}

$tpl->show();

?>