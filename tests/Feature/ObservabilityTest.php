<?php

test('the database readiness check reports ok with a non-sensitive payload', function () {
    $response = $this->getJson('/up/db');

    $response->assertOk();
    $response->assertJson(['status' => 'ok', 'database' => 'ok']);
    $response->assertJsonMissingPath('connection');
    $response->assertJsonMissingPath('dsn');
});

test('every response carries a request id, and an inbound one is honored', function () {
    $response = $this->get('/up/db');
    $response->assertHeader('X-Request-Id');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();

    $inbound = 'test-correlation-id-123';
    $response2 = $this->withHeader('X-Request-Id', $inbound)->get('/up/db');
    $response2->assertHeader('X-Request-Id', $inbound);
});
