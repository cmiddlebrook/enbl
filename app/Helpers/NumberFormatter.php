<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class NumberFormatter
{
    public static function format($number)
    {
        if ($number >= 1000)
        {
            $suffixes = [
                'k' => 1000,
                'm' => 1000000,
                'b' => 1000000000
            ];

            $suffix = '';
            $divisor = 1;

            // Determine the suffix and divisor based on the number's magnitude
            foreach ($suffixes as $key => $value)
            {
                if ($number >= $value)
                {
                    $suffix = $key;
                    $divisor = $value;
                }
            }

            $formattedNumber = $number / $divisor;

            // Determine if we should round or keep one decimal place
            $shouldRound = $formattedNumber >= 10;
            $formattedNumber = $shouldRound
                ? round($formattedNumber)
                : round($formattedNumber, 1);

            // Remove unnecessary trailing zeros and decimal point
            $formattedNumber = rtrim(rtrim($formattedNumber, '0'), '.');

            return $formattedNumber . $suffix;
        }

        // If the number is less than 1000, just format it with no decimals
        return number_format($number);
    }
}
