import './bootstrap';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

window.L = L;

const initializeServicesDropdowns = () => {
    const dropdowns = document.querySelectorAll('[data-services-dropdown]');

    if (! dropdowns.length) {
        return;
    }

    const closeDropdown = (dropdown) => {
        const toggle = dropdown.querySelector('[data-services-dropdown-toggle]');
        const panel = dropdown.querySelector('[data-services-dropdown-panel]');
        const icon = dropdown.querySelector('[data-services-dropdown-icon]');

        panel?.classList.add('hidden');
        toggle?.setAttribute('aria-expanded', 'false');
        icon?.classList.remove('rotate-180');
    };

    const openDropdown = (dropdown) => {
        dropdowns.forEach((item) => {
            if (item !== dropdown) {
                closeDropdown(item);
            }
        });

        const toggle = dropdown.querySelector('[data-services-dropdown-toggle]');
        const panel = dropdown.querySelector('[data-services-dropdown-panel]');
        const icon = dropdown.querySelector('[data-services-dropdown-icon]');

        panel?.classList.remove('hidden');
        toggle?.setAttribute('aria-expanded', 'true');
        icon?.classList.add('rotate-180');
    };

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-services-dropdown-toggle]');
        const panel = dropdown.querySelector('[data-services-dropdown-panel]');

        if (! toggle || ! panel) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (panel.classList.contains('hidden')) {
                openDropdown(dropdown);
            } else {
                closeDropdown(dropdown);
            }
        });

        panel.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => closeDropdown(dropdown));
        });
    });

    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (! dropdown.contains(event.target)) {
                closeDropdown(dropdown);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dropdowns.forEach((dropdown) => closeDropdown(dropdown));
        }
    });
};

const initializeMarketingMobileMenu = () => {
    const button = document.querySelector('[data-marketing-mobile-menu-toggle]');
    const menu = document.querySelector('[data-marketing-mobile-menu]');

    if (! button || ! menu) {
        return;
    }

    const closeMenu = () => {
        menu.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
        menu.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
    };

    button.addEventListener('click', () => {
        if (menu.classList.contains('hidden')) {
            openMenu();
        } else {
            closeMenu();
        }
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeServicesDropdowns();
        initializeMarketingMobileMenu();
    }, { once: true });
} else {
    initializeServicesDropdowns();
    initializeMarketingMobileMenu();
}
