<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

it('verifies that the test environment is working', function () {
    expect(true)->toBeTrue();
});

it('has WordPress functions available', function () {
    expect(function_exists('is_plugin_active'))->toBeTrue();
});

it('has Pest and PHPUnit assertions loaded', function () {
    expect(class_exists(\PHPUnit\Framework\TestCase::class))->toBeTrue();
});
