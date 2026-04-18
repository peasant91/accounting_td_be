<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

class ProcessRecurringInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:process-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and generate scheduled recurring invoices';

    /**
     * Execute the console command.
     */
    public function handle(RecurringInvoiceService $service): int
    {
        cache()->forever('recurring_cron.last_run_at', now());

        $this->info('Starting recurring invoice processing...');

        $count = $service->processScheduledInvoices();
        $this->info("Successfully processed {$count} recurring invoices.");

        return Command::SUCCESS;
    }
}
