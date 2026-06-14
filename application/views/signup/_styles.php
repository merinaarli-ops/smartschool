<style>
/* ============================================================
   SmartSchool.bd signup — modern light theme (v3)
   White background, soft cards, Tiro Bangla + Inter pairing.
   Each variant overrides accent palette + subtle background
   pattern via the .bd-shell--<flavour> class.
   ============================================================ */
:root {
  --bd-bg:           #f8fafc;
  --bd-bg-accent-1:  #eef2ff;
  --bd-bg-accent-2:  #ecfeff;
  --bd-bg-accent-3:  #fef3f9;

  --bd-card-bg:      #ffffff;
  --bd-card-border:  #d1d5db;
  --bd-card-shadow-sm:0 1px 2px rgba(15, 23, 42, .05);
  --bd-card-shadow:   0 10px 25px -8px rgba(15, 23, 42, .15), 0 4px 10px -4px rgba(15, 23, 42, .08);
  --bd-card-shadow-lg:0 25px 50px -12px rgba(15, 23, 42, .22), 0 10px 20px -8px rgba(15, 23, 42, .12);

  /* Stronger text contrast — every text color a half-step darker so labels,
     helper text and placeholders stay readable on real phone screens. */
  --bd-text:          #0b1220;
  --bd-text-soft:     #1e293b;
  --bd-text-muted:    #475569;
  --bd-text-dim:      #64748b;

  --bd-accent:        #4f46e5;     /* indigo — deeper for contrast */
  --bd-accent-2:      #0891b2;     /* cyan  — deeper for contrast */
  --bd-accent-3:      #059669;     /* emerald — deeper for contrast */
  --bd-accent-warm:   #db2777;     /* pink  — deeper for contrast */
  --bd-accent-soft:   rgba(79, 70, 229, .12);

  --bd-input-bg:      #ffffff;
  --bd-input-bg-soft: #f8fafc;
  --bd-input-text:    #0b1220;
  --bd-input-line:    #cbd5e1;
  --bd-input-line-hover:#94a3b8;

  --bd-radius:        18px;
  --bd-radius-sm:     10px;
  --bd-radius-xs:     6px;
  --bd-radius-pill:   999px;

  --bd-ease:          cubic-bezier(.4,.0,.2,1);

  --bd-font-en:       'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --bd-font-bn:       'Tiro Bangla', 'Hind Siliguri', system-ui, sans-serif;
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
  font-family: var(--bd-font-en);
  color: var(--bd-text);
  background: var(--bd-bg);
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}
:lang(bn), [lang="bn"] { font-family: var(--bd-font-bn); line-height: 1.75; }
a { color: var(--bd-accent); text-decoration: none; }
a:hover { text-decoration: underline; }

/* =========================
   Page shell + background
   ========================= */
.bd-shell {
  position: relative;
  min-height: 100vh;
  padding: 36px 16px 60px;
  display: flex;
  flex-direction: column;
  align-items: center;
  isolation: isolate;
  overflow: hidden;
  background: var(--bd-bg);
}
.bd-shell::before {
  content: "";
  position: absolute;
  inset: -10%;
  z-index: -2;
  background:
    radial-gradient(50% 60% at 15% 10%, var(--bd-bg-accent-1) 0%, transparent 55%),
    radial-gradient(45% 55% at 90% 20%, var(--bd-bg-accent-2) 0%, transparent 55%),
    radial-gradient(60% 50% at 50% 100%, var(--bd-bg-accent-3) 0%, transparent 55%);
  opacity: .85;
  animation: bd-mesh 26s linear infinite alternate;
}
@keyframes bd-mesh {
  0%   { transform: translate3d(0, 0, 0) scale(1); }
  100% { transform: translate3d(2%, -2%, 0) scale(1.05); }
}
@media (prefers-reduced-motion: reduce) {
  .bd-shell::before { animation: none; }
}

/* Floating SVG decorations — books/stars/caps in soft indigo */
.bd-deco {
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  opacity: .08;
  color: var(--bd-accent);
}
.bd-deco svg {
  position: absolute;
  width: 56px; height: 56px;
  animation: bd-float 16s ease-in-out infinite;
}
.bd-deco svg:nth-child(1) { top: 6%;  left: 5%;  animation-delay: 0s;   }
.bd-deco svg:nth-child(2) { top: 14%; right: 8%; animation-delay: 1.5s; width: 44px; height: 44px;}
.bd-deco svg:nth-child(3) { top: 52%; left: 3%;  animation-delay: 3s;   width: 50px; height: 50px;}
.bd-deco svg:nth-child(4) { top: 68%; right: 5%; animation-delay: 4.5s; width: 64px; height: 64px;}
.bd-deco svg:nth-child(5) { top: 38%; left: 50%; animation-delay: 6s;   width: 36px; height: 36px;}
.bd-deco svg:nth-child(6) { bottom: 4%; left: 38%;animation-delay: 7.5s; width: 48px; height: 48px;}
@keyframes bd-float {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50%      { transform: translateY(-18px) rotate(6deg); }
}
@media (prefers-reduced-motion: reduce) {
  .bd-deco svg { animation: none; }
}

/* =========================
   Brand + footer
   ========================= */
.bd-brand {
  text-align: center;
  margin-bottom: 22px;
  font-family: var(--bd-font-en);
  font-weight: 800;
  font-size: 22px;
  letter-spacing: -0.02em;
  color: var(--bd-text);
}
.bd-brand small {
  display: block;
  font-weight: 500;
  font-size: 13px;
  color: var(--bd-text-muted);
  margin-top: 4px;
  letter-spacing: 0.01em;
}
.bd-wrap { width: 100%; max-width: 880px; position: relative; }
.bd-footer {
  text-align: center;
  font-size: 13px;
  color: var(--bd-text-muted);
  margin-top: 18px;
}

/* =========================
   Card
   ========================= */
.bd-card {
  position: relative;
  background: var(--bd-card-bg);
  border: 1px solid var(--bd-card-border);
  border-radius: var(--bd-radius);
  box-shadow: var(--bd-card-shadow);
  padding: 36px 40px 38px;
  color: var(--bd-text);
  overflow: hidden;
  transition: box-shadow .35s var(--bd-ease), transform .35s var(--bd-ease), border-color .25s var(--bd-ease);
}
/* Colored top accent strip */
.bd-card::after {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--bd-accent), var(--bd-accent-2), var(--bd-accent-3));
  border-radius: var(--bd-radius) var(--bd-radius) 0 0;
}
/* Glow on hover */
.bd-card::before {
  content: "";
  position: absolute;
  inset: -1px;
  border-radius: inherit;
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  z-index: -1;
  opacity: 0;
  filter: blur(14px);
  transition: opacity .35s var(--bd-ease);
}
.bd-card:hover { transform: translateY(-2px); box-shadow: var(--bd-card-shadow-lg); border-color: rgba(99, 102, 241, .25); }
.bd-card:hover::before { opacity: .35; }
.bd-card:focus-within::before { opacity: .45; }

/* Calligraphic gradient heading (Tiro Bangla) */
.bd-card h1 {
  font-family: var(--bd-font-bn);
  margin: 0 0 8px;
  font-size: clamp(22px, 2.6vw, 30px);
  font-weight: 400;
  letter-spacing: 0.005em;
  line-height: 1.5;
  text-align: center;
  background: linear-gradient(120deg, var(--bd-accent) 0%, var(--bd-accent-2) 50%, var(--bd-accent-warm) 100%);
  -webkit-background-clip: text;
          background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent;
  background-size: 200% 100%;
  animation: bd-heading-shift 9s ease-in-out infinite;
}
@keyframes bd-heading-shift {
  0%, 100% { background-position: 0% 50%; }
  50%      { background-position: 100% 50%; }
}
.bd-card .bd-warning {
  display: table;
  margin: 6px auto 22px;
  padding: 10px 18px;
  font-family: var(--bd-font-bn);
  font-size: 14.5px;
  font-weight: 500;
  text-align: center;
  border-radius: var(--bd-radius-pill);
  background: linear-gradient(90deg, rgba(219, 39, 119, .10), rgba(79, 70, 229, .10));
  color: var(--bd-text-soft);
  border: 1.5px solid rgba(79, 70, 229, .22);
  box-shadow: 0 4px 10px -4px rgba(79, 70, 229, .18);
}

/* =========================
   Section header chips — group fields visually so the form scans as
   four clear blocks (Institution / Contact / Location / Domain).
   The header spans the full width of the bd-grid.
   ========================= */
.bd-section {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 6px 0 -2px;
  padding: 10px 16px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(79, 70, 229, .08) 0%, rgba(8, 145, 178, .08) 100%);
  border: 1px solid rgba(79, 70, 229, .15);
  font-family: var(--bd-font-en);
  font-weight: 700;
  color: var(--bd-text);
  letter-spacing: 0.01em;
}
.bd-section--standalone {
  grid-column: auto;
  margin: 0 0 4px;
}
.bd-section--green { background: linear-gradient(135deg, rgba(5, 150, 105, .08), rgba(16, 185, 129, .08)); border-color: rgba(5, 150, 105, .18); }
.bd-section--pink  { background: linear-gradient(135deg, rgba(219, 39, 119, .08), rgba(236, 72, 153, .08)); border-color: rgba(219, 39, 119, .18); }
.bd-section--amber { background: linear-gradient(135deg, rgba(234, 88, 12, .08), rgba(245, 158, 11, .08)); border-color: rgba(234, 88, 12, .18); }
.bd-section-icon {
  width: 34px; height: 34px;
  flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  color: #fff;
  font-size: 17px;
}
.bd-section--green .bd-section-icon { background: linear-gradient(135deg, #059669, #10b981); }
.bd-section--pink  .bd-section-icon { background: linear-gradient(135deg, #db2777, #ec4899); }
.bd-section--amber .bd-section-icon { background: linear-gradient(135deg, #ea580c, #f59e0b); }
.bd-section-title { font-size: 15px; line-height: 1.3; }
.bd-section-title small {
  display: block;
  font-family: var(--bd-font-bn);
  font-weight: 500;
  font-size: 13px;
  color: var(--bd-text-soft);
  margin-top: 1px;
}

/* =========================
   Form internals
   ========================= */
.bd-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 20px;
  margin-bottom: 6px;
}
@media (max-width: 640px) {
  .bd-grid { grid-template-columns: 1fr; gap: 14px; }
  .bd-card { padding: 22px 16px 26px; border-radius: 16px; }
  .bd-card h1 { font-size: 19px; line-height: 1.55; }
  .bd-card .bd-warning { font-size: 13px; }
  .bd-input, .bd-select, textarea.bd-input { font-size: 16px; padding: 13px 14px; min-height: 46px; }
  /* Pick-one subdomain on mobile: stack the two rows, prevent flex:1 1 0 from collapsing height. */
  .bd-subdomain-double { flex-direction: column; gap: 10px; padding-right: 0; }
  .bd-subdomain-double .bd-subdomain { flex: 0 0 auto; width: 100%; }
  .bd-subdomain .bd-fix { padding: 0 10px; font-size: 12.5px; min-height: 46px; }
  .bd-subdomain input { font-size: 16px; padding: 12px 10px; min-height: 46px; }
  .bd-subdomain .bd-availability { width: 42px; flex-basis: 42px; }
  .bd-or-badge--vertical { transform: none; }
  .bd-subdomain-pick-one { font-size: 13px; padding: 10px 12px; line-height: 1.7; }
  .bd-btn { width: 100%; padding: 14px 22px; }
  .bd-submit-row { flex-direction: column; align-items: stretch; }
  .bd-domain-preview { padding: 10px 14px; gap: 6px; font-size: 13px; border-radius: 16px; }
  .bd-domain-preview-label { font-size: 13px; }
  .bd-domain-preview-url { font-size: 13.5px; padding: 3px 10px; }
  .bd-domain-preview-status { width: 24px; height: 24px; font-size: 12.5px; }
}
@media (max-width: 420px) {
  /* On narrow phones the row is too cramped for `www.` + input + `.suffix.bd`
     side-by-side. Drop the `www.` prefix (decorative only, no information loss),
     keep the input on row 1, drop the suffix to row 2 as a chip. */
  .bd-card { padding: 20px 14px 24px; }
  .bd-subdomain { flex-wrap: wrap; }
  .bd-subdomain .bd-fix:not(.bd-fix--suffix) { display: none; }
  .bd-subdomain input { order: 1; flex: 1 1 auto; min-width: 0; }
  .bd-subdomain .bd-availability { order: 2; flex: 0 0 42px; align-self: stretch; }
  .bd-subdomain .bd-fix--suffix {
    order: 3;
    flex: 1 1 100%;
    justify-content: flex-end;
    padding: 7px 12px;
    border-top: 1px dashed var(--bd-input-line);
    min-height: auto;
    font-size: 12.5px;
  }
}
.bd-field {
  position: relative;
  display: block;
  opacity: 0;
  transform: translateY(10px);
  animation: bd-enter .55s var(--bd-ease) forwards;
}
@keyframes bd-enter {
  to { opacity: 1; transform: translateY(0); }
}
/* 60ms stagger via nth-child */
.bd-grid .bd-field:nth-child(1) { animation-delay: .05s; }
.bd-grid .bd-field:nth-child(2) { animation-delay: .11s; }
.bd-grid .bd-field:nth-child(3) { animation-delay: .17s; }
.bd-grid .bd-field:nth-child(4) { animation-delay: .23s; }
.bd-grid .bd-field:nth-child(5) { animation-delay: .29s; }
.bd-grid .bd-field:nth-child(6) { animation-delay: .35s; }
.bd-grid .bd-field:nth-child(7) { animation-delay: .41s; }
.bd-grid .bd-field:nth-child(8) { animation-delay: .47s; }
.bd-grid .bd-field:nth-child(9) { animation-delay: .53s; }
.bd-grid .bd-field:nth-child(10){ animation-delay: .59s; }
.bd-grid .bd-field:nth-child(11){ animation-delay: .65s; }
.bd-grid .bd-field:nth-child(12){ animation-delay: .71s; }
.bd-field-subdomain { animation-delay: .77s; }
.bd-card form > .bd-field:not(.bd-field-subdomain) { animation-delay: .83s; }
@media (prefers-reduced-motion: reduce) {
  .bd-field { opacity: 1; transform: none; animation: none; }
}
.bd-label {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: var(--bd-text);
  margin-bottom: 7px;
  font-family: var(--bd-font-en);
  letter-spacing: 0.01em;
  transition: color .2s var(--bd-ease);
}
.bd-label .req { color: var(--bd-accent-warm); margin-right: 2px; font-weight: 800; }
.bd-field:focus-within .bd-label { color: var(--bd-accent); }

.bd-input,
.bd-select,
textarea.bd-input {
  display: block;
  width: 100%;
  padding: 12px 14px;
  font-size: 15px;
  font-weight: 500;
  border: 1.5px solid var(--bd-input-line);
  border-radius: var(--bd-radius-sm);
  background: var(--bd-input-bg);
  color: var(--bd-input-text);
  font-family: var(--bd-font-en);
  transition: box-shadow .2s var(--bd-ease), border-color .2s var(--bd-ease), background .2s var(--bd-ease), transform .15s var(--bd-ease);
}
.bd-input::placeholder, textarea.bd-input::placeholder {
  color: #94a3b8;
  font-weight: 400;
}
.bd-input:hover, .bd-select:hover, textarea.bd-input:hover {
  border-color: var(--bd-input-line-hover);
  background: #fafbff;
}
.bd-input:focus,
.bd-select:focus,
textarea.bd-input:focus {
  outline: none;
  border-color: var(--bd-accent);
  box-shadow: 0 0 0 4px rgba(79, 70, 229, .18), 0 4px 12px -4px rgba(79, 70, 229, .25);
  background: #fff;
}
.bd-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='9' viewBox='0 0 14 9'%3E%3Cpath d='M1 1l6 6 6-6' stroke='%234f46e5' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 38px;
  cursor: pointer;
}

/* Animated accent underline on focus */
.bd-field::after {
  content: "";
  position: absolute;
  left: 14px; right: 14px; bottom: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--bd-accent), var(--bd-accent-2));
  border-radius: 2px;
  transform: scaleX(0);
  transform-origin: left center;
  transition: transform .3s var(--bd-ease);
  pointer-events: none;
}
.bd-field:focus-within::after { transform: scaleX(1); }

/* =========================
   Subdomain — TWO inputs, pick one
   ========================= */
.bd-subdomain-pick-one {
  margin: -2px 0 12px;
  padding: 10px 14px;
  border-radius: var(--bd-radius-sm);
  background: linear-gradient(90deg, rgba(99, 102, 241, .07), rgba(6, 182, 212, .07));
  border: 1px solid rgba(99, 102, 241, .18);
  font-family: var(--bd-font-bn);
  font-size: 14.5px;
  line-height: 1.7;
  color: var(--bd-text-soft);
  display: flex;
  align-items: center;
  gap: 10px;
}
.bd-subdomain-pick-one .bd-pick-icon {
  flex: 0 0 auto;
  color: var(--bd-accent);
  font-weight: 700;
  font-size: 18px;
  line-height: 1;
}
.bd-subdomain-pick-one strong {
  color: var(--bd-accent);
  font-weight: 700;
  padding: 0 4px;
  background: linear-gradient(180deg, transparent 60%, rgba(99, 102, 241, .18) 60%);
}

.bd-subdomain-double {
  display: flex;
  align-items: stretch;
  gap: 12px;
}
.bd-subdomain-double .bd-subdomain {
  /* `auto` basis (NOT 0) — guarantees content-driven height when the parent
     switches to flex-direction: column at the mobile breakpoint. */
  flex: 1 1 auto;
  position: relative;
  min-width: 0;
}
.bd-subdomain {
  position: relative;
  display: flex;
  align-items: stretch;
  min-height: 48px;
  border-radius: var(--bd-radius-sm);
  overflow: hidden;
  background: var(--bd-input-bg);
  border: 1px solid var(--bd-input-line);
  transition: box-shadow .25s var(--bd-ease), border-color .25s var(--bd-ease), opacity .25s var(--bd-ease), transform .25s var(--bd-ease);
}
.bd-subdomain--ok {
  border-color: var(--bd-accent-3);
  box-shadow: 0 0 0 1px var(--bd-accent-3), 0 8px 22px -10px rgba(16, 185, 129, .45);
  background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 50%);
}
.bd-subdomain--bad {
  border-color: #ef4444;
  box-shadow: 0 0 0 1px rgba(239, 68, 68, .55), 0 8px 22px -10px rgba(239, 68, 68, .35);
}
.bd-subdomain:focus-within {
  border-color: var(--bd-accent);
  box-shadow: 0 0 0 4px rgba(99, 102, 241, .15);
}
.bd-subdomain--filled {
  border-color: var(--bd-accent);
  box-shadow: 0 0 0 1px var(--bd-accent), 0 4px 14px -4px rgba(99, 102, 241, .35);
}
.bd-subdomain--filled.bd-subdomain--ok {
  /* Available wins over filled — green ring. */
  border-color: var(--bd-accent-3);
  box-shadow: 0 0 0 1px var(--bd-accent-3), 0 10px 26px -10px rgba(16, 185, 129, .50);
}
.bd-subdomain--disabled {
  opacity: .42;
}
.bd-subdomain--disabled input { background: var(--bd-input-bg-soft); cursor: not-allowed; }
.bd-subdomain .bd-fix {
  display: inline-flex;
  align-items: center;
  padding: 0 12px;
  background: var(--bd-input-bg-soft);
  color: var(--bd-text-muted);
  font-weight: 600;
  font-size: 13.5px;
  font-family: ui-monospace, 'SF Mono', monospace;
  white-space: nowrap;
}
.bd-subdomain .bd-fix--suffix {
  color: var(--bd-accent);
  background: var(--bd-accent-soft);
}
.bd-subdomain input {
  border: 0;
  flex: 1;
  min-width: 0;
  padding: 11px 12px;
  font-size: 14.5px;
  background: transparent;
  color: var(--bd-input-text);
  font-family: var(--bd-font-en);
}
.bd-subdomain input:focus { outline: none; }

/* OR badge between the two inputs */
.bd-or-badge {
  align-self: center;
  padding: 4px 10px;
  border-radius: var(--bd-radius-pill);
  font-size: 11px;
  font-weight: 800;
  font-family: var(--bd-font-en);
  letter-spacing: 0.10em;
  color: #fff;
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  box-shadow: 0 4px 12px -2px rgba(99, 102, 241, .45);
  white-space: nowrap;
}
.bd-or-badge--vertical {
  flex: 0 0 auto;
  align-self: center;
}

/* =========================
   Availability badge + panel
   Badge sits INSIDE the subdomain row as a slim right-edge cell so it stays
   inside the `overflow: hidden` clip and is visible on every viewport.
   ========================= */
.bd-availability {
  flex: 0 0 36px;
  width: 36px;
  align-self: stretch;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  background: var(--bd-input-bg);
  border-left: 1px solid var(--bd-input-line);
  color: var(--bd-text-muted);
  transition: background .2s var(--bd-ease), color .2s var(--bd-ease), border-color .2s var(--bd-ease);
}
.bd-availability[hidden] { display: none; }
.bd-availability--ok {
  background: linear-gradient(135deg, #10b981, #34d399);
  border-left-color: var(--bd-accent-3);
  color: #ffffff;
  animation: bd-avail-pop .35s var(--bd-ease) both;
}
.bd-availability--bad {
  background: linear-gradient(135deg, #ef4444, #f87171);
  border-left-color: #ef4444;
  color: #ffffff;
  animation: bd-avail-pop .35s var(--bd-ease) both;
}
.bd-availability--loading {
  background: var(--bd-input-bg-soft);
  border-left-color: var(--bd-accent);
  color: var(--bd-accent);
}
@keyframes bd-avail-pop {
  0%   { transform: scale(.6); opacity: 0; }
  60%  { transform: scale(1.12); opacity: 1; }
  100% { transform: scale(1);    opacity: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .bd-availability--ok, .bd-availability--bad { animation: none; }
}
.bd-spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid var(--bd-accent);
  border-top-color: transparent;
  animation: bd-spin .8s linear infinite;
}
@keyframes bd-spin { to { transform: rotate(360deg); } }
.sr-only {
  position: absolute !important;
  width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden;
  clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

.bd-subdomain-double { padding-right: 0; }

.bd-availability-panel {
  margin-top: 10px;
  padding: 10px 14px;
  border-radius: var(--bd-radius-sm);
  background: linear-gradient(135deg, rgba(236, 72, 153, .06), rgba(239, 68, 68, .04));
  border: 1px solid rgba(239, 68, 68, .25);
  animation: bd-stagger-in .25s var(--bd-ease) both;
}
.bd-availability-panel--bad { /* default */ }
.bd-availability-msg {
  margin: 0 0 6px;
  font-size: 13px;
  color: #b91c1c;
  font-weight: 600;
}
.bd-availability-suggestions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}
.bd-availability-label {
  font-size: 12px;
  color: var(--bd-text-muted);
  font-weight: 600;
  margin-right: 4px;
}
.bd-availability-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.bd-suggest-chip {
  display: inline-flex;
  align-items: center;
  padding: 5px 12px;
  border-radius: var(--bd-radius-pill);
  font-size: 12.5px;
  font-weight: 600;
  font-family: var(--bd-font-en);
  background: var(--bd-card-bg);
  border: 1.5px solid var(--bd-input-line);
  color: var(--bd-accent);
  cursor: pointer;
  transition: all .15s var(--bd-ease);
}
.bd-suggest-chip:hover {
  border-color: var(--bd-accent);
  background: var(--bd-accent-soft);
  transform: translateY(-1px);
  box-shadow: 0 4px 10px -3px rgba(99, 102, 241, .35);
}

/* =========================
   Live domain preview chip
   "আপনার ওয়েবসাইট হবে : foo.smartschool.bd"
   Updates as the user types; shows green/red status from the
   /signup/check_subdomain AJAX response.
   ========================= */
.bd-domain-preview {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
  padding: 12px 16px;
  border-radius: var(--bd-radius-pill);
  background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
  border: 1px solid #bbf7d0;
  color: #065f46;
  font-family: var(--bd-font-en);
  font-size: 14px;
  line-height: 1.4;
  box-shadow: 0 6px 16px -10px rgba(16, 185, 129, .35);
  transition: background .3s var(--bd-ease), border-color .3s var(--bd-ease), box-shadow .3s var(--bd-ease), transform .3s var(--bd-ease);
  animation: bd-preview-in .35s var(--bd-ease) both;
}
.bd-domain-preview[hidden] { display: none; }
.bd-domain-preview-label {
  font-family: var(--bd-font-bn);
  font-weight: 600;
  font-size: 14px;
  color: #047857;
  line-height: 1.55;
}
.bd-domain-preview-url {
  font-family: ui-monospace, 'SF Mono', monospace;
  font-weight: 700;
  font-size: 15px;
  letter-spacing: .01em;
  padding: 4px 12px;
  border-radius: var(--bd-radius-pill);
  background: rgba(255, 255, 255, .92);
  border: 1px solid rgba(16, 185, 129, .35);
  color: #047857;
  word-break: break-all;
  transition: color .25s var(--bd-ease), background .25s var(--bd-ease), border-color .25s var(--bd-ease);
}
.bd-domain-preview-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  font-size: 14px;
  font-weight: 800;
  margin-left: auto;
  flex-shrink: 0;
  transition: background .25s var(--bd-ease), color .25s var(--bd-ease), transform .25s var(--bd-ease);
}
.bd-domain-preview-status:empty { display: none; }
.bd-domain-preview--ok {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-color: #10b981;
  box-shadow: 0 8px 22px -8px rgba(16, 185, 129, .50);
}
.bd-domain-preview--ok .bd-domain-preview-status {
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  animation: bd-preview-pop .45s var(--bd-ease) both;
}
.bd-domain-preview--bad {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border-color: #fecaca;
  color: #991b1b;
  box-shadow: 0 8px 22px -8px rgba(239, 68, 68, .35);
}
.bd-domain-preview--bad .bd-domain-preview-label { color: #b91c1c; }
.bd-domain-preview--bad .bd-domain-preview-url {
  background: #fff;
  border-color: rgba(239, 68, 68, .35);
  color: #b91c1c;
}
.bd-domain-preview--bad .bd-domain-preview-status {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff;
  animation: bd-preview-pop .45s var(--bd-ease) both;
}
.bd-domain-preview--loading .bd-domain-preview-status {
  background: rgba(99, 102, 241, .12);
  color: var(--bd-accent);
}
.bd-domain-preview--empty {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-color: #e2e8f0;
  color: var(--bd-text-muted);
  box-shadow: none;
}
.bd-domain-preview--empty .bd-domain-preview-label { color: var(--bd-text-muted); }
.bd-domain-preview--empty .bd-domain-preview-url {
  background: #fff;
  border-color: var(--bd-input-line);
  color: var(--bd-text-dim);
  font-style: italic;
}
.bd-domain-preview--empty .bd-domain-preview-status { display: none; }

@keyframes bd-preview-in {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes bd-preview-pop {
  0%   { transform: scale(.4) rotate(-15deg); opacity: 0; }
  60%  { transform: scale(1.15) rotate(0); opacity: 1; }
  100% { transform: scale(1)    rotate(0); opacity: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .bd-domain-preview, .bd-domain-preview-status { animation: none; }
}

/* =========================
   Plan picker (variants C/D)
   ========================= */
.bd-plans {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}
.bd-plan {
  position: relative;
  padding: 16px;
  border: 1.5px solid var(--bd-card-border);
  border-radius: var(--bd-radius-sm);
  background: var(--bd-card-bg);
  color: var(--bd-text);
  cursor: pointer;
  transition: border-color .2s var(--bd-ease), transform .15s var(--bd-ease), box-shadow .2s var(--bd-ease);
}
.bd-plan input { position: absolute; opacity: 0; pointer-events: none; }
.bd-plan:hover {
  border-color: var(--bd-accent);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px -8px rgba(99, 102, 241, .35);
}
.bd-plan:has(input:checked) {
  border-color: var(--bd-accent);
  box-shadow: 0 0 0 1px var(--bd-accent), 0 10px 22px -10px rgba(99, 102, 241, .45);
}
.bd-plan strong { display: block; font-size: 15px; font-weight: 700; color: var(--bd-text); }
.bd-plan .bd-price {
  font-size: 19px;
  font-weight: 800;
  margin: 4px 0 6px;
  background: linear-gradient(120deg, var(--bd-accent), var(--bd-accent-2));
  -webkit-background-clip: text;
          background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent;
}
.bd-plan .bd-price small { font-size: 12px; font-weight: 500; color: var(--bd-text-muted); -webkit-text-fill-color: var(--bd-text-muted);}
.bd-plan small { color: var(--bd-text-muted); font-size: 12.5px; }

/* =========================
   Checkbox + hint + error
   ========================= */
.bd-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13.5px;
  color: var(--bd-text-soft);
  margin: 14px 0 18px;
  cursor: pointer;
}
.bd-check input { margin-top: 3px; accent-color: var(--bd-accent); }
.bd-check a { color: var(--bd-accent); text-decoration: underline; }
.bd-hint { font-size: 13px; font-weight: 500; color: var(--bd-text-muted); margin-top: 6px; display: block; line-height: 1.6; }
.bd-hint[hidden] { display: none !important; }
.bd-hint strong { color: var(--bd-text-soft); font-weight: 700; }

.bd-error {
  background: rgba(236, 72, 153, .06);
  border-left: 4px solid var(--bd-accent-warm);
  color: #831843;
  padding: 12px 14px;
  border-radius: var(--bd-radius-sm);
  margin-bottom: 16px;
  font-size: 13.5px;
}
.bd-error ul { margin: 4px 0 0 18px; padding: 0; }

/* =========================
   Animated CTA button
   ========================= */
.bd-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-family: var(--bd-font-bn);
  font-weight: 500;
  padding: 15px 36px;
  border-radius: var(--bd-radius-pill);
  border: 0;
  font-size: 17px;
  cursor: pointer;
  overflow: hidden;
  isolation: isolate;
  color: #fff;
  background: linear-gradient(120deg, var(--bd-accent), var(--bd-accent-2), var(--bd-accent-3));
  background-size: 200% 100%;
  background-position: 0% 50%;
  box-shadow: 0 14px 28px -10px rgba(79, 70, 229, .55), 0 6px 14px -6px rgba(8, 145, 178, .35);
  transition: transform .15s var(--bd-ease), box-shadow .25s var(--bd-ease), background-position .5s var(--bd-ease);
}
.bd-btn:hover { background-position: 100% 50%; }
.bd-btn::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(120deg, var(--bd-accent-warm), var(--bd-accent), var(--bd-accent-2));
  opacity: 0;
  transition: opacity .35s var(--bd-ease);
  z-index: -1;
}
.bd-btn::after {
  content: "";
  position: absolute;
  top: 0; left: -40%; bottom: 0;
  width: 30%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,.45), transparent);
  transform: skewX(-20deg);
  transition: left .8s var(--bd-ease);
}
.bd-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -8px rgba(99, 102, 241, .60); }
.bd-btn:hover::before { opacity: 1; }
.bd-btn:hover::after { left: 110%; }
.bd-btn:active { transform: translateY(0); }
.bd-btn .bd-btn-arrow {
  display: inline-block;
  font-family: var(--bd-font-en);
  font-weight: 700;
  transition: transform .25s var(--bd-ease);
}
.bd-btn:hover .bd-btn-arrow { transform: translateX(4px); }
.bd-btn-en {
  font-family: var(--bd-font-en);
  font-weight: 600;
  font-size: 15px;
}

.bd-submit-row {
  margin-top: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  flex-wrap: wrap;
}

/* =========================
   Progress chip (live %)
   ========================= */
.bd-progress {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: var(--bd-radius-pill);
  background: var(--bd-input-bg-soft);
  border: 1px solid var(--bd-card-border);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bd-text-soft);
  font-family: var(--bd-font-en);
  transition: background .25s var(--bd-ease), border-color .25s var(--bd-ease);
}
.bd-progress-ring {
  width: 18px; height: 18px;
  border-radius: 50%;
  background: conic-gradient(var(--bd-accent) calc(var(--bd-pct, 0) * 1%), var(--bd-input-line) 0);
  position: relative;
  transition: background .3s var(--bd-ease);
}
.bd-progress-ring::after {
  content: "";
  position: absolute;
  inset: 3px;
  border-radius: 50%;
  background: var(--bd-card-bg);
}
.bd-progress[data-complete="true"] {
  background: rgba(16, 185, 129, .08);
  border-color: rgba(16, 185, 129, .35);
  color: #065f46;
}
.bd-progress[data-complete="true"] .bd-progress-ring {
  background: conic-gradient(var(--bd-accent-3) 100%, var(--bd-input-line) 0);
}

/* Plan-section label color */
.bd-card .bd-label-plans { color: var(--bd-text); }

/* =========================
   Variant theme hooks
   A: soft aurora glow on white  (default)
   B: dotted geometric grid
   C: tilted card on warm cream wash
   D: monochrome white / ink
   ========================= */
.bd-shell--aurora { /* default — uses :root mesh */ }

.bd-shell--dots {
  --bd-bg-accent-1: #e0e7ff;
  --bd-bg-accent-2: #cffafe;
  --bd-bg-accent-3: #fce7f3;
}
.bd-shell--dots::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background:
    radial-gradient(circle at 1px 1px, rgba(15, 23, 42, .10) 1px, transparent 0) 0 0 / 18px 18px,
    radial-gradient(circle at 1px 1px, rgba(15, 23, 42, .05) 1px, transparent 0) 9px 9px / 18px 18px;
  mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, #000 0%, transparent 75%);
  -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, #000 0%, transparent 75%);
}

.bd-shell--tilt {
  --bd-accent:        #f97316; /* orange */
  --bd-accent-2:      #ef4444; /* red */
  --bd-accent-3:      #f59e0b; /* amber */
  --bd-accent-warm:   #ec4899; /* pink */
  --bd-accent-soft:   rgba(249, 115, 22, .10);
  --bd-bg-accent-1:   #fff7ed;
  --bd-bg-accent-2:   #ffe4e6;
  --bd-bg-accent-3:   #fef3c7;
}
.bd-shell--tilt .bd-card {
  transform: perspective(1200px) rotateX(.4deg) rotateY(-1deg);
}
.bd-shell--tilt .bd-card:hover {
  transform: perspective(1200px) rotateX(0) rotateY(0) translateY(-2px);
}
.bd-shell--tilt .bd-plan {
  transition: transform .25s var(--bd-ease), border-color .2s var(--bd-ease), box-shadow .2s var(--bd-ease);
}
.bd-shell--tilt .bd-plan:hover {
  transform: perspective(800px) rotateY(-3deg) translateY(-3px);
}

.bd-shell--mono {
  --bd-accent:        #111827;
  --bd-accent-2:      #4b5563;
  --bd-accent-3:      #9ca3af;
  --bd-accent-warm:   #111827;
  --bd-accent-soft:   rgba(17, 24, 39, .06);
  --bd-bg:            #ffffff;
  --bd-bg-accent-1:   #f5f5f4;
  --bd-bg-accent-2:   #fafaf9;
  --bd-bg-accent-3:   #ffffff;
  --bd-input-line:    #d4d4d4;
  --bd-input-line-hover:#a3a3a3;
  --bd-card-border:   #e5e5e5;
}
.bd-shell--mono .bd-card::after {
  background: var(--bd-text);
}
.bd-shell--mono .bd-card h1 {
  background: linear-gradient(120deg, #0a0a0a 0%, #525252 50%, #0a0a0a 100%);
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent; color: transparent;
}
.bd-shell--mono .bd-card .bd-warning {
  background: #fafaf9;
  color: var(--bd-text-soft);
  border-color: #e5e5e5;
}
.bd-shell--mono .bd-btn {
  background: linear-gradient(120deg, #111827, #374151);
  box-shadow: 0 10px 22px -8px rgba(17, 24, 39, .45);
}
.bd-shell--mono .bd-btn::before {
  background: linear-gradient(120deg, #0a0a0a, #1f2937);
}
.bd-shell--mono .bd-or-badge {
  background: var(--bd-text);
  box-shadow: 0 4px 12px -2px rgba(17, 24, 39, .35);
}
.bd-shell--mono .bd-subdomain .bd-fix--suffix {
  color: var(--bd-text);
  background: #f5f5f4;
}
.bd-shell--mono .bd-subdomain-pick-one {
  background: #fafaf9;
  border-color: #e5e5e5;
}
.bd-shell--mono .bd-subdomain-pick-one .bd-pick-icon,
.bd-shell--mono .bd-subdomain-pick-one strong { color: var(--bd-text); background: none; }

/* ============================================================
   3-step wizard — stepper + per-step panels + nav buttons
   ============================================================ */
.bd-stepper {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 0 18px;
  margin: 0 0 14px;
  border-bottom: 1px solid var(--bd-card-border);
}
.bd-stepper-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 10px 4px 4px;
  border-radius: 999px;
  cursor: default;
  transition: background .15s ease, color .15s ease;
  user-select: none;
  flex: 0 0 auto;
}
.bd-stepper-item.is-done { cursor: pointer; }
.bd-stepper-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: #e5e7eb;
  color: #475569;
  font-weight: 700;
  font-size: 13px;
  flex: 0 0 auto;
}
.bd-stepper-item.is-active .bd-stepper-num {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  box-shadow: 0 6px 14px -4px rgba(37, 99, 235, .55);
}
.bd-stepper-item.is-done .bd-stepper-num {
  background: #16a34a;
  color: #fff;
}
.bd-stepper-label {
  display: inline-flex;
  flex-direction: column;
  line-height: 1.15;
}
.bd-stepper-label strong { font-size: 13px; font-weight: 700; color: #0f172a; }
.bd-stepper-label small  { font-size: 11px; color: #475569; }
.bd-stepper-line {
  flex: 1 1 auto;
  height: 2px;
  background: linear-gradient(to right, #e5e7eb, #e5e7eb);
  border-radius: 2px;
  min-width: 16px;
}

.bd-step {
  display: none;
  animation: bdStepFade .25s ease;
}
.bd-step.is-active { display: block; }
@keyframes bdStepFade {
  from { opacity: 0; transform: translateY(4px); }
  to   { opacity: 1; transform: translateY(0); }
}

.bd-step-helper {
  margin: 0 0 14px;
  padding: 8px 12px;
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(37, 99, 235, .08), rgba(16, 185, 129, .08));
  color: #1f2937;
  font-size: 14px;
  font-weight: 600;
  font-family: 'Tiro Bangla', 'Hind Siliguri', serif;
}

.bd-step-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px dashed var(--bd-card-border);
}
.bd-btn--ghost {
  background: #f1f5f9;
  color: #0f172a;
}
.bd-btn--ghost:hover { background: #e2e8f0; }
.bd-btn--back { /* no special styling — uses .bd-btn--ghost */ }
.bd-btn-arrow--rev { display: inline-block; transform: rotate(180deg) translateY(1px); }

.bd-field--span-2 { grid-column: 1 / -1; }
.bd-field--subtype select {
  background: #fef3c7;
  border-color: #f59e0b;
}
.bd-field--subtype label { color: #92400e; }
/* `.bd-field { display: block }` (line ~328) wins specificity over the
   browser's default `[hidden] { display: none }` rule, so the HTML
   `hidden` attribute alone won't actually hide a `.bd-field`. Force it
   here so the cascading sub-type wrap can be reliably toggled. */
.bd-field[hidden],
[data-bd-subtype-wrap][hidden] { display: none !important; }

@media (max-width: 640px) {
  .bd-stepper { gap: 4px; padding-bottom: 12px; }
  .bd-stepper-item { padding: 2px 4px; }
  .bd-stepper-label strong { font-size: 11px; }
  .bd-stepper-label small  { font-size: 9px; }
  .bd-stepper-num { width: 22px; height: 22px; font-size: 11px; }
  .bd-step-nav { flex-direction: column; align-items: stretch; }
  .bd-step-nav .bd-btn { width: 100%; }
}
</style>
