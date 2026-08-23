<?php

namespace App\Console\Commands;

use App\Shared\Services\AbuseGuard;
use Illuminate\Console\Command;

class UnbanIpCommand extends Command
{
    protected $signature = 'security:unban-ip {ip : The IP address to unban}';

    protected $description = 'Remove an IP from the security ban list';

    public function handle(AbuseGuard $abuse): int
    {
        $ip = (string) $this->argument('ip');

        if ($abuse->unban($ip)) {
            $this->info("Unbanned {$ip}");

            return self::SUCCESS;
        }

        $this->warn("No active ban found for {$ip}");

        return self::SUCCESS;
    }
}
