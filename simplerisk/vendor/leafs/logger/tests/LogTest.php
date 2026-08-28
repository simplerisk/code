<?php

use Leaf\Log;

beforeEach(function () {
    $this->writer = new SpyWriter();
    $this->log = new Log($this->writer);
});

it('routes every level method to write with the correct level constant', function () {
    $this->log->emergency('m');
    $this->log->alert('m');
    $this->log->critical('m');
    $this->log->error('m');
    $this->log->warning('m');
    $this->log->notice('m');
    $this->log->info('m');
    $this->log->debug('m');

    $levels = array_column($this->writer->writes, 'level');

    expect($levels)->toBe([
        Log::EMERGENCY,
        Log::ALERT,
        Log::CRITICAL,
        Log::ERROR,
        Log::WARN,
        Log::NOTICE,
        Log::INFO,
        Log::DEBUG,
    ]);

    expect(array_column($this->writer->writes, 'message'))->each->toBe('m');
});

it('gets and sets the log level', function () {
    expect($this->log->level())->toBe(Log::DEBUG);

    $this->log->level(Log::ERROR);

    expect($this->log->level())->toBe(Log::ERROR);
});

it('warns on an invalid level and leaves the previous level unchanged', function () {
    $this->log->level(Log::WARN);

    $warned = false;

    set_error_handler(function ($errno, $errstr) use (&$warned) {
        $warned = true;
        expect($errstr)->toContain('Invalid log level');

        return true;
    });

    try {
        $this->log->level(42);
    } finally {
        restore_error_handler();
    }

    expect($warned)->toBeTrue();
    expect($this->log->level())->toBe(Log::WARN);
});

it('returns false from log() when the level is invalid', function () {
    set_error_handler(fn () => true);

    try {
        $result = $this->log->log(99, 'message');
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeFalse();
    expect($this->writer->writes)->toBeEmpty();
});

it('returns false and does not write when the logger is disabled', function () {
    $this->log->enabled(false);

    expect($this->log->info('hello'))->toBeFalse();
    expect($this->writer->writes)->toBeEmpty();
});

it('filters out messages less severe than the threshold', function () {
    $this->log->level(Log::WARN);

    expect($this->log->debug('too verbose'))->toBeFalse();
    expect($this->log->info('too verbose'))->toBeFalse();
    expect($this->writer->writes)->toBeEmpty();
});

it('always writes messages at or above the severity threshold', function () {
    $this->log->level(Log::WARN);

    $this->log->warning('at threshold');
    $this->log->emergency('most severe');

    expect($this->writer->writes)->toHaveCount(2);
});

it('print_rs array messages', function () {
    $this->log->info(['a' => 1]);

    expect($this->writer->writes[0]['message'])->toBe(print_r(['a' => 1], true));
});

it('print_rs plain object messages', function () {
    $object = new stdClass();
    $object->key = 'value';

    $this->log->info($object);

    expect($this->writer->writes[0]['message'])->toBe(print_r($object, true));
});

it('casts objects with __toString to string', function () {
    $this->log->info(new StringableMessage());

    expect($this->writer->writes[0]['message'])->toBe('stringable message');
});

it('interpolates {placeholder} values from context', function () {
    $this->log->info('User {name} did {action}', ['name' => 'ama', 'action' => 'login']);

    expect($this->writer->writes[0]['message'])->toBe('User ama did login');
});

it('appends a context exception to the message', function () {
    $exception = new \Exception('boom');

    $this->log->error('Something failed', ['exception' => $exception]);

    expect($this->writer->writes[0]['message'])->toBe('Something failed - ' . $exception);
});

it('appends any Throwable from context, including Error', function () {
    $error = new \Error('fatal-ish');

    $this->log->error('Engine issue', ['exception' => $error]);

    expect($this->writer->writes[0]['message'])->toBe('Engine issue - ' . $error);
});

it('does not treat a non-throwable exception context key specially', function () {
    $this->log->error('Oops {exception}', ['exception' => 'just a string']);

    expect($this->writer->writes[0]['message'])->toBe('Oops just a string');
});

it('reports and toggles enabled state', function () {
    expect($this->log->enabled())->toBeTrue();
    expect($this->log->isEnabled())->toBeTrue();

    $this->log->enabled(false);

    expect($this->log->enabled())->toBeFalse();
    expect($this->log->isEnabled())->toBeFalse();
});

it('gets and sets the writer', function () {
    expect($this->log->writer())->toBe($this->writer);

    $newWriter = new SpyWriter();
    $this->log->writer($newWriter);

    expect($this->log->writer())->toBe($newWriter);

    $this->log->info('hello');

    expect($newWriter->writes)->toHaveCount(1);
    expect($this->writer->writes)->toBeEmpty();
});

it('returns whatever the writer returns on a successful log', function () {
    $this->writer->returnValue = 'writer-result';

    expect($this->log->info('hello'))->toBe('writer-result');
});
