<?php

namespace App\Http\Requests\Concerns;

use App\Services\Region\RegionService;

trait ValidatesRegionSelection
{
    protected function validateRegionHierarchy($validator): void
    {
        $validator->after(function ($validator) {
            $province = (string) $this->input('province_code', '');
            $regency = (string) $this->input('regency_code', '');
            $district = (string) $this->input('district_code', '');
            $village = (string) $this->input('village_code', '');

            if ($province === '' && $regency === '' && $district === '' && $village === '') {
                return;
            }

            foreach ([
                'province_code' => $province,
                'regency_code' => $regency,
                'district_code' => $district,
                'village_code' => $village,
            ] as $field => $code) {
                if ($code === '') {
                    $validator->errors()->add($field, 'Wilayah lengkap wajib dipilih.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $regions = app(RegionService::class);

                if (! collect($regions->provinces())->contains(fn ($item) => (string) $item['id'] === $province)) {
                    $validator->errors()->add('province_code', 'Provinsi tidak valid.');

                    return;
                }

                if (! $regions->hasChild($province, $regency)) {
                    $validator->errors()->add('regency_code', 'Kabupaten/Kota tidak sesuai dengan Provinsi.');

                    return;
                }

                if (! $regions->hasChild($regency, $district)) {
                    $validator->errors()->add('district_code', 'Kecamatan tidak sesuai dengan Kabupaten/Kota.');

                    return;
                }

                if (! $regions->hasChild($district, $village)) {
                    $validator->errors()->add('village_code', 'Desa/Kelurahan tidak sesuai dengan Kecamatan.');

                    return;
                }

                foreach ([
                    'province_name' => collect($regions->provinces())->firstWhere('id', $province)['name'] ?? null,
                    'regency_name' => $regions->childName($province, $regency),
                    'district_name' => $regions->childName($regency, $district),
                    'village_name' => $regions->childName($district, $village),
                ] as $field => $expected) {
                    if ($expected !== null && (string) $this->input($field) !== (string) $expected) {
                        $validator->errors()->add($field, 'Nama wilayah tidak sesuai dengan kode wilayah.');
                    }
                }
            } catch (\Throwable) {
                $validator->errors()->add('province_code', 'Data wilayah tidak dapat diverifikasi. Coba lagi.');
            }
        });
    }
}

