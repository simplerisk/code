<?php

use Leaf\FS\Storage;

beforeAll(function () {
    if (!file_exists(TEST_PATH)) {
        mkdir(TEST_PATH);
    }
});

afterAll(function () {
    Storage::delete(TEST_PATH);
});

test('Create a new file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';

    expect(Storage::createFile($file))->toBe(true);
});

test('Create can also write content to a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test2.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    expect(file_get_contents($file))->toBe($content);
});

test('Create can add file content from closure', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test3.md';
    $content = 'my foo bar';

    Storage::createFile($file, function () use ($content) {
        return $content;
    });

    expect(file_get_contents($file))->toBe($content);
});

test('Write to an existing file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test4.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    Storage::writeFile($file, 'new content');

    expect(file_get_contents($file))->toBe('new content');
});

test('Write to an existing file using closure', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test5.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    Storage::writeFile($file, function () {
        return 'new content';
    });

    expect(file_get_contents($file))->toBe('new content');
});

test('Read the content of a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test6.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::read($file))->toBe($content);
});

test('Get file info', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test7.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    $info = Storage::info($file);

    expect($info['size'])->toBe(strlen($content));
    expect($info['type'])->toBe('text');
    expect($info['path'])->toBe($file);
});

test('Get file size', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test8.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::size($file))->toBe(strlen($content));
});

test('Get file size in KB', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test9.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::size($file, 'kb'))->toBe(strlen($content) / 1024);
});

test('Get file size in MB', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test10.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::size($file, 'mb'))->toBe(strlen($content) / 1024 / 1024);
});

test('Delete a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test11.md';

    Storage::createFile($file);
    Storage::delete($file);

    expect(file_exists($file))->toBe(false);
});

test('Copy a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test12.md';
    $newFile = TEST_PATH . DIRECTORY_SEPARATOR . 'test12-copy.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    Storage::copy($file, $newFile);

    expect(file_exists($newFile))->toBe(true);
    expect(file_get_contents($newFile))->toBe($content);
});

test('Move a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test13.md';
    $newFile = TEST_PATH . DIRECTORY_SEPARATOR . 'test13-new.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    Storage::move($file, $newFile);

    expect(file_exists($newFile))->toBe(true);
    expect(file_get_contents($newFile))->toBe($content);
    expect(file_exists($file))->toBe(false);
});

test('Rename a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test14.md';
    $newFile = TEST_PATH . DIRECTORY_SEPARATOR . 'test14-new.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);
    Storage::rename($file, $newFile);

    expect(file_exists($newFile))->toBe(true);
    expect(file_get_contents($newFile))->toBe($content);
    expect(file_exists($file))->toBe(false);
});

test('Check if a file exists', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test15.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::exists($file))->toBe(true);
});

test('Check if a file does not exist', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test16.md';

    expect(Storage::exists($file))->toBe(false);
});

test('Can get permissions', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test17.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::chmod($file))->toBeNumeric();
});

test('Can set permissions', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test18.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::chmod($file, 0777))->toBe(true);
});

test('Can get file extension', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test19.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::extension($file))->toBe('md');
});

test('Can get file type', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test23.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::type($file))->toBe('text');
});

test('Can get file last modified date', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test24.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::lastModified($file))->toBeNumeric();
});

test('Can get file basename', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test25.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::basename($file))->toBe('test25.md');
});

test('Can get file dirname', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test26.md';
    $content = 'foo bar';

    Storage::createFile($file, $content);

    expect(Storage::dirname($file))->toBe(TEST_PATH);
});
