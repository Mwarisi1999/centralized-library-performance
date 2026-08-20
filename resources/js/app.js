import './dashboard-charts';
import './campus-dashboard-charts';
import './university-dashboard-charts';

const sidebar = document.querySelector('#app-sidebar');
const sidebarOverlay = document.querySelector('#sidebar-overlay');
const sidebarButton = document.querySelector('[data-sidebar-open]');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarOverlay || !sidebarButton) return;

    sidebar.classList.toggle('-translate-x-full', !open);
    sidebarOverlay.classList.toggle('hidden', !open);
    sidebarButton.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
};

sidebarButton?.addEventListener('click', () => setSidebarOpen(true));
document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setSidebarOpen(false));
sidebarOverlay?.addEventListener('click', () => setSidebarOpen(false));

const userMenu = document.querySelector('[data-user-menu]');
const userMenuButton = document.querySelector('[data-user-menu-button]');
const userMenuPanel = document.querySelector('[data-user-menu-panel]');

const setUserMenuOpen = (open) => {
    if (!userMenuButton || !userMenuPanel) return;
    userMenuPanel.classList.toggle('hidden', !open);
    userMenuButton.setAttribute('aria-expanded', String(open));
};

userMenuButton?.addEventListener('click', () => {
    setUserMenuOpen(userMenuButton.getAttribute('aria-expanded') !== 'true');
});

document.addEventListener('click', (event) => {
    if (userMenu && !userMenu.contains(event.target)) setUserMenuOpen(false);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    setSidebarOpen(false);
    setUserMenuOpen(false);
});
