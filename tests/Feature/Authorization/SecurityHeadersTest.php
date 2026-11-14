<?php

namespace Tests\Feature\Authorization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function 全レスポンスにセキュリティヘッダーが付く(): void
    {
        foreach (['/login', '/register', '/missing-page'] as $path) {
            $this->get($path)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    #[Test]
    public function 本番以外ではStrictTransportSecurityを付けない(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function 本番ではStrictTransportSecurityを付ける(): void
    {
        $this->app['env'] = 'production';

        $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age='.config('trainingmax.hsts_max_age'));
    }
}
