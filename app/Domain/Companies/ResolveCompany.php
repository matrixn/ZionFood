<?php

namespace App\Domain\Companies;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResolveCompany
{
    private const URL = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

    public function execute(string $input): ?array
    {
        $cui = preg_replace('/^RO\s*/i', '', trim($input));
        if (! preg_match('/^[1-9][0-9]{1,9}$/', $cui)) {
            throw ValidationException::withMessages(['tax_id' => __('Enter a Romanian CUI containing 2 to 10 digits, optionally prefixed with RO.')]);
        }
        $day = now('Europe/Bucharest')->format('Y-m-d');
        $key = 'company:anaf:v1:'.$day.':'.$cui;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached['company'];
        }
        $lock = Cache::lock('company:anaf:lock', 20);
        if (! $lock->get()) {
            $this->busy();
        }
        try {
            if (! Cache::add('company:anaf:cooldown', true, 1)) {
                $this->busy();
            }
            try {
                $response = Http::acceptJson()->withUserAgent('ZionFood/'.config('zionfood.version'))->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false, 'proxy' => '', 'verify' => true, 'stream' => true, 'read_timeout' => 3])->post(self::URL, [['cui' => (int) $cui, 'data' => $day]]);
                if (! $response->successful()) {
                    throw new \RuntimeException('Company provider rejected request.');
                }
                $body = $response->toPsrResponse()->getBody();
                try {
                    $raw = '';
                    $deadline = hrtime(true) + 10_000_000_000;
                    while (! $body->eof()) {
                        if (hrtime(true) > $deadline) {
                            throw new \RuntimeException('Company provider timed out.');
                        }
                        $chunk = $body->read(min(8192, 131073 - strlen($raw)));
                        if ($chunk === '' && ! $body->eof()) {
                            throw new \RuntimeException('Incomplete company response.');
                        }
                        $raw .= $chunk;
                        if (strlen($raw) > 131072) {
                            throw new \RuntimeException('Oversized company response.');
                        }
                    }
                } finally {
                    $body->close();
                }
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($data['found'] ?? null) || ! is_array($data['notFound'] ?? null)) {
                    throw new \RuntimeException('Malformed company response.');
                }
                $row = collect($data['found'])->first(fn ($item) => is_array($item) && (string) data_get($item, 'date_generale.cui') === $cui);
                if (! $row && ! in_array($cui, array_map('strval', $data['notFound']), true)) {
                    throw new \RuntimeException('Mismatched company response.');
                }
                $company = $row ? $this->normalize($row, $cui) : null;
            } catch (\Throwable $e) {
                Log::warning('Company lookup provider unavailable.', ['exception_class' => $e::class]);
                throw ValidationException::withMessages(['tax_id' => __('Company lookup is temporarily unavailable. Try again later or complete the fields manually.')]);
            }
            Cache::put($key, ['company' => $company], $company ? 86400 : 600);

            return $company;
        } finally {
            $lock->release();
        }
    }

    private function text(array $row, string $key, int $limit = 100): string
    {
        $value = data_get($row, $key);

        return is_string($value) || is_numeric($value) ? mb_substr(Str::squish(strip_tags((string) $value)), 0, $limit) : '';
    }

    private function flag(array $row, string $key): ?bool
    {
        $value = data_get($row, $key);

        return is_bool($value) ? $value : null;
    }

    private function normalize(array $row, string $cui): array
    {
        $address = implode(', ', array_filter(array_map(fn ($key) => $this->text($row, 'adresa_sediu_social.'.$key), ['sdenumire_Strada', 'snumar_Strada', 'sdetalii_Adresa', 'sdenumire_Localitate', 'sdenumire_Judet', 'stara'])));

        return [
            'tax_id' => $cui, 'company_name' => $this->text($row, 'date_generale.denumire', 200),
            'company_address' => mb_substr($address ?: $this->text($row, 'date_generale.adresa', 255), 0, 255),
            'registration_number' => $this->text($row, 'date_generale.nrRegCom', 40),
            'company_phone' => $this->text($row, 'date_generale.telefon', 40),
            'company_postal_code' => $address ? $this->text($row, 'adresa_sediu_social.scod_Postal', 20) : $this->text($row, 'date_generale.codPostal', 20),
            'company_city' => $this->text($row, 'adresa_sediu_social.sdenumire_Localitate'),
            'company_county' => $this->text($row, 'adresa_sediu_social.sdenumire_Judet'),
            'company_country' => $this->text($row, 'adresa_sediu_social.stara'),
            'company_caen' => $this->text($row, 'date_generale.cod_CAEN', 20),
            'company_legal_form' => $this->text($row, 'date_generale.forma_juridica'),
            'company_status' => $this->text($row, 'date_generale.stare_inregistrare', 200),
            'company_iban' => $this->text($row, 'date_generale.iban', 40),
            'company_vat' => $this->flag($row, 'inregistrare_scop_Tva.scpTVA'),
            'company_inactive' => $this->flag($row, 'stare_inactiv.statusInactivi'),
            'company_efactura' => $this->flag($row, 'date_generale.statusRO_e_Factura'),
        ];
    }

    private function busy(): never
    {
        throw ValidationException::withMessages(['tax_id' => __('Company lookup is busy. Wait a few seconds and try again.')]);
    }
}
