<?php

namespace App\Http\Controllers;

use App\Models\BirthProfile;
use App\Services\PlaceGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthProfileController extends Controller
{
    public function store(Request $request, PlaceGeocoder $places): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:self,relation'],
            'label' => ['nullable', 'string', 'max:80', 'required_if:role,relation'],
            'birth_at' => ['required', 'date'],
            'birth_country' => ['nullable', 'string', 'max:80'],
            'birth_city' => ['nullable', 'string', 'max:120'],
            'introduction' => ['nullable', 'string', 'max:500'],
        ]);

        $coords = null;
        $country = trim((string) ($data['birth_country'] ?? ''));
        $city = trim((string) ($data['birth_city'] ?? ''));
        if ($country !== '' && $city !== '') {
            $coords = $places->coordinates($country, $city);
        }

        $user = $request->user();
        if ($data['role'] === 'self') {
            $update = [
                'birth_at' => $data['birth_at'],
                'birth_country' => $country !== '' ? $country : null,
                'birth_city' => $city !== '' ? $city : null,
                'birth_latitude' => $coords['latitude'] ?? null,
                'birth_longitude' => $coords['longitude'] ?? null,
            ];
            $introduction = trim((string) ($data['introduction'] ?? ''));
            if ($introduction !== '') {
                $update['introduction'] = mb_substr($introduction, 0, 200);
            }
            $user->update($update);

            return response()->json([
                'role' => 'self',
                'source' => 'self',
            ]);
        }

        $profile = BirthProfile::query()->create([
            'user_id' => $user->id,
            'label' => trim((string) $data['label']),
            'birth_at' => $data['birth_at'],
            'birth_country' => $country !== '' ? $country : null,
            'birth_city' => $city !== '' ? $city : null,
            'birth_latitude' => $coords['latitude'] ?? null,
            'birth_longitude' => $coords['longitude'] ?? null,
            'introduction' => trim((string) ($data['introduction'] ?? '')) ?: null,
        ]);

        return response()->json([
            'role' => 'relation',
            'id' => $profile->id,
            'label' => $profile->label,
        ]);
    }
}
