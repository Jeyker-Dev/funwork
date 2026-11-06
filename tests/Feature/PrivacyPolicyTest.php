<?php

test('guests can read the privacy policy', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('drive.file')
        ->assertSee(route('integrations.edit'));
});

test('the privacy policy is served at /privacy', function () {
    expect(route('privacy', absolute: false))->toBe('/privacy');
});
