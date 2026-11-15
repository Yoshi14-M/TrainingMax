<?php

namespace Tests\Unit;

use App\Support\Format;
use App\Support\Training\OneRepMax;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OneRepMaxTest extends TestCase
{
    #[Test]
    public function 推定1RMは重量と実績レップ数と実績RIRから計算される(): void
    {
        $this->assertEqualsWithDelta(80.0, OneRepMax::estimate(60, 8, 2), 1e-9);
        $this->assertEqualsWithDelta(60 * (1 + 8 / 30), OneRepMax::estimate(60, 8, 0), 1e-9);
        $this->assertEqualsWithDelta(100.0, OneRepMax::estimate(50, 30, 0), 1e-9);
    }

    #[Test]
    public function セットボリュームは重量とレップ数の積になる(): void
    {
        $this->assertEqualsWithDelta(480.0, OneRepMax::volume(60, 8), 1e-9);
        $this->assertEqualsWithDelta(462.5, OneRepMax::volume(92.5, 5), 1e-9);
    }

    #[Test]
    public function 推奨重量は基準の推定1RMから逆算して刻みで切り下げる(): void
    {
        // 80 ÷ (1 + (6 + 2) ÷ 30) ＝ 63.157…
        $this->assertSame(63.0, OneRepMax::recommend(80, 6, 2, 0.5));
        $this->assertSame(63.0, OneRepMax::recommend(80, 6, 2, 1));
        $this->assertSame(62.5, OneRepMax::recommend(80, 6, 2, 1.25));
        $this->assertSame(62.5, OneRepMax::recommend(80, 6, 2, 2.5));
        $this->assertSame(60.0, OneRepMax::recommend(80, 6, 2, 5));
    }

    #[Test]
    public function 要件定義書の例どおり刻み2_5kgで63_7kgは62_5kgになる(): void
    {
        $this->assertSame(62.5, OneRepMax::floorToStep(63.7, 2.5));
        $this->assertSame(65.0, OneRepMax::floorToStep(65.0, 2.5));
        $this->assertSame(61.25, OneRepMax::floorToStep(61.9, 1.25));
        $this->assertSame(0.0, OneRepMax::floorToStep(1.2, 2.5));
    }

    #[Test]
    public function 浮動小数点の誤差で刻みの値が1つ下にならない(): void
    {
        $this->assertSame(62.5, OneRepMax::floorToStep(62.49999999999999, 2.5));
        $this->assertSame(62.5, OneRepMax::floorToStep(62.5000000000001, 2.5));
        $this->assertSame(1.0, OneRepMax::floorToStep(0.3 + 0.7, 0.5));
        $this->assertSame(3.0, OneRepMax::floorToStep(0.1 * 3 * 10, 1));
    }

    #[Test]
    public function 推定1RMから逆算した推奨重量は元の重量に戻る(): void
    {
        foreach ([0.5, 1, 1.25, 2.5, 5] as $step) {
            foreach ([20.0, 37.5, 42.5, 62.5, 87.5, 102.5, 147.5] as $weight) {
                if (fmod($weight * 100, $step * 100) !== 0.0) {
                    continue;
                }

                foreach ([[6, 2], [8, 1], [12, 0], [15, 3]] as [$reps, $rir]) {
                    $base = OneRepMax::estimate($weight, $reps, $rir);

                    $this->assertSame($weight, OneRepMax::recommend($base, $reps, $rir, $step), "step={$step} weight={$weight} reps={$reps} rir={$rir}");
                }
            }
        }
    }

    #[Test]
    public function 推奨重量の表は刻みごとに目標RIR0から10まで作られる(): void
    {
        $tables = OneRepMax::recommendTables(80, 6);

        // PHP の配列では '1' や '5' のキーは整数になるが、JSON にすると文字列のキーになる
        $this->assertSame(['0.5', '1', '1.25', '2.5', '5'], array_map('strval', array_keys($tables)));
        $json = json_encode($tables);
        foreach (['0.5', '1', '1.25', '2.5', '5'] as $step) {
            $this->assertStringContainsString('"'.$step.'":[', $json);
        }

        foreach ($tables as $step => $table) {
            $this->assertSame(range(0, 10), array_keys($table));

            foreach ($table as $rir => $weight) {
                $this->assertSame(OneRepMax::recommend(80, 6, $rir, (float) $step), $weight);
                $this->assertEqualsWithDelta(0.0, fmod(round($weight * 100), round((float) $step * 100)), 1e-9);
            }

            // 目標RIRが大きいほど（余力を残すほど）推奨重量は軽くなる
            $values = array_values($table);
            $sorted = $values;
            rsort($sorted);
            $this->assertSame($sorted, $values);
        }

        $this->assertSame(62.5, $tables['2.5'][2]);
    }

    #[Test]
    public function 重量は必要な桁まで表示される(): void
    {
        $this->assertSame('60.0kg', Format::weight(60));
        $this->assertSame('60.0kg', Format::weight('60.00'));
        $this->assertSame('62.5kg', Format::weight(62.5));
        $this->assertSame('61.25kg', Format::weight('61.25'));
        $this->assertSame('0.5kg', Format::weight(0.5));
        $this->assertSame('', Format::weight(null));
    }

    #[Test]
    public function 推定1RMとボリュームは小数第1位で四捨五入して表示される(): void
    {
        $this->assertSame('80.0', Format::oneDecimal(80.04));
        $this->assertSame('80.1', Format::oneDecimal(80.05));
        $this->assertSame('106.7', Format::oneDecimal(106.666));
        $this->assertSame('480.0', Format::oneDecimal('480.00'));
    }
}
