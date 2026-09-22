<?php

declare(strict_types=1);

class SchoolLocationService
{
    /** Load the school reference point and allowed radius without inventing a default location. */
    public static function settings(): ?array
    {
        $settings = json_decode((string) getSetting('school_location', 'attendance', ''), true);
        if (!is_array($settings)) {
            return null;
        }
        try {
            return self::validate($settings);
        } catch (InvalidArgumentException $error) {
            return null;
        }
    }

    /** Validate decimal coordinates and a non-negative allowance in metres. */
    public static function validate(array $input): array
    {
        $values = [];
        foreach (['latitude', 'longitude', 'radius_m'] as $field) {
            $value = $input[$field] ?? null;
            if (!is_scalar($value) || is_bool($value) || !is_numeric($value) || !is_finite((float) $value)) {
                throw new InvalidArgumentException('Enter valid school coordinates and an allowed distance in metres.');
            }
            $values[$field] = (float) $value;
        }
        if (!isValidLatitude((string) $values['latitude']) || !isValidLongitude((string) $values['longitude']) || $values['radius_m'] < 0) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90, longitude between -180 and 180, and allowed distance must be zero or more.');
        }
        return $values;
    }

    /** Great-circle distance using the mean Earth radius and the Haversine formula. */
    public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;
        return 6371008.8 * 2 * asin(sqrt(max(0.0, min(1.0, $a))));
    }

    /** Classify stored coordinates against the current school settings. */
    public static function assess($latitude, $longitude, ?array $school): array
    {
        $unknown = ['class' => 'text-muted', 'distance_m' => null, 'outside' => null, 'label' => 'Location unavailable'];
        if (!is_scalar($latitude) || !is_scalar($longitude)
            || !isValidLatitude((string) $latitude) || !isValidLongitude((string) $longitude)) {
            return $unknown;
        }
        if ($school === null) {
            $unknown['label'] = 'School location not configured';
            return $unknown;
        }
        $distance = self::distance((float) $latitude, (float) $longitude, $school['latitude'], $school['longitude']);
        $outside = $distance > $school['radius_m'];
        return [
            'class' => $outside ? 'text-danger fw-bold' : 'text-success',
            'distance_m' => $distance,
            'outside' => $outside,
            'label' => $outside ? 'Outside Range' : 'Inside Range',
        ];
    }
}
