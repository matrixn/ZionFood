<?php

namespace App\Domain\Marketing;

use DOMDocument;
use DOMXPath;

class PricingMarkup
{
    public function apply(string $markup, array $pricing, string $locale): string
    {
        return preg_replace_callback('/<section\b[^>]*\bid="pricing"[^>]*>.*?<\/section>/s', function ($match) use ($pricing, $locale) {
            $dom = new DOMDocument('1.0', 'UTF-8');
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$match[0], LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
            $xpath = new DOMXPath($dom);
            foreach ($pricing['plans'] as $plan) {
                $card = $xpath->query('//article[@data-plan="'.$plan['key'].'"]')->item(0);
                if (! $card) {
                    continue;
                }
                $title = $xpath->query('.//h3', $card)->item(0);
                $amount = $xpath->query('.//data', $card)->item(0);
                $unit = $xpath->query('.//p[contains(@class,"pricing-amount")]/span', $card)->item(0);
                if (! $title || ! $amount || ! $unit) {
                    continue;
                }
                $title->textContent = $plan['name'];
                $minor = $plan['display_minor'];
                $amount->setAttribute('value', intdiv($minor, 100).($minor % 100 ? '.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) : ''));
                $amount->textContent = number_format($minor / 100, $minor % 100 === 0 ? 0 : 2, $locale === 'en' ? '.' : ',', '');
                if ($pricing['currency'] !== 'RON') {
                    $unit->textContent = $pricing['currency'];
                }
            }
            $selected = $xpath->query('//*[@data-testid="currency-selector"]//*[contains(@class,"p-select-label")]')->item(0);
            if ($selected) {
                $selected->textContent = $pricing['currency'];
            }
            $note = $xpath->query('//p[contains(@class,"pricing-quote-note")]')->item(0);
            if ($note) {
                $strings = json_decode(file_get_contents(resource_path('js/i18n/'.$locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
                $text = $strings['pricing_invoice_note'];
                if ($pricing['rate_date']) {
                    $text .= ' '.str_replace('{date}', $pricing['rate_date'], $strings['pricing_rate_date']);
                }
                if ($pricing['requested_currency'] !== $pricing['currency']) {
                    $text .= ' '.$strings['pricing_rates_missing'];
                }
                if ($pricing['stale']) {
                    $text .= ' '.$strings['pricing_rates_stale'];
                }
                $note->textContent = $text;
            }

            return $dom->saveHTML($xpath->query('//section')->item(0));
        }, $markup);
    }
}
