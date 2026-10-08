<?php

declare(strict_types=1);

namespace app\models\form;

use Yii;
use yii\base\Model;

/**
 * Filter stock scanner (task 6.1).
 */
final class ScanFilterForm extends Model
{
    /** Atribut bertipe angka bulat. */
    private const INT_ATTRS = ['minScore', 'limit', 'minMarketCap', 'maxMarketCap'];

    /** Atribut bertipe angka desimal. */
    private const FLOAT_ATTRS = ['minBuyRatio', 'minVolumeGrowth', 'minVolume', 'minPrice', 'maxPrice', 'minRvol'];

    /** Atribut bertipe teks. */
    private const STRING_ATTRS = ['symbol', 'exchange', 'sector', 'signal', 'mode'];

    /** Atribut rasio (0..1) — tanda "%" diartikan sebagai rasio: "50%" → 0.5. */
    private const RATIO_ATTRS = ['minBuyRatio', 'minVolumeGrowth'];

    /** Mode tampilan yang valid. */
    private const MODES = ['weekly', 'daily'];

    public ?string $symbol = null;
    public ?string $exchange = null;
    public ?string $signal = null;
    public ?float $minBuyRatio = null;       // 0..1
    public ?float $minVolumeGrowth = null;   // 0..1
    public ?float $minVolume = null;         // total weekly volume (buy + sell)
    public ?float $minRvol = null;           // RVOL minimum (e.g. 1.5, 2.0)
    public ?int $minScore = null;
    public ?string $sector = null;
    public ?int $minMarketCap = null;
    public ?int $maxMarketCap = null;
    public ?float $minPrice = null;
    public ?float $maxPrice = null;
    public ?int $limit = 50;
    public ?string $mode = 'weekly'; // 'weekly' | 'daily'

    /**
     * Input mentah yang tidak bisa diinterpretasikan, mis. "abc" untuk minScore.
     * Dipakai untuk memberi pesan ke user alih-alih menampilkan hasil kosong
     * tanpa penjelasan.
     *
     * @var array<string, string> attribute => nilai mentah
     */
    public array $invalidInput = [];

    public function formName(): string
    {
        return ''; // query string flat: ?exchange=NASDAQ&minScore=70
    }

    /**
     * Override load(): query string selalu berupa string (atau array bila user
     * menulis `?minScore[]=1`), sedangkan properti di sini bertipe ?int/?float.
     * Tanpa koersi, PHP melempar TypeError → HTTP 500. Semua nilai dinormalkan
     * dulu (mis. "$1,000" → 1000, "50%" → 50) sebelum ditugaskan.
     */
    public function load($data, $formName = null): bool
    {
        $scope = $formName === null ? $this->formName() : $formName;
        $raw = ($scope === '' ? $data : ($data[$scope] ?? null));

        if (!is_array($raw) || $raw === []) {
            return false;
        }

        $this->invalidInput = [];
        foreach ($raw as $name => $value) {
            if (!is_string($name) || !in_array($name, $this->filterAttributes(), true)) {
                continue; // 'r' (route), 'sort', 'page', token, dsb. bukan filter
            }
            $this->{$name} = $this->coerce($name, $value);
        }

        return true;
    }

    /**
     * Daftar putih atribut filter — hanya kolom di form yang boleh diisi dari
     * query string (mencegah atribut internal/asing ikut tertimpa).
     *
     * @return string[]
     */
    private function filterAttributes(): array
    {
        return array_merge(self::STRING_ATTRS, self::INT_ATTRS, self::FLOAT_ATTRS);
    }

    /**
     * Validasi + laporkan input yang tidak terbaca. Fail-closed: bila ada
     * filter invalid, ScannerService mengembalikan hasil kosong dan user
     * melihat pesan error di form (bukan hasil menyesatkan).
     */
    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        $valid = parent::validate($attributeNames, $clearErrors);

        foreach ($this->invalidInput as $attribute => $raw) {
            $this->addError($attribute, "Nilai \"{$raw}\" tidak valid untuk {$this->getAttributeLabel($attribute)} — perbaiki filter untuk melihat hasil.");
        }

        return $valid && $this->invalidInput === [];
    }

    /**
     * Label filter (dipakai pada pesan validasi & header kolom).
     */
    public function attributeLabels(): array
    {
        return [
            'symbol' => 'Symbol',
            'exchange' => 'Exchange',
            'sector' => 'Sector',
            'signal' => 'Signal',
            'mode' => 'Mode',
            'minScore' => 'Score minimum',
            'minBuyRatio' => 'Buy ratio minimum',
            'minVolumeGrowth' => 'Volume growth minimum',
            'minVolume' => 'Volume minimum',
            'minRvol' => 'RVOL minimum',
            'minMarketCap' => 'Market cap minimum',
            'maxMarketCap' => 'Market cap maksimum',
            'minPrice' => 'Harga minimum',
            'maxPrice' => 'Harga maksimum',
            'limit' => 'Baris per halaman',
        ];
    }

    /**
     * Normalisasi nilai mentah → tipe properti. Tidak pernah melempar exception.
     */
    private function coerce(string $attribute, mixed $raw): int|float|string|null
    {
        // Dropdown: normalisasi ke huruf kecil agar "DAILY"/"Daily" diterima,
        // sedangkan nilai asing tetap ditolak rule 'in' dengan pesan yang jelas.
        if ($attribute === 'mode') {
            if (!is_scalar($raw)) {
                $this->markInvalid($attribute, $raw);
                return null;
            }
            $s = strtolower(trim((string) $raw));
            return $s === '' ? null : $s;
        }

        if (in_array($attribute, self::STRING_ATTRS, true)) {
            if (!is_scalar($raw)) {
                $this->markInvalid($attribute, $raw);
                return null;
            }
            $s = trim((string) $raw);
            return $s === '' ? null : $s;
        }

        $number = $this->parseNumber($raw, $attribute);

        if ($number === null) {
            // Array (`?minScore[]=1`), objek, atau teks tak terbaca → laporkan.
            // Jangan diam-diam diabaikan: user akan bingung melihat hasil kosong.
            if (!is_scalar($raw) || trim((string) $raw) !== '') {
                $this->markInvalid($attribute, $raw);
            }
            return null;
        }

        if (in_array($attribute, self::INT_ATTRS, true)) {
            // Jaga batas int 64-bit agar penugasan tidak overflow.
            if (abs($number) > 9.2e18) {
                $this->markInvalid($attribute, $raw);
                return null;
            }
            return (int) $number; // '70.5' → 70 (dipotong, bukan dibulatkan)
        }

        return $number;
    }

    /**
     * Terima format angka yang umum ditulis user:
     * "1,000,000" (pemisah ribuan), "70,5" (desimal koma), "$50", "50%", "1e12".
     * "%" hanya diartikan rasio pada field rasio (minBuyRatio, minVolumeGrowth):
     * "50%" → 0.5. Di field lain "%" hanya dibuang.
     */
    private function parseNumber(mixed $raw, ?string $attribute = null): ?float
    {
        if (!is_scalar($raw)) {
            return null; // array / objek / null bukan angka
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        $isPercent = str_contains($s, '%');

        // Buang mata uang/percent & spasi (termasuk non-breaking space).
        $s = (string) preg_replace('/usd|idr|rp\.?|[$€£¥%]/iu', '', $s);
        $s = (string) preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $s);

        if ($s === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s) === 1) {
            $s = str_replace(',', '', $s);          // 1,000,000 → 1000000
        } elseif (preg_match('/^\d+,\d{1,4}$/', $s) === 1) {
            $s = str_replace(',', '.', $s);          // 70,5 → 70.5
        } else {
            $s = str_replace(',', '', $s);
        }

        if (!is_numeric($s)) {
            return null;
        }

        $f = (float) $s;

        if ($isPercent && in_array((string) $attribute, self::RATIO_ATTRS, true)) {
            $f /= 100.0; // rasio: 50% → 0.5
        }

        return is_finite($f) ? $f : null;
    }

    /** Catat input yang tidak terbaca (dipakai untuk pesan error). */
    private function markInvalid(string $attribute, mixed $raw): void
    {
        if (is_array($raw)) {
            $this->invalidInput[$attribute] = 'array';
            return;
        }
        if (is_object($raw)) {
            $this->invalidInput[$attribute] = 'object';
            return;
        }
        $this->invalidInput[$attribute] = mb_substr((string) $raw, 0, 40);
    }

    public function rules(): array
    {
        return [
            [['symbol', 'exchange'], 'string', 'max' => 16],
            [['signal'], 'in', 'range' => array_keys(\app\models\WeeklyAnalysis::SIGNALS)],
            [['mode'], 'in', 'range' => self::MODES,
                'message' => 'Mode harus "weekly" atau "daily".'],
            [['minBuyRatio'], 'number', 'min' => 0, 'max' => 1],
            [['minVolumeGrowth', 'minVolume', 'minPrice', 'maxPrice', 'minRvol'], 'number', 'min' => 0],
            // Market cap realistis (dolar penuh, bukan juta): maks ~$10T.
            [['minMarketCap', 'maxMarketCap'], 'integer', 'min' => 0, 'max' => 10000000000000],
            [['minScore'], 'integer', 'min' => 0, 'max' => 100],
            [['limit'], 'integer', 'min' => 1, 'max' => 500],
            [['sector'], 'string', 'max' => 64],
            [['limit'], 'default', 'value' => 50],
            // Auto-swap bila user terbalik (min > max) agar hasil tidak kosong diam-diam.
            [['maxPrice'], 'compare', 'compareAttribute' => 'minPrice', 'operator' => '>=',
                'when' => fn ($m) => $m->minPrice !== null && $m->maxPrice !== null,
                'message' => 'Max price harus >= min price.'],
            [['maxMarketCap'], 'compare', 'compareAttribute' => 'minMarketCap', 'operator' => '>=',
                'when' => fn ($m) => $m->minMarketCap !== null && $m->maxMarketCap !== null,
                'message' => 'Max market cap harus >= min market cap.'],
        ];
    }
}
