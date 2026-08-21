<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpLang\ScopeExit;

class ScopeExitBasicTest extends PHPUnit_Framework_TestCase {

    private function fCall(&$arg) {
        $arg = 1;
        $_ = new ScopeExit(function() use (&$arg) { $arg = 2; });
        $this->assertEquals(1, $arg);
        return;
    }

    public function testFCall() {
        $x = 0;
        $this->fCall($x);
        $this->assertEquals(2, $x);
    }

    public function testUnset() {
      $x = 0;
      $_ = new ScopeExit(function() use (&$x) { $x = 123; });
      $this->assertEquals(0, $x);
      unset($_);
      $this->assertEquals(123, $x);
    }
}
