@once
    <style>
        .fp-protection-shell {
            width: 100%;
            display: grid;
            gap: 1rem;
        }

        .fp-protection-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            align-items: start;
        }

        /* Keep side-by-side Filament v5 widget cells the same height. */
        .fi-page .fi-grid-col > .fi-sc-component {
            height: 100%;
        }

        .fi-page .fi-grid-col > .fi-sc-component > .fi-wi-widget {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .fi-page .fi-grid-col > .fi-sc-component > .fi-wi-widget > .fi-section {
            height: 100%;
        }

        /* Chart widgets with fixed maxHeight should render equal canvas height. */
        .fi-page .fi-wi-chart .fi-wi-chart-canvas-ctn.fi-wi-chart-canvas-ctn-no-aspect-ratio > canvas {
            height: 320px !important;
            max-height: 320px !important;
        }

        @media (max-width: 1024px) {
            .fp-protection-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce
