// The tracker: turns what happens in the shop into events and sends them to
// the Go API, POST /api/v1/events.
//
// It does not touch the shop's pages. shop.js announces what happened as
// browser events (market:page, market:add-to-cart, market:checkout); this
// file listens for them, plus clicks on product cards, and builds one API
// event for each. Removing this <script> tag would leave a working shop that
// sends nothing.
//
// The page and the API are on the same address (http://localhost, behind
// Caddy), so the browser allows the request without any CORS setup.

const API_URL = '/api/v1/events';
const SESSION_KEY = 'pasaj.session';
const LOG_KEY = 'pasaj.eventlog';
// Written by the demo login in shop.js; read here, so the two files share a
// key name and nothing else.
const CUSTOMER_KEY_FOR_TRACKING = 'pasaj.customer';

// A visit ends after 30 minutes without activity; the next event starts a
// new session. This is the usual analytics definition of a session.
const SESSION_IDLE_MS = 30 * 60 * 1000;

// Our page names -> the event_source values the rest of the system uses.
const SOURCES = { list: 'listing', product: 'detail', cart: 'cart' };

/* ---------- the parts of every event ---------- */

// One id per visit, kept in localStorage so it survives page changes.
// It travels inside event_payload until the schema gets a session_id column
// (a question for the mentor).
//
// A visit ends, and the next event starts a new one, when:
//   - 30 minutes pass without an event, or
//   - the person changes: a customer logs out, or another customer logs in.
// Logging in from anonymous does NOT end the visit. The anonymous events
// before the login and the identified ones after it keep one session_id,
// which is how an analyst sees what someone did before logging in.
//
// `user` is the user_id this event is sent with (null when anonymous).
function sessionId(user) {
    const now = Date.now();
    let session = null;
    try {
        session = JSON.parse(localStorage.getItem(SESSION_KEY));
    } catch {
        // unreadable: start a new one
    }

    const expired = !session || now - session.lastSeen > SESSION_IDLE_MS;
    // Sessions stored before this rule have no userId; treat them as
    // anonymous. Only a session that already belongs to someone can switch.
    const owner = session?.userId ?? null;
    const switched = owner !== null && owner !== user;

    if (expired || switched) {
        session = { id: crypto.randomUUID(), lastSeen: now, userId: user };
    }
    session.lastSeen = now;
    // Records the login on a kept anonymous session, so a later switch to
    // another customer is recognised.
    session.userId = user;
    localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    return session.id;
}

// The logged-in demo customer's id, or null for an anonymous visitor.
// Read on every event, so logging in or out takes effect from the next
// event on. What a change of user does to the session: see sessionId().
function userId() {
    try {
        return JSON.parse(localStorage.getItem(CUSTOMER_KEY_FOR_TRACKING))?.id ?? null;
    } catch {
        return null;
    }
}

// "tablet" for a touch screen of tablet width, "web" otherwise. A phone
// browser is still the web; "app" is reserved for a native app.
function platform() {
    const touch = matchMedia('(pointer: coarse)').matches;
    return touch && innerWidth >= 600 && innerWidth < 1024 ? 'tablet' : 'web';
}

/* ---------- sending ---------- */

// Builds one event in the shape the API expects and POSTs it. Returns the
// HTTP status (201 when stored), or 0 when the request did not get through.
async function track(action, source, payload = {}) {
    // Read once, so user_id and the session decision agree for this event.
    const user = userId();
    const event = {
        event_id: crypto.randomUUID(),
        user_id: user,
        // The browser does not know its own public address; the API fills
        // it in from the connection.
        user_ip: null,
        event_platform: platform(),
        event_domain: location.hostname,
        event_source: source,
        event_action: action,
        event_payload: { ...payload, session_id: sessionId(user) },
        event_timestamp: new Date().toISOString(),
    };

    const entry = logEntry(event);
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(event),
            // Lets the request finish even if the page is being left, e.g.
            // the click that opens a product page.
            keepalive: true,
        });
        entry.status = response.status;
        if (!response.ok) {
            entry.error = (await response.json().catch(() => ({}))).error ?? response.statusText;
        }
    } catch {
        entry.status = 0;
        entry.error = 'API cevap vermedi';
    }
    saveLog();
    return entry.status;
}

/* ---------- what triggers an event ---------- */

// The same product fields on every product event, so whoever reads the
// events - the panel's "Top products" - can name a product without a copy
// of this shop's catalogue. Real trackers do the same (Google Analytics'
// item_id / item_name / item_brand / item_category).
function productFields(product) {
    return {
        product_id: product.id,
        product_name: product.name,
        brand: product.brand,
        category: product.category,
        product_type: product.type,
    };
}

let productsOnPage = new Map();

// Every drawn page is one page_view.
document.addEventListener('market:page', ({ detail }) => {
    const source = SOURCES[detail.page];
    if (!source) return;

    if (detail.shown) productsOnPage = new Map(detail.shown.map((p) => [String(p.id), p]));

    const payload = { path: location.pathname + location.search };
    if (detail.product) Object.assign(payload, productFields(detail.product));
    track('page_view', source, payload);
});

// A click on a product card. The browser would leave the page at once and
// could cut the request off, so the tracker holds the navigation until the
// event is sent - but never for more than 300 ms, so a slow API cannot make
// the shop feel stuck.
document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[data-product-id]');
    if (!link) return;

    // Opening in a new tab (cmd/ctrl/shift/middle click) does not leave this
    // page, so there is nothing to hold.
    const newTab = e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0;
    const product = productsOnPage.get(link.dataset.productId);
    if (!product) return;

    const sent = track('product_click', 'listing', {
        ...productFields(product),
        position: Number(link.dataset.position),
    });

    if (newTab) return;
    e.preventDefault();
    await Promise.race([sent, new Promise((resolve) => setTimeout(resolve, 300))]);
    location.href = link.href;
});

document.addEventListener('market:add-to-cart', ({ detail }) => {
    track('add_to_cart', 'detail', {
        ...productFields(detail.product),
        quantity: detail.quantity,
        price: detail.product.price,
        currency: 'TRY',
        size: detail.size,
        color: detail.color,
    });
});

document.addEventListener('market:checkout', ({ detail }) => {
    track('checkout_start', 'cart', {
        cart_total: Math.round(detail.total * 100) / 100,
        item_count: detail.itemCount,
        currency: 'TRY',
    });
});

/* ---------- the event log drawer ---------- */

// A small window onto the pipeline while demoing: every event this browser
// tab sent, and what the API answered. Kept in sessionStorage so it survives
// moving between pages, and cleared when the tab is closed.

// The API's error text is shown in a tooltip; escape it so it stays text.
// (Own copy rather than shop.js's, so the tracker depends on nothing.)
const escapeText = (value) => String(value).replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);

let log = [];
try {
    log = JSON.parse(sessionStorage.getItem(LOG_KEY)) ?? [];
} catch {
    log = [];
}

function logEntry(event) {
    const entry = {
        id: event.event_id,
        action: event.event_action,
        source: event.event_source,
        time: new Date().toLocaleTimeString('tr-TR'),
        status: null, // filled in when the API answers
    };
    log.unshift(entry);
    log = log.slice(0, 50);
    saveLog();
    return entry;
}

function saveLog() {
    sessionStorage.setItem(LOG_KEY, JSON.stringify(log));
    renderLog();
}

const drawer = document.createElement('div');
drawer.className = 'event-log';
drawer.innerHTML = `
    <section class="event-log__panel" id="event-log-panel" hidden aria-label="Gönderilen event'ler">
        <header class="event-log__head">
            <p><strong>Bu sekmeden giden event'ler</strong></p>
            <button type="button" class="event-log__clear" data-log-clear>Temizle</button>
        </header>
        <ol class="event-log__list" data-log-list></ol>
        <footer class="event-log__foot">
            201 = kaydedildi. Hepsini <a href="/admin/events">panelde</a> görebilirsin.
        </footer>
    </section>
    <button type="button" class="event-log__toggle" aria-expanded="false" aria-controls="event-log-panel" data-log-toggle>
        <span class="event-log__dot" data-log-dot></span>
        Events <span data-log-count>0</span>
    </button>`;

function renderLog() {
    if (!drawer.isConnected) return;

    drawer.querySelector('[data-log-count]').textContent = log.length;

    const last = log[0];
    drawer.querySelector('[data-log-dot]').dataset.state =
        !last || last.status === null ? 'idle' : last.status >= 200 && last.status < 300 ? 'ok' : 'fail';

    drawer.querySelector('[data-log-list]').innerHTML = log.length
        ? log.map((e) => {
            const state = e.status === null ? 'pending' : e.status >= 200 && e.status < 300 ? 'ok' : 'fail';
            const status = e.status === null ? '…' : e.status === 0 ? 'yok' : e.status;
            return `
                <li class="event-log__row">
                    <span class="event-log__action">${e.action}</span>
                    <span class="event-log__source">${e.source}</span>
                    <span class="event-log__time">${e.time}</span>
                    <span class="event-log__status" data-state="${state}" title="${e.error ? escapeText(e.error) : ''}">${status}</span>
                </li>`;
        }).join('')
        : '<li class="event-log__empty">Henüz event yok. Bir ürüne tıkla.</li>';
}

document.addEventListener('DOMContentLoaded', () => {
    document.body.append(drawer);

    const toggle = drawer.querySelector('[data-log-toggle]');
    const panel = drawer.querySelector('#event-log-panel');
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });
    drawer.querySelector('[data-log-clear]').addEventListener('click', () => {
        log = [];
        saveLog();
    });

    renderLog();
});
