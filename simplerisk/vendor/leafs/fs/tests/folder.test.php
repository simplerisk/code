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

test('Create a new folder', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir1';

    expect(Storage::createFolder($folder))->toBe(true);
    expect(is_dir($folder))->toBe(true);
});

test('Rename a folder', function () {
    $old = TEST_PATH . DIRECTORY_SEPARATOR . 'dir1';
    $new = TEST_PATH . DIRECTORY_SEPARATOR . 'dir2';

    Storage::createFolder($old);

    $result = Storage::rename($old, $new);

    expect($result)->toBe(true);
    expect(is_dir($new))->toBe(true);
    expect(is_dir($old))->toBe(false);
});

test('Delete a folder', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir1';

    Storage::createFolder($folder);
    Storage::delete($folder);
    expect(is_dir($folder))->toBe(false);
});

test('Copy a folder', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir4';
    $copy = TEST_PATH . DIRECTORY_SEPARATOR . 'dir5';

    Storage::createFolder($folder);
    Storage::copy($folder, $copy);

    expect(is_dir($copy))->toBe(true);
    expect(is_dir($folder))->toBe(true);
});

test('Move a folder', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir6';
    $move = TEST_PATH . DIRECTORY_SEPARATOR . 'dir7';

    Storage::createFolder($folder);
    Storage::move($folder, $move);

    expect(is_dir($move))->toBe(true);
    expect(is_dir($folder))->toBe(false);
});

test('Recursively copy a folder\'s content', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir8';
    $copy = TEST_PATH . DIRECTORY_SEPARATOR . 'dir9';
    
    Storage::createFolder($folder);
    Storage::createFile($folder . DIRECTORY_SEPARATOR . 'test.md', 'foo bar');
    Storage::createFolder($folder . DIRECTORY_SEPARATOR . 'subdir');

    Storage::copy($folder, $copy, ['recursive' => true]);

    expect(is_dir($copy))->toBe(true);
    expect(is_dir($folder))->toBe(true);
    expect(is_dir($copy . DIRECTORY_SEPARATOR . 'subdir'))->toBe(true);
    expect(file_exists($copy . DIRECTORY_SEPARATOR . 'test.md'))->toBe(true);
});

test('Recursively move a folder\'s content', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir10';
    $move = TEST_PATH . DIRECTORY_SEPARATOR . 'dir11';
    
    Storage::createFolder($folder);
    Storage::createFile($folder . DIRECTORY_SEPARATOR . 'test.md', 'foo bar');
    Storage::createFolder($folder . DIRECTORY_SEPARATOR . 'subdir');

    Storage::move($folder, $move, ['recursive' => true]);

    expect(is_dir($move))->toBe(true);
    expect(is_dir($folder))->toBe(false);
    expect(is_dir($move . DIRECTORY_SEPARATOR . 'subdir'))->toBe(true);
    expect(file_exists($move . DIRECTORY_SEPARATOR . 'test.md'))->toBe(true);
});

test('Check if a folder exists', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir13';

    Storage::createFolder($folder);

    expect(Storage::exists($folder))->toBe(true);
});

test('Check if a folder does not exist', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir14';

    expect(Storage::exists($folder))->toBe(false);
});

test('Check if a folder is empty', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir15';

    Storage::createFolder($folder);

    expect(Storage::isEmpty($folder))->toBe(true);
});

test('Check if a folder is not empty', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir16';

    Storage::createFolder($folder);
    Storage::createFile($folder . DIRECTORY_SEPARATOR . 'test.md', 'foo bar');

    expect(Storage::isEmpty($folder))->toBe(false);
});

test('List the content of a folder', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir17';

    Storage::createFolder($folder);
    Storage::createFile($folder . DIRECTORY_SEPARATOR . 'test.md', 'foo bar');
    Storage::createFolder($folder . DIRECTORY_SEPARATOR . 'subdir');

    $list = Storage::list($folder);

    expect($list)->toBeArray();
    expect($list)->toContain('test.md');
    expect($list)->toContain('subdir');
});

test('List the content of a folder with a filter', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir18';

    Storage::createFolder($folder);
    Storage::createFile($folder . DIRECTORY_SEPARATOR . 'test.md', 'foo bar');
    Storage::createFolder($folder . DIRECTORY_SEPARATOR . 'subdir');

    $list = Storage::list($folder, function ($item) {
        return $item !== 'subdir';
    });

    expect($list)->toBeArray();
    expect($list)->toContain('test.md');
    expect($list)->not()->toContain('subdir');
});

test('Get the parent directory of a file', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'test.md';
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'dir19';

    Storage::createFile($file);
    Storage::createFolder($folder);

    expect(Storage::dirname($file))->toBe(TEST_PATH);
    expect(Storage::dirname($folder))->toBe(TEST_PATH);
});
