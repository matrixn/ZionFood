<?php

namespace App\Domain\Auth;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class RegistrationPhone
{
    public function countries(): array
    {
        return PhoneNumberUtil::getInstance()->getSupportedRegions();
    }

    public function normalize(mixed $country, mixed $input): ?string
    {
        if (! is_string($country) || ! is_string($input) || strlen($input) > 40 || ! in_array($country, $this->countries(), true)) {
            return null;
        }
        $input = trim($input);
        // Phone parsing is otherwise deliberately lenient (for example, alphabetic vanity numbers).
        if (! preg_match('/^\+?[0-9 ().-]+$/D', $input)) {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($input, $country);
            if ($number->hasExtension() || ! $util->isValidNumberForRegion($number, $country)) {
                return null;
            }

            return $util->format($number, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return null;
        }
    }
}
