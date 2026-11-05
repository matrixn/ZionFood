<?php

namespace App\Domain\Localization;

class UiLocale
{
    public const CODES = ['ro', 'en', 'it', 'es', 'nl'];

    public const ADMIN = ['ro', 'en'];

    public const TAGS = ['ro' => 'ro_RO', 'en' => 'en_GB', 'it' => 'it_IT', 'es' => 'es_ES', 'nl' => 'nl_NL'];

    public static function normalize(mixed $locale, array $allowed = self::CODES): string
    {
        return is_string($locale) && in_array($locale, $allowed, true) ? $locale : 'ro';
    }

    public static function path(string $locale): string
    {
        return $locale === 'ro' ? '/' : '/'.self::normalize($locale);
    }

    public static function alternates(string $base): array
    {
        return array_combine(self::CODES, array_map(fn ($locale) => $base.self::path($locale), self::CODES));
    }
}
