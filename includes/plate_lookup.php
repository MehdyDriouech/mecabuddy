<?php
/**
 * MecaBuddy — Recherche véhicule par plaque (API apiplaqueimmatriculation.com)
 */

/**
 * @param array{api_key?: string} $config
 * @return array{
 *   found: bool,
 *   source: string,
 *   brand: ?string,
 *   model: ?string,
 *   year: ?int,
 *   engine: ?string,
 *   fuel: ?string,
 *   transmission: ?string,
 *   error?: string,
 *   raw_body?: string,
 *   http_code?: int
 * }
 */
function lookupPlate(string $plate, array $config): array
{
    $out = [
        'found' => false,
        'source' => 'plate_api',
        'brand' => null,
        'model' => null,
        'year' => null,
        'engine' => null,
        'fuel' => null,
        'transmission' => null,
    ];

    $apiKey = trim((string) ($config['api_key'] ?? ''));
    if ($apiKey === '') {
        $out['error'] = 'no_api_key';
        return $out;
    }

    if (!function_exists('curl_init')) {
        $out['error'] = 'curl_unavailable';
        return $out;
    }

    $url = 'https://apiplaqueimmatriculation.com/api/?immat=' . rawurlencode($plate);

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $rawBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $out['raw_body'] = is_string($rawBody) ? $rawBody : '';
    $out['http_code'] = $httpCode;

    if ($rawBody === false) {
        $out['error'] = $curlErr !== '' ? $curlErr : 'curl_exec_failed';
        return $out;
    }

    if ($httpCode !== 200) {
        $out['error'] = 'http_' . $httpCode;
        return $out;
    }

    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        $out['error'] = 'invalid_json';
        return $out;
    }

    $src = $data;
    if (isset($data['data']) && is_array($data['data'])) {
        $src = $data['data'];
    }

    $brand = plate_lookup_pick($src, ['marque', 'brand']);
    $model = plate_lookup_pick($src, ['modele', 'model']);
    $yearRaw = plate_lookup_pick($src, ['date1erCir_us', 'date1erCir_US', 'date1erCirUs']);
    $engine = plate_lookup_pick($src, ['sra_commercial', 'sraCommercial']);
    $fuel = plate_lookup_pick($src, ['energieNGC', 'energie_ngc']);
    $transmission = plate_lookup_pick($src, ['boite_vitesse', 'boiteVitesse']);

    $year = plate_lookup_extract_year($yearRaw);

    $out['brand'] = $brand !== null && $brand !== '' ? (string) $brand : null;
    $out['model'] = $model !== null && $model !== '' ? (string) $model : null;
    $out['year'] = $year;
    $out['engine'] = $engine !== null && $engine !== '' ? (string) $engine : null;
    $out['fuel'] = $fuel !== null && $fuel !== '' ? (string) $fuel : null;
    $out['transmission'] = $transmission !== null && $transmission !== '' ? (string) $transmission : null;

    $out['found'] = $out['brand'] !== null && $out['model'] !== null;

    return $out;
}

/**
 * @param array<string, mixed> $row
 * @param array<int, string> $keys
 */
function plate_lookup_pick(array $row, array $keys): mixed
{
    foreach ($row as $k => $v) {
        foreach ($keys as $want) {
            if (strcasecmp((string) $k, $want) === 0) {
                return $v;
            }
        }
    }
    return null;
}

function plate_lookup_extract_year(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value)) {
        return $value >= 1900 && $value <= 2100 ? $value : null;
    }
    $s = (string) $value;
    if (preg_match('/(\d{4})/', $s, $m)) {
        $y = (int) $m[1];
        if ($y >= 1900 && $y <= 2100) {
            return $y;
        }
    }
    return null;
}
