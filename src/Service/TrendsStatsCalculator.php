<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Service;

class TrendsStatsCalculator
{
    /**
     * @param list<array{date: string, count: int|string, unique: int|string}> $daily
     * @return array{peakDay: ?array{date: string, views: int}, lowDay: ?array{date: string, views: int}, dailyAvgViews: int, dailyAvgVisitors: int, weekdayAvg: int, weekendAvg: int}
     */
    public function compute(array $daily): array
    {
        if ($daily === []) {
            return [
                'peakDay' => null,
                'lowDay' => null,
                'dailyAvgViews' => 0,
                'dailyAvgVisitors' => 0,
                'weekdayAvg' => 0,
                'weekendAvg' => 0,
            ];
        }

        $dates = [];
        $views = [];
        $visitors = [];
        $weekdayViews = [];
        $weekendViews = [];

        foreach ($daily as $row) {
            $date = $row['date'];
            $count = (int) $row['count'];
            /** @infection-ignore-all — array_sum handles string values identically to ints */
            $unique = (int) $row['unique'];

            $dates[] = $date;
            $views[] = $count;
            $visitors[] = $unique;

            /** @infection-ignore-all — format('N') returns '1'..'7'; PHP loose comparison with <= 5 works on strings */
            $dow = (int) (new \DateTimeImmutable($date))->format('N');
            if ($dow <= 5) {
                $weekdayViews[] = $count;
            } else {
                $weekendViews[] = $count;
            }
        }

        $numDays = count($views);
        $totalViews = array_sum($views);
        $totalVisitors = array_sum($visitors);

        $maxIdx = array_search(max($views), $views, true);
        $minIdx = array_search(min($views), $views, true);

        return [
            'peakDay' => ['date' => $dates[$maxIdx], 'views' => $views[$maxIdx]],
            'lowDay' => ['date' => $dates[$minIdx], 'views' => $views[$minIdx]],
            'dailyAvgViews' => (int) round($totalViews / $numDays),
            'dailyAvgVisitors' => (int) round($totalVisitors / $numDays),
            'weekdayAvg' => count($weekdayViews) > 0 ? (int) round(array_sum($weekdayViews) / count($weekdayViews)) : 0,
            'weekendAvg' => count($weekendViews) > 0 ? (int) round(array_sum($weekendViews) / count($weekendViews)) : 0,
        ];
    }
}
