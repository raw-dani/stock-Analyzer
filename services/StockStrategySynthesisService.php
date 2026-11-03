<?php

declare(strict_types=1);

namespace app\services;

use app\models\Stock;
use Yii;

/**
 * Service Evaluasi Multi-Strategi untuk Saham Tunggal (Unified Multi-Strategy Analyzer).
 * Menjalankan dan merangkum analisis dari seluruh strategi yang ada dalam aplikasi:
 * 1. Confluence Multi-Indikator (MA + RSI + MACD + Fib Golden Pocket)
 * 2. Moving Average Crossover & Support
 * 3. RSI Divergence & Double Bottom
 * 4. Bollinger Bands (Squeeze & Rebound)
 * 5. Stochastic Oscillator
 * 6. Candlestick Reversal & RVOL
 * 7. Fibonacci Retracement & Extension
 * 8. Bullish Breakout Patterns (Cup & Handle, Darvas Box, Flag, Triangle)
 * 9. Double Bottom Classical Reversal
 * 10. Triple Bottom Reversal
 */
final class StockStrategySynthesisService
{
    /**
     * Jalankan evaluasi semua strategi atau strategi tertentu untuk 1 saham.
     *
     * @param Stock $stock
     * @param string $requestedStrategy 'all' atau nama strategi spesifik
     * @param array $options parameter tambahan seperti timeframe
     * @return array
     */
    public function evaluateStock(Stock $stock, string $requestedStrategy = 'all', array $options = []): array
    {
        $timeframe = (int) ($options['timeframe'] ?? 24);
        $stockId = (int) $stock->id;

        $strategies = [
            'confluence' => [
                'name' => 'Confluence PRO (Multi-Indikator)',
                'icon' => 'bullseye',
                'badge' => 'PRO',
                'url' => ['/strategy-confluence/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/strategy-confluence/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'moving_average' => [
                'name' => 'Moving Average Setup',
                'icon' => 'graph-up',
                'badge' => 'Trend',
                'url' => ['/moving-average/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/moving-average/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'rsi_momentum' => [
                'name' => 'RSI Divergence & Reversal',
                'icon' => 'activity',
                'badge' => 'Momentum',
                'url' => ['/rsi-double-bottom/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/rsi-double-bottom/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'bollinger_band' => [
                'name' => 'Bollinger Bands & Squeeze',
                'icon' => 'layers',
                'badge' => 'Volatility',
                'url' => ['/bollinger-band/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/bollinger-band/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'stochastic' => [
                'name' => 'Stochastic Oscillator',
                'icon' => 'speedometer2',
                'badge' => 'Oscillator',
                'url' => ['/stochastic/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/stochastic/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'candlestick' => [
                'name' => 'Candlestick Reversal & RVOL',
                'icon' => 'bar-chart-steps',
                'badge' => 'Price Action',
                'url' => ['/candlestick/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/candlestick/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'fibonacci' => [
                'name' => 'Fibonacci Retracement & Extension',
                'icon' => 'bounding-box',
                'badge' => 'Golden Ratio',
                'url' => ['/fibonacci/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/fibonacci/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'bullish_pattern' => [
                'name' => 'Bullish Continuation (Cup, Flag, Triangle)',
                'icon' => 'rocket-takeoff',
                'badge' => 'Breakout',
                'url' => ['/bullish-pattern/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/bullish-pattern/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'double_bottom' => [
                'name' => 'Double Bottom (W-Pattern)',
                'icon' => 'graph-down',
                'badge' => 'Reversal',
                'url' => ['/double-bottom/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/double-bottom/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
            'triple_bottom' => [
                'name' => 'Triple Bottom / Inv H&S',
                'icon' => 'triangle-half',
                'badge' => 'Reversal',
                'url' => ['/triple-bottom/detail', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
                'scan_url' => ['/triple-bottom/index', 'symbol' => $stock->symbol, 'timeframe' => $timeframe],
            ],
        ];

        $results = [];
        $buyVotes = 0;
        $neutralVotes = 0;
        $sellVotes = 0;

        foreach ($strategies as $key => $meta) {
            if ($requestedStrategy !== 'all' && $requestedStrategy !== $key) {
                continue;
            }

            $evaluation = $this->runSingleStrategy($key, $stockId, $timeframe);
            $results[$key] = array_merge($meta, $evaluation);

            if ($evaluation['action_type'] === 'BUY') {
                $buyVotes++;
            } elseif ($evaluation['action_type'] === 'SELL') {
                $sellVotes++;
            } else {
                $neutralVotes++;
            }
        }

        // Hitung Keputusan Konsensus (Waktunya Beli atau Tidak)
        $totalEvaluated = count($results);
        $consensus = $this->determineConsensus($buyVotes, $sellVotes, $neutralVotes, $totalEvaluated);

        return [
            'stock' => $stock,
            'timeframe' => $timeframe,
            'requested_strategy' => $requestedStrategy,
            'strategies' => $results,
            'stats' => [
                'total' => $totalEvaluated,
                'buy_votes' => $buyVotes,
                'sell_votes' => $sellVotes,
                'neutral_votes' => $neutralVotes,
                'buy_percentage' => $totalEvaluated > 0 ? round(($buyVotes / $totalEvaluated) * 100, 1) : 0,
            ],
            'consensus' => $consensus,
        ];
    }

    /**
     * Eksekusi satu strategi spesifik untuk suatu saham.
     */
    private function runSingleStrategy(string $strategyKey, int $stockId, int $timeframe): array
    {
        try {
            switch ($strategyKey) {
                case 'confluence':
                    $svc = new MultiIndicatorStrategyService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true);
                    $isSell = ($r['action'] === 'SELL');
                    return [
                        'status' => 'success',
                        'action' => $r['action'],
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['signal_title'] ?? 'Confluence Analysis',
                        'score' => $r['confluence_score'] ?? 0,
                        'entry' => $r['entry_range'] ?? ('$' . $r['entry_price']),
                        'stop_loss' => '$' . number_format($r['stop_loss'], 2) . ' (-' . $r['stop_loss_pct'] . '%)',
                        'target' => '$' . number_format($r['target_1'], 2) . ' (+' . $r['target_1_pct'] . '%)',
                        'rr_ratio' => ($r['rr_ratio'] ?? 0) . 'x',
                        'description' => implode(' • ', array_slice($r['signals_list'] ?? [], 0, 3)),
                    ];

                case 'moving_average':
                    $svc = new MovingAverageService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true);
                    $isSell = in_array($r['action'], ['SELL', 'TAKE_PROFIT'], true);
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'NEUTRAL',
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['strategy_name'] ?? ($r['signal'] ?? 'MA Analysis'),
                        'score' => $isBuy ? 80 : ($isSell ? 20 : 50),
                        'entry' => $r['entry_range'] ?? ('$' . $r['entry_price']),
                        'stop_loss' => '$' . number_format($r['stop_loss'], 2) . ' (-' . $r['stop_loss_pct'] . '%)',
                        'target' => '$' . number_format($r['target_1'], 2) . ' (+' . $r['target_1_pct'] . '%)',
                        'rr_ratio' => ($r['rr_ratio'] ?? 0) . 'x',
                        'description' => $r['description'] ?? 'Analisis posisi MA Fast vs Slow.',
                    ];

                case 'rsi_momentum':
                    $svc = new RsiDoubleBottomService();
                    $r = $svc->detectPattern($stockId, $timeframe);
                    if ($r === null) {
                        return $this->buildFallbackResult('Pola atau sinyal RSI belum terkonfirmasi');
                    }
                    $isBuy = in_array($r['action'] ?? '', ['BUY', 'STRONG_BUY'], true) || ($r['breakout'] ?? false);
                    $isSell = in_array($r['action'] ?? '', ['SELL', 'TAKE_PROFIT'], true);
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['signal_reason'] ?? ($r['action_label'] ?? 'RSI Momentum'),
                        'score' => $r['confidence'] ?? 50,
                        'entry' => isset($r['entry_zone_min']) ? ('$' . number_format($r['entry_zone_min'], 2) . ' - $' . number_format($r['entry_zone_max'], 2)) : ('$' . number_format($r['current_price'], 2)),
                        'stop_loss' => '$' . number_format($r['sl_price'] ?? ($r['current_price'] * 0.96), 2),
                        'target' => '$' . number_format($r['target_price'] ?? ($r['current_price'] * 1.05), 2) . ' (+' . ($r['potential_upside'] ?? 5) . '%)',
                        'rr_ratio' => ($r['risk_reward'] ?? 1.5) . 'x',
                        'description' => 'RSI Level: ' . round($r['current_rsi'] ?? 50, 1) . '. ' . ($r['signal_reason'] ?? ''),
                    ];

                case 'bollinger_band':
                    $svc = new BollingerBandService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true);
                    $isSell = in_array($r['action'], ['SELL', 'TAKE_PROFIT'], true);
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['strategy_name'] ?? ($r['signal'] ?? 'Bollinger Band'),
                        'score' => $isBuy ? 75 : ($isSell ? 25 : 50),
                        'entry' => $r['entry_range'] ?? ('$' . $r['entry_price']),
                        'stop_loss' => '$' . number_format($r['stop_loss'], 2) . ' (-' . $r['stop_loss_pct'] . '%)',
                        'target' => '$' . number_format($r['target_1'], 2) . ' (+' . $r['target_1_pct'] . '%)',
                        'rr_ratio' => ($r['rr_ratio'] ?? 0) . 'x',
                        'description' => $r['description'] ?? 'Posisi harga terhadap Upper/Lower Band & Bandwidth.',
                    ];

                case 'stochastic':
                    $svc = new StochasticService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true);
                    $isSell = in_array($r['action'], ['SELL', 'TAKE_PROFIT'], true);
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'NEUTRAL',
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['strategy_name'] ?? ($r['signal'] ?? 'Stochastic'),
                        'score' => $r['confidence_score'] ?? ($isBuy ? 70 : 40),
                        'entry' => '$' . number_format($r['entry_price'], 2),
                        'stop_loss' => '$' . number_format($r['stop_loss'], 2) . ' (-' . $r['stop_loss_pct'] . '%)',
                        'target' => '$' . number_format($r['target_profit_1'], 2) . ' (+' . $r['target_profit_1_pct'] . '%)',
                        'rr_ratio' => ($r['risk_reward_ratio'] ?? 0) . 'x',
                        'description' => $r['description'] ?? ('%K: ' . $r['curr_k'] . ', %D: ' . $r['curr_d']),
                    ];

                case 'candlestick':
                    $svc = new CandlestickService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true);
                    $patternName = $r['primary_pattern']['name'] ?? 'Netral / Tanpa Pola Khusus';
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : 'WAIT',
                        'signal_title' => $patternName,
                        'score' => $r['confidence'] ?? ($isBuy ? 70 : 45),
                        'entry' => '$' . number_format($r['entry_price'] ?? $r['current_price'], 2),
                        'stop_loss' => isset($r['stop_loss']) ? ('$' . number_format($r['stop_loss'], 2)) : '-',
                        'target' => isset($r['target_1']) ? ('$' . number_format($r['target_1'], 2)) : '-',
                        'rr_ratio' => ($r['rr_ratio'] ?? 0) . 'x',
                        'description' => 'Pola: ' . $patternName . ' (RVOL: ' . ($r['rvol'] ?? 1.0) . 'x)',
                    ];

                case 'fibonacci':
                    $svc = new FibonacciService();
                    $r = $svc->analyzeStock($stockId, ['timeframe' => $timeframe]);
                    if ($r === null) {
                        return $this->buildFallbackResult('Data historis tidak mencukupi');
                    }
                    $isBuy = in_array($r['action'], ['BUY', 'STRONG_BUY'], true) || ($r['signal'] === 'GOLDEN_POCKET');
                    $isSell = ($r['action'] === 'SELL');
                    return [
                        'status' => 'success',
                        'action' => $r['action'] ?? 'HOLD',
                        'action_type' => $isBuy ? 'BUY' : ($isSell ? 'SELL' : 'WAIT'),
                        'signal_title' => $r['strategy_name'] ?? ($r['signal'] ?? 'Fibonacci Retracement'),
                        'score' => $isBuy ? 80 : 50,
                        'entry' => $r['entry_range'] ?? ('$' . $r['entry_price']),
                        'stop_loss' => '$' . number_format($r['stop_loss'], 2) . ' (-' . $r['stop_loss_pct'] . '%)',
                        'target' => '$' . number_format($r['target_1'], 2) . ' (+' . $r['target_1_pct'] . '%)',
                        'rr_ratio' => ($r['rr_ratio'] ?? 0) . 'x',
                        'description' => 'Retracement: ' . ($r['retrace_pct'] ?? 0) . '%. ' . ($r['description'] ?? ''),
                    ];

                case 'bullish_pattern':
                    $svc = new BullishPatternService();
                    $r = $svc->detectPattern($stockId, $timeframe);
                    if ($r === null) {
                        return $this->buildFallbackResult('Tidak terdeteksi pola kelanjutan bullish aktif');
                    }
                    $isBuy = in_array($r['status'] ?? '', ['buy_zone', 'retest', 'consolidation_bounce'], true) || ($r['breakout'] ?? false);
                    return [
                        'status' => 'success',
                        'action' => $isBuy ? 'BUY' : 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : 'WAIT',
                        'signal_title' => $r['pattern_name'] ?? 'Bullish Continuation',
                        'score' => $r['confidence'] ?? 65,
                        'entry' => isset($r['entry_zone_min']) ? ('$' . number_format($r['entry_zone_min'], 2) . ' - $' . number_format($r['entry_zone_max'], 2)) : ('$' . number_format($r['current_price'], 2)),
                        'stop_loss' => '$' . number_format($r['stop_loss'] ?? 0, 2),
                        'target' => '$' . number_format($r['target_price'] ?? 0, 2),
                        'rr_ratio' => ($r['risk_reward'] ?? 1.5) . 'x',
                        'description' => 'Pola terdeteksi: ' . ($r['pattern_name'] ?? '') . ' (Status: ' . ($r['status'] ?? '') . ')',
                    ];

                case 'double_bottom':
                    $svc = new DoubleBottomService();
                    $r = $svc->detectPattern($stockId, $timeframe);
                    if ($r === null) {
                        return $this->buildFallbackResult('Tidak terdeteksi formasi Double Bottom');
                    }
                    $isBuy = ($r['breakout'] ?? false) || in_array($r['trade_action'] ?? '', ['BUY', 'ENTRY'], true);
                    return [
                        'status' => 'success',
                        'action' => $isBuy ? 'BUY' : 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : 'WAIT',
                        'signal_title' => ($r['breakout'] ?? false) ? 'Double Bottom Breakout' : 'Double Bottom Formation',
                        'score' => $r['confidence'] ?? 60,
                        'entry' => isset($r['entry_zone_min']) ? ('$' . number_format($r['entry_zone_min'], 2) . ' - $' . number_format($r['entry_zone_max'], 2)) : ('$' . number_format($r['neckline'] ?? 0, 2)),
                        'stop_loss' => '$' . number_format($r['stop_loss'] ?? 0, 2),
                        'target' => '$' . number_format($r['target_price'] ?? 0, 2),
                        'rr_ratio' => ($r['best_rr'] ?? ($r['risk_reward'] ?? 1.5)) . 'x',
                        'description' => 'Neckline: $' . number_format($r['neckline'] ?? 0, 2) . ' (Confidence: ' . ($r['confidence'] ?? 0) . '%)',
                    ];

                case 'triple_bottom':
                    $svc = new TripleBottomService();
                    $r = $svc->detectPattern($stockId, $timeframe);
                    if ($r === null) {
                        return $this->buildFallbackResult('Tidak terdeteksi formasi Triple Bottom');
                    }
                    $isBuy = ($r['breakout'] ?? false) || in_array($r['trade_action'] ?? '', ['BUY', 'ENTRY'], true);
                    return [
                        'status' => 'success',
                        'action' => $isBuy ? 'BUY' : 'WATCH',
                        'action_type' => $isBuy ? 'BUY' : 'WAIT',
                        'signal_title' => $r['pattern_name'] ?? 'Triple Bottom',
                        'score' => $r['confidence'] ?? 60,
                        'entry' => isset($r['entry_zone_min']) ? ('$' . number_format($r['entry_zone_min'], 2) . ' - $' . number_format($r['entry_zone_max'], 2)) : ('$' . number_format($r['neckline'] ?? 0, 2)),
                        'stop_loss' => '$' . number_format($r['stop_loss'] ?? 0, 2),
                        'target' => '$' . number_format($r['target_price'] ?? 0, 2),
                        'rr_ratio' => ($r['best_rr'] ?? 1.5) . 'x',
                        'description' => '3 Lembah Support kokoh. Neckline: $' . number_format($r['neckline'] ?? 0, 2),
                    ];

                default:
                    return $this->buildFallbackResult('Strategi tidak dikenali');
            }
        } catch (\Throwable $e) {
            return $this->buildFallbackResult('Gagal memproses strategi: ' . $e->getMessage());
        }
    }

    private function buildFallbackResult(string $reason): array
    {
        return [
            'status' => 'no_signal',
            'action' => 'WAIT',
            'action_type' => 'WAIT',
            'signal_title' => 'Netral / Wait & See',
            'score' => 30,
            'entry' => '-',
            'stop_loss' => '-',
            'target' => '-',
            'rr_ratio' => '-',
            'description' => $reason,
        ];
    }

    /**
     * Hitung kesimpulan umum (Waktunya Beli atau Tidak).
     */
    private function determineConsensus(int $buyVotes, int $sellVotes, int $neutralVotes, int $total): array
    {
        if ($total === 0) {
            return [
                'verdict' => 'TIDAK / WAIT',
                'badge' => 'secondary',
                'headline' => 'Data Belum Cukup',
                'summary' => 'Belum ada strategi yang memberikan konfirmasi sinyal aktif untuk saham ini.',
                'is_buy_time' => false,
            ];
        }

        $buyRatio = $buyVotes / $total;
        $sellRatio = $sellVotes / $total;

        if ($buyRatio >= 0.5 || ($buyVotes >= 4 && $buyVotes > $sellVotes * 2)) {
            return [
                'verdict' => 'WAKTUNYA BELI (HIGH PROBABILITY)',
                'badge' => 'success',
                'headline' => "Sangat Disarankan Beli ({$buyVotes} dari {$total} Strategi Mendukung Beli)",
                'summary' => 'Mayoritas indikator teknikal, pola candlestick, dan strategi breakout memberikan konfirmasi sinyal akumulasi dan beli aktif pada harga saat ini.',
                'is_buy_time' => true,
            ];
        }

        if ($buyVotes >= 2 && $buyVotes > $sellVotes) {
            return [
                'verdict' => 'POTENSIAL BELI BERSYARAT (ACCUMULATE ON DIP)',
                'badge' => 'primary',
                'headline' => "Peluang Beli ({$buyVotes} Strategi Memberikan Sinyal Beli)",
                'summary' => 'Terdapat konfirmasi beli dari beberapa strategi unggulan. Disarankan masuk bertahap (DCA/Buy on Support) dengan disiplin pasang stop loss.',
                'is_buy_time' => true,
            ];
        }

        if ($sellRatio >= 0.3 || ($sellVotes >= 2 && $sellVotes >= $buyVotes)) {
            return [
                'verdict' => 'JANGAN BELI / WAKTUNYA TAKE PROFIT',
                'badge' => 'danger',
                'headline' => "Bukan Waktu Beli ({$sellVotes} Strategi Memberikan Peringatan Jual/Overbought)",
                'summary' => 'Harga berada pada area jenuh beli (overbought), menabrak resistance kuat, atau mengalami tekanan jual. Hindari masuk di harga saat ini.',
                'is_buy_time' => false,
            ];
        }

        return [
            'verdict' => 'WAIT & OBSERVE (TUNGGU KONFIRMASI)',
            'badge' => 'warning text-dark',
            'headline' => 'Netral / Sedang Konsolidasi',
            'summary' => 'Saham sedang berada di area konsolidasi dan belum ada sinyal pembalikan atau breakout yang cukup kuat. Disarankan menunggu hingga setup terpenuhi.',
            'is_buy_time' => false,
        ];
    }
}

