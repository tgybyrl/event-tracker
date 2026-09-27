// The market's pages: listing, product detail and cart.
//
// There is no server-side code for the shop. Each HTML file is an empty
// frame with a data-page attribute on <body>; this script reads the products
// from products.json and draws the page into it. The cart lives in the
// browser's localStorage, so it survives reloads and page changes but is
// private to this browser.

const CART_KEY = 'pasaj.cart';

const GENDERS = ['Kadın', 'Erkek', 'Çocuk'];
const CATEGORIES = ['Ayakkabı', 'Spor', 'Giyim', 'Çanta ve Aksesuar'];

// 1399.99 -> "1.399,99 TL", the way Turkish shops write prices.
const money = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatPrice = (amount) => `${money.format(amount)} TL`;

// Anything that came from the address bar or the user (the search text) is
// escaped before it goes into HTML, so it is shown as text and never run.
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);
}

const ICONS = {
    search: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>',
    cart: '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4h2.6l2.3 10.2a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.1L21 7.5H6"/><circle cx="9.5" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/></svg>',
    heart: '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 7.9 3.6 4.5 7 4.5c2 0 3.5 1.1 5 2.9 1.5-1.8 3-2.9 5-2.9 3.4 0 5.6 3.4 4.3 6.8-1.8 4.6-9.3 9.2-9.3 9.2Z"/></svg>',
    truck: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5h11v9h-11zM13.5 9.5h4l3 3.2v2.8h-7z"/><circle cx="6.5" cy="17" r="1.6" fill="#fff"/><circle cx="17" cy="17" r="1.6" fill="#fff"/></svg>',
    trophy: '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M7 3h10v2h3v2.5a4.5 4.5 0 0 1-4.1 4.5A5 5 0 0 1 13 14.9V17h3v2H8v-2h3v-2.1A5 5 0 0 1 8.1 12 4.5 4.5 0 0 1 4 7.5V5h3V3Zm0 4H6v.5a2.5 2.5 0 0 0 1.3 2.2A5 5 0 0 1 7 8V7Zm10 0v1c0 .6-.1 1.2-.3 1.7A2.5 2.5 0 0 0 18 7.5V7h-1Z"/></svg>',
};

/* ---------- cart (localStorage) ---------- */

// One line per product + size + colour, because the same shoe in two sizes
// is two different things to ship.
function readCart() {
    try {
        return JSON.parse(localStorage.getItem(CART_KEY)) ?? [];
    } catch {
        return [];
    }
}

function writeCart(lines) {
    localStorage.setItem(CART_KEY, JSON.stringify(lines));
    updateCartCount();
}

function addToCart(line) {
    const lines = readCart();
    const same = lines.find((l) => l.id === line.id && l.size === line.size && l.color === line.color);
    if (same) {
        same.qty += line.qty;
    } else {
        lines.push(line);
    }
    writeCart(lines);
}

function updateCartCount() {
    const count = readCart().reduce((sum, line) => sum + line.qty, 0);
    const badge = document.querySelector('[data-cart-count]');
    if (!badge) return;
    badge.textContent = count;
    badge.hidden = count === 0;
}

/* ---------- shared header and footer ---------- */

function renderChrome(params) {
    const gender = params.get('gender');
    const category = params.get('category');
    const q = params.get('q') ?? '';

    document.getElementById('site-header').innerHTML = `
        <div class="utility">
            <div class="container utility__inner">
                <p>Demo mağaza: buradaki her tıklama bir event olarak kaydedilir.</p>
                <a href="/admin/events">Event'leri panelde gör</a>
            </div>
        </div>
        <header class="masthead">
            <div class="container masthead__inner">
                <a class="wordmark" href="/market/list">pasaj</a>
                <nav class="gender-nav" aria-label="Reyon">
                    ${GENDERS.map((g) => `<a href="/market/list?gender=${encodeURIComponent(g)}"${g === gender ? ' aria-current="page"' : ''}>${g}</a>`).join('')}
                </nav>
                <form class="search" action="/market/list" role="search">
                    ${ICONS.search}
                    <input type="search" name="q" value="${escapeHtml(q)}" placeholder="Örneğin: koşu ayakkabısı" aria-label="Ürün ara">
                </form>
                <a class="cart-link" href="/market/cart">
                    ${ICONS.cart}
                    <span>Sepetim</span>
                    <span class="cart-count" data-cart-count hidden></span>
                </a>
            </div>
        </header>
        <nav class="category-bar" aria-label="Kategoriler">
            <div class="container category-bar__inner">
                ${CATEGORIES.map((c) => `<a href="/market/list?category=${encodeURIComponent(c)}"${c === category ? ' aria-current="page"' : ''}>${c}</a>`).join('')}
                <a href="/market/list"${!gender && !category && !q ? ' aria-current="page"' : ''}>Tüm ürünler</a>
            </div>
        </nav>`;

    document.getElementById('site-footer').innerHTML = `
        <div class="container footer__inner">
            <span class="wordmark wordmark--small">pasaj</span>
            <p>Bu bir demo mağazadır. Ürünler ve markalar hayalidir, sipariş ve ödeme alınmaz.</p>
        </div>`;

    updateCartCount();
}

function crumbs(items) {
    // items: [label, href] pairs; the last one is the current page, not a link.
    return `<nav class="crumbs" aria-label="Konum">${items.map(([label, href], i) =>
        i === items.length - 1
            ? `<span aria-current="page">${escapeHtml(label)}</span>`
            : `<a href="${href}">${escapeHtml(label)}</a><span aria-hidden="true">/</span>`
    ).join('')}</nav>`;
}

/* ---------- listing ---------- */

function renderList(products, params) {
    const gender = params.get('gender');
    const category = params.get('category');
    const q = (params.get('q') ?? '').trim();
    const needle = q.toLocaleLowerCase('tr');

    // A product with no gender (bags, caps) belongs to every reyon.
    const shown = products.filter((p) =>
        (!gender || p.gender === gender || p.gender === null) &&
        (!category || p.category === category) &&
        (!needle || `${p.brand} ${p.name} ${p.category}`.toLocaleLowerCase('tr').includes(needle)));

    const trail = [['Anasayfa', '/market/list']];
    if (gender) trail.push([gender, `/market/list?gender=${encodeURIComponent(gender)}`]);
    if (category) trail.push([category, `/market/list?category=${encodeURIComponent(category)}`]);
    if (trail.length === 1) trail.push(['Tüm ürünler', '/market/list']);

    const title = q ? `“${escapeHtml(q)}” için sonuçlar` : escapeHtml(category ?? gender ?? 'Tüm ürünler');

    const cards = shown.map((p, i) => `
        <article class="card">
            <a class="card__link" href="/market/product/${p.id}" data-product-id="${p.id}" data-position="${i + 1}">
                <div class="card__media">
                    ${productArt(p, p.colors[0])}
                    ${p.freeShipping ? `<span class="pill">${ICONS.truck}Kargo Bedava</span>` : ''}
                </div>
                <div class="card__body">
                    <p class="card__title"><strong>${p.brand}</strong> ${p.name}</p>
                    <p class="fav">${ICONS.heart}${p.favorites} kişi favoriledi</p>
                    <p class="card__price">${formatPrice(p.price)}</p>
                </div>
            </a>
        </article>`).join('');

    document.getElementById('main').innerHTML = `
        ${crumbs(trail)}
        <div class="list-head">
            <h1>${title}</h1>
            <p>${shown.length} ürün</p>
        </div>
        ${shown.length
            ? `<div class="grid">${cards}</div>`
            : `<div class="empty">
                   <h2>Bu aramaya uyan ürün yok</h2>
                   <p>Başka bir kelime dene ya da tüm ürünlere dön.</p>
                   <a class="btn btn--ghost" href="/market/list">Tüm ürünleri göster</a>
               </div>`}`;

    return shown;
}

/* ---------- product detail ---------- */

function renderProduct(products) {
    // The proxy serves product.html for every /market/product/<id> address,
    // so the id is read from the address itself.
    const id = Number(location.pathname.split('/').pop());
    const product = products.find((p) => p.id === id);
    const main = document.getElementById('main');

    if (!product) {
        main.innerHTML = `
            ${crumbs([['Anasayfa', '/market/list'], ['Ürün bulunamadı', '']])}
            <div class="empty">
                <h2>Bu ürün artık yok</h2>
                <p>Adres yanlış olabilir ya da ürün satıştan kalkmış olabilir.</p>
                <a class="btn btn--ghost" href="/market/list">Ürünlere dön</a>
            </div>`;
        return null;
    }

    document.title = `${product.brand} ${product.name} | pasaj`;

    // "Most favourited in its category" is computed, not made up: it shows
    // only on the product with the highest favourites count in its category.
    const topOfCategory = products
        .filter((p) => p.category === product.category)
        .every((p) => p.favorites <= product.favorites);

    const trail = [['Anasayfa', '/market/list']];
    if (product.gender) trail.push([product.gender, `/market/list?gender=${encodeURIComponent(product.gender)}`]);
    trail.push([product.category, `/market/list?category=${encodeURIComponent(product.category)}`]);
    trail.push([`${product.brand} ${product.name}`, '']);

    main.innerHTML = `
        ${crumbs(trail)}
        <div class="pdp">
            <div class="pdp__media pdp__media--product">
                ${product.freeShipping ? `<span class="pill">${ICONS.truck}Kargo Bedava</span>` : ''}
                <div class="pdp__art" data-art>${productArt(product, product.colors[0])}</div>
            </div>
            <div class="pdp__media pdp__media--scene" data-scene>${sceneArt(product, product.colors[0])}</div>
            <div class="pdp__buy">
                <section class="buy-card">
                    ${topOfCategory ? `
                        <p class="highlight">${ICONS.trophy}<span>${escapeHtml(product.category)} kategorisinde<b>En çok favorilenen ürün</b></span></p>` : ''}
                    <h1 class="pdp__title"><strong>${product.brand}</strong> ${product.name}</h1>
                    <p class="fav fav--accent">${ICONS.heart}<span>${product.favorites} kişi</span>favoriledi</p>
                    <p class="pdp__price">${formatPrice(product.price)}</p>
                    <p class="installment">Ayda ${formatPrice(product.price / 6)}'den başlayan 6 aya varan taksitlerle</p>
                </section>
                <section class="buy-card">
                    <div class="option-head">
                        <p>Renk: <strong data-color-name>${product.colors[0].name}</strong></p>
                        <p class="muted">${product.colors.length} farklı renk</p>
                    </div>
                    <div class="swatches" role="group" aria-label="Renk">
                        ${product.colors.map((c, i) => `
                            <button type="button" class="swatch" data-color="${i}" aria-pressed="${i === 0}" aria-label="${c.name}">
                                ${productArt(product, c)}
                            </button>`).join('')}
                    </div>
                    <div class="option-head">
                        <p>Beden</p>
                        <p class="size-hint" data-size-hint hidden>Önce bedenini seç</p>
                    </div>
                    <div class="sizes" role="group" aria-label="Beden" data-sizes>
                        ${product.sizes.map((s) => `<button type="button" class="size" data-size="${s}" aria-pressed="false">${s}</button>`).join('')}
                    </div>
                    <button type="button" class="btn btn--primary btn--block" data-add>Sepete ekle</button>
                    <p class="added" data-added hidden>Sepete eklendi. <a href="/market/cart">Sepete git</a></p>
                </section>
            </div>
        </div>`;

    // What the shopper has chosen so far. A single-size item (bags, caps)
    // starts with its only size already chosen.
    const state = { color: 0, size: product.sizes.length === 1 ? product.sizes[0] : null };
    if (state.size) main.querySelector('[data-size]').setAttribute('aria-pressed', 'true');

    main.querySelectorAll('[data-color]').forEach((button) => {
        button.addEventListener('click', () => {
            state.color = Number(button.dataset.color);
            const color = product.colors[state.color];
            main.querySelectorAll('[data-color]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            main.querySelector('[data-color-name]').textContent = color.name;
            main.querySelector('[data-art]').innerHTML = productArt(product, color);
            main.querySelector('[data-scene]').innerHTML = sceneArt(product, color);
        });
    });

    main.querySelectorAll('[data-size]').forEach((button) => {
        button.addEventListener('click', () => {
            state.size = button.dataset.size;
            main.querySelectorAll('[data-size]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            main.querySelector('[data-size-hint]').hidden = true;
            main.querySelector('[data-sizes]').classList.remove('is-missing');
        });
    });

    main.querySelector('[data-add]').addEventListener('click', () => {
        // No size, no cart: say which choice is missing, next to the choice.
        if (!state.size) {
            main.querySelector('[data-size-hint]').hidden = false;
            main.querySelector('[data-sizes]').classList.add('is-missing');
            main.querySelector('[data-size]').focus();
            return;
        }

        const color = product.colors[state.color];
        addToCart({ id: product.id, size: state.size, color: color.name, qty: 1 });
        main.querySelector('[data-added]').hidden = false;

        document.dispatchEvent(new CustomEvent('market:add-to-cart', {
            detail: { product, size: state.size, color: color.name, quantity: 1 },
        }));
    });

    return product;
}

/* ---------- cart ---------- */

function renderCart(products) {
    const main = document.getElementById('main');
    const byId = new Map(products.map((p) => [p.id, p]));

    // Lines whose product no longer exists in products.json are dropped.
    const lines = readCart().filter((l) => byId.has(l.id));
    const total = lines.reduce((sum, l) => sum + byId.get(l.id).price * l.qty, 0);
    const itemCount = lines.reduce((sum, l) => sum + l.qty, 0);

    if (lines.length === 0) {
        main.innerHTML = `
            ${crumbs([['Anasayfa', '/market/list'], ['Sepetim', '']])}
            <div class="empty">
                <h2>Sepetin boş</h2>
                <p>Beğendiğin bir ürünün bedenini seçip sepete ekleyebilirsin.</p>
                <a class="btn btn--primary" href="/market/list">Ürünlere göz at</a>
            </div>`;
        return;
    }

    main.innerHTML = `
        ${crumbs([['Anasayfa', '/market/list'], ['Sepetim', '']])}
        <div class="cart">
            <section>
                <div class="list-head"><h1>Sepetim</h1><p>${itemCount} ürün</p></div>
                <ul class="cart-items">
                    ${lines.map((l, i) => {
                        const p = byId.get(l.id);
                        const color = p.colors.find((c) => c.name === l.color) ?? p.colors[0];
                        return `
                        <li class="cart-item">
                            <a class="cart-item__media" href="/market/product/${p.id}">${productArt(p, color)}</a>
                            <div>
                                <p class="cart-item__title"><a href="/market/product/${p.id}"><strong>${p.brand}</strong> ${p.name}</a></p>
                                <p class="cart-item__meta">Beden: ${escapeHtml(l.size)} &nbsp;|&nbsp; Renk: ${escapeHtml(color.name)}</p>
                                <div class="cart-item__actions">
                                    <div class="stepper">
                                        <button type="button" data-step="-1" data-line="${i}" aria-label="Bir azalt">−</button>
                                        <output aria-live="polite">${l.qty}</output>
                                        <button type="button" data-step="1" data-line="${i}" aria-label="Bir artır">+</button>
                                    </div>
                                    <button type="button" class="remove" data-remove="${i}">Kaldır</button>
                                </div>
                            </div>
                            <p class="line-price">${formatPrice(p.price * l.qty)}</p>
                        </li>`;
                    }).join('')}
                </ul>
            </section>
            <aside class="summary">
                <h2>Sipariş özeti</h2>
                <dl>
                    <dt>Ürünler (${itemCount})</dt><dd>${formatPrice(total)}</dd>
                    <dt>Kargo</dt><dd class="free">Bedava</dd>
                    <dt class="total">Toplam</dt><dd class="total">${formatPrice(total)}</dd>
                </dl>
                <button type="button" class="btn btn--primary btn--block" data-checkout>Siparişi tamamla</button>
                <p class="summary__note">Demo mağaza: ödeme alınmaz.</p>
            </aside>
        </div>`;

    main.querySelectorAll('[data-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const current = readCart().filter((l) => byId.has(l.id));
            const line = current[Number(button.dataset.line)];
            line.qty = Math.max(1, line.qty + Number(button.dataset.step));
            writeCart(current);
            renderCart(products);
        });
    });

    main.querySelectorAll('[data-remove]').forEach((button) => {
        button.addEventListener('click', () => {
            const current = readCart().filter((l) => byId.has(l.id));
            current.splice(Number(button.dataset.remove), 1);
            writeCart(current);
            renderCart(products);
        });
    });

    main.querySelector('[data-checkout]').addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('market:checkout', {
            detail: { total, itemCount },
        }));

        writeCart([]);
        main.innerHTML = `
            ${crumbs([['Anasayfa', '/market/list'], ['Sepetim', '']])}
            <div class="empty empty--done">
                <h2>Siparişin alındı</h2>
                <p>${itemCount} ürün, toplam ${formatPrice(total)}. Bu bir demo: ödeme alınmadı, kargo çıkmayacak.</p>
                <a class="btn btn--primary" href="/market/list">Alışverişe dön</a>
            </div>`;
    });
}

/* ---------- start ---------- */

async function start() {
    const params = new URLSearchParams(location.search);
    renderChrome(params);

    const products = await fetch('/market/products.json').then((r) => r.json());
    const page = document.body.dataset.page;

    let context = {};
    if (page === 'list') context = { shown: renderList(products, params) };
    if (page === 'product') context = { product: renderProduct(products) };
    if (page === 'cart') renderCart(products);

    // Tells anything listening (the tracker) that the page is drawn and what
    // it shows. The shop itself does not know events exist.
    document.dispatchEvent(new CustomEvent('market:page', { detail: { page, ...context } }));
}

start();
