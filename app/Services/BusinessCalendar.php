<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessCalendar
{
    public function waitingHours(CarbonInterface $from, CarbonInterface $to): float
    {
        $start = CarbonImmutable::instance($from)->setTimezone(config('app.timezone'));
        $end = CarbonImmutable::instance($to)->setTimezone(config('app.timezone'));
        if ($end->lte($start)) {
            return 0;
        }
        $partial = static function (CarbonImmutable $day, CarbonImmutable $from, CarbonImmutable $to): float {
            if ($day->isWeekend()) {
                return 0;
            }
            $left = $day->setTime(9, 0)->max($from);
            $right = $day->setTime(17, 0)->min($to);

            return $right->gt($left) ? $left->diffInSeconds($right) / 3600 : 0;
        };
        if ($start->isSameDay($end)) {
            return $partial($start, $start, $end);
        }
        $middle = $start->startOfDay()->addDay();
        $days = (int) $middle->diffInDays($end->startOfDay());
        $weekdays = intdiv($days, 7) * 5;
        for ($i = 0; $i < $days % 7; $i++) {
            $weekdays += $middle->addDays($i)->isWeekday() ? 1 : 0;
        }

        return $weekdays * 8 + $partial($start, $start, $end) + $partial($end, $start, $end);
    }

    public function latestStart(string $deadline, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::parse($deadline)->startOfDay();
        while ($date->isWeekend()) {
            $date = $date->subDay();
        }

        return $date->subWeekdays($days - 1);
    }
}
