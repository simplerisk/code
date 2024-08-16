<?php

use Leaf\FS;

test('Create a new directory', function () {
    $dir = TEST_PATH;

    FS::createFolder($dir);
    expect($dir)->toBeDirectory();
});

test('Rename a directory', function () {
    $dir = TEST_PATH;
    $new = __DIR__ . DIRECTORY_SEPARATOR . 'test-new';

    FS::createFolder($dir);
    FS::renameFolder($dir, $new);
    expect($new)->toBeDirectory();
});

test('Delete a directory', function () {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'test-new';

    FS::createFolder($dir);
    FS::deleteFolder($dir);
    expect(false)->toBe(is_dir($dir));
});

test('List all files and folders in a directory', function () {
    $dir = __DIR__;

    FS::createFolder($dir);

    $list = FS::listDir($dir);
    $scanned_directory = array_values(array_diff(scandir($dir), array('..', '.')));

    expect(true)->toBe($list == $scanned_directory);
});


test('Get all the directories', function () {
    $dir = TEST_PATH;

    FS::createFolder($dir);
    
    $list = FS::listFolders(__DIR__);

    $this->assertCount(1, $list);
});
