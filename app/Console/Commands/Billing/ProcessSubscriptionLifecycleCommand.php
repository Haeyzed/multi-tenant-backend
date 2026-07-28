<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Services\Central\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class ProcessSubscriptionLifecycleCommand extends Command
{
    protected $signature = 'billing:process-lifecycle';

    protected $description = 'Advance trials, renew Fake periods, settle invoices, and expire/suspend subscriptions';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $result = $lifecycle->process();

        $this->info(sprintf(
            'Trials: %d; renewed: %d; invoices settled: %d; grace: %d; suspended: %d; expired/cancelled: %d; trial notices: %d',
            $result['trials_activated'],
            $result['renewed'],
            $result['invoices_settled'],
            $result['grace_entered'],
            $result['suspended'],
            $result['expired'],
            $result['trials_ending_notified'],
        ));

        return self::SUCCESS;
    }
}
