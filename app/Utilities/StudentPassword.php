<?php

namespace App\Utilities;

use DateTimeImmutable;

class StudentPassword
{
    public static function defaultFromBirthDate(?string $birthDate): ?string
    {
        if (!$birthDate) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        if (!$date) {
            return null;
        }

        return $date->format('dmY') . '*';
    }
}
