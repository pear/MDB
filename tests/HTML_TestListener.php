<?php

class HTML_TestListener extends PHPUnit_TestListener {
    function addError(&$test, &$t) {
        echo "MyListener::addError() called.\n";
    }

    function addFailure(&$test, &$t) {
        $this->_fails += 1;
        if ($this->_fails == 1) {
            echo '<div class="failure">';
        }
        echo "Error $this->_fails : $t<br>";
    }

    function endTest(&$test) {
        if ($this->_fails == 0) {
            echo ' Test passed';
        } else {
            echo "There were $this->_fails failures for " . $test->getName() . "</div>";
        }
        echo "</div>";
    }

    function startTest(&$test) {
        $this->_fails = 0;
        echo "\n<div class=\"test\">" . get_class($test) . " : Starting " . $test->getName() .  " ...";
    }
}

?>