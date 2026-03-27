@once
    <style>
        .dark .fi-page .fi-section {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.82));
            border-color: rgba(96, 165, 250, 0.16);
            box-shadow: 0 24px 50px rgba(2, 6, 23, 0.34);
        }

        .dark .fi-page .fi-section-content-ctn,
        .dark .fi-page .fi-section-content,
        .dark .fi-page .fi-section-footer {
            background: transparent;
        }

        .dark .fi-page .fi-section-header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .dark .fi-page .fi-wi-stats-overview .fi-section,
        .dark .fi-page .fi-wi-stats-overview .fi-section-content-ctn,
        .dark .fi-page .fi-wi-stats-overview .fi-section-content,
        .dark .fi-page .fi-wi-stats-overview .fi-section-footer,
        .dark .fi-page .fi-wi-stats-overview .fi-section-header {
            background: transparent;
            box-shadow: none;
        }

        .dark .fi-page .fi-wi-stats-overview .fi-section {
            border-color: transparent;
        }

        .dark .fi-page .fi-section-header-heading {
            color: rgb(248 250 252);
        }

        .dark .fi-page .fi-section-header-description,
        .dark .fi-page .fi-section-content,
        .dark .fi-page .fi-ta-header-cell,
        .dark .fi-page .fi-ta-cell {
            color: rgb(203 213 225);
        }

        .dark .fi-page .fi-ta-ctn,
        .dark .fi-page .fi-ta-content-ctn {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(255, 255, 255, 0.08);
        }

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
