<?php

use Leaf\FS\Directory;
use Leaf\FS\File;
use Leaf\FS\Storage;

beforeAll(function () {
    if (!file_exists(TEST_PATH)) {
        mkdir(TEST_PATH);
    }
});

afterAll(function () {
    Storage::delete(TEST_PATH);
});

test('directory read filters with string patterns', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'regex-dir';

    Directory::create($folder, ['recursive' => true]);
    File::create($folder . DIRECTORY_SEPARATOR . 'app.test.php', 'x', ['overwrite' => true]);
    File::create($folder . DIRECTORY_SEPARATOR . 'notes.md', 'x', ['overwrite' => true]);

    $matches = Directory::read($folder, '*.test.php');

    expect($matches)->toBeArray()
        ->and(array_values($matches))->toBe(['app.test.php']);
});

test('directory create with overwrite replaces an existing directory', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'overwrite-dir';

    Directory::create($folder, ['recursive' => true]);
    File::create($folder . DIRECTORY_SEPARATOR . 'old.txt', 'old', ['overwrite' => true]);

    expect(Directory::create($folder, ['overwrite' => true]))->toBeTrue()
        ->and(File::exists($folder . DIRECTORY_SEPARATOR . 'old.txt'))->toBeFalse();
});

test('directory helpers return arrays for empty directories', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'empty-dir';

    Directory::create($folder, ['recursive' => true]);

    expect(Directory::files($folder))->toBe([])
        ->and(Directory::dirs($folder))->toBe([])
        ->and(Directory::isEmpty($folder))->toBeTrue();
});

test('directory isEmpty on a missing directory fails without a TypeError', function () {
    expect(Directory::isEmpty(TEST_PATH . DIRECTORY_SEPARATOR . 'does-not-exist'))->toBeFalse();
});

test('file create writes falsy string content', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'zero.txt';

    File::create($file, '0', ['overwrite' => true]);

    expect(File::read($file))->toBe('0');
});

test('bucket paths fail helpfully when leafs/s3 is not installed', function () {
    expect(File::create('s3://bucket/file.txt', 'x'))->toBeFalse()
        ->and(File::errors()['file'])->toContain('leafs/s3');
});

test('storage rename works for directories with contents', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'rename-src';
    $renamed = TEST_PATH . DIRECTORY_SEPARATOR . 'rename-dest';

    Directory::create($folder, ['recursive' => true]);
    File::create($folder . DIRECTORY_SEPARATOR . 'keep.txt', 'kept', ['overwrite' => true]);

    expect(Storage::rename($folder, $renamed))->toBeTrue()
        ->and(Directory::exists($folder))->toBeFalse()
        ->and(File::read($renamed . DIRECTORY_SEPARATOR . 'keep.txt'))->toBe('kept');
});

test('directory move deletes the full source tree', function () {
    $source = TEST_PATH . DIRECTORY_SEPARATOR . 'move-src';
    $destination = TEST_PATH . DIRECTORY_SEPARATOR . 'move-dest';

    Directory::create($source . DIRECTORY_SEPARATOR . 'nested', ['recursive' => true]);
    File::create($source . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deep.txt', 'deep', ['overwrite' => true]);

    expect(Directory::move($source, $destination, ['recursive' => true]))->toBeTrue()
        ->and(Directory::exists($source))->toBeFalse()
        ->and(File::read($destination . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deep.txt'))->toBe('deep');
});

test('upload moves a regular file and validates types from the original name', function () {
    $stagingDir = TEST_PATH . DIRECTORY_SEPARATOR . 'upload-staging';
    $uploadDir = TEST_PATH . DIRECTORY_SEPARATOR . 'uploads';

    Directory::create($stagingDir, ['recursive' => true]);
    // simulate the extensionless tmp file PHP gives uploads
    $tmp = $stagingDir . DIRECTORY_SEPARATOR . 'php3xk1';
    file_put_contents($tmp, 'file contents');

    $result = File::upload([
        'name' => 'notes.md',
        'tmp_name' => $tmp,
        'size' => 13,
    ], $uploadDir, [
        'validate' => true,
        'allowedTypes' => ['text'],
    ]);

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('notes.md')
        ->and($result['type'])->toBe('text')
        ->and(File::read($uploadDir . DIRECTORY_SEPARATOR . 'notes.md'))->toBe('file contents');
});

test('upload rejects disallowed types with a helpful message', function () {
    $stagingDir = TEST_PATH . DIRECTORY_SEPARATOR . 'upload-staging2';

    Directory::create($stagingDir, ['recursive' => true]);
    $tmp = $stagingDir . DIRECTORY_SEPARATOR . 'php9aa2';
    file_put_contents($tmp, 'binary');

    $result = File::upload([
        'name' => 'malware.exe',
        'tmp_name' => $tmp,
        'size' => 6,
    ], TEST_PATH . DIRECTORY_SEPARATOR . 'uploads2', [
        'validate' => true,
        'allowedTypes' => ['image'],
    ]);

    expect($result)->toBeFalse()
        ->and(File::errors()['upload'])->toContain('expected: image');
});

test('upload accepts a plain file path and copies without destroying the source', function () {
    // https://github.com/leafsphp/fs/issues/6 — string input was a TypeError
    $source = TEST_PATH . DIRECTORY_SEPARATOR . 'issue6.md';
    $uploadDir = TEST_PATH . DIRECTORY_SEPARATOR . 'issue6-uploads';

    File::create($source, 'from a string path', ['overwrite' => true]);

    $result = File::upload($source, $uploadDir);

    expect($result)->toBeArray()
        ->and($result['name'])->toBe('issue6.md')
        ->and(File::read($uploadDir . DIRECTORY_SEPARATOR . 'issue6.md'))->toBe('from a string path')
        ->and(File::exists($source))->toBeTrue();
});

test('upload with a missing path fails with a message, not a TypeError', function () {
    expect(File::upload(TEST_PATH . DIRECTORY_SEPARATOR . 'ghost.md', TEST_PATH))->toBeFalse()
        ->and(File::errors()['upload'])->toContain('does not exist');
});

test('resource uploads to local destinations fail gracefully', function () {
    $resource = fopen('php://temp', 'r+');

    expect(File::upload($resource, TEST_PATH . DIRECTORY_SEPARATOR . 'uploads3'))->toBeFalse()
        ->and(File::errors()['upload'])->toContain('buckets');

    fclose($resource);
});

test('storage helper works without a leaf app', function () {
    expect(storage())->toBeInstanceOf(\Leaf\FS\Storage::class);
});

test('readRange reads exact byte windows', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'range.txt';

    File::create($file, '0123456789', ['overwrite' => true]);

    expect(File::readRange($file, 0, 4))->toBe('0123')
        ->and(File::readRange($file, 4))->toBe('456789')
        ->and(File::readRange($file, 4, 2))->toBe('45')
        ->and(File::readRange($file, -3))->toBe('789')
        ->and(File::readRange($file, 0, 0))->toBe('')
        ->and(Storage::readRange($file, 2, 3))->toBe('234');
});

test('readRange rejects out-of-bounds and negative lengths', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'range2.txt';

    File::create($file, 'abc', ['overwrite' => true]);

    expect(File::readRange($file, 10))->toBeFalse()
        ->and(File::errors()['file'])->toContain('beyond the end')
        ->and(File::readRange($file, 0, -1))->toBeFalse();
});

test('chunks streams a file in bounded pieces that reassemble exactly', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'chunky.bin';
    $content = str_repeat('leaf', 2048); // 8KB

    File::create($file, $content, ['overwrite' => true]);

    $pieces = iterator_to_array(File::chunks($file, 1000), false);

    expect(implode('', $pieces))->toBe($content)
        ->and(max(array_map('strlen', $pieces)))->toBeLessThanOrEqual(1000)
        ->and(count($pieces))->toBe((int) ceil(strlen($content) / 1000));
});

test('chunks honours start and length windows', function () {
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'chunky2.txt';

    File::create($file, '0123456789', ['overwrite' => true]);

    expect(implode('', iterator_to_array(Storage::chunks($file, 3, 2, 6), false)))->toBe('234567')
        ->and(implode('', iterator_to_array(File::chunks($file, 4, -4), false)))->toBe('6789');
});

test('chunks validates eagerly, before iteration', function () {
    expect(File::chunks(TEST_PATH . DIRECTORY_SEPARATOR . 'nope.bin'))->toBeFalse()
        ->and(File::errors()['file'])->toContain('does not exist')
        ->and(File::chunks(TEST_PATH . DIRECTORY_SEPARATOR . 'nope.bin', 0))->toBeFalse();
});

test('documented aliases fileInfo, isFolder and extname exist', function () {
    $folder = TEST_PATH . DIRECTORY_SEPARATOR . 'alias-dir';
    $file = TEST_PATH . DIRECTORY_SEPARATOR . 'alias.txt';

    Directory::create($folder, ['recursive' => true]);
    File::create($file, 'aliased', ['overwrite' => true]);

    expect(Storage::isFolder($folder))->toBeTrue()
        ->and(Storage::isFolder($file))->toBeFalse()
        ->and(Storage::fileInfo($file))->toBeArray()
        ->and(Storage::fileInfo($file)['name'])->toBe('alias.txt')
        ->and(path('some/file.txt')->extname())->toBe('txt');
});

test('stream wrapper paths are not mistaken for bucket paths', function () {
    // bucket routing keys off a `scheme://` prefix, and php's own wrappers
    // look identical — they must keep working as local/stream reads
    $parse = new ReflectionMethod(\Leaf\FS\File::class, 'parseBucketPath');
    $parse->setAccessible(true);

    foreach (['php://input', 'https://example.com/a.txt', 'file:///etc/hosts', 'data://text/plain,hi'] as $path) {
        expect($parse->invoke(null, $path))->toBeNull();
    }

    // a bucket connection name is not a registered wrapper, so it still routes
    expect($parse->invoke(null, 's3://bucket/file.txt'))->toBe(['s3', 'bucket/file.txt']);

    // object keys stay forward-slash on every OS — normalize() speaks
    // DIRECTORY_SEPARATOR, which turned keys into backslashes on Windows
    expect($parse->invoke(null, 's3://bucket\\nested\\file.txt'))->toBe(['s3', 'bucket/nested/file.txt']);
});

test('stream wrapper paths fail as local paths, not as bucket errors', function () {
    // Path::normalize() has never handled wrapper urls, so these were
    // already unusable. What matters is that they stay a local-path miss
    // instead of being routed to a bucket that was never configured.
    $result = \Leaf\FS\File::read('file:///tmp/leaf-does-not-exist.txt');

    expect($result)->toBeFalse();
    expect(\Leaf\FS\File::errors()['file'] ?? '')->not->toContain('leafs/s3');
});
