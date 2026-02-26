<x-filament-widgets::widget>
    <x-filament::section heading="Request Map" description="Interactive request distribution by country.">
        <div
            x-data="{
                map: null,
                points: @js($points),
                initMap() {
                    const boot = () => {
                        if (! window.L || ! this.$refs.canvas) {
                            setTimeout(boot, 120);
                            return;
                        }

                        if (this.map) {
                            this.map.remove();
                        }

                        this.map = window.L.map(this.$refs.canvas, {
                            zoomControl: true,
                            scrollWheelZoom: true,
                            minZoom: 2,
                        }).setView([20, 0], 2);

                        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 9,
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(this.map);

                        const bounds = [];

                        this.points.forEach((point) => {
                            const lat = Number(point.lat || 0);
                            const lng = Number(point.lng || 0);

                            if (! Number.isFinite(lat) || ! Number.isFinite(lng) || (lat === 0 && lng === 0)) {
                                return;
                            }

                            const radius = Math.max(5, Math.min(18, Number(point.size || 6)));
                            const marker = window.L.circleMarker([lat, lng], {
                                radius,
                                color: 'rgba(30, 64, 175, 0.95)',
                                weight: 1,
                                fillColor: 'rgba(30, 64, 175, 0.45)',
                                fillOpacity: 0.75,
                            }).addTo(this.map);

                            marker.bindPopup(
                                `<strong>${point.country}</strong><br>` +
                                `Requests: ${Number(point.requests || 0).toLocaleString()}<br>` +
                                `Blocked: ${Number(point.blocked_pct || 0).toFixed(2)}%<br>` +
                                `Suspicious: ${Number(point.suspicious_pct || 0).toFixed(2)}%`
                            );

                            bounds.push([lat, lng]);
                        });

                        if (bounds.length > 0) {
                            this.map.fitBounds(bounds, { padding: [24, 24], maxZoom: 4 });
                        }

                        setTimeout(() => this.map.invalidateSize(), 180);
                    };

                    boot();
                },
            }"
            x-init="initMap()"
            wire:ignore
            class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            style="min-height: 460px; position: relative;"
        >
            <div x-ref="canvas" style="height: 460px; width: 100%; position: relative; z-index: 1;"></div>
        </div>

        @if (empty($points))
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">No telemetry yet. Once traffic flows through protection, hotspots will appear.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
