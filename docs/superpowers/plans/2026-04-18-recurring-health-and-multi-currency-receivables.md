# Recurring Schedule Health + Multi-Currency Receivables Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface recurring-schedule health on the dashboard (cron heartbeat + per-row overdue badge) and replace the single-currency-sum Total Receivables card with a per-currency breakdown plus an IDR-equivalent headline using admin-maintained FX rates.

**Architecture:** Two independent features that share the dashboard payload. Feature 1 adds a nullable `last_attempted_at` column and a cache heartbeat (`recurring_cron.last_run_at`) so the dashboard can show a red banner when the cron is silent and a red "Overdue" row badge per schedule. Feature 2 adds a `currency_rates` table, a `CurrencyConverter` service, structured dashboard payload, and a new admin settings page. Both features follow the thin-controller / service-layer pattern already established in `backend/app/Services/`.

**Tech Stack:** Laravel 11 (PHP 8.3, PHPUnit, Pest not in use), Next.js 16 (App Router, TanStack Query, TypeScript, Tailwind v4, shadcn/ui), SQLite for tests.

Spec: `docs/superpowers/specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md`.

**Conventions:**
- All backend paths are relative to `backend/`; all frontend paths relative to `frontend/`. Runs can be launched from the repo root (e.g. `cd backend && php artisan ...`).
- Every backend task adds tests first (TDD) using the existing PHPUnit setup (`:memory:` sqlite, `RefreshDatabase` trait).
- Frontend has no JS test runner configured; "tests" are `npm run build` (types + static generation) plus a browser smoke check described per task.
- Each task ends with `git add <specific files>` followed by a commit. **Never** use `git add -A` / `git add .` — the repo has pre-existing uncommitted changes from earlier work that must not sweep in.
- Run backend tests with `cd backend && php artisan test`. Run frontend build with `cd frontend && npm run build`.

**Feature order is independent.** Feature 1 and Feature 2 can ship in either order. The plan presents Feature 1 first because it has a smaller blast radius.

---

## FEATURE 1 — Recurring Schedule Health

### Task 1.1: Migration — add `last_attempted_at` to `recurring_invoices`

**Files:**
- Create: `backend/database/migrations/2026_04_19_000000_add_last_attempted_at_to_recurring_invoices.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->timestamp('last_attempted_at')->nullable()->after('last_generated_at');
            $table->index('last_attempted_at', 'recurring_invoices_last_attempted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropIndex('recurring_invoices_last_attempted_at_index');
            $table->dropColumn('last_attempted_at');
        });
    }
};
```

- [ ] **Step 2: Run migrations in test environment**

```bash
cd backend && php artisan migrate --env=testing
```

Expected: migration runs without error.

- [ ] **Step 3: Commit**

```bash
cd backend && git add database/migrations/2026_04_19_000000_add_last_attempted_at_to_recurring_invoices.php
git commit -m "feat(recurring): add last_attempted_at column"
```

---

### Task 1.2: Model — expose `last_attempted_at` + `isOverdue()` accessor

**Files:**
- Modify: `backend/app/Models/RecurringInvoice.php`
- Create: `backend/tests/Unit/Models/RecurringInvoiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'T',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDays(2)->toDateString(),
            'next_invoice_date' => now()->subDays(2)->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $attrs));
    }

    public function test_overdue_when_next_date_past_and_status_active(): void
    {
        $this->assertTrue($this->make()->isOverdue());
    }

    public function test_overdue_when_status_pending_and_next_date_past(): void
    {
        $this->assertTrue($this->make(['status' => RecurringStatus::Pending->value])->isOverdue());
    }

    public function test_not_overdue_when_next_date_today(): void
    {
        $this->assertFalse($this->make(['next_invoice_date' => now()->toDateString()])->isOverdue());
    }

    public function test_not_overdue_when_next_date_future(): void
    {
        $this->assertFalse($this->make(['next_invoice_date' => now()->addDay()->toDateString()])->isOverdue());
    }

    public function test_not_overdue_when_manual(): void
    {
        $this->assertFalse(
            $this->make([
                'recurrence_type' => RecurrenceType::Manual->value,
                'next_invoice_date' => null,
            ])->isOverdue()
        );
    }

    public function test_not_overdue_when_completed(): void
    {
        $this->assertFalse($this->make(['status' => RecurringStatus::Completed->value])->isOverdue());
    }

    public function test_not_overdue_when_terminated(): void
    {
        $this->assertFalse($this->make(['status' => RecurringStatus::Terminated->value])->isOverdue());
    }

    public function test_last_attempted_at_is_fillable_and_cast_to_carbon(): void
    {
        $ts = now()->subHour();
        $row = $this->make(['last_attempted_at' => $ts]);
        $this->assertEquals($ts->toIso8601String(), $row->fresh()->last_attempted_at->toIso8601String());
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=RecurringInvoiceTest
```

Expected: FAIL — `isOverdue()` method missing; `last_attempted_at` not fillable.

- [ ] **Step 3: Update the model**

In `backend/app/Models/RecurringInvoice.php`:

Add `'last_attempted_at'` to `$fillable` (place it right after `'last_generated_at'`).

Add to `$casts`:

```php
'last_attempted_at' => 'datetime',
```

Add this accessor method below the existing `scopeDueForGeneration` method:

```php
public function isOverdue(): bool
{
    if ($this->recurrence_type === RecurrenceType::Manual) {
        return false;
    }
    if (!in_array($this->status, [RecurringStatus::Active, RecurringStatus::Pending], true)) {
        return false;
    }
    if (!$this->next_invoice_date) {
        return false;
    }
    return $this->next_invoice_date->lt(Carbon::today());
}
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=RecurringInvoiceTest
```

Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Models/RecurringInvoice.php tests/Unit/Models/RecurringInvoiceTest.php
git commit -m "feat(recurring): add isOverdue accessor and last_attempted_at cast"
```

---

### Task 1.3: Cron heartbeat in `ProcessRecurringInvoices` command

**Files:**
- Modify: `backend/app/Console/Commands/ProcessRecurringInvoices.php`
- Create: `backend/tests/Feature/Console/ProcessRecurringInvoicesTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProcessRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_heartbeat_even_with_no_schedules(): void
    {
        Cache::forget('recurring_cron.last_run_at');

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        $this->assertNotNull(Cache::get('recurring_cron.last_run_at'));
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=ProcessRecurringInvoicesTest
```

Expected: FAIL — cache key not written.

- [ ] **Step 3: Edit the command**

In `backend/app/Console/Commands/ProcessRecurringInvoices.php`, modify `handle()`:

```php
public function handle(RecurringInvoiceService $service): int
{
    cache()->forever('recurring_cron.last_run_at', now());

    $this->info('Starting recurring invoice processing...');

    $count = $service->processScheduledInvoices();
    $this->info("Successfully processed {$count} recurring invoices.");

    return Command::SUCCESS;
}
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=ProcessRecurringInvoicesTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Console/Commands/ProcessRecurringInvoices.php tests/Feature/Console/ProcessRecurringInvoicesTest.php
git commit -m "feat(recurring): write cron heartbeat on every run"
```

---

### Task 1.4: Service — stamp `last_attempted_at` before generation

**Files:**
- Modify: `backend/app/Services/RecurringInvoiceService.php`
- Create: `backend/tests/Feature/Services/RecurringInvoiceServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Services;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activeSchedule(array $overrides = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'Retainer',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDay()->toDateString(),
            'next_invoice_date' => now()->subDay()->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [
                ['description' => 'Svc', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100],
            ],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $overrides));
    }

    public function test_processScheduledInvoices_stamps_last_attempted_at(): void
    {
        $schedule = $this->activeSchedule();

        $count = app(RecurringInvoiceService::class)->processScheduledInvoices();

        $this->assertSame(1, $count);
        $this->assertNotNull($schedule->fresh()->last_attempted_at);
    }

    public function test_processScheduledInvoices_stamps_last_attempted_at_even_when_generation_fails(): void
    {
        // Schedule with empty line_items — generation will fail inside Invoice create validation / items sync
        $schedule = $this->activeSchedule(['line_items' => null]);

        try {
            app(RecurringInvoiceService::class)->processScheduledInvoices();
        } catch (\Throwable) {
            // swallow — behaviour under test is the timestamp, not the outcome
        }

        $this->assertNotNull($schedule->fresh()->last_attempted_at);
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=RecurringInvoiceServiceTest
```

Expected: FAIL — `last_attempted_at` stays null.

- [ ] **Step 3: Edit `processScheduledInvoices`**

In `backend/app/Services/RecurringInvoiceService.php`, replace the `processScheduledInvoices` method:

```php
public function processScheduledInvoices(?Carbon $asOf = null): int
{
    $count = 0;

    RecurringInvoice::dueForGeneration($asOf)
        ->chunkById(100, function ($chunk) use (&$count) {
            foreach ($chunk as $recurringInvoice) {
                $recurringInvoice->forceFill(['last_attempted_at' => now()])->save();

                try {
                    if ($this->generateInvoice($recurringInvoice)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to generate recurring invoice ID {$recurringInvoice->id}: " . $e->getMessage());
                }
            }
        });

    return $count;
}
```

The `forceFill(...)->save()` runs outside the `try`, so the timestamp is written even if `generateInvoice` throws.

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=RecurringInvoiceServiceTest
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Services/RecurringInvoiceService.php tests/Feature/Services/RecurringInvoiceServiceTest.php
git commit -m "feat(recurring): stamp last_attempted_at on every cron touch"
```

---

### Task 1.5: Dashboard payload — expose `overdue_count` and `cron` health

**Files:**
- Modify: `backend/app/Services/DashboardService.php`
- Create: `backend/tests/Feature/DashboardRecurringHealthTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardRecurringHealthTest extends TestCase
{
    use RefreshDatabase;

    private function schedule(array $overrides = []): RecurringInvoice
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        return RecurringInvoice::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'T',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDays(3)->toDateString(),
            'next_invoice_date' => now()->subDays(3)->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
        ], $overrides));
    }

    public function test_overdue_count_reflects_overdue_schedules(): void
    {
        $this->schedule();                                // overdue
        $this->schedule(['next_invoice_date' => now()->addDay()->toDateString()]);  // not overdue

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertSame(1, $res['data']['recurring_invoices']['overdue_count']);
    }

    public function test_cron_is_silent_when_cache_missing(): void
    {
        Cache::forget('recurring_cron.last_run_at');

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertTrue($res['data']['recurring_invoices']['cron']['is_silent']);
        $this->assertNull($res['data']['recurring_invoices']['cron']['last_run_at']);
    }

    public function test_cron_is_silent_when_last_run_before_today(): void
    {
        Cache::forever('recurring_cron.last_run_at', now()->subDays(2));

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertTrue($res['data']['recurring_invoices']['cron']['is_silent']);
    }

    public function test_cron_not_silent_when_last_run_today(): void
    {
        Cache::forever('recurring_cron.last_run_at', now());

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $this->assertFalse($res['data']['recurring_invoices']['cron']['is_silent']);
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=DashboardRecurringHealthTest
```

Expected: FAIL — keys missing from payload.

- [ ] **Step 3: Edit `getRecurringInvoicesSummary`**

In `backend/app/Services/DashboardService.php`, replace the method body:

```php
public function getRecurringInvoicesSummary(): array
{
    $generatedToday = Invoice::where('type', \App\Enums\InvoiceType::Recurring->value)
        ->whereDate('created_at', now()->today())
        ->count();

    $overdueCount = \App\Models\RecurringInvoice::dueForGeneration()->count();

    $lastRun = cache('recurring_cron.last_run_at');
    $cron = [
        'last_run_at' => $lastRun?->toIso8601String(),
        'is_silent' => $lastRun === null || $lastRun->lt(now()->startOfDay()),
    ];

    $today = now()->today();
    $upcoming = \App\Models\RecurringInvoice::where('status', \App\Enums\RecurringStatus::Active->value)
        ->where('recurrence_type', '!=', \App\Enums\RecurrenceType::Manual->value)
        ->where('next_invoice_date', '>=', $today->toDateString())
        ->where('next_invoice_date', '<=', $today->copy()->addDays(7)->toDateString())
        ->with('customer')
        ->orderBy('next_invoice_date')
        ->get()
        ->map(fn ($inv) => [
            'id' => $inv->id,
            'customer_id' => $inv->customer_id,
            'customer_name' => $inv->customer->name,
            'title' => $inv->title,
            'next_invoice_date' => $inv->next_invoice_date?->toDateString(),
        ]);

    return [
        'generated_today' => $generatedToday,
        'overdue_count' => $overdueCount,
        'cron' => $cron,
        'upcoming' => $upcoming,
    ];
}
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=DashboardRecurringHealthTest
```

Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Services/DashboardService.php tests/Feature/DashboardRecurringHealthTest.php
git commit -m "feat(dashboard): expose overdue_count and cron health"
```

---

### Task 1.6: Resource — add `is_overdue` + `last_attempted_at` to `RecurringInvoiceResource`

**Files:**
- Modify: `backend/app/Http/Resources/RecurringInvoiceResource.php`
- Create: `backend/tests/Feature/Http/Resources/RecurringInvoiceResourceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Http\Resources;

use App\Enums\RecurrenceType;
use App\Enums\RecurringStatus;
use App\Http\Resources\RecurringInvoiceResource;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecurringInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_includes_is_overdue_and_last_attempted_at(): void
    {
        $customer = Customer::factory()->create(['currency' => 'IDR']);
        $schedule = RecurringInvoice::create([
            'customer_id' => $customer->id,
            'title' => 'T',
            'recurrence_type' => RecurrenceType::Monthly->value,
            'recurrence_interval' => 1,
            'start_date' => now()->subDays(3)->toDateString(),
            'next_invoice_date' => now()->subDays(3)->toDateString(),
            'status' => RecurringStatus::Active->value,
            'line_items' => [],
            'tax_rate' => 0,
            'currency' => 'IDR',
            'last_attempted_at' => now()->subHour(),
        ]);

        $array = (new RecurringInvoiceResource($schedule))->toArray(new Request());

        $this->assertTrue($array['is_overdue']);
        $this->assertNotNull($array['last_attempted_at']);
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=RecurringInvoiceResourceTest
```

Expected: FAIL — keys missing.

- [ ] **Step 3: Edit the resource**

In `backend/app/Http/Resources/RecurringInvoiceResource.php`, add two lines to the `toArray` return array (below `last_generated_at`):

```php
            'last_attempted_at' => $this->last_attempted_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=RecurringInvoiceResourceTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Http/Resources/RecurringInvoiceResource.php tests/Feature/Http/Resources/RecurringInvoiceResourceTest.php
git commit -m "feat(recurring): expose is_overdue and last_attempted_at on resource"
```

---

### Task 1.7: Frontend — types + dashboard CronSilentBanner + widget overdue row

**Files:**
- Modify: `frontend/types/api.ts`
- Modify: `frontend/types/invoice.ts`
- Create: `frontend/components/dashboard/CronSilentBanner.tsx`
- Modify: `frontend/components/dashboard/RecurringInvoicesWidget.tsx`
- Modify: `frontend/components/dashboard/Dashboard.tsx`

- [ ] **Step 1: Extend types**

In `frontend/types/api.ts`, replace the `recurring_invoices` block inside `DashboardSummary`:

```ts
    recurring_invoices: {
        generated_today: number;
        overdue_count: number;
        cron: {
            last_run_at: string | null;
            is_silent: boolean;
        };
        upcoming: {
            id: number;
            customer_id: number;
            customer_name: string;
            title: string;
            next_invoice_date: string;
        }[];
    };
```

In `frontend/types/invoice.ts`, extend the `RecurringInvoice` interface (at the end of the interface body, before the closing brace):

```ts
    last_attempted_at: string | null;
    is_overdue: boolean;
```

- [ ] **Step 2: Create `CronSilentBanner`**

Create `frontend/components/dashboard/CronSilentBanner.tsx`:

```tsx
'use client';

import { useEffect, useState } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import { formatDate } from '@/lib/utils';

interface CronSilentBannerProps {
    lastRunAt: string | null;
}

export function CronSilentBanner({ lastRunAt }: CronSilentBannerProps) {
    const dismissKey = `cronSilentBanner.dismissedOn.${new Date().toDateString()}`;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(typeof window !== 'undefined' && localStorage.getItem(dismissKey) === '1');
    }, [dismissKey]);

    if (dismissed) return null;

    const handleDismiss = () => {
        localStorage.setItem(dismissKey, '1');
        setDismissed(true);
    };

    const lastRunCopy = lastRunAt
        ? `hasn't run since ${formatDate(lastRunAt)}`
        : 'has never been recorded as running';

    return (
        <div className="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
                <div className="font-semibold">Recurring invoice cron {lastRunCopy}.</div>
                <div className="text-destructive/80 mt-0.5">
                    Check <code className="rounded bg-destructive/10 px-1 py-0.5 text-xs">invoices:process-recurring</code> on the server.
                </div>
            </div>
            <button onClick={handleDismiss} className="rounded p-1 hover:bg-destructive/10" aria-label="Dismiss">
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
```

- [ ] **Step 3: Update `RecurringInvoicesWidget` to show `overdue_count`**

In `frontend/components/dashboard/RecurringInvoicesWidget.tsx`, extend the `RecurringInvoicesWidgetProps` data shape and replace the top counter row:

```tsx
import { cn } from '@/lib/utils';

interface RecurringInvoicesWidgetProps {
    data: {
        generated_today: number;
        overdue_count: number;
        upcoming: {
            id: number;
            customer_id: number;
            customer_name: string;
            title: string;
            next_invoice_date: string;
        }[];
    };
}
```

Replace the old `<div className="flex items-center space-x-2 text-2xl font-bold">...</div>` block with:

```tsx
                <div className="flex items-center gap-6 text-2xl font-bold">
                    <div className="flex items-center gap-2">
                        <span>{data.generated_today}</span>
                        <span className="text-sm font-normal text-muted-foreground">generated today</span>
                    </div>
                    <div className="flex items-center gap-2 border-l border-border pl-6">
                        <span className={cn(data.overdue_count > 0 ? 'text-destructive' : 'text-muted-foreground')}>
                            {data.overdue_count}
                        </span>
                        <span className="text-sm font-normal text-muted-foreground">overdue</span>
                    </div>
                </div>
```

- [ ] **Step 4: Render banner on `Dashboard.tsx`**

In `frontend/components/dashboard/Dashboard.tsx`, import the banner:

```tsx
import { CronSilentBanner } from './CronSilentBanner';
```

Immediately inside the root `<div className="space-y-8">`, after the `<header>` block, add:

```tsx
            {summary?.recurring_invoices?.cron?.is_silent && (
                <CronSilentBanner lastRunAt={summary.recurring_invoices.cron.last_run_at} />
            )}
```

- [ ] **Step 5: Build + smoke check**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors.

Browser smoke: with the backend running, navigate to the dashboard. Manually clear the cache key (`cd backend && php artisan tinker` → `cache()->forget('recurring_cron.last_run_at')`) and reload the page — the red banner should appear. Run the cron (`php artisan invoices:process-recurring`) and reload — banner disappears.

- [ ] **Step 6: Commit**

```bash
cd frontend && git add types/api.ts types/invoice.ts components/dashboard/CronSilentBanner.tsx components/dashboard/RecurringInvoicesWidget.tsx components/dashboard/Dashboard.tsx
git commit -m "feat(dashboard): cron-silent banner and overdue count on widget"
```

---

### Task 1.8: Frontend — overdue badge on customer detail recurring list

**Files:**
- Modify: `frontend/components/recurring/RecurringInvoiceList.tsx`

- [ ] **Step 1: Update list status cell to show the overdue badge**

In `frontend/components/recurring/RecurringInvoiceList.tsx`, find the `<td>` for `status` (contains `<StatusBadge status={invoice.status} />`) and replace with:

```tsx
                                    <td className="px-4 py-3">
                                        {invoice.is_overdue ? (
                                            <span
                                                className="inline-flex items-center rounded-full bg-destructive/10 px-2.5 py-0.5 text-xs font-medium text-destructive"
                                                title={
                                                    invoice.next_invoice_date
                                                        ? `Next run was ${invoice.next_invoice_date}`
                                                        : 'Overdue'
                                                }
                                            >
                                                Overdue
                                                {invoice.next_invoice_date && (() => {
                                                    const days = Math.max(
                                                        1,
                                                        Math.floor(
                                                            (Date.now() - new Date(invoice.next_invoice_date).getTime()) /
                                                                (24 * 60 * 60 * 1000)
                                                        )
                                                    );
                                                    return ` · ${days}d`;
                                                })()}
                                            </span>
                                        ) : (
                                            <StatusBadge status={invoice.status} />
                                        )}
                                    </td>
```

- [ ] **Step 2: Build + smoke check**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors.

Browser smoke: navigate to a customer with a recurring schedule whose `next_invoice_date` is in the past and `status` is active. The row should show the red "Overdue · Nd" badge.

- [ ] **Step 3: Commit**

```bash
cd frontend && git add components/recurring/RecurringInvoiceList.tsx
git commit -m "feat(recurring): overdue badge on customer detail list"
```

---

## FEATURE 2 — Multi-Currency Total Receivables

### Task 2.1: Add `base_currency` to `config/billing.php`

**Files:**
- Modify: `backend/config/billing.php`

- [ ] **Step 1: Edit the config**

Replace the file contents of `backend/config/billing.php` with:

```php
<?php

return [
    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'IDR'),
    'base_currency' => env('BILLING_BASE_CURRENCY', 'IDR'),

    'invoice_number' => [
        'prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
        'format' => env('BILLING_INVOICE_FORMAT', '%s-%d-%04d'),
    ],
];
```

- [ ] **Step 2: Verify config loads**

```bash
cd backend && php artisan tinker --execute="echo config('billing.base_currency');"
```

Expected output: `IDR`.

- [ ] **Step 3: Commit**

```bash
cd backend && git add config/billing.php
git commit -m "feat(billing): add base_currency config key"
```

---

### Task 2.2: Migration — `currency_rates` table

**Files:**
- Create: `backend/database/migrations/2026_04_19_000001_create_currency_rates_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->string('currency', 3)->primary();
            $table->decimal('rate_to_base', 20, 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
```

- [ ] **Step 2: Run migrations**

```bash
cd backend && php artisan migrate --env=testing
```

Expected: migration runs without error.

- [ ] **Step 3: Commit**

```bash
cd backend && git add database/migrations/2026_04_19_000001_create_currency_rates_table.php
git commit -m "feat(rates): create currency_rates table"
```

---

### Task 2.3: Model — `CurrencyRate`

**Files:**
- Create: `backend/app/Models/CurrencyRate.php`

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $primaryKey = 'currency';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'currency',
        'rate_to_base',
    ];

    protected $casts = [
        'rate_to_base' => 'decimal:10',
    ];
}
```

- [ ] **Step 2: Commit**

```bash
cd backend && git add app/Models/CurrencyRate.php
git commit -m "feat(rates): add CurrencyRate model"
```

---

### Task 2.4: Exception — `MissingRateException`

**Files:**
- Create: `backend/app/Exceptions/MissingRateException.php`

- [ ] **Step 1: Write the exception**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class MissingRateException extends RuntimeException
{
    public function __construct(public readonly string $currency)
    {
        parent::__construct("No exchange rate set for currency: {$currency}");
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd backend && git add app/Exceptions/MissingRateException.php
git commit -m "feat(rates): add MissingRateException"
```

---

### Task 2.5: Service — `CurrencyConverter`

**Files:**
- Create: `backend/app/Services/CurrencyConverter.php`
- Create: `backend/tests/Unit/Services/CurrencyConverterTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Exceptions\MissingRateException;
use App\Models\CurrencyRate;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_to_base_is_identity(): void
    {
        $converter = app(CurrencyConverter::class);
        $this->assertSame(100.0, $converter->convert(100, 'IDR'));
        $this->assertSame(100.0, $converter->convert(100, 'IDR', 'IDR'));
    }

    public function test_foreign_to_base_uses_stored_rate(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $converter = app(CurrencyConverter::class);

        $this->assertSame(1_600_000.0, $converter->convert(100, 'USD'));
    }

    public function test_unknown_currency_throws(): void
    {
        $converter = app(CurrencyConverter::class);

        $this->expectException(MissingRateException::class);
        $converter->convert(1, 'SGD');
    }

    public function test_rates_map_includes_base_identity(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $map = app(CurrencyConverter::class)->ratesMap();

        $this->assertSame(1.0, $map['IDR']);
        $this->assertSame(16000.0, $map['USD']);
    }

    public function test_rates_updated_at_returns_max_timestamp(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        CurrencyRate::create(['currency' => 'JPY', 'rate_to_base' => 110]);

        $this->assertNotNull(app(CurrencyConverter::class)->ratesUpdatedAt());
    }

    public function test_rates_updated_at_null_when_empty(): void
    {
        $this->assertNull(app(CurrencyConverter::class)->ratesUpdatedAt());
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=CurrencyConverterTest
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Write the service**

Create `backend/app/Services/CurrencyConverter.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\MissingRateException;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class CurrencyConverter
{
    private ?array $ratesCache = null;
    private ?Carbon $ratesUpdatedAtCache = null;
    private bool $ratesUpdatedAtLoaded = false;

    public function convert(float $amount, string $from, ?string $to = null): float
    {
        $to ??= $this->baseCurrency();

        if ($from === $to) {
            return $amount;
        }

        $map = $this->ratesMap();
        if (!isset($map[$from])) {
            throw new MissingRateException($from);
        }
        if (!isset($map[$to])) {
            throw new MissingRateException($to);
        }

        // Convert: amount in $from → base → $to
        $amountInBase = $amount * $map[$from];
        return $amountInBase / $map[$to];
    }

    public function ratesMap(): array
    {
        if ($this->ratesCache !== null) {
            return $this->ratesCache;
        }

        $map = [$this->baseCurrency() => 1.0];
        foreach (CurrencyRate::all() as $row) {
            $map[$row->currency] = (float) $row->rate_to_base;
        }

        return $this->ratesCache = $map;
    }

    public function ratesUpdatedAt(): ?Carbon
    {
        if ($this->ratesUpdatedAtLoaded) {
            return $this->ratesUpdatedAtCache;
        }
        $this->ratesUpdatedAtLoaded = true;

        $max = CurrencyRate::max('updated_at');
        return $this->ratesUpdatedAtCache = $max ? Carbon::parse($max) : null;
    }

    public function knownCurrencies(): array
    {
        return array_keys($this->ratesMap());
    }

    private function baseCurrency(): string
    {
        return config('billing.base_currency', 'IDR');
    }
}
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=CurrencyConverterTest
```

Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
cd backend && git add app/Services/CurrencyConverter.php tests/Unit/Services/CurrencyConverterTest.php
git commit -m "feat(rates): add CurrencyConverter service"
```

---

### Task 2.6: Dashboard payload — structured `total_receivables`

**Files:**
- Modify: `backend/app/Services/DashboardService.php`
- Create: `backend/tests/Feature/DashboardReceivablesTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $currency, float $total, InvoiceStatus $status): Invoice
    {
        $customer = Customer::factory()->create(['currency' => $currency]);
        return Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'currency' => $currency,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'tax_rate' => 0,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'status' => $status,
            'type' => InvoiceType::Manual,
        ]);
    }

    public function test_receivables_payload_is_structured(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $this->invoice('IDR', 5_000_000, InvoiceStatus::Sent);
        $this->invoice('USD', 1_000, InvoiceStatus::Overdue);
        $this->invoice('IDR', 100_000, InvoiceStatus::Paid);  // excluded — paid

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertSame('IDR', $tr['base_currency']);
        $this->assertEqualsWithDelta(5_000_000 + (1_000 * 16000), $tr['base_total'], 0.001);
        $this->assertCount(2, $tr['breakdown']);
        $this->assertSame([], $tr['missing_rates']);
    }

    public function test_missing_rate_is_reported_and_excluded_from_base_total(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $this->invoice('USD', 1_000, InvoiceStatus::Sent);
        $this->invoice('JPY', 500_000, InvoiceStatus::Sent);  // no rate

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertEqualsWithDelta(1_000 * 16000, $tr['base_total'], 0.001);
        $this->assertSame(['JPY'], $tr['missing_rates']);
        $jpyRow = collect($tr['breakdown'])->firstWhere('currency', 'JPY');
        $this->assertNull($jpyRow['base_equivalent']);
        $this->assertSame(500000.0, $jpyRow['amount']);
    }

    public function test_empty_when_no_unpaid_invoices(): void
    {
        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertSame(0.0, $tr['base_total']);
        $this->assertSame([], $tr['breakdown']);
        $this->assertSame([], $tr['missing_rates']);
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=DashboardReceivablesTest
```

Expected: FAIL — payload is a float, not a structured array.

- [ ] **Step 3: Refactor `DashboardService`**

In `backend/app/Services/DashboardService.php`:

Add to imports at the top:

```php
use App\Enums\CustomerStatus;
use App\Exceptions\MissingRateException;
use App\Services\CurrencyConverter;
```

Change the constructor (add one — the class currently has none):

```php
public function __construct(
    protected CurrencyConverter $converter
) {
}
```

Change the return type of `getTotalReceivables`:

```php
public function getTotalReceivables(): array
{
    $byCurrency = Invoice::unpaid()
        ->selectRaw('currency, SUM(total) as amount')
        ->groupBy('currency')
        ->get();

    $base = config('billing.base_currency', 'IDR');
    $breakdown = [];
    $missing = [];
    $baseTotal = 0.0;

    foreach ($byCurrency as $row) {
        $amount = (float) $row->amount;
        $entry = [
            'currency' => $row->currency,
            'amount' => $amount,
            'base_equivalent' => null,
        ];
        try {
            $entry['base_equivalent'] = $this->converter->convert($amount, $row->currency, $base);
            $baseTotal += $entry['base_equivalent'];
        } catch (MissingRateException) {
            $missing[] = $row->currency;
        }
        $breakdown[] = $entry;
    }

    return [
        'base_currency' => $base,
        'base_total' => $baseTotal,
        'breakdown' => $breakdown,
        'rates_updated_at' => $this->converter->ratesUpdatedAt()?->toIso8601String(),
        'missing_rates' => $missing,
    ];
}
```

- [ ] **Step 4: Run and verify pass**

```bash
cd backend && php artisan test --filter=DashboardReceivablesTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Run the full suite to catch regressions**

```bash
cd backend && php artisan test
```

Expected: ALL PASS. The earlier `DashboardRecurringHealthTest` should still pass.

- [ ] **Step 6: Commit**

```bash
cd backend && git add app/Services/DashboardService.php tests/Feature/DashboardReceivablesTest.php
git commit -m "feat(dashboard): structured total_receivables with FX breakdown"
```

---

### Task 2.7: Currency rates endpoints

**Files:**
- Create: `backend/app/Http/Requests/CurrencyRate/UpsertRateRequest.php`
- Create: `backend/app/Http/Resources/CurrencyRateResource.php`
- Create: `backend/app/Http/Controllers/CurrencyRateController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Http/Controllers/CurrencyRateControllerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_rates_and_base_currency(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        CurrencyRate::create(['currency' => 'JPY', 'rate_to_base' => 110]);

        $res = $this->getJson('/api/v1/currency-rates')->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertSame('IDR', $res->json('base_currency'));
    }

    public function test_upsert_creates_new_rate(): void
    {
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 16250])->assertOk();

        $this->assertDatabaseHas('currency_rates', ['currency' => 'USD', 'rate_to_base' => '16250.0000000000']);
    }

    public function test_upsert_updates_existing_rate(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);

        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 16250])->assertOk();

        $this->assertSame('16250.0000000000', (string) CurrencyRate::find('USD')->rate_to_base);
    }

    public function test_upsert_rejects_base_currency(): void
    {
        $this->putJson('/api/v1/currency-rates/IDR', ['rate_to_base' => 1])
            ->assertUnprocessable();
    }

    public function test_upsert_rejects_invalid_code(): void
    {
        $this->putJson('/api/v1/currency-rates/us', ['rate_to_base' => 16000])
            ->assertUnprocessable();
    }

    public function test_upsert_rejects_zero_or_negative_rate(): void
    {
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 0])
            ->assertUnprocessable();
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => -1])
            ->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Run and verify failure**

```bash
cd backend && php artisan test --filter=CurrencyRateControllerTest
```

Expected: FAIL — route not defined.

- [ ] **Step 3: Create the FormRequest**

`backend/app/Http/Requests/CurrencyRate/UpsertRateRequest.php`:

```php
<?php

namespace App\Http\Requests\CurrencyRate;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_to_base' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
```

- [ ] **Step 4: Create the Resource**

`backend/app/Http/Resources/CurrencyRateResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,
            'rate_to_base' => (float) $this->rate_to_base,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Create the Controller**

`backend/app/Http/Controllers/CurrencyRateController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRate\UpsertRateRequest;
use App\Http\Resources\CurrencyRateResource;
use App\Models\CurrencyRate;
use Illuminate\Http\JsonResponse;

class CurrencyRateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyRateResource::collection(CurrencyRate::orderBy('currency')->get()),
            'base_currency' => config('billing.base_currency', 'IDR'),
        ]);
    }

    public function upsert(UpsertRateRequest $request, string $currency): JsonResponse
    {
        $code = strtoupper($currency);
        $base = config('billing.base_currency', 'IDR');

        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return response()->json(['message' => 'Invalid currency code'], 422);
        }
        if ($code === $base) {
            return response()->json(['message' => 'Cannot set a rate for the base currency'], 422);
        }

        $rate = CurrencyRate::updateOrCreate(
            ['currency' => $code],
            ['rate_to_base' => $request->validated()['rate_to_base']]
        );

        return response()->json(['data' => new CurrencyRateResource($rate->fresh())]);
    }
}
```

- [ ] **Step 6: Wire the routes**

In `backend/routes/api.php`, add to the `v1` group:

```php
    Route::get('/currency-rates', [\App\Http\Controllers\CurrencyRateController::class, 'index']);
    Route::put('/currency-rates/{currency}', [\App\Http\Controllers\CurrencyRateController::class, 'upsert']);
```

- [ ] **Step 7: Run and verify pass**

```bash
cd backend && php artisan test --filter=CurrencyRateControllerTest
```

Expected: PASS (6 tests).

- [ ] **Step 8: Commit**

```bash
cd backend && git add app/Http/Requests/CurrencyRate app/Http/Resources/CurrencyRateResource.php app/Http/Controllers/CurrencyRateController.php routes/api.php tests/Feature/Http/Controllers/CurrencyRateControllerTest.php
git commit -m "feat(rates): currency-rates endpoints (index + upsert)"
```

---

### Task 2.8: Frontend — remove `formatCurrency` IDR default and fix callers

**Files:**
- Modify: `frontend/lib/utils/formatters.ts`
- Modify: callers that break (enumerated)

- [ ] **Step 1: Make `currency` required in formatters**

In `frontend/lib/utils/formatters.ts`, change the signature:

```ts
export function formatCurrency(amount: number, currency: string, locale?: string): string {
    const finalLocale = locale || getLocaleForCurrency(currency);
    return new Intl.NumberFormat(finalLocale, {
        style: 'currency',
        currency,
        minimumFractionDigits: currency === 'JPY' ? 0 : 2,
    }).format(amount);
}
```

Only the signature line changes (removing `= 'IDR'` default).

- [ ] **Step 2: Run build to discover callers**

```bash
cd frontend && npm run build
```

Expected: FAIL with TS errors listing every call site that doesn't pass a currency.

- [ ] **Step 3: Fix each broken caller**

Enumerated caller fixes (verified against the current code as of this plan):

**`frontend/components/dashboard/Dashboard.tsx`** — the card for "Total Receivables" will be replaced in Task 2.10. For now, temporarily pass an explicit currency to keep the build green:

```tsx
value={formatCurrency(summary?.total_receivables || 0, 'IDR')}
```

And for the "Due This Month" subValue:

```tsx
subValue={formatCurrency(summary?.invoices_due_this_month?.amount || 0, 'IDR')}
```

Both uses here are IDR-default today; Task 2.10 replaces the first with a multi-currency card, but the Due-This-Month amount remains a mixed-currency aggregate. That's tracked as a known limitation in the spec (same root cause as Total Receivables).

**Any other caller** surfaced by the build — pass the row's `invoice.currency` or `customer.currency`. To enumerate callers before running the build, you can also:

```bash
cd frontend && rg -n "formatCurrency\(" --type=tsx --type=ts
```

Every call site must now pass an explicit currency string.

- [ ] **Step 4: Re-run build**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
cd frontend && git add lib/utils/formatters.ts components/dashboard/Dashboard.tsx <any-other-files-you-edited>
git commit -m "feat(format): require currency in formatCurrency"
```

---

### Task 2.9: Frontend types + hook for receivables

**Files:**
- Modify: `frontend/types/api.ts`

- [ ] **Step 1: Add `ReceivablesSummary` type and update `DashboardSummary`**

In `frontend/types/api.ts`, add above `DashboardSummary`:

```ts
export interface ReceivablesBreakdownEntry {
    currency: string;
    amount: number;
    base_equivalent: number | null;
}

export interface ReceivablesSummary {
    base_currency: string;
    base_total: number;
    breakdown: ReceivablesBreakdownEntry[];
    rates_updated_at: string | null;
    missing_rates: string[];
}
```

Change the `DashboardSummary` field:

```ts
    total_receivables: ReceivablesSummary;
```

- [ ] **Step 2: Build**

```bash
cd frontend && npm run build
```

Expected: build now fails where `summary.total_receivables` is used as a number (at minimum in `Dashboard.tsx`). That gets fixed in Task 2.10.

- [ ] **Step 3: Commit**

```bash
cd frontend && git add types/api.ts
git commit -m "feat(types): ReceivablesSummary shape"
```

---

### Task 2.10: Frontend — Total Receivables card (variant A) + MissingRatesWarning

**Files:**
- Create: `frontend/components/dashboard/TotalReceivablesCard.tsx`
- Create: `frontend/components/dashboard/MissingRatesWarning.tsx`
- Modify: `frontend/components/dashboard/Dashboard.tsx`

- [ ] **Step 1: Create `TotalReceivablesCard`**

`frontend/components/dashboard/TotalReceivablesCard.tsx`:

```tsx
'use client';

import { ReceivablesSummary } from '@/types';
import { formatCurrency, formatRelativeTime } from '@/lib/utils';

interface TotalReceivablesCardProps {
    data: ReceivablesSummary;
}

export function TotalReceivablesCard({ data }: TotalReceivablesCardProps) {
    const hasBreakdown = data.breakdown.length > 0;
    const isSingleCurrencyBase =
        data.breakdown.length === 1 && data.breakdown[0].currency === data.base_currency;

    const headline = isSingleCurrencyBase
        ? formatCurrency(data.base_total, data.base_currency)
        : `≈ ${formatCurrency(data.base_total, data.base_currency)}`;

    return (
        <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
            <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                Total Receivables
            </div>
            <div className="mt-1 text-2xl font-bold text-foreground">{headline}</div>
            {!isSingleCurrencyBase && (
                <div className="text-sm text-muted-foreground">
                    {data.base_currency} equivalent
                    {data.rates_updated_at && (
                        <>
                            {' · rates updated '}
                            {formatRelativeTime(data.rates_updated_at)}
                        </>
                    )}
                </div>
            )}
            {hasBreakdown && !isSingleCurrencyBase && (
                <>
                    <div className="my-3 h-px bg-border" />
                    <div className="grid grid-cols-[auto_1fr_auto] gap-x-3 gap-y-1 text-sm text-foreground">
                        {data.breakdown.map((row) => (
                            <BreakdownRow key={row.currency} row={row} base={data.base_currency} />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function BreakdownRow({
    row,
    base,
}: {
    row: ReceivablesSummary['breakdown'][number];
    base: string;
}) {
    const isBase = row.currency === base;
    return (
        <>
            <span>{row.currency}</span>
            <span className="text-right">{formatCurrency(row.amount, row.currency)}</span>
            <span className="text-muted-foreground">
                {isBase
                    ? '(native)'
                    : row.base_equivalent === null
                    ? '— no rate'
                    : `≈ ${formatCurrency(row.base_equivalent, base)}`}
            </span>
        </>
    );
}
```

- [ ] **Step 2: Create `MissingRatesWarning`**

`frontend/components/dashboard/MissingRatesWarning.tsx`:

```tsx
import Link from 'next/link';
import { AlertTriangle } from 'lucide-react';

interface MissingRatesWarningProps {
    currencies: string[];
}

export function MissingRatesWarning({ currencies }: MissingRatesWarningProps) {
    if (currencies.length === 0) return null;

    return (
        <div className="flex items-start gap-3 rounded-lg border border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
                No exchange rate set for{' '}
                <strong>{currencies.join(', ')}</strong>. Receivables in
                {currencies.length > 1 ? ' those currencies' : ' this currency'} are excluded from the base-currency total.
            </div>
            <Link href="/settings/exchange-rates" className="font-semibold hover:underline">
                Set rates →
            </Link>
        </div>
    );
}
```

- [ ] **Step 3: Wire into `Dashboard.tsx`**

Replace the existing Total Receivables `SummaryCard` with the new card, and render the warning above the grid. Full replacement of the relevant region in `frontend/components/dashboard/Dashboard.tsx`:

```tsx
import { TotalReceivablesCard } from './TotalReceivablesCard';
import { MissingRatesWarning } from './MissingRatesWarning';

// ... inside the render, replace the grid + any total_receivables lines:

            {summary?.total_receivables && summary.total_receivables.missing_rates.length > 0 && (
                <MissingRatesWarning currencies={summary.total_receivables.missing_rates} />
            )}

            {summary?.recurring_invoices?.cron?.is_silent && (
                <CronSilentBanner lastRunAt={summary.recurring_invoices.cron.last_run_at} />
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {summary?.total_receivables && (
                    <TotalReceivablesCard data={summary.total_receivables} />
                )}

                <SummaryCard
                    title="Active Customers"
                    value={summary?.total_customers || 0}
                    icon="👥"
                    variant="default"
                    href="/customers"
                />

                <SummaryCard
                    title="Due This Month"
                    value={`${summary?.invoices_due_this_month?.count || 0} invoices`}
                    subValue={formatCurrency(summary?.invoices_due_this_month?.amount || 0, summary?.total_receivables?.base_currency || 'IDR')}
                    icon="📅"
                    variant="default"
                    href="/invoices?due=this_month"
                />
            </div>
```

Remove the `formatCurrency(summary?.total_receivables || 0, 'IDR')` line that no longer compiles (since the field is now an object).

- [ ] **Step 4: Build**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors.

- [ ] **Step 5: Browser smoke**

- Seed: `cd backend && php artisan tinker --execute="\App\Models\CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);"`.
- Create a customer with currency `USD`, then create + send an invoice with total `1000`.
- Reload the dashboard. The card should read `≈ Rp 16,000,000` with a breakdown row `USD · $1,000.00 · ≈ Rp 16,000,000`.
- Create a customer with `JPY` and an unpaid invoice of `500,000` without seeding the JPY rate. The yellow `MissingRatesWarning` banner should appear above the card, and the JPY row in the breakdown should read `JPY · ¥500,000 · — no rate`.

- [ ] **Step 6: Commit**

```bash
cd frontend && git add components/dashboard/TotalReceivablesCard.tsx components/dashboard/MissingRatesWarning.tsx components/dashboard/Dashboard.tsx
git commit -m "feat(dashboard): multi-currency receivables card and missing-rate warning"
```

---

### Task 2.11: Frontend — API layer + TanStack hook for currency rates

**Files:**
- Create: `frontend/lib/api/currency-rates.ts`
- Create: `frontend/lib/hooks/useCurrencyRates.ts`
- Modify: `frontend/lib/api/index.ts`
- Modify: `frontend/lib/hooks/index.ts`

- [ ] **Step 1: API module**

`frontend/lib/api/currency-rates.ts`:

```ts
import { apiClient } from './client';

export interface CurrencyRate {
    currency: string;
    rate_to_base: number;
    updated_at: string | null;
}

interface ListResponse {
    data: CurrencyRate[];
    base_currency: string;
}

interface ItemResponse {
    data: CurrencyRate;
}

export async function list(): Promise<ListResponse> {
    return apiClient.get<ListResponse>('/currency-rates');
}

export async function upsert(currency: string, rate_to_base: number): Promise<ItemResponse> {
    return apiClient.put<ItemResponse>(`/currency-rates/${currency}`, { rate_to_base });
}
```

- [ ] **Step 2: Hook**

`frontend/lib/hooks/useCurrencyRates.ts`:

```ts
'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as api from '@/lib/api/currency-rates';

export function useCurrencyRates() {
    return useQuery({
        queryKey: ['currency-rates'],
        queryFn: () => api.list(),
    });
}

export function useUpsertCurrencyRate() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: ({ currency, rate_to_base }: { currency: string; rate_to_base: number }) =>
            api.upsert(currency, rate_to_base),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['currency-rates'] });
            qc.invalidateQueries({ queryKey: ['dashboard'] });
        },
    });
}
```

- [ ] **Step 3: Re-export**

Append to `frontend/lib/api/index.ts`:

```ts
export * as currencyRatesApi from './currency-rates';
```

Append to `frontend/lib/hooks/index.ts`:

```ts
export * from './useCurrencyRates';
```

- [ ] **Step 4: Build**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
cd frontend && git add lib/api/currency-rates.ts lib/api/index.ts lib/hooks/useCurrencyRates.ts lib/hooks/index.ts
git commit -m "feat(rates): frontend API + hooks for currency rates"
```

---

### Task 2.12: Frontend — admin settings page

**Files:**
- Create: `frontend/app/settings/exchange-rates/page.tsx`
- Modify: `frontend/components/Layout.tsx`

- [ ] **Step 1: Create the settings page**

`frontend/app/settings/exchange-rates/page.tsx`:

```tsx
'use client';

import { useState } from 'react';
import { useCurrencyRates, useUpsertCurrencyRate } from '@/lib/hooks';
import { Button, ErrorState, Input, LoadingState } from '@/components/ui';
import { formatRelativeTime } from '@/lib/utils';

const CURRENCY_NAMES: Record<string, string> = {
    USD: 'US Dollar',
    JPY: 'Japanese Yen',
    SGD: 'Singapore Dollar',
    AUD: 'Australian Dollar',
    EUR: 'Euro',
    GBP: 'British Pound',
};

export default function ExchangeRatesPage() {
    const { data, isLoading, error } = useCurrencyRates();
    const upsert = useUpsertCurrencyRate();
    const [drafts, setDrafts] = useState<Record<string, string>>({});
    const [newCurrency, setNewCurrency] = useState('');
    const [newRate, setNewRate] = useState('');

    if (isLoading) return <LoadingState message="Loading exchange rates..." />;
    if (error) return <ErrorState title="Error loading rates" />;

    const rates = data?.data ?? [];
    const base = data?.base_currency ?? 'IDR';

    const saveRow = async (currency: string) => {
        const raw = drafts[currency];
        if (raw === undefined) return;
        const value = Number(raw);
        if (!Number.isFinite(value) || value <= 0) return;
        await upsert.mutateAsync({ currency, rate_to_base: value });
        setDrafts((d) => {
            const { [currency]: _, ...rest } = d;
            return rest;
        });
    };

    const addNew = async () => {
        const code = newCurrency.trim().toUpperCase();
        const value = Number(newRate);
        if (!/^[A-Z]{3}$/.test(code)) return;
        if (!Number.isFinite(value) || value <= 0) return;
        await upsert.mutateAsync({ currency: code, rate_to_base: value });
        setNewCurrency('');
        setNewRate('');
    };

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-3xl font-bold">Exchange Rates</h1>
                <p className="text-muted-foreground mt-1">
                    Base currency: <strong>{base}</strong>. All invoice totals are converted to {base} on the dashboard using these rates.
                </p>
            </header>

            <div className="rounded-lg border border-border bg-card overflow-hidden">
                <table className="w-full">
                    <thead className="bg-muted/50 text-left text-sm">
                        <tr>
                            <th className="px-4 py-3 font-medium text-muted-foreground">Currency</th>
                            <th className="px-4 py-3 font-medium text-muted-foreground text-right">1 unit =</th>
                            <th className="px-4 py-3 font-medium text-muted-foreground">Updated</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {rates.map((rate) => {
                            const draft = drafts[rate.currency];
                            const isDirty = draft !== undefined && draft !== String(rate.rate_to_base);
                            return (
                                <tr key={rate.currency}>
                                    <td className="px-4 py-3">
                                        <strong>{rate.currency}</strong>
                                        {CURRENCY_NAMES[rate.currency] && (
                                            <span className="text-muted-foreground"> · {CURRENCY_NAMES[rate.currency]}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            value={draft ?? String(rate.rate_to_base)}
                                            onChange={(e) =>
                                                setDrafts((d) => ({ ...d, [rate.currency]: e.target.value }))
                                            }
                                            className="w-40 text-right inline-block"
                                        />
                                        <span className="ml-2 text-muted-foreground">{base}</span>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground text-sm">
                                        {rate.updated_at ? formatRelativeTime(rate.updated_at) : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Button
                                            size="sm"
                                            disabled={!isDirty || upsert.isPending}
                                            onClick={() => saveRow(rate.currency)}
                                        >
                                            Save
                                        </Button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="rounded-lg border border-border bg-card p-4 flex items-end gap-3">
                <div className="flex-1 grid grid-cols-2 gap-3">
                    <Input
                        label="Currency code"
                        placeholder="USD"
                        value={newCurrency}
                        onChange={(e) => setNewCurrency(e.target.value)}
                    />
                    <Input
                        label={`Rate to ${base}`}
                        type="number"
                        placeholder="16250"
                        step="0.0001"
                        value={newRate}
                        onChange={(e) => setNewRate(e.target.value)}
                    />
                </div>
                <Button onClick={addNew} disabled={!newCurrency || !newRate || upsert.isPending}>
                    Add currency
                </Button>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Add nav link**

In `frontend/components/Layout.tsx`, import `Settings` icon and extend `navItems`:

```tsx
import { LayoutDashboard, Users, FileText, Menu, X, Settings } from 'lucide-react';

const navItems = [
    { href: '/', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/customers', label: 'Customers', icon: Users },
    { href: '/invoices', label: 'Invoices', icon: FileText },
    { href: '/settings/exchange-rates', label: 'Exchange Rates', icon: Settings },
];
```

- [ ] **Step 3: Build + smoke**

```bash
cd frontend && npm run build
```

Expected: no TypeScript errors; the new route `/settings/exchange-rates` appears in the build output route list.

Browser smoke:
- Navigate to `/settings/exchange-rates`. The USD rate from the previous smoke should appear.
- Edit USD to `16500`, click Save. The "Updated" column should change. Dashboard reflects the new conversion after invalidation.
- Add `JPY` with rate `110`. The `MissingRatesWarning` on the dashboard disappears for JPY.

- [ ] **Step 4: Commit**

```bash
cd frontend && git add app/settings components/Layout.tsx
git commit -m "feat(rates): admin settings page for exchange rates"
```

---

## Post-Implementation Checks

Once all tasks are merged on the branch:

- [ ] Full backend test suite: `cd backend && php artisan test` — all green.
- [ ] Full frontend build: `cd frontend && npm run build` — no TypeScript errors.
- [ ] Manual end-to-end walkthrough of both features:
  - **Recurring health**: clear cache key → banner appears; run cron → banner disappears; create an overdue schedule → row badge + widget counter both reflect it.
  - **Multi-currency receivables**: with at least one invoice per currency IDR/USD/JPY (one currency without a rate), verify headline equals the sum of convertible currencies, breakdown shows all three rows, missing-rate warning surfaces the uncovered currency.

## Decisions flagged for follow-up (out of scope)

- Policies / authorization for the rates endpoints. Today every `FormRequest::authorize()` returns `true`. Tracked separately — no auth model exists yet.
- Per-row "Skipped by cron" badge using `last_attempted_at < next_invoice_date`. Data is captured but not surfaced.
- Auto-pulled FX rates via external API + per-invoice locked rates. Kept manual this round.
- `Due This Month` card still aggregates a mixed-currency sum into IDR naively (same bug as old Total Receivables). Fix left intentionally for a follow-up that generalises the pattern once the per-currency breakdown shipping here proves out.
