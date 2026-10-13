<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

afterEach(function (): void {
    Request::setTrustedHosts([]);
});

test('forwarded headers are trusted only from private network proxies', function () {
    Route::get('proxy-probe', fn (Request $request) => [
        'secure' => $request->secure(),
        'ip' => $request->ip(),
    ]);

    $forwarded = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'];

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->get('http://localhost/proxy-probe', $forwarded)
        ->assertExactJson(['secure' => true, 'ip' => '203.0.113.9']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get('http://localhost/proxy-probe', $forwarded)
        ->assertExactJson(['secure' => false, 'ip' => '198.51.100.7']);
});

test('requests for untrusted hosts are rejected in production', function () {
    config(['app.url' => 'https://mail.example.com', 'app.trusted_hosts' => ['internal.example.net']]);

    $this->app['env'] = 'production';

    $this->get('https://mail.example.com/up')->assertOk();
    $this->get('https://internal.example.net/up')->assertOk();
    $this->get('http://127.0.0.1/up')->assertOk();
    $this->get('https://evil.example.org/up')->assertBadRequest();
});
