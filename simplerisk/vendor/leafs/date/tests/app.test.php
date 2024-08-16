<?php

test('tick returns the date instance', function () {
    expect((new \Leaf\Date())->tick())->toBeInstanceOf(\Leaf\Date::class);
});
