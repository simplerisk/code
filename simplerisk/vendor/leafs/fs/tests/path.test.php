<?php

use Leaf\FS\Path;

test('Can get path', function () {
    $path = '/path/to/file.txt';
    
    expect(path($path))->toBeInstanceOf(Path::class);
});

test('Can get parent directory', function () {
    $path = '/path/to/file.txt';
    
    expect(path($path)->dirname())->toBe('/path/to');
});

test('Can get the last part of the path', function () {
    $path = '/path/to/file.txt';
    
    expect(path($path)->basename())->toBe('file.txt');
});

test('Can get the extension of the file', function () {
    $path = '/path/to/file.txt';
    
    expect(path($path)->extension())->toBe('txt');
});

test('Can join paths', function () {
    $path2 = '/path/to';
    
    expect(path($path2)->join('file2.txt'))->toBe(
        DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'to' . DIRECTORY_SEPARATOR . 'file2.txt'
    );
});

test('Can normalize path', function () {
    $path = '/path/to/../to/file.txt';
    
    expect(path($path)->normalize())->toBe(
        DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'to' . DIRECTORY_SEPARATOR . 'file.txt'
    );
});
