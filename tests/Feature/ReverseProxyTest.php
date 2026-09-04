<?php

/**
 * Render (and any platform like it) terminates TLS at its edge and forwards
 * the request to this container over plain HTTP, with the original scheme
 * carried in X-Forwarded-Proto. Without trusting that header, every
 * generated asset/route URL comes back as http:// on an https:// page —
 * the browser then blocks it as mixed content. See bootstrap/app.php's
 * trustProxies(at: '*') and docs/DEPLOYMENT.md.
 */
test('a request forwarded as HTTPS by the platform proxy is treated as secure', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/login');

    expect(request()->isSecure())->toBeTrue();
    expect(asset('build/assets/app.js'))->toStartWith('https://');
});

test('without a forwarded-proto header, the request is not treated as secure', function () {
    $this->get('/login');

    expect(request()->isSecure())->toBeFalse();
    expect(asset('build/assets/app.js'))->toStartWith('http://');
});
