// Off-canvas sidebar, under lg only. The sidebar is always in the DOM;
// below lg it sits off-screen via -translate-x-full and slides in.
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebar-backdrop');
const toggles = document.querySelectorAll('[data-sidebar-toggle]');

function setSidebar(open) {
    if (!sidebar) return;

    sidebar.classList.toggle('-translate-x-full', !open);
    if (backdrop) backdrop.hidden = !open;
    document.body.classList.toggle('overflow-hidden', open);
    toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
}

toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => setSidebar(sidebar.classList.contains('-translate-x-full')));
});

backdrop?.addEventListener('click', () => setSidebar(false));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setSidebar(false);
});

// Events toolbar. The page-size select applies on change — a lone select with
// no submit next to it reads as broken — while the filter fields wait for
// Apply, because changing four of them should be one request, not four.
document.getElementById('per-page')?.addEventListener('change', (event) => {
    event.target.form.requestSubmit();
});

const filterToggle = document.getElementById('filter-toggle');
const filterPanel = document.getElementById('filter-panel');

filterToggle?.addEventListener('click', () => {
    filterPanel.hidden = !filterPanel.hidden;
    filterToggle.setAttribute('aria-expanded', String(!filterPanel.hidden));
});
