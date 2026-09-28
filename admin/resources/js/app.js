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

// Chart tooltips. Any element inside a .chart with a data-tip attribute shows
// that text next to the pointer. One tooltip element serves every chart.
const chartTip = document.createElement('div');
chartTip.className = 'chart-tip';
chartTip.hidden = true;
document.body.append(chartTip);

document.addEventListener('pointerover', (event) => {
    const target = event.target.closest?.('.chart [data-tip]');
    if (!target) return;
    chartTip.textContent = target.dataset.tip;
    chartTip.hidden = false;
});

document.addEventListener('pointermove', (event) => {
    if (chartTip.hidden) return;
    chartTip.style.left = `${event.pageX}px`;
    chartTip.style.top = `${event.pageY}px`;
});

document.addEventListener('pointerout', (event) => {
    if (event.target.closest?.('.chart [data-tip]')) chartTip.hidden = true;
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') chartTip.hidden = true;
});

// Range switch on a chart card: buttons with data-range pick which
// data-range-panel is shown. Both panels are rendered by the server, so
// switching needs no request.
document.querySelectorAll('[data-range-switch]').forEach((group) => {
    const buttons = group.querySelectorAll('[data-range]');
    const panels = document.querySelectorAll(`[data-range-panel][data-range-for="${group.dataset.rangeSwitch}"]`);

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.rangePanel !== button.dataset.range;
            });
        });
    });
});
