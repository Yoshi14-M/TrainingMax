<?php

namespace Tests\Feature\Body;

use App\Models\BodyComposition;
use App\Models\Gym;
use App\Models\User;
use App\Queries\LatestBodyWeightQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LatestBodyWeightQueryTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client(Gym::factory()->create())->create();
    }

    private function record(string $date, array $values, ?User $user = null): void
    {
        $user ??= $this->client;
        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, ...$values]);
        $record->forceFill(['user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
    }

    private function latest(string $sessionDate, ?User $user = null): ?array
    {
        return app(LatestBodyWeightQuery::class)->handle($user ?? $this->client, Carbon::parse($sessionDate));
    }

    #[Test]
    public function セッション日以前で最新の体重を返す(): void
    {
        $this->record('2026-10-01', ['weight_kg' => 72.0]);
        $this->record('2026-10-20', ['weight_kg' => 70.5]);
        $this->record('2026-11-10', ['weight_kg' => 69.0]);

        $result = $this->latest('2026-11-01');

        $this->assertSame(70.5, $result['weight']);
        $this->assertSame('2026-10-20', $result['measured_on']->toDateString());
    }

    #[Test]
    public function セッションと同じ日の体重も対象になる(): void
    {
        $this->record('2026-10-20', ['weight_kg' => 70.5]);
        $this->record('2026-11-01', ['weight_kg' => 70.0]);

        $this->assertSame(70.0, $this->latest('2026-11-01')['weight']);
    }

    #[Test]
    public function セッション日より後の記録だけなら体重なしになる(): void
    {
        $this->record('2026-11-02', ['weight_kg' => 70.0]);

        $this->assertNull($this->latest('2026-11-01'));
    }

    #[Test]
    public function 体重が空の記録は飛ばしてその前の体重を返す(): void
    {
        $this->record('2026-10-20', ['weight_kg' => 70.5]);
        $this->record('2026-10-25', ['body_fat_percent' => 18.0, 'waist_cm' => 80]);

        $result = $this->latest('2026-11-01');

        $this->assertSame(70.5, $result['weight']);
        $this->assertSame('2026-10-20', $result['measured_on']->toDateString());
    }

    #[Test]
    public function 他の利用者の体重は使わない(): void
    {
        $other = User::factory()->client(Gym::factory()->create())->create();
        $this->record('2026-10-20', ['weight_kg' => 99.0], $other);

        $this->assertNull($this->latest('2026-11-01'));
    }

    #[Test]
    public function 記録がなければ体重なしになる(): void
    {
        $this->assertNull($this->latest('2026-11-01'));
    }
}
