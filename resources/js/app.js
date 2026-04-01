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

const initializeCookieConsent = () => {
    const root = document.querySelector('[data-cookie-consent]');
    const modal = document.querySelector('[data-cookie-consent-modal]');

    if (! root || ! modal) {
        return;
    }

    const consentCookieName = 'firephage_cookie_consent';
    const consentMaxAgeDays = 180;
    const categoryInputs = modal.querySelectorAll('[data-cookie-consent-category]');
    const manageButton = root.querySelector('[data-cookie-consent-manage]');
    const acceptButton = root.querySelector('[data-cookie-consent-accept]');
    const essentialButton = root.querySelector('[data-cookie-consent-essential]');
    const closeButton = modal.querySelector('[data-cookie-consent-close]');
    const saveButton = modal.querySelector('[data-cookie-consent-save]');
    const saveEssentialButton = modal.querySelector('[data-cookie-consent-save-essential]');

    const setVisible = (element, visible, display = 'block') => {
        if (! element) {
            return;
        }

        element.classList.toggle('hidden', ! visible);

        if (visible) {
            element.classList.add(display);
        } else {
            element.classList.remove('block', 'flex');
        }
    };

    const readCookie = (name) => {
        const escaped = name.replace(/[-[\]/{}()*+?.\\^$|]/g, '\\$&');
        const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));

        return match ? decodeURIComponent(match[1]) : null;
    };

    const writeCookie = (name, value, days) => {
        const maxAge = Math.max(1, Math.floor(days * 86400));
        document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax; Secure`;
    };

    const normalizeConsent = (value) => ({
        necessary: true,
        preferences: Boolean(value?.preferences),
        analytics: Boolean(value?.analytics),
        marketing: Boolean(value?.marketing),
        version: 1,
        updated_at: value?.updated_at || new Date().toISOString(),
    });

    const readConsent = () => {
        const raw = readCookie(consentCookieName);

        if (! raw) {
            return null;
        }

        try {
            return normalizeConsent(JSON.parse(raw));
        } catch {
            return null;
        }
    };

    const applyConsentToInputs = (consent) => {
        categoryInputs.forEach((input) => {
            const category = input.dataset.cookieConsentCategory;
            input.checked = Boolean(consent?.[category]);
        });
    };

    const openModal = () => {
        const consent = readConsent();
        applyConsentToInputs(consent);
        setVisible(modal, true, 'flex');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
        setVisible(modal, false);
        modal.setAttribute('aria-hidden', 'true');
    };

    const persistConsent = (value) => {
        const consent = normalizeConsent(value);
        writeCookie(consentCookieName, JSON.stringify(consent), consentMaxAgeDays);
        window.firephageConsent = consent;
        window.dispatchEvent(new CustomEvent('firephage:consent-updated', { detail: consent }));
        setVisible(root, false);
        closeModal();
    };

    const collectModalConsent = () => {
        const next = {
            preferences: false,
            analytics: false,
            marketing: false,
        };

        categoryInputs.forEach((input) => {
            const category = input.dataset.cookieConsentCategory;
            next[category] = input.checked;
        });

        return next;
    };

    const existingConsent = readConsent();
    window.firephageConsent = existingConsent;

    if (! existingConsent) {
        setVisible(root, true);
    }

    manageButton?.addEventListener('click', openModal);
    acceptButton?.addEventListener('click', () => persistConsent({
        preferences: true,
        analytics: true,
        marketing: true,
    }));
    essentialButton?.addEventListener('click', () => persistConsent({
        preferences: false,
        analytics: false,
        marketing: false,
    }));
    closeButton?.addEventListener('click', closeModal);
    saveButton?.addEventListener('click', () => persistConsent(collectModalConsent()));
    saveEssentialButton?.addEventListener('click', () => persistConsent({
        preferences: false,
        analytics: false,
        marketing: false,
    }));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeServicesDropdowns();
        initializeMarketingMobileMenu();
        initializeCookieConsent();
    }, { once: true });
} else {
    initializeServicesDropdowns();
    initializeMarketingMobileMenu();
    initializeCookieConsent();
}
