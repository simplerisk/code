<?php

namespace PhpLang;

/**
 * Emulate C++'s SCOPE_EXIT { expr; }; construct by means of a
 * short-lived object which takes a callable as its ctor arg
 * and invokes it on destruction (i.e. when it drops out of scope).
 *
 * Note that if you retain a copy of this object, such as in
 * another object's property.  It's scope will not fully exit
 * until that object dies.
 *
 * This allows ScopeExit to apply to more than just lexical scopes.
 */
class ScopeExit {
    /** @var Callable Method to invoke on object destruction */
    private $onexit = null;

    /**
     * Initialize object from Callable
     *
     * When this object drops out of scope,
     * either by leaving the scope, or by explicit unset()
     * The $onexit property is invoked.
     *
     * @param Callable $onexit - Invocable callback with zero arg
     */
    public function __construct(Callable $onexit) {
        $this->onexit = $onexit;
    }

    /**
     * Invoke the callback since the object is falling out of scope
     */
    public function __destruct() {
        $cb = $this->onexit;
        $cb();
    }
}
