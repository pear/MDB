<?php

require_once 'PHPUnit.php';
require_once 'test_setup.php';
require_once 'testUtils.php';

foreach ($testcases as $testcase) {
    include_once $testcase . '.php';
    $output .= "<div class=\"testlineup\">\n";
    $output .= "<h1>TestCase : $testcase</h1>\n";
    $testmethods[$testcase] = getTests($testcase);
    foreach ($testmethods[$testcase] as $method) {
        $output .= testCheck($testcase, $method);
    }
    $output .= "</div>\n";
}

?>
<html>
<head>
<title>MDB Tests</title>
<link href="tests.css" rel="stylesheet" type="text/css">
</head>
<body>

<form method="post" action="test.php">
<?php
echo $output;
?>
<input type="submit">
</form>
</body>
</html>