<?php

class YearAction
{

    public static function CalcYear($year): int
    {
        $now_time = strtotime('-8 month');
        $now_year = intval(date('Y', $now_time));
        return $now_year - $year + 1;
    }
}