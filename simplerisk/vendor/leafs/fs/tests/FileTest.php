<?php

use Leaf\FS;

afterEach(function () {
    mkdir(TEST_PATH);
});

test('Create a new file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';
    $result = FS::createFile($file);

    expect(true)->toBe($result);
});

test('Write content to a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';
    $content = 'foo bar';
    
    FS::writeFile($file, $content);
    expect(true)->toBe(file_get_contents($file) === $content);
});


test('Read the content of a file into a string', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';
    $content = 'foo bar';
    
    FS::writeFile($file, $content);

    $read = FS::readFile($file);

    expect(true)->toBe($read === $content);
});

test('Rename a file', function () {
    $old = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';
    $new = TEST_PATH . DIRECTORY_SEPARATOR . 'test-new.md';

    FS::createFile($old);

    $result = FS::renameFile($old, $new);

    expect(true)->toBe($result);
});

test('Delete a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';

    FS::createFile($file);
    FS::deleteFile($file);
    expect(false)->toBe(file_exists($file));
});