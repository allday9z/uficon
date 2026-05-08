<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath   = $getStatePath();
        $defaultLat  = $field->getDefaultLat();
        $defaultLng  = $field->getDefaultLng();
        $defaultZoom = $field->getDefaultZoom();
        $height      = $field->getHeight();
        $latField    = $field->getLatField();
        $lngField    = $field->getLngField();
        $tileUrl     = $field->getTileUrl();
        $mapId       = 'leaflet-map-' . str_replace(['.', '[', ']'], '-', $statePath);

        $parts       = explode('.', $statePath);
        array_pop($parts);
        $prefix      = implode('.', $parts);
        $latWirePath = $prefix ? $prefix . '.' . $latField : $latField;
        $lngWirePath = $prefix ? $prefix . '.' . $lngField : $lngField;
        $alpineKey   = 'lp_' . str_replace('-', '_', $mapId);
    @endphp

    @once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endonce

    <script>
    (function() {
        const KEY = '{{ $alpineKey }}';
        if (window[KEY + '_registered']) return;
        window[KEY + '_registered'] = true;

        document.addEventListener('alpine:init', function () {
            Alpine.data(KEY, function (cfg) {
                return {
                    map: null,
                    marker: null,
                    lat: cfg.defaultLat,
                    lng: cfg.defaultLng,

                    init() {
                        var self = this;
                        var poll = setInterval(function () {
                            if (typeof L === 'undefined') return;
                            clearInterval(poll);
                            var wLat = self.$wire.get(cfg.latPath);
                            var wLng = self.$wire.get(cfg.lngPath);
                            if (wLat) self.lat = parseFloat(wLat);
                            if (wLng) self.lng = parseFloat(wLng);
                            self.$nextTick(function () { self.initMap(); });
                        }, 200);

                        this.$watch(function () { return self.$wire.get(cfg.latPath); }, function (val) {
                            if (val) { self.lat = parseFloat(val); self.moveMarker(self.lat, self.lng); }
                        });
                        this.$watch(function () { return self.$wire.get(cfg.lngPath); }, function (val) {
                            if (val) { self.lng = parseFloat(val); self.moveMarker(self.lat, self.lng); }
                        });
                    },

                    initMap() {
                        var el = document.getElementById(cfg.mapId);
                        if (!el || this.map) return;

                        this.map = L.map(cfg.mapId).setView([this.lat, this.lng], cfg.zoom);

                        L.tileLayer(cfg.tileUrl, {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                            maxZoom: 19,
                        }).addTo(this.map);

                        this.marker = L.marker([this.lat, this.lng], { draggable: true }).addTo(this.map);

                        var self = this;
                        this.marker.on('dragend', function (e) {
                            var pos = e.target.getLatLng();
                            self.updateFields(pos.lat, pos.lng);
                        });
                        this.map.on('click', function (e) {
                            self.marker.setLatLng(e.latlng);
                            self.updateFields(e.latlng.lat, e.latlng.lng);
                        });

                        setTimeout(function () { self.map.invalidateSize(); }, 400);
                    },

                    moveMarker(lat, lng) {
                        if (!this.map || !this.marker) return;
                        var l = parseFloat(lat), g = parseFloat(lng);
                        if (isNaN(l) || isNaN(g)) return;
                        this.marker.setLatLng([l, g]);
                        this.map.panTo([l, g]);
                    },

                    updateFields(lat, lng) {
                        var l = Math.round(lat * 1e8) / 1e8;
                        var g = Math.round(lng * 1e8) / 1e8;
                        this.lat = l;
                        this.lng = g;
                        this.$wire.set(cfg.latPath, l);
                        this.$wire.set(cfg.lngPath, g);
                    },
                };
            });
        });
    })();
    </script>

    <div
        wire:ignore
        x-data="{{ $alpineKey }}({
            mapId: '{{ $mapId }}',
            defaultLat: {{ $defaultLat }},
            defaultLng: {{ $defaultLng }},
            zoom: {{ $defaultZoom }},
            latPath: '{{ $latWirePath }}',
            lngPath: '{{ $lngWirePath }}',
            tileUrl: '{{ addslashes($tileUrl) }}'
        })"
        x-init="init()"
    >
        <div
            id="{{ $mapId }}"
            style="height: {{ $height }}; width: 100%; border-radius: 0.5rem; z-index: 1;"
        ></div>

        <p class="mt-1 text-xs text-gray-400">
            คลิกบนแผนที่หรือลาก marker เพื่อเลือกตำแหน่ง &bull; OpenStreetMap
        </p>
    </div>
</x-dynamic-component>
