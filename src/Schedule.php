<?php

namespace App;

use App\Constants\TimeConstants;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Fetch cryptocurrency rates every 5 minutes
            ->command('app:fetch-rates')->every(TimeConstants::SCHEDULE_EVERY_5_MINUTES)->description('Fetch cryptocurrency rates from Binance API')
        ;
    }
}
