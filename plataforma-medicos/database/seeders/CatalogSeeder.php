<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            'Medicina General' => ['consulta general', 'chequeo médico', 'certificado médico'],
            'Pediatría' => ['pediatra', 'control del niño sano', 'vacunación'],
            'Ginecología y Obstetricia' => ['ginecólogo', 'control prenatal', 'papanicolaou'],
            'Dermatología' => ['dermatólogo', 'acné', 'revisión de lunares'],
            'Otorrinolaringología' => ['otorrino', 'sinusitis', 'audición'],
            'Urología' => ['urólogo', 'próstata', 'cálculos renales'],
            'Ortopedia y Traumatología' => ['ortopedista', 'rodilla', 'columna'],
            'Cardiología' => ['cardiólogo', 'electrocardiograma', 'presión arterial'],
            'Oftalmología' => ['oftalmólogo', 'examen de la vista', 'cataratas'],
            'Odontología' => ['dentista', 'limpieza dental', 'muelas del juicio'],
            'Psicología' => ['psicólogo', 'terapia', 'ansiedad'],
            'Psiquiatría' => ['psiquiatra', 'salud mental', 'depresión'],
            'Nutrición' => ['nutriólogo', 'plan de alimentación', 'control de peso'],
            'Medicina Interna' => ['internista', 'diabetes', 'hipertensión'],
            'Endocrinología' => ['endocrinólogo', 'tiroides', 'diabetes'],
            'Gastroenterología' => ['gastroenterólogo', 'colitis', 'endoscopía'],
            'Neurología' => ['neurólogo', 'migraña', 'epilepsia'],
            'Cirugía General' => ['cirujano', 'vesícula', 'hernia'],
            'Cirugía Plástica' => ['cirujano plástico', 'cirugía reconstructiva', 'valoración'],
            'Fisioterapia' => ['fisioterapeuta', 'rehabilitación', 'lesiones deportivas'],
        ];

        foreach ($specialties as $name => $keywords) {
            Specialty::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'ad_keywords' => $keywords,
            ]);
        }

        $cities = [
            ['Ciudad de México', 'CDMX'], ['Guadalajara', 'Jalisco'], ['Monterrey', 'Nuevo León'],
            ['Puebla', 'Puebla'], ['Querétaro', 'Querétaro'], ['Tijuana', 'Baja California'],
            ['Mexicali', 'Baja California'], ['Hermosillo', 'Sonora'], ['Culiacán', 'Sinaloa'],
            ['Mazatlán', 'Sinaloa'], ['Los Mochis', 'Sinaloa'], ['Chihuahua', 'Chihuahua'],
            ['Ciudad Juárez', 'Chihuahua'], ['León', 'Guanajuato'], ['Mérida', 'Yucatán'],
            ['Cancún', 'Quintana Roo'], ['San Luis Potosí', 'San Luis Potosí'], ['Aguascalientes', 'Aguascalientes'],
            ['Saltillo', 'Coahuila'], ['Torreón', 'Coahuila'], ['Toluca', 'Estado de México'],
            ['Morelia', 'Michoacán'], ['Veracruz', 'Veracruz'], ['Oaxaca', 'Oaxaca'],
        ];

        foreach ($cities as [$name, $state]) {
            City::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'state' => $state]);
        }
    }
}
