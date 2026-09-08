<?php

declare(strict_types=1);

test('email test endpoint is hidden unless explicitly enabled', function () {
    config(['mail.test_endpoint_enabled' => false]);

    $this->postJson('/api/v1/email/test', [
        'email' => 'recipient@example.com',
    ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Not found.');
});

test('email test endpoint validates recipient email', function () {
    config(['mail.test_endpoint_enabled' => true]);

    $this->postJson('/api/v1/email/test', [
        'email' => 'not-an-email',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('email test endpoint sends synchronously without using the queue', function () {
    config([
        'mail.test_endpoint_enabled' => true,
        'mail.default' => 'array',
        'mail.from.address' => 'jod@example.com',
    ]);

    $this->postJson('/api/v1/email/test', [
        'email' => 'recipient@example.com',
        'subject' => 'JOD mail test',
        'message' => 'Mail transport test.',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Test email sent successfully.')
        ->assertJsonPath('data.deliveryMode', 'synchronous')
        ->assertJsonPath('data.mailer', 'array')
        ->assertJsonPath('data.recipient', 'recipient@example.com');
});
