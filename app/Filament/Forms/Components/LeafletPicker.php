<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LeafletPicker extends Field
{
    protected string $view = 'filament.forms.components.leaflet-picker';

    protected float $defaultLat = 13.7563;
    protected float $defaultLng = 100.5018;
    protected int $defaultZoom = 13;
    protected string $height = '400px';
    protected string $latField = 'latitude';
    protected string $lngField = 'longitude';
    protected string $tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    public function defaultLocation(float $lat, float $lng): static
    {
        $this->defaultLat = $lat;
        $this->defaultLng = $lng;
        return $this;
    }

    public function defaultZoom(int $zoom): static
    {
        $this->defaultZoom = $zoom;
        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function latField(string $field): static
    {
        $this->latField = $field;
        return $this;
    }

    public function lngField(string $field): static
    {
        $this->lngField = $field;
        return $this;
    }

    public function getDefaultLat(): float { return $this->defaultLat; }
    public function getDefaultLng(): float { return $this->defaultLng; }
    public function getDefaultZoom(): int { return $this->defaultZoom; }
    public function getHeight(): string { return $this->height; }
    public function getLatField(): string { return $this->latField; }
    public function getLngField(): string { return $this->lngField; }
    public function getTileUrl(): string { return $this->tileUrl; }
}
