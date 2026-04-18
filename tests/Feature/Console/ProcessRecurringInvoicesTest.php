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
