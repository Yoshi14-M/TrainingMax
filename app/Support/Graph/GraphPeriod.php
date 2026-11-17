<?php

namespace App\Support\Graph;

use Carbon\CarbonImmutable;

/**
 * グラフの表示期間（全期間／直近30日／直近90日／期間指定。機能設計書F-12）。
 */
final class GraphPeriod
{
    public const ALL = 'all';

    public const LAST_30 = '30';

    public const LAST_90 = '90';

    public const CUSTOM = 'custom';

    public const DEFAULT = self::LAST_90;

    /**
     * @var array<string, string>
     */
    public const OPTIONS = [
        self::ALL => '全期間',
        self::LAST_30 => '直近30日',
        self::LAST_90 => '直近90日',
        self::CUSTOM => '期間指定',
    ];

    private function __construct(
        public readonly string $key,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
    ) {}

    /**
     * 期間を作る。期間指定で日付が足りない場合は、初期値（直近90日）にする。
     */
    public static function make(string $key, ?string $from, ?string $to): self
    {
        $today = CarbonImmutable::today();

        return match (true) {
            $key === self::ALL => new self(self::ALL, null, null),
            $key === self::LAST_30 => new self(self::LAST_30, $today->subDays(30), $today),
            $key === self::CUSTOM && $from !== null && $to !== null => new self(self::CUSTOM, CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
            default => new self(self::LAST_90, $today->subDays(90), $today),
        };
    }
}
