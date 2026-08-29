<?php

declare(strict_types=1);

// ---- required-check falsy fix ----

test('boolean false value passes a boolean rule instead of failing required', function () {
    $data = validator()->validate(['flag' => false], ['flag' => 'boolean']);

    expect(validator()->errors())->not()->toHaveKey('flag');
    expect($data)->not()->toBe(false);
});

test('string zero value passes a boolean rule instead of failing required', function () {
    $data = validator()->validate(['flag' => '0'], ['flag' => 'boolean']);

    expect(validator()->errors())->not()->toHaveKey('flag');
    expect($data)->not()->toBe(false);
});

test('integer zero value passes a boolean rule instead of failing required', function () {
    $data = validator()->validate(['flag' => 0], ['flag' => 'boolean']);

    expect(validator()->errors())->not()->toHaveKey('flag');
    expect($data)->not()->toBe(false);
});

test('null value still fails required', function () {
    expect(validator()->validate(['flag' => null], ['flag' => 'boolean']))->toBe(false);
    expect(validator()->errors())->toHaveKey('flag');
    expect(validator()->errors()['flag'])->toContain('required');
});

test('empty string still fails required', function () {
    expect(validator()->validate(['flag' => ''], ['flag' => 'boolean']))->toBe(false);
    expect(validator()->errors())->toHaveKey('flag');
});

test('empty array still fails required', function () {
    expect(validator()->validate(['flag' => []], ['flag' => 'array']))->toBe(false);
    expect(validator()->errors())->toHaveKey('flag');
});

test('optional field with string zero value is validated rather than skipped', function () {
    // '0' is not "missing", so a failing rule must actually fail
    expect(validator()->validate(['name' => '0'], ['name' => 'optional|alpha']))->toBe(false);
    expect(validator()->errors())->toHaveKey('name');

    // and a passing rule validates it
    $data = validator()->validate(['flag' => '0'], ['flag' => 'optional|boolean']);
    expect(validator()->errors())->not()->toHaveKey('flag');
    expect($data)->not()->toBe(false);
});

// ---- bool-to-string casting ----

test('native booleans pass the boolean rule', function () {
    expect(validator()->validateRule('boolean', true))->toBeTrue();
    expect(validator()->validateRule('boolean', false))->toBeTrue();
});

// ---- rule-name-only lowercasing ----

test('contains param keeps its case', function () {
    expect(validator()->validateRule('contains<Foo>', 'xFoox'))->toBeTrue();
    expect(validator()->validateRule('contains<Foo>', 'xfoox'))->toBeFalse();
});

test('uppercase rule names still resolve', function () {
    expect(validator()->validateRule('EMAIL', 'user@example.com'))->toBeTrue();
    expect(validator()->validateRule('Number', '123'))->toBeTrue();
});

// ---- filter_var-backed rules ----

test('email rule accepts long TLDs and rejects malformed addresses', function () {
    expect(validator()->validateRule('email', 'user@domain.photography'))->toBeTrue();
    expect(validator()->validateRule('email', 'nope@@x'))->toBeFalse();
});

test('ip rule validates octet ranges', function () {
    expect(validator()->validateRule('ip', '192.168.1.1'))->toBeTrue();
    expect(validator()->validateRule('ip', '999.999.999.999'))->toBeFalse();
});

test('ipv6 rule accepts compressed form', function () {
    expect(validator()->validateRule('ipv6', '::1'))->toBeTrue();
    expect(validator()->validateRule('ipv6', 'not-an-ip'))->toBeFalse();
});

test('url rule validates real urls', function () {
    expect(validator()->validateRule('url', 'https://example.com/path?q=1'))->toBeTrue();
    expect(validator()->validateRule('url', 'not a url'))->toBeFalse();
});

test('json rule actually parses json', function () {
    expect(validator()->validateRule('json', '{"a":1}'))->toBeTrue();
    expect(validator()->validateRule('json', '[1,2]'))->toBeTrue();
    expect(validator()->validateRule('json', '{bad}'))->toBeFalse();
});

// ---- isEmail ----

test('isEmail returns true for valid and false for invalid emails', function () {
    expect(validator()->isEmail('user@example.com'))->toBeTrue();
    expect(validator()->isEmail('not-an-email'))->toBeFalse();
});

// ---- submit() escaping ----

test('submit quotes and escapes field values (and is deprecated)', function () {
    $deprecations = [];
    set_error_handler(function ($errno, $errstr) use (&$deprecations) {
        $deprecations[] = $errstr;
        return true;
    }, E_USER_DEPRECATED);

    ob_start();
    validator()->submit('POST', '/target', ['field' => '" onmouseover=alert(1) x="']);
    $output = ob_get_clean();

    restore_error_handler();

    expect($deprecations)->toHaveCount(1);
    expect($output)->toContain('value="&quot; onmouseover=alert(1) x=&quot;"');
    expect($output)->not()->toContain('value="" onmouseover=');
});

// ---- data-aware rules (issue 1) ----

test('matchesvalueof compares within the validated data set', function () {
    $result = validator()->validate([
        'password' => 'secret123',
        'confirm' => 'secret123',
    ], [
        'password' => 'string',
        'confirm' => 'matchesvalueof<password>',
    ]);

    expect($result)->toBeArray();

    $result = validator()->validate([
        'password' => 'secret123',
        'confirm' => 'different',
    ], [
        'password' => 'string',
        'confirm' => 'matchesvalueof<password>',
    ]);

    expect($result)->toBeFalse();
    expect(validator()->errors())->toHaveKey('confirm');
});

test('custom rules receive the full data set as fourth argument', function () {
    validator()->addRule('afterstart', function ($value, $param, $fieldName, array $dataSource) {
        return strtotime($value) > strtotime($dataSource['start_date'] ?? '');
    }, '{Field} must be after start_date');

    expect(validator()->validate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-01',
    ], [
        'start_date' => 'date',
        'end_date' => 'date|afterstart',
    ]))->toBeArray();

    expect(validator()->validate([
        'start_date' => '2026-03-01',
        'end_date' => '2026-02-01',
    ], [
        'start_date' => 'date',
        'end_date' => 'date|afterstart',
    ]))->toBeFalse();
});

test('custom rules with fewer parameters still work', function () {
    validator()->addRule('shouty', function ($value) {
        return strtoupper($value) === $value;
    }, '{Field} must be uppercase');

    expect(validator()->validate(['tag' => 'YES'], ['tag' => 'shouty']))->toBeArray();
    expect(validator()->validate(['tag' => 'nope'], ['tag' => 'shouty']))->toBeFalse();
});

// ---- per-field messages (issue 2) ----

test('field.rule messages override rule messages for that field only', function () {
    $v = validator();
    $v->addMessage('email.email', 'We need a real email to reach you');

    $v->validate(['email' => 'not-an-email'], ['email' => 'email']);
    expect($v->errors()['email'])->toBe('We need a real email to reach you');

    // another field falls back to the rule-level message, whatever an
    // earlier test set it to — it must NOT get the per-field override
    $v->validate(['backup' => 'not-an-email'], ['backup' => 'email']);
    expect($v->errors()['backup'])->not()->toBe('We need a real email to reach you');
});

test('field.required messages override the required message', function () {
    $v = validator();
    $v->addMessage('username.required', 'Pick a username first');

    $v->validate([], ['username' => 'string', 'bio' => 'string']);

    expect($v->errors()['username'])->toBe('Pick a username first');
    expect($v->errors()['bio'] ?? '')->toContain('required');
});
