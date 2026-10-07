<?php

namespace App\Services;

use App\Models\Doctor;

/** Datos estructurados (JSON-LD) para que Google entienda cada perfil como un médico local. */
class SchemaOrg
{
    public function physician(Doctor $doctor): array
    {
        $doctor->loadMissing(['specialty', 'city', 'schedules']);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            'name' => $doctor->displayName(),
            'url' => route('doctors.show', $doctor),
            'medicalSpecialty' => $doctor->specialty->name,
            'description' => $doctor->bio,
            'image' => $doctor->photoUrl(),
            'telephone' => $doctor->phone,
            'priceRange' => $doctor->consultation_price_mxn ? '$'.$doctor->consultation_price_mxn.' MXN' : null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $doctor->address,
                'addressLocality' => $doctor->city->name,
                'addressRegion' => $doctor->city->state,
                'postalCode' => $doctor->postal_code,
                'addressCountry' => 'MX',
            ],
            'openingHoursSpecification' => $doctor->schedules->map(fn ($s) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$s->weekday],
                'opens' => substr($s->start_time, 0, 5),
                'closes' => substr($s->end_time, 0, 5),
            ])->values()->all(),
        ];

        if ($doctor->lat && $doctor->lng) {
            $data['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $doctor->lat, 'longitude' => $doctor->lng];
        }

        // Válido porque somos un directorio de terceros con reseñas de pacientes con cita
        // (Google no muestra estrellas si el propio negocio controla las reseñas).
        if ($doctor->rating_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $doctor->rating_avg,
                'reviewCount' => $doctor->rating_count,
                'bestRating' => 5,
            ];
        }

        return array_filter($data, fn ($v) => $v !== null && $v !== []);
    }
}
