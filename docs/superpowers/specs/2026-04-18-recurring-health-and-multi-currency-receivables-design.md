# Recurring Schedule Health + Multi-Currency Total Receivables

**Date:** 2026-04-18
**Status:** Approved (brainstorm)
**Surfaces touched:** dashboard, customer detail, recurring service + cron, new admin settings page

Two related but independent features land together because they share the dashboard and a single API round-trip. They can be implemented and merged separately.

---

## Feature 1 — Recurring schedule health tracking

### Problem

Recurring schedules can fail silently. The cron logs errors to `storage/logs/laravel.log` but nothing surfaces in the UI. Today there is no way for a user to answer "did the cron actually run today, and did it process this schedule?" without SSHing to the server.

Scope of this spec: detect "did it run when it should have?" Out of scope: distinguishing "tried but errored" from "skipped silently" at the row level (see *Future work*).

### Data model

One schema change:

```php
Schema::table('recurring_invoices', function (Blueprint $table) {
    $table->timestamp('last_attempted_at')->nullable()->after('last_generated_at');
    $table->index('last_attempted_at');
});
```

One cache key:

```
recurring_cron.last_run_at  (Carbon, set with cache()->forever)
```

Cache is fine because it's a single most-recent-write key. If the cache is flushed, the dashboard shows "rates updated: unknown" — itself a useful signal.

### Backend changes

`App\Console\Commands\ProcessRecurringInvoices::handle()` writes the heartbeat at the start of every run:

```php
cache()->forever('recurring_cron.last_run_at', now());
```

`App\Services\RecurringInvoiceService::processScheduledInvoices()` stamps `last_attempted_at = now()` on every row it picks up, **before** calling `generateInvoice()` so the timestamp is recorded even if generation throws. The existing per-row try/catch wraps `generateInvoice()` only; the timestamp write is outside it.

`App\Services\DashboardService::getRecurringInvoicesSummary()` adds two derived signals:

```php
[
  'generated_today' => /* unchanged */,
  'overdue_count'   => RecurringInvoice::dueForGeneration()->count(),
  'cron'            => [
    'last_run_at' => cache('recurring_cron.last_run_at')?->toIso8601String(),
    'is_silent'   => optional(cache('recurring_cron.last_run_at'))->lt(now()->startOfDay()) ?? true,
  ],
  'upcoming'        => /* unchanged */,
]
```

The existing `dueForGeneration` scope (added in the recent /simplify pass) already returns the rows we want — `status IN (active, pending) AND recurrence_type != manual AND next_invoice_date <= today` — so "overdue" reuses it.

`RecurringInvoiceResource` adds two fields used by the customer-detail row badge:

```php
'last_attempted_at' => $this->last_attempted_at?->toIso8601String(),
'is_overdue'        => $this->isOverdue(),  // computed accessor on the model
```

`RecurringInvoice::isOverdue(): bool` returns `true` when the schedule's `next_invoice_date < today` AND `status` is `Active|Pending` AND recurrence type is not `Manual`. Single source of truth for the badge.

### UI

**Dashboard banner** (red, dismissable for the day): shown when `summary.cron.is_silent` is true. Copy: "Recurring invoice cron hasn't run since {date}. Check `invoices:process-recurring` on the server."

**Recurring widget** (existing card): adds a second number next to "generated today":

```
0  generated today    |    2  overdue
```

The "overdue" number uses destructive color when > 0, muted otherwise.

**Customer detail recurring list**: each row with `is_overdue: true` gets a single red badge "Overdue · N days" replacing the normal status badge. We do NOT split into separate "Overdue" / "Skipped by cron" badges — one severity at the row level is enough; the cron-silent banner handles the system-level distinction.

### Edge cases

- `recurrence_type = manual` → never overdue (no `next_invoice_date`).
- `status = pending` with future `start_date` → not overdue (next_invoice_date is in the future).
- Schedule created today with `next_invoice_date = today` and cron hasn't run yet → not overdue (today is not strictly less than today).
- Newly-merged or seeded rows with `last_attempted_at = NULL` → fine; the row-badge logic doesn't depend on `last_attempted_at` (only the unused-for-now skipped-by-cron computation does, which we are not surfacing in this spec).
- Cron-silent banner can be dismissed for the day via a `localStorage` flag keyed on the date — re-appears next day if still silent.

### Testing

- Feature: `processScheduledInvoices` writes `last_attempted_at` even when `generateInvoice` throws.
- Feature: `ProcessRecurringInvoices` command updates the cache heartbeat on success and on caught exceptions.
- Unit: `RecurringInvoice::isOverdue()` returns the right value for each `(status, recurrence_type, next_invoice_date)` combination.
- Feature: dashboard summary returns `cron.is_silent = true` when the cache key is stale or missing.

---

## Feature 2 — Multi-currency Total Receivables

### Problem

`DashboardService::getTotalReceivables()` does `Invoice::unpaid()->sum('total')` across all invoices, mixing IDR + USD + JPY + AUD + SGD into a single number. The frontend then formats that nonsense number with a default IDR symbol. Users see "Rp 3,000.00" for a $3,000 USD invoice.

### Decisions

- **Base currency:** IDR (configured via `BILLING_BASE_CURRENCY`, default 'IDR').
- **Rate source:** manual, via a new admin settings page. No external API in scope.
- **Conversion timing:** live (today's rate) at dashboard read time. AR snapshot at this instant in IDR equivalent; not an audit-grade per-invoice locked rate.
- **Display:** single IDR-equivalent headline + always-visible per-currency breakdown (variant A from the mockups).

### Data model

```php
// config/billing.php — extend existing file
return [
    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'IDR'),
    'base_currency'    => env('BILLING_BASE_CURRENCY', 'IDR'),
    'invoice_number'   => [...],  // unchanged
];
```

```php
Schema::create('currency_rates', function (Blueprint $table) {
    $table->string('currency', 3)->primary();
    $table->decimal('rate_to_base', 20, 10);   // 1 unit of `currency` = N units of base
    $table->timestamps();
});
```

The base currency is implicit (`rate_to_base = 1`) and not stored as a row.

### Backend

**New model:** `App\Models\CurrencyRate` — fillable `[currency, rate_to_base]`, no special behaviour.

**New service:** `App\Services\CurrencyConverter`:

```php
public function convert(float $amount, string $from, ?string $to = null): float;
public function ratesMap(): array;            // ['USD' => 16250.0, 'JPY' => 110.0, ...]
public function ratesUpdatedAt(): ?Carbon;    // max(updated_at) across the table
public function knownCurrencies(): array;     // base + all rate codes
```

- Rates loaded once per request and memoised on the service instance.
- `convert()` returns `$amount * rate` when converting *to* base; throws `MissingRateException` when the currency is unknown.
- Base currency converts to itself with `rate = 1`.

**New exception:** `App\Exceptions\MissingRateException` — extends `RuntimeException`. Not rendered to HTTP directly (callers catch it).

**`DashboardService::getTotalReceivables()`** changes signature from `float` to `array`:

```php
public function getTotalReceivables(): array
{
    $byCurrency = Invoice::unpaid()
        ->selectRaw('currency, SUM(total) as amount')
        ->groupBy('currency')
        ->get();

    $base = config('billing.base_currency');
    $missing = [];
    $breakdown = [];
    $baseTotal = 0.0;

    foreach ($byCurrency as $row) {
        $entry = ['currency' => $row->currency, 'amount' => (float) $row->amount, 'base_equivalent' => null];
        try {
            $entry['base_equivalent'] = $this->converter->convert($entry['amount'], $row->currency, $base);
            $baseTotal += $entry['base_equivalent'];
        } catch (MissingRateException) {
            $missing[] = $row->currency;
        }
        $breakdown[] = $entry;
    }

    return [
        'base_currency'    => $base,
        'base_total'       => $baseTotal,
        'breakdown'        => $breakdown,
        'rates_updated_at' => $this->converter->ratesUpdatedAt()?->toIso8601String(),
        'missing_rates'    => $missing,
    ];
}
```

`CurrencyConverter` is constructor-injected into `DashboardService`.

**Routes** (admin only — see *Open question* below):

```
GET    /api/v1/currency-rates              CurrencyRateController@index
PUT    /api/v1/currency-rates/{currency}   CurrencyRateController@upsert
```

Validation: `currency` must be 3-letter uppercase, must not equal the base currency, `rate_to_base` must be positive numeric.

### Frontend

**Type for the new payload** (in `types/api.ts` or a new `dashboard.ts`):

```ts
export interface ReceivablesSummary {
  base_currency: string;
  base_total: number;
  breakdown: Array<{ currency: string; amount: number; base_equivalent: number | null }>;
  rates_updated_at: string | null;
  missing_rates: string[];
}
```

**Dashboard card** (variant A from mockups):

- Headline: `≈ {formatCurrency(base_total, base_currency)}` (the `≈` glyph is part of the literal string, signalling "this is converted").
- Subtext: `IDR equivalent · rates updated {relative time}`.
- Always-visible breakdown grid: per-currency native amount on the left, IDR equivalent on the right. The base-currency row shows "(native)" instead of an equivalent.
- Above the card: a yellow `MissingRatesWarning` banner per missing currency, linking to the Exchange Rates settings page. Shown only when `missing_rates.length > 0`.

**`formatCurrency` change** ([lib/utils/formatters.ts:6](../../../frontend/lib/utils/formatters.ts:6)):

```ts
// before
export function formatCurrency(amount: number, currency = 'IDR', locale?: string): string;

// after
export function formatCurrency(amount: number, currency: string, locale?: string): string;
```

The IDR default is removed so the type system catches every caller that wasn't passing a currency. Each call site that breaks is fixed by passing the correct currency from context.

**New admin page** at `app/settings/exchange-rates/page.tsx`:

- Header: title, subtitle ("Base currency: IDR. All invoice totals are converted to IDR on the dashboard using these rates."), `+ Add currency` button (opens a modal: 3-letter code + initial rate).
- Table: currency code + name (resolved from a small static map), editable "1 unit = N IDR" input, last-updated timestamp, per-row Save button.
- Currencies in use but missing a rate appear with a yellow background and "(missing)" label.
- Base currency (IDR) does not appear in the table.

**Hooks**: new `lib/hooks/useCurrencyRates.ts` with `useCurrencyRates()` (list) and `useUpsertCurrencyRate()` (mutation). On mutation success, invalidates `['currency-rates']` and `['dashboard']`.

### Edge cases

- **Missing rate**: invoice currency exists but no rate row. The currency total is still listed in `breakdown` (with `base_equivalent: null`) and added to `missing_rates`. NOT included in `base_total`. Dashboard shows the warning banner with a deep link to the settings page.
- **All invoices in base currency**: `breakdown` has one row, `base_total === breakdown[0].amount`, `missing_rates: []`. The card reads exactly like today's card with no `≈` and no IDR-equivalent column on that row.
- **No unpaid invoices**: `breakdown: []`, `base_total: 0`, no warnings. Headline shows `Rp 0`.
- **Stale rates**: there is no automatic staleness threshold. Users see the relative timestamp ("rates updated 2 days ago") and decide. We do NOT lock rates older than X days; out of scope.
- **Currency removed from invoices but rate still exists**: rate row stays. Harmless. Manual cleanup via the same settings page.
- **Adding a currency rate that doesn't match any invoice yet**: allowed. No constraint between `currency_rates.currency` and `invoices.currency`.

### Testing

- Unit: `CurrencyConverter::convert` for base-to-base, foreign-to-base, unknown currency (throws), and `ratesMap()` memoisation.
- Feature: `DashboardController@summary` with mixed-currency invoices returns the correct grouped breakdown, base total, and missing-rates list.
- Feature: `CurrencyRateController` PUT endpoint upserts and returns the updated row; rejects invalid codes and the base currency.
- Frontend: `formatCurrency` callers compile after the default is removed (TS step in the build catches this).

### Open question

**Authorization for the rates endpoints.** Today every `FormRequest::authorize()` returns `true` — there is no real auth model. For now we ship the rates endpoints with the same posture as the rest of the API and add a follow-up ticket to introduce policies once an auth model exists. Flagged in the implementation plan but not blocking.

---

## Out of scope (both features)

- Per-row "skipped by cron" badge (would require comparing `last_attempted_at` with `next_invoice_date`; data is captured but not surfaced this round).
- Generic activity log per recurring schedule (the heavier "Approach 3" from brainstorm).
- Auto-pulled FX rates from an external API.
- Per-invoice locked exchange rate at creation time.
- Auth policies for the new currency-rates endpoints (tracked separately).
- Email/Slack alerts when the cron is silent (UI banner only, this round).

## Migration plan

Both migrations are additive (new table; new nullable column with index). No data backfill required. Rollback is `down()` only.

## Affected files (summary)

**Backend**
- new `app/Enums/RecurringStatus.php` — already added in prior pass; no change here
- migrate `recurring_invoices.last_attempted_at` (new column)
- migrate `currency_rates` (new table)
- new `app/Models/CurrencyRate.php`
- new `app/Services/CurrencyConverter.php`
- new `app/Exceptions/MissingRateException.php`
- new `app/Http/Controllers/CurrencyRateController.php` + `Requests/CurrencyRate/UpsertRateRequest.php`
- edit `app/Console/Commands/ProcessRecurringInvoices.php` (heartbeat)
- edit `app/Services/RecurringInvoiceService.php` (stamp last_attempted_at)
- edit `app/Services/DashboardService.php` (new receivables shape + cron health)
- edit `app/Http/Resources/RecurringInvoiceResource.php` (+is_overdue, +last_attempted_at)
- edit `app/Models/RecurringInvoice.php` (+isOverdue accessor)
- edit `routes/api.php` (+currency-rates routes)
- tests as listed under each feature

**Frontend**
- edit `lib/utils/formatters.ts` (drop IDR default)
- edit `lib/hooks/useDashboard.ts` types (none if structural)
- new `lib/hooks/useCurrencyRates.ts`
- new `lib/api/currency-rates.ts`
- edit `types/dashboard.ts` (or `api.ts`) — add `ReceivablesSummary`, recurring-cron fields
- edit `components/dashboard/SummaryCard.tsx` (or new `TotalReceivablesCard.tsx`) — variant A layout
- edit `components/dashboard/RecurringInvoicesWidget.tsx` (+overdue count, +cron-silent banner)
- new `components/dashboard/MissingRatesWarning.tsx`
- edit `components/recurring/RecurringInvoiceList.tsx` (+overdue badge)
- new `app/settings/exchange-rates/page.tsx` + supporting components
- nav link to settings/exchange-rates in `components/Layout.tsx`
