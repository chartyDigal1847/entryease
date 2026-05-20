<?php

namespace App\Console\Commands;

use App\Models\SsoToken;
use Illuminate\Console\Command;

class CleanupExpiredSsoTokens extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sso:cleanup-tokens {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Remove expired SSO tokens from database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete all SSO tokens older than 10 minutes. Continue?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $deleted = SsoToken::cleanupExpired();

        $this->info("Deleted $deleted expired SSO token(s).");

        return 0;
    }
}
