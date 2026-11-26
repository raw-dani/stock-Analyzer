<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\DoubleBottomService;
use Codeception\Test\Unit;

/**
 * Unit test murni (tanpa DB) untuk inti deteksi Double Bottom via refleksi
 * private findDoubleBottom(): pola W sintetis harus terdeteksi,
 * tren datar/turun monoton harus null.
 *
 * @group double-bottom
 */
final class DoubleBottomServiceTest extends Unit
{
    private DoubleBottomService $service;

    /** @var \ReflectionMethod */
    private $find;

    /** @var \ReflectionMethod */
    private $confidence;

    protected function _before(): void
    {
        $this->service = new DoubleBottomService();
        $this->find = new \ReflectionMethod(DoubleBottomService::class, 'findDoubleBottom');
        $this->find->setAccessible(true);
        $this->confidence = new \ReflectionMethod(DoubleBottomService::class, 'calculateConfidence');
        $this->confidence->setAccessible(true);
    }

    /**
     * Bangun candle sintetis. $highs opsional: bila kosong, high = low * 1.005
     * (bar sempit) sehingga kasus sideways tetap datar & dangkal.
     *
     * @param float[] $lows harga low per candle
     * @param float[] $highs harga high per candle (indeks sama dengan $lows)
     */
    private function candles(array $lows, array $highs = [], float $base = 100.0, int $vol = 1000): array
    {
        $out = [];
        foreach ($lows as $i => $low) {
            $high = $highs[$i] ?? ((float) $low * 1.005);
            $out[] = [
                'open' => $base,
                'high' => max((float) $high, (float) $low),
                'low' => (float) $low,
                'close' => $base,
                'volume' => $vol,
                'date' => sprintf('2026-09-%02d', $i + 1),
            ];
        }
        return $out;
    }

    public function testDetectsClassicWShape(): void
    {
        // W: low1=90 (idx1), neckline=102 (idx3), low2=90.5 (idx5), breakout close=103
        $candles = $this->candles(
            [95, 90, 96, 96, 96, 90.5, 97],
            [97, 96, 101, 102, 101, 96, 98]
        );
        $candles[count($candles) - 1]['close'] = 103.0; // breakout di atas neckline
        $res = $this->find->invoke($this->service, $candles, 0.02, 3, 10, 0.015);
        $this->assertIsArray($res, 'Pola W klasik harus terdeteksi');
        $this->assertEquals(90.0, $res['low1_price']);
        $this->assertEquals(90.5, $res['low2_price']);
        $this->assertEquals(102.0, $res['neckline']);
        $this->assertTrue($res['breakout']);
        $this->assertGreaterThanOrEqual(70, $res['confidence']);
        // target = neckline + (neckline - avgLow) = 102 + (102-90.25) = 113.75
        $this->assertEqualsWithDelta(113.75, $res['target_price'], 0.001);
        // stop loss harus selaras dengan stop_loss_swing di bawah min(low1, low2)
        $this->assertSame($res['stop_loss_swing'], $res['stop_loss']);
        $this->assertLessThan(min($res['low1_price'], $res['low2_price']), $res['stop_loss']);
    }

    public function testFlatSidewaysIsRejected(): void
    {
        // Sideways datar: neckline hanya ~0.5% di atas low -> di bawah min depth 1.5%
        $candles = $this->candles([100, 99.5, 100, 99.5, 100, 99.6, 100]);
        $res = $this->find->invoke($this->service, $candles, 0.02, 2, 10, 0.015);
        $this->assertNull($res, 'Sideways datar bukan double bottom (neckline terlalu dangkal)');
    }

    public function testMonotonicDowntrendHasNoPattern(): void
    {
        // Downtrend monoton tanpa local minima pair -> null
        $candles = $this->candles([110, 108, 106, 104, 102, 100, 98]);
        $res = $this->find->invoke($this->service, $candles, 0.02, 2, 10, 0.015);
        $this->assertNull($res, 'Downtrend monoton tidak boleh menghasilkan pola');
    }

    public function testLowMismatchBeyondToleranceIsRejected(): void
    {
        // Low1=90 vs Low2=95 -> selisih ~5.4% > toleransi 2%
        $candles = $this->candles([95, 90, 100, 96, 95, 97, 101]);
        $res = $this->find->invoke($this->service, $candles, 0.02, 2, 10, 0.015);
        $this->assertNull($res, 'Selisih low di atas toleransi harus ditolak');
    }

    public function testWaitingPatternHasNullRiskReward(): void
    {
        // W valid tapi close terakhir di bawah neckline -> waiting, risk_reward null
        $candles = $this->candles([95, 90, 96, 100, 96, 90.5, 97]);
        $candles[count($candles) - 1]['close'] = 99.0;
        $res = $this->find->invoke($this->service, $candles, 0.02, 3, 10, 0.015);
        $this->assertIsArray($res);
        $this->assertFalse($res['breakout']);
        $this->assertNull($res['risk_reward']);
    }

    public function testVolumeBonusRewardsQuietSecondLowAndVolumeBreakout(): void
    {
        // Skenario dasar dipilih agar skor masih di bawah cap 100 (ada ruang bonus):
        // lowDiff 1.9% (+5), breakout (+20), depth (94 vs 90.25 = ~4.2%) (+5) => 80.
        $plain = $this->confidence->invoke($this->service, 90.0, 90.5, 94.0, 95.0, true, 0.019);
        $this->assertSame(80, $plain, 'Basis skor tanpa volume harus 80 (bukan mentok cap)');

        $candles = $this->candles([95, 90, 96, 100, 96, 90.5, 97, 98, 99, 103]);
        foreach ($candles as $i => &$c) {
            $c['volume'] = 1000;
        }
        unset($c);
        $candles[5]['volume'] = 500;    // low2 sepi (< 90% rata-rata)
        $candles[9]['volume'] = 3000;   // breakout bervolume (> 150% rata-rata)
        $withVol = $this->confidence->invoke($this->service, 90.0, 90.5, 94.0, 95.0, true, 0.019, $candles, 1, 5);
        $this->assertEquals($plain + 10, $withVol, 'Bonus volume sehat harus +10');
        $this->assertLessThanOrEqual(100, $withVol);
    }

    public function testEnhancedTradingMetrics(): void
    {
        // W: low1=90, neckline=102, low2=90.5, breakout close=103
        $candles = $this->candles(
            [95, 90, 96, 96, 96, 90.5, 97],
            [97, 96, 101, 102, 101, 96, 98]
        );
        $candles[count($candles) - 1]['close'] = 103.0;
        $candles[1]['volume'] = 2000; // low1
        $candles[5]['volume'] = 800;  // low2 dry-up (< 90% of low1)
        $candles[count($candles) - 1]['volume'] = 4000; // breakout vol

        $res = $this->find->invoke($this->service, $candles, 0.02, 3, 10, 0.015);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('tp1_price', $res);
        $this->assertArrayHasKey('tp2_price', $res);
        $this->assertArrayHasKey('tp3_price', $res);
        $this->assertArrayHasKey('stop_loss_tight', $res);
        $this->assertArrayHasKey('trade_status', $res);
        $this->assertArrayHasKey('trade_action', $res);
        $this->assertArrayHasKey('pattern_type', $res);
        $this->assertSame('Higher Low', $res['pattern_type']);
        $this->assertTrue($res['volume_dry_up']);
        $this->assertEquals(99.96, $res['stop_loss_tight']); // 102 * 0.98
        $this->assertGreaterThan(0, $res['best_rr']);
    }

    public function testBrokenSupportAfterLow2Rejected(): void
    {
        // W formasi tapi candle terakhir crash di bawah low1/low2 -> harus ditolak
        $candles = $this->candles(
            [95, 90, 96, 102, 96, 90.5, 95, 75],
            [97, 96, 101, 102, 101, 96, 98, 80]
        );
        $candles[count($candles) - 1]['close'] = 75.0; // breakdown support
        $res = $this->find->invoke($this->service, $candles, 0.02, 3, 10, 0.015);
        $this->assertNull($res, 'Pola dengan breakdown support harus ditolak');
    }

    public function testMidPatternLowerLowIsRejected(): void
    {
        // Di antara B1 (90) dan B2 (90.5) ada candle yang jebol jauh ke 83 -> bukan Double Bottom valid
        $candles = $this->candles(
            [95, 90, 96, 98, 83, 96, 90.5, 98],
            [97, 95, 102, 104, 89, 100, 95, 105]
        );
        $candles[count($candles) - 1]['close'] = 105.0;
        $res = $this->find->invoke($this->service, $candles, 0.025, 3, 10, 0.015);
        $this->assertNull($res, 'Pola dengan lembah lebih dalam di tengah L1-L2 harus ditolak');
    }

    public function testShallowPriorDowntrendIsRejectedOnFullHistory(): void
    {
        // 20 bar datar sebelum B1 (penurunan < 2% sebelum B1) -> tidak memenuhi syarat pola reversal
        $candles = [];
        for ($i = 0; $i < 22; $i++) {
            $candles[] = [
                'open' => 100.0,
                'high' => 101.0,
                'low' => 99.5,
                'close' => 100.0,
                'volume' => 1000,
                'date' => sprintf('2026-09-%02d', $i + 1),
            ];
        }
        // Bentuk B1=98.5 (drop dari 101 hanya ~2.5%), N=108, B2=98.8, breakout=109
        $candles[12]['low'] = 98.5;
        $candles[15]['high'] = 108.0;
        $candles[18]['low'] = 98.8;
        $candles[21]['close'] = 109.0;
        $candles[21]['high'] = 109.5;
        $res = $this->find->invoke($this->service, $candles, 0.025, 3, 12, 0.02);
        $this->assertNull($res, 'Pola tanpa prior downtrend memadai harus ditolak');
    }

    public function testBottomDiffStrictlyEnforcedEvenOnHighAtr(): void
    {
        // High ATR (~10%) tetapi selisih L1=90 vs L2=94 (4.35%) > toleransi 2% -> harus ditolak tanpa bypass ATR
        $candles = [];
        $highs = [110, 108, 106, 104, 102, 100, 100, 99, 103, 105, 108, 104, 101, 100, 102, 106, 110];
        $lows  = [102, 100, 98,  96,  94,  92,  90,  93, 96,  98,  100, 97,  95,  94,  96,  99,  103];
        foreach ($lows as $i => $l) {
            $candles[] = [
                'open' => ($l + $highs[$i]) / 2,
                'high' => (float) $highs[$i],
                'low' => (float) $l,
                'close' => ($l + $highs[$i]) / 2,
                'volume' => 1000,
                'date' => sprintf('2026-09-%02d', $i + 1),
            ];
        }
        $candles[16]['close'] = 109.5;
        $res = $this->find->invoke($this->service, $candles, 0.02, 4, 12, 0.02);
        $this->assertNull($res, 'Selisih L1-L2 > toleransi tidak boleh lolos lewat bypass ATR');
    }

    public function testCompletedTp2PatternIsRejected(): void
    {
        // W: downtrend dari 115 -> B1=90 (idx 7) -> N=100 (idx 10) -> B2=90.5 (idx 13) -> rally melewati TP2=110 (idx 16 high=115) lalu turun kembali ke 101
        $candles = [];
        $data = [
            [115, 110], [114, 108], [112, 105], [108, 100], [104, 96], [100, 93], [96, 91],
            [95, 90],   // idx 7: B1
            [97, 92], [99, 95],
            [100, 96],  // idx 10: N
            [98, 93], [95, 91],
            [96, 90.5], // idx 13: B2
            [99, 94], [104, 99],
            [115, 105], // idx 16: sudah tembus TP2 (110)
            [108, 103], [104, 100.5],
        ];
        foreach ($data as $i => [$h, $l]) {
            $candles[] = [
                'open' => ($h + $l) / 2,
                'high' => (float) $h,
                'low' => (float) $l,
                'close' => ($i === 18) ? 101.0 : (($h + $l) / 2),
                'volume' => 1000,
                'date' => sprintf('2026-10-%02d', $i + 1),
            ];
        }
        $res = $this->find->invoke($this->service, $candles, 0.025, 3, 12, 0.02);
        $this->assertNull($res, 'Pola yang sudah mencapai target penuh TP2 sebelumnya tidak boleh muncul sebagai setup aktif');
    }

    public function testSessionAwareIntradayAggregationDoesNotMixDays(): void
    {
        $agg = new \ReflectionMethod(DoubleBottomService::class, 'aggregate');
        $agg->setAccessible(true);

        $bars = [
            ['open' => 100, 'high' => 102, 'low' => 99,  'close' => 101, 'volume' => 100, 'date' => '2026-11-24 14:30:00'],
            ['open' => 101, 'high' => 103, 'low' => 100, 'close' => 102, 'volume' => 200, 'date' => '2026-11-24 15:30:00'],
            ['open' => 105, 'high' => 106, 'low' => 104, 'close' => 105, 'volume' => 300, 'date' => '2026-11-25 09:30:00'],
            ['open' => 105, 'high' => 107, 'low' => 104, 'close' => 106, 'volume' => 400, 'date' => '2026-11-25 10:30:00'],
        ];
        $res = $agg->invoke($this->service, $bars, 4);
        $this->assertCount(2, $res, 'Agregasi 4H wajib dipisah per hari perdagangan agar tidak menggabungkan dua hari berbeda');
        $this->assertSame('2026-11-24 15:30:00', $res[0]['date']);
        $this->assertSame('2026-11-25 10:30:00', $res[1]['date']);
    }

    public function testBreakoutVolumeConfirmedTracksBreakoutBarRvolNotDryUp(): void
    {
        // B2 dry-up (vol B2 < 90% B1), tapi breakout RVOL rendah (0.5x) -> breakout_volume_confirmed harus FALSE
        $candles = $this->candles(
            [95, 90, 96, 96, 96, 90.5, 97],
            [97, 96, 101, 102, 101, 96, 98]
        );
        foreach ($candles as &$c) {
            $c['volume'] = 2000;
        }
        unset($c);
        $candles[1]['volume'] = 3000; // B1
        $candles[5]['volume'] = 1000; // B2 dry-up
        $candles[6]['close'] = 103.0; // breakout
        $candles[6]['volume'] = 1000; // RVOL 0.5x -> lemah

        $res = $this->find->invoke($this->service, $candles, 0.02, 3, 10, 0.015);
        $this->assertIsArray($res);
        $this->assertTrue($res['volume_dry_up']);
        $this->assertFalse($res['breakout_volume_confirmed']);
        $this->assertFalse($res['volume_confirmed']);
    }
}

