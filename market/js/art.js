// Product illustrations, drawn as SVG instead of photos.
//
// The market has no product photography, and copying photos from a real shop
// is not an option. Each product type (sneaker, boot, t-shirt...) is one
// drawing whose parts are painted from the product's colour entry in
// products.json:
//   main   = the body of the product
//   mid    = the second surface (midsole, hood, flap...)
//   sole   = the darkest part (outsole, hood lining, straps...)
//   accent = the detail colour (stripe, toe cap, pocket...)
//   lace   = laces, strings, stitching
// Choosing another colour swatch on the product page simply redraws the same
// shape with another colour entry.

// Every drawing uses the same 240 x 180 canvas, so any of them fits any slot.
const ART = {
    sneaker: (c) => `
        <ellipse cx="122" cy="152" rx="102" ry="8" fill="#000" opacity=".08"/>
        <path d="M24 132 Q22 146 38 146 L204 146 Q222 146 222 132 L222 124 L24 124 Z" fill="${c.sole}"/>
        <path d="M24 124 L222 124 L222 116 Q180 114 140 117 L24 116 Z" fill="${c.mid}"/>
        <path d="M30 117 L32 90 Q34 72 56 68 L88 62 Q102 60 110 50 L120 40 Q128 34 138 42 L152 64 Q164 78 188 86 Q216 94 220 110 L222 117 Z" fill="${c.main}"/>
        <path d="M172 85 Q210 93 220 110 L222 117 L182 117 Q186 100 172 85 Z" fill="${c.accent}" opacity=".9"/>
        <path d="M62 108 Q120 90 184 100 Q126 108 70 116 Z" fill="${c.accent}"/>
        <path d="M34 90 Q38 72 58 69 L64 72 Q46 77 44 93 Z" fill="${c.sole}" opacity=".55"/>
        <g stroke="${c.lace}" stroke-width="3.2" stroke-linecap="round">
            <path d="M116 52 L128 58"/><path d="M122 60 L136 66"/><path d="M129 68 L145 74"/>
        </g>`,

    boot: (c) => {
        // Lugs along the outsole: a row of small blocks, like a hiking sole.
        const lugs = Array.from({ length: 11 }, (_, i) =>
            `<rect x="${38 + i * 16}" y="143" width="10" height="6" rx="1.5" fill="${c.sole}"/>`).join('');
        return `
        <ellipse cx="124" cy="154" rx="102" ry="7" fill="#000" opacity=".08"/>
        ${lugs}
        <path d="M28 134 L220 134 Q222 145 208 146 L40 146 Q26 146 28 134 Z" fill="${c.sole}"/>
        <path d="M28 124 L220 124 L220 134 L28 134 Z" fill="${c.mid}"/>
        <path d="M34 124 L34 54 Q34 38 50 36 L94 34 Q108 34 110 46 L114 72 Q140 80 176 88 Q212 96 216 114 L218 124 Z" fill="${c.main}"/>
        <path d="M34 56 Q34 38 50 36 L94 34 Q108 34 110 46 L110 51 Q100 43 90 45 L46 47 Q38 47 38 60 Z" fill="${c.accent}"/>
        <path d="M150 84 Q206 94 214 112 L218 124 L160 124 Q166 102 150 84 Z" fill="${c.accent}" opacity=".85"/>
        <path d="M34 92 L60 96 Q66 110 60 124 L34 124 Z" fill="${c.accent}"/>
        <g fill="none" stroke="${c.lace}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M100 50 L113 57 L103 64 L117 70 L107 78 L123 84"/>
        </g>
        <g fill="${c.mid}"><circle cx="100" cy="50" r="2.4"/><circle cx="103" cy="64" r="2.4"/><circle cx="107" cy="78" r="2.4"/></g>`;
    },

    tshirt: (c) => `
        <ellipse cx="120" cy="164" rx="70" ry="6" fill="#000" opacity=".07"/>
        <path d="M84 30 L102 24 Q120 40 138 24 L156 30 L190 58 L172 82 L160 72 L160 156 Q120 162 80 156 L80 72 L68 82 L50 58 Z" fill="${c.main}"/>
        <path d="M102 24 Q120 40 138 24 L133 22 Q120 33 107 22 Z" fill="${c.sole}"/>
        <path d="M172 82 L190 58 L186 55 L168 79 Z M68 82 L50 58 L54 55 L72 79 Z" fill="${c.mid}"/>
        <rect x="124" y="56" width="18" height="12" rx="3" fill="${c.accent}"/>
        <path d="M82 150 Q120 156 158 150" stroke="${c.lace}" stroke-width="1.6" fill="none" stroke-dasharray="3 3"/>`,

    hoodie: (c) => `
        <ellipse cx="120" cy="164" rx="74" ry="6" fill="#000" opacity=".07"/>
        <path d="M78 44 L96 34 Q120 50 144 34 L162 44 L192 120 L176 126 L162 90 L162 158 L78 158 L78 90 L64 126 L48 120 Z" fill="${c.main}"/>
        <path d="M96 36 Q98 12 120 10 Q142 12 144 36 Q120 52 96 36 Z" fill="${c.mid}"/>
        <path d="M105 35 Q108 21 120 21 Q132 21 135 35 Q120 44 105 35 Z" fill="${c.sole}"/>
        <path d="M94 118 L146 118 L152 146 L88 146 Z" fill="${c.mid}"/>
        <rect x="78" y="150" width="84" height="8" fill="${c.mid}"/>
        <path d="M176 126 L192 120 L194 126 L178 132 Z M64 126 L48 120 L46 126 L62 132 Z" fill="${c.mid}"/>
        <g stroke="${c.lace}" stroke-width="2.6" stroke-linecap="round"><path d="M113 44 L111 74"/><path d="M127 44 L129 74"/></g>`,

    backpack: (c) => `
        <ellipse cx="120" cy="162" rx="58" ry="6" fill="#000" opacity=".08"/>
        <rect x="60" y="62" width="10" height="78" rx="5" fill="${c.sole}"/>
        <rect x="170" y="62" width="10" height="78" rx="5" fill="${c.sole}"/>
        <path d="M104 42 Q104 20 120 20 Q136 20 136 42" fill="none" stroke="${c.sole}" stroke-width="6" stroke-linecap="round"/>
        <rect x="68" y="38" width="104" height="120" rx="28" fill="${c.main}"/>
        <path d="M68 66 Q68 38 96 38 L144 38 Q172 38 172 66 L172 80 Q120 94 68 80 Z" fill="${c.mid}"/>
        <rect x="86" y="104" width="68" height="42" rx="12" fill="${c.accent}"/>
        <path d="M92 114 L148 114" stroke="${c.lace}" stroke-width="2" stroke-linecap="round"/>
        <rect x="112" y="84" width="16" height="10" rx="3" fill="${c.sole}"/>`,

    cap: (c) => `
        <ellipse cx="128" cy="134" rx="92" ry="7" fill="#000" opacity=".08"/>
        <path d="M148 108 Q208 102 224 122 Q190 130 146 124 Z" fill="${c.accent}"/>
        <path d="M58 116 Q58 52 122 50 Q182 52 184 116 Z" fill="${c.main}"/>
        <g fill="none" stroke="${c.mid}" stroke-width="2"><path d="M122 52 Q100 80 98 116"/><path d="M122 52 Q146 80 150 116"/></g>
        <rect x="58" y="112" width="126" height="8" rx="2" fill="${c.mid}"/>
        <circle cx="122" cy="52" r="5" fill="${c.accent}"/>`,
};

// Backdrops for the second, larger picture on the product page: the product
// placed in a setting that suits it, standing in for a lifestyle photo.
const SCENES = {
    forest: {
        sky: ['#dfe9df', '#f3efe2'],
        ground: '#8b7a5c',
        props: `
            <circle cx="232" cy="70" r="26" fill="#f6d68a" opacity=".8"/>
            <path d="M20 270 L58 150 L96 270 Z" fill="#3f6b4b"/>
            <path d="M70 270 L112 120 L154 270 Z" fill="#2f5a3d"/>
            <path d="M180 270 L222 140 L264 270 Z" fill="#3f6b4b"/>
            <path d="M236 270 L270 176 L304 270 Z" fill="#2f5a3d"/>
            <rect y="262" width="300" height="12" fill="#6f8f5c"/>`,
    },
    track: {
        sky: ['#fbe3cf', '#fff6ee'],
        ground: '#c9573a',
        props: `
            <circle cx="70" cy="80" r="30" fill="#ffffff" opacity=".7"/>
            <g stroke="#fff" stroke-width="2" opacity=".85">
                <path d="M0 300 Q150 280 300 300"/><path d="M0 330 Q150 308 300 330"/><path d="M0 360 Q150 336 300 360"/>
            </g>`,
    },
    city: {
        sky: ['#dbe6f3', '#f4f6f9'],
        ground: '#b7b2aa',
        props: `
            <g fill="#aebcca">
                <rect x="10" y="150" width="46" height="120"/><rect x="62" y="110" width="58" height="160"/>
                <rect x="126" y="170" width="40" height="100"/><rect x="172" y="96" width="62" height="174"/>
                <rect x="240" y="140" width="52" height="130"/>
            </g>
            <g fill="#f4f6f9" opacity=".7">
                <rect x="72" y="124" width="10" height="12"/><rect x="92" y="124" width="10" height="12"/>
                <rect x="184" y="112" width="10" height="12"/><rect x="206" y="112" width="10" height="12"/>
                <rect x="184" y="136" width="10" height="12"/><rect x="206" y="136" width="10" height="12"/>
            </g>`,
    },
    studio: {
        sky: ['#f1ece6', '#faf8f5'],
        ground: '#e3dbd1',
        props: `
            <path d="M40 270 L40 150 Q40 60 150 60 Q260 60 260 150 L260 270 Z" fill="#ffffff" opacity=".75"/>`,
    },
};

function productArt(product, color, className = 'art') {
    return `<svg class="${className}" viewBox="0 0 240 180" aria-hidden="true">${ART[product.art](color)}</svg>`;
}

let sceneCount = 0;

function sceneArt(product, color) {
    const scene = SCENES[product.scene] ?? SCENES.studio;
    // Gradient ids must be unique in the page, or a second scene would reuse
    // the first one's colours.
    const id = `sky-${++sceneCount}`;
    return `
        <svg class="scene" viewBox="0 0 300 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="${scene.sky[0]}"/><stop offset="1" stop-color="${scene.sky[1]}"/>
            </linearGradient></defs>
            <rect width="300" height="380" fill="url(#${id})"/>
            ${scene.props}
            <rect y="270" width="300" height="110" fill="${scene.ground}" opacity=".35"/>
            <svg x="10" y="176" width="280" height="210" viewBox="0 0 240 180">${ART[product.art](color)}</svg>
        </svg>`;
}
