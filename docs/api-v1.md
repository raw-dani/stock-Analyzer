# REST API v1 — US Stock Volume Analyzer

Base URL (dev): `http://127.0.0.1:8089/api/v1`

- Format respons: JSON (`application/json`).
- Error JSON: `{ "name": "...", "message": "...", "code": ..., "status": ... }` — semua exception pada path `api/*` dirender JSON oleh `ApiErrorHandler`.
- **Rate limit** (task 13.2): semua endpoint dibatasi `apiRateLimitPerMinute` (default 120/menit) per Bearer token / IP. Header: `X-Rate-Limit-Limit`, `X-Rate-Limit-Remaining`. Melebihi → HTTP 429.

## Autentikasi (Bearer token)

Endpoint publik (read-only data pasar): `stocks`, `scanner`, `signals`.
Endpoint user-scoped (wajib Bearer): `watchlist`, `alerts`.

```
Authorization: Bearer <access_token>
```

Token disimpan di kolom `user.access_token` (seed: admin = `100-token`, demo = `demo-token` — lihat `scripts/seed-admin.php`).

## Endpoint

### 1. `GET /api/v1/stocks` — daftar saham (publik)

Query: `symbol` (like), `sector`, `exchange`, `limit` (1–100, default 20), `page`.

```bash
curl "http://127.0.0.1:8089/api/v1/stocks?sector=Technology&limit=10"
```

### 2. `GET /api/v1/stocks/{symbol}` — detail saham (publik)

Query: `bars` (1–365, default 30) — jumlah bar OHLCV.

```bash
curl "http://127.0.0.1:8089/api/v1/stocks/NVDA?bars=60"
```

Respons: `stock`, `latestWeekly` (buy_ratio, rvol, score, signal, ...), `latestSignal` (termasuk `reason` JSON string), `bars` (OHLCV).

### 3. `GET /api/v1/scanner` — hasil scanner terurut score (publik)

Query sama dengan web scanner: `symbol`, `exchange`, `sector`, `signal` (STRONG_BUY/BUY/WATCH/WEAK/SELL), `minScore`, `minBuyRatio`, `minRvol`, `minPrice`, `maxPrice`, `minVolume`, `limit`, `page`.

```bash
curl "http://127.0.0.1:8089/api/v1/scanner?signal=STRONG_BUY&limit=5"
```

Respons: `criteria`, `items[]`, `totalCount`.

### 4. `GET /api/v1/signals` — histori sinyal (publik)

Query: `symbol`, `signal`, `dateFrom`, `dateTo`, `limit`, `page`.

```bash
curl "http://127.0.0.1:8089/api/v1/signals?symbol=NVDA&signal=BUY&dateFrom=2026-01-01"
```

Respons: `items[]` (dengan `reasons` array) + `totalCount`.

### 5. `GET /api/v1/watchlists` — watchlist user (Bearer)

```bash
curl -H "Authorization: Bearer 100-token" "http://127.0.0.1:8089/api/v1/watchlists"
```

### 6. `GET /api/v1/alerts` — alert user (Bearer)

```bash
curl -H "Authorization: Bearer 100-token" "http://127.0.0.1:8089/api/v1/alerts"
```

Respons: `items[]` — `symbol` null berarti alert berlaku semua simbol.

## Contoh error

```bash
curl -i "http://127.0.0.1:8089/api/v1/stocks/ZZZZ"        # 404 JSON
curl -i "http://127.0.0.1:8089/api/v1/watchlists"           # 401 tanpa token
curl -i "http://127.0.0.1:8089/api/v1/stocks" -X POST      # 405 (GET-only)
```
