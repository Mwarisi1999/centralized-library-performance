import './dashboard-charts';
import './campus-dashboard-charts';
import './university-dashboard-charts';
import './weekly-activities';

const sidebar = document.querySelector('#app-sidebar');
const sidebarOverlay = document.querySelector('#sidebar-overlay');
const sidebarButton = document.querySelector('[data-sidebar-open]');
const sidebarCollapseButton = document.querySelector('[data-sidebar-collapse]');
const sidebarStorageKey = 'library-performance-sidebar-collapsed';

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

const setSidebarCollapsed = (collapsed) => {
    if (!sidebar || !sidebarCollapseButton) return;

    document.documentElement.dataset.sidebarCollapsed = String(collapsed);
    sidebarCollapseButton.setAttribute('aria-pressed', String(collapsed));
    sidebarCollapseButton.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');

    try {
        localStorage.setItem(sidebarStorageKey, String(collapsed));
    } catch (_) {
        // The sidebar still works when browser storage is unavailable.
    }
};

if (sidebarCollapseButton) {
    setSidebarCollapsed(document.documentElement.dataset.sidebarCollapsed === 'true');
    sidebarCollapseButton.addEventListener('click', () => {
        setSidebarCollapsed(document.documentElement.dataset.sidebarCollapsed !== 'true');
    });
}

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
