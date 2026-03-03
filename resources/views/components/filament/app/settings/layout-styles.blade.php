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

        @media (max-width: 1024px) {
            .fp-protection-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce
