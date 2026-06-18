<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    // Baca preferensi map dari session, default OSM
    protected function mapType(): string
    {
        return session('map_type', 'osm');
    }

    // Data map yang dibutuhkan semua view
    protected function mapConfig(): array
    {
        return [
            'mapType'       => $this->mapType(),
            'googleMapsKey' => config('services.google_maps.key', ''),
        ];
    }
}