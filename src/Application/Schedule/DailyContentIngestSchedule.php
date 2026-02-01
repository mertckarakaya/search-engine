<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Application\Message\IngestContentMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('daily_ingest')]
final class DailyContentIngestSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run daily at 02:00 AM
                RecurringMessage::cron('0 2 * * *', new IngestContentMessage())
            );
    }
}
