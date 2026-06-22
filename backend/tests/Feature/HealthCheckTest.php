<?php

test('health endpoint returns ok status', function () {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'ResiliAI API',
        ]);
});

test('health endpoint exposes required json structure', function () {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'service',
        ]);
});
