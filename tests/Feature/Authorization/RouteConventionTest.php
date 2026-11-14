<?php

namespace Tests\Feature\Authorization;

use App\Http\Controllers\Records\RecordsController;
use App\Http\Controllers\Staff\StaffController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ProbeRecordsController;
use Tests\Support\RegistersProbeRoutes;
use Tests\TestCase;

/**
 * /clients/{client} と /staff 配下のルートが、認可を行う基底コントローラーを継承していること。
 * 認可の付け忘れ（基底クラスを使わないコントローラーやクロージャ）を検出する。
 */
class RouteConventionTest extends TestCase
{
    use RefreshDatabase;
    use RegistersProbeRoutes;

    /**
     * @return array<int, string>
     */
    private function violations(): array
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            $required = match (true) {
                str_starts_with($uri, 'clients/{client}') => RecordsController::class,
                str_starts_with($uri, 'staff') => StaffController::class,
                default => null,
            };

            if ($required === null) {
                continue;
            }

            $controller = $route->getControllerClass();

            if ($controller === null || ! is_subclass_of($controller, $required)) {
                $violations[] = $route->methods()[0].' /'.$uri;
            }
        }

        return $violations;
    }

    #[Test]
    public function 記録とスタッフ専用のルートはすべて認可する基底コントローラーを継承している(): void
    {
        $this->registerProbeRoutes();

        $this->assertSame([], $this->violations());
    }

    #[Test]
    public function 基底コントローラーを使わないルートは違反として検出される(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/clients/{client}/closure', fn () => 'ng');
            Route::get('/staff/plain', [self::class, 'violations']);
            Route::get('/clients/{client}/ok', [ProbeRecordsController::class, 'show']);
        });

        $this->assertEqualsCanonicalizing(
            ['GET /clients/{client}/closure', 'GET /staff/plain'],
            $this->violations(),
        );
    }
}
