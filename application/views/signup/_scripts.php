<?php
/**
 * Shared signup scripts + decorative SVG layer.
 * Loaded by every variant_*.php after the form.
 *
 * Provides:
 *   - Floating SVG decorations (book / star / cap / pencil)
 *   - Pick-one enforcement between the per-suffix subdomain inputs
 *   - Live progress chip (% required fields complete)
 *   - Anti-tamper hint if extra subdomain fields are injected
 *   - prefers-reduced-motion aware
 */
?>
<div class="bd-deco" aria-hidden="true">
  <!-- Open book -->
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M2 6c4-2 8-2 10 0M22 6c-4-2-8-2-10 0M2 6v13c4-2 8-2 10 0M22 6v13c-4-2-8-2-10 0M12 6v13"/>
  </svg>
  <!-- Star -->
  <svg viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2l2.6 6.6L22 9.3l-5.6 4.6L18.1 22 12 18l-6.1 4 1.7-8.1L2 9.3l7.4-.7z"/>
  </svg>
  <!-- Graduation cap -->
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M2 9l10-5 10 5-10 5L2 9zM6 11v5c0 2 3 3 6 3s6-1 6-3v-5M22 9v6"/>
  </svg>
  <!-- Pencil -->
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M3 21l4-1 12-12-3-3L4 17l-1 4zM15 6l3 3"/>
  </svg>
  <!-- Sparkle -->
  <svg viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z"/>
  </svg>
  <!-- Bookmark -->
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M6 3h12v18l-6-4-6 4z"/>
  </svg>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var form = document.querySelector('.bd-card form');
  if (!form) return;

  // ----- Pick-one between subdomain_* inputs -----
  // When the visitor types into one of the subdomain inputs, disable+dim
  // the others. The disabled inputs are excluded from `required` counting
  // by the progress chip and from the validity model so the browser does
  // not block submission with "Please fill out this field".
  var pickoneInputs = Array.prototype.slice.call(
    form.querySelectorAll('[data-bd-pickone-input]')
  );
  var errorBox = form.querySelector('[data-bd-pickone-error]');

  function syncPickone(){
    var anyFilled = pickoneInputs.some(function(i){ return (i.value || '').trim() !== ''; });
    var filledCount = pickoneInputs.filter(function(i){ return (i.value || '').trim() !== ''; }).length;
    pickoneInputs.forEach(function(input){
      var row    = input.closest('.bd-subdomain');
      var filled = (input.value || '').trim() !== '';
      if (filled) {
        row && row.classList.add('bd-subdomain--filled');
        row && row.classList.remove('bd-subdomain--disabled');
        input.removeAttribute('disabled');
      } else if (anyFilled) {
        row && row.classList.remove('bd-subdomain--filled');
        row && row.classList.add('bd-subdomain--disabled');
        // keep it tabbable so they can switch — only soft-disable visually
        input.setAttribute('tabindex', '-1');
      } else {
        row && row.classList.remove('bd-subdomain--filled');
        row && row.classList.remove('bd-subdomain--disabled');
        input.removeAttribute('tabindex');
      }
    });
    // Pick-one error if somehow multiple have values
    if (errorBox) {
      if (filledCount > 1) {
        errorBox.textContent = 'Please fill in only ONE of the two boxes — clear the other.';
        errorBox.hidden = false;
      } else {
        errorBox.textContent = '';
        errorBox.hidden = true;
      }
    }
  }
  pickoneInputs.forEach(function(input){
    input.addEventListener('input', function(){
      // If user starts typing in a previously-empty input, clear any other
      // input that already had a value so we maintain pick-one.
      if ((input.value || '').trim() !== '') {
        pickoneInputs.forEach(function(other){
          if (other !== input && (other.value || '').trim() !== '') {
            other.value = '';
          }
        });
      }
      syncPickone();
      recomputeProgress();
    });
    input.addEventListener('focus', function(){
      // Clicking on a disabled-looking row clears the other so this one
      // becomes the active choice.
      var other = pickoneInputs.find(function(i){ return i !== input && (i.value || '').trim() !== ''; });
      if (other) {
        other.value = '';
        syncPickone();
        recomputeProgress();
      }
    });
  });

  // On submit, ensure exactly one is filled
  form.addEventListener('submit', function(e){
    if (!pickoneInputs.length) return;
    var filled = pickoneInputs.filter(function(i){ return (i.value || '').trim() !== ''; });
    if (filled.length !== 1) {
      e.preventDefault();
      if (errorBox) {
        errorBox.textContent = filled.length === 0
          ? 'Please fill in the website short name in ONE of the two boxes.'
          : 'Please fill in only ONE of the two boxes — clear the other.';
        errorBox.hidden = false;
        errorBox.scrollIntoView({behavior: 'smooth', block: 'center'});
      }
    }
  });

  syncPickone();

  // ----- Live progress chip -----
  var chip = document.querySelector('[data-bd-progress]');
  var ring  = chip && chip.querySelector('.bd-progress-ring');
  var label = chip && chip.querySelector('.bd-progress-label');

  function requiredFields(){
    var fields = Array.prototype.slice.call(
      form.querySelectorAll('[required]')
    );
    // Treat the pick-one group as a SINGLE required field: if any is filled,
    // count as 1; otherwise 0.
    return fields;
  }
  function isFilled(el){
    if (el.type === 'checkbox') return el.checked;
    if (el.type === 'radio') {
      return !!form.querySelector('input[name="'+el.name+'"]:checked');
    }
    return (el.value || '').trim() !== '';
  }
  function recomputeProgress(){
    if (!chip) return;
    var fields = requiredFields();
    var seen = {};
    var unique = [];
    fields.forEach(function(f){
      if (f.type === 'radio') {
        if (seen['radio:' + f.name]) return;
        seen['radio:' + f.name] = 1;
      }
      unique.push(f);
    });
    var done = unique.filter(isFilled).length;
    var total = unique.length;
    // Plus 1 virtual required-slot for the pick-one subdomain group
    var pickoneDone = pickoneInputs.length > 0
      ? (pickoneInputs.some(function(i){ return (i.value || '').trim() !== ''; }) ? 1 : 0)
      : 0;
    if (pickoneInputs.length > 0) total += 1;
    done += pickoneDone;

    var pct = total ? Math.round(done / total * 100) : 0;
    if (ring)  ring.style.setProperty('--bd-pct', pct);
    if (label) label.textContent = pct + '% complete';
    chip.setAttribute('data-complete', pct === 100 ? 'true' : 'false');
  }
  form.addEventListener('input',  recomputeProgress);
  form.addEventListener('change', recomputeProgress);
  recomputeProgress();

  // ----- AJAX subdomain availability check -----
  // Debounce input on each subdomain input, hit /signup/check_subdomain,
  // render an availability badge inside the input row + a suggestions
  // panel underneath when the slug is taken.
  var availTimer = null;
  var availPanel       = form.querySelector('[data-bd-availability-panel]');
  var availMsg         = form.querySelector('[data-bd-availability-msg]');
  var availSugWrap     = form.querySelector('[data-bd-availability-suggestions]');
  var availSugChips    = form.querySelector('[data-bd-availability-chips]');
  var checkUrl         = (form.getAttribute('action') || '').replace(/\/signup\/?[a-d]?\/?$/, '/signup/check_subdomain');
  if (!/\/signup\/check_subdomain$/.test(checkUrl)) {
    checkUrl = (window.BD_BASE_URL || '/') + 'signup/check_subdomain';
  }

  function setAvailIcon(input, state){
    var badge = input.parentElement && input.parentElement.querySelector('[data-bd-availability]');
    var row   = input.closest('.bd-subdomain');
    if (row) {
      row.classList.remove('bd-subdomain--ok', 'bd-subdomain--bad');
      if (state === 'ok')  row.classList.add('bd-subdomain--ok');
      if (state === 'bad') row.classList.add('bd-subdomain--bad');
    }
    if (!badge) return;
    badge.hidden = false;
    badge.classList.remove('bd-availability--ok','bd-availability--bad','bd-availability--loading');
    if (state === 'ok')      { badge.classList.add('bd-availability--ok');      badge.innerHTML = '<span aria-hidden="true">✓</span><span class="sr-only">Available</span>'; }
    else if (state === 'bad'){ badge.classList.add('bd-availability--bad');     badge.innerHTML = '<span aria-hidden="true">✕</span><span class="sr-only">Taken</span>'; }
    else if (state === 'loading'){
      badge.classList.add('bd-availability--loading');
      badge.innerHTML = '<span class="bd-spinner" aria-hidden="true"></span><span class="sr-only">Checking…</span>';
    } else {
      badge.hidden = true;
    }
  }

  // ----- Live domain preview chip ("আপনার ওয়েবসাইট হবে : ...") -----
  // Rebuilds the preview line every time the user types or AJAX comes back.
  // `state` is one of: 'empty' | 'loading' | 'ok' | 'bad'.
  var previewEl     = form.querySelector('[data-bd-preview]');
  var previewUrlEl  = form.querySelector('[data-bd-preview-url]');
  var previewStatEl = form.querySelector('[data-bd-preview-status]');
  var previewPlaceholder = previewUrlEl ? previewUrlEl.textContent : '';

  function setPreview(value, suffix, state){
    if (!previewEl || !previewUrlEl) return;
    previewEl.classList.remove('bd-domain-preview--ok','bd-domain-preview--bad','bd-domain-preview--loading','bd-domain-preview--empty');
    var v = (value || '').trim();
    if (!v) {
      previewEl.classList.add('bd-domain-preview--empty');
      previewUrlEl.textContent = previewPlaceholder;
      if (previewStatEl) previewStatEl.textContent = '';
      return;
    }
    previewUrlEl.textContent = v + (suffix ? ('.' + suffix) : '');
    if (state === 'ok') {
      previewEl.classList.add('bd-domain-preview--ok');
      if (previewStatEl) previewStatEl.innerHTML = '<span aria-hidden="true">✓</span><span class="sr-only">Available</span>';
    } else if (state === 'bad') {
      previewEl.classList.add('bd-domain-preview--bad');
      if (previewStatEl) previewStatEl.innerHTML = '<span aria-hidden="true">✕</span><span class="sr-only">Taken</span>';
    } else if (state === 'loading') {
      previewEl.classList.add('bd-domain-preview--loading');
      if (previewStatEl) previewStatEl.innerHTML = '<span class="bd-spinner" aria-hidden="true"></span><span class="sr-only">Checking…</span>';
    } else {
      if (previewStatEl) previewStatEl.textContent = '';
    }
  }

  function renderAvailPanel(input, payload){
    if (!availPanel) return;
    if (!payload || payload.available) {
      availPanel.hidden = true;
      if (availMsg)      availMsg.textContent = '';
      if (availSugWrap)  availSugWrap.hidden = true;
      if (availSugChips) availSugChips.innerHTML = '';
      return;
    }
    availPanel.hidden = false;
    availPanel.classList.toggle('bd-availability-panel--bad', !payload.available);
    if (availMsg) availMsg.textContent = payload.reason || 'This subdomain is not available.';

    if (payload.suggestions && payload.suggestions.length) {
      availSugWrap.hidden = false;
      availSugChips.innerHTML = '';
      payload.suggestions.forEach(function(s){
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'bd-suggest-chip';
        b.textContent = s;
        b.addEventListener('click', function(){
          input.value = s;
          input.dispatchEvent(new Event('input', {bubbles:true}));
          input.focus();
        });
        availSugChips.appendChild(b);
      });
    } else {
      availSugWrap.hidden = true;
      availSugChips.innerHTML = '';
    }
  }

  function checkAvailability(input){
    var v = (input.value || '').trim();
    if (v.length < 3) {
      setAvailIcon(input, '');
      renderAvailPanel(input, null);
      return;
    }
    setAvailIcon(input, 'loading');
    var data = new FormData();
    data.append('subdomain', v);
    var suffix = input.getAttribute('data-bd-suffix') || '';
    if (suffix) data.append('suffix', suffix);
    // CodeIgniter 3 CSRF: pick up whichever hidden token the form already
    // carries (name is configurable; default install uses 'school_csrf_name').
    // The token is re-read on every request so a `csrf_regenerate=true`
    // config also works — we always send the latest value the page knows of.
    var csrfInput = form.querySelector('input[type=hidden][name*="csrf" i]');
    if (csrfInput && csrfInput.name) {
      data.append(csrfInput.name, csrfInput.value || '');
    }

    fetch(checkUrl, {method:'POST', body:data, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(j){
        // Only render result if input value hasn't changed since the request
        if ((input.value || '').trim() !== v) return;
        var sfx = input.getAttribute('data-bd-suffix') || '';
        if (j && j.available) {
          setAvailIcon(input, 'ok');
          renderAvailPanel(input, j);
          setPreview(v, sfx, 'ok');
        } else {
          setAvailIcon(input, 'bad');
          renderAvailPanel(input, j);
          setPreview(v, sfx, 'bad');
        }
      })
      .catch(function(){
        setAvailIcon(input, '');
        setPreview(input.value, input.getAttribute('data-bd-suffix') || '', 'loading');
      });
  }

  function activePickoneInput(){
    return pickoneInputs.find(function(i){ return (i.value || '').trim() !== ''; }) || null;
  }
  function refreshPreviewFromCurrent(state){
    var active = activePickoneInput();
    if (!active) { setPreview('', '', 'empty'); return; }
    setPreview(active.value, active.getAttribute('data-bd-suffix') || '', state || 'loading');
  }

  pickoneInputs.forEach(function(input){
    input.addEventListener('input', function(){
      clearTimeout(availTimer);
      // Hide panel + icon for the OTHER input(s) — we're checking just this one
      pickoneInputs.forEach(function(other){
        if (other !== input) setAvailIcon(other, '');
      });
      if ((input.value || '').trim() === '') {
        setAvailIcon(input, '');
        renderAvailPanel(input, null);
        // Either the user just cleared THIS one, or it was already empty —
        // either way the preview reflects whichever (if any) is still filled.
        refreshPreviewFromCurrent('loading');
        return;
      }
      // Live-update the preview URL immediately so it tracks every keystroke;
      // AJAX result will upgrade the status pill from 'loading' to ok/bad.
      setPreview(input.value, input.getAttribute('data-bd-suffix') || '', 'loading');
      availTimer = setTimeout(function(){ checkAvailability(input); }, 350);
    });
    // Initial check if the field was server-prefilled (validation re-render)
    if ((input.value || '').trim() !== '') {
      setPreview(input.value, input.getAttribute('data-bd-suffix') || '', 'loading');
      checkAvailability(input);
    }
  });
  // Initial state of the preview chip (in case server pre-filled one input).
  refreshPreviewFromCurrent('loading');

  // ----- Hidden-field tamper hint -----
  function checkExtra(){
    var subs = form.querySelectorAll(
      'input[name="subdomain"], input[name="subdomain[]"],' +
      'input[name^="subdomain_"]:not([data-bd-pickone-input]),' +
      'input[name^="subdomain_"][type="hidden"]'
    );
    var hint = form.querySelector('[data-bd-extra-warn]');
    if (hint) hint.hidden = !(subs.length > 0);
  }
  form.addEventListener('submit', checkExtra);
  if (window.MutationObserver) {
    new MutationObserver(checkExtra).observe(form, {childList: true, subtree: true});
  }
  checkExtra();
});
</script>
