<?php

namespace App\Support;

class Duration
{
    /**
     * Turn a number of minutes into a short French text: "45 min", "3 h 20", "2 j 5 h".
     */
    public static function humanize(?float $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $minutes = (int) round($minutes);

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return sprintf('%d h %02d', $hours, $minutes % 60);
        }

        return sprintf('%d j %d h', intdiv($hours, 24), $hours % 24);
    }
}
