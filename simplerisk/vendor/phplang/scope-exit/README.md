# ScopeExit

This simple class provides an implementation of C++'s `SCOPE_EXIT`, or GoLang's `defer`.

To use, assign an instance of this object to a local variable.  When that variable falls out of scope (or is explicitly `unset`, the callback passed to the constructor will be invoked.  This is useful, for example, to aid cleanup at the end of a funciton.

```
function f(&$x) {
  $x = 1;
  $_ = new \PhpLang\ScopeExit(function() use (&$x) { $x = 2; });
  // $x is still 1 at this point.
  return 42;
  // After the return, the local scope is cleaned up, the closure is invoked, and it's set to 2
}

f($a);
var_dump($a); // int(2)
```
