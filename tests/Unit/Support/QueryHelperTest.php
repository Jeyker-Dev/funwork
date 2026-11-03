<?php

use App\Support\QueryHelper;

test('it escapes exclamation marks first', function () {
    expect(QueryHelper::escapeLike('hello!world'))->toBe('hello!!world');
});

test('it escapes percent signs', function () {
    expect(QueryHelper::escapeLike('100%'))->toBe('100!%');
});

test('it escapes underscore wildcards', function () {
    expect(QueryHelper::escapeLike('test_file'))->toBe('test!_file');
});

test('it escapes mixed special characters in proper sequence', function () {
    expect(QueryHelper::escapeLike('!_%'))->toBe('!!!_!%');
    expect(QueryHelper::escapeLike('foo_bar%baz!qux'))->toBe('foo!_bar!%baz!!qux');
});

test('it leaves normal text unchanged', function () {
    expect(QueryHelper::escapeLike('reporte 2026'))->toBe('reporte 2026');
    expect(QueryHelper::escapeLike(''))->toBe('');
});
