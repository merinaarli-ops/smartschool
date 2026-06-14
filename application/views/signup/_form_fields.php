<?php /**
 * Shared form fields for every signup variant — bd.education-style layout.
 *
 * Expects:
 *   $with_package        : bool
 *   $packages            : array
 *   $domain_suffixes     : string[]
 *   $divisions           : stdClass[] from bd_geo_model->divisions()
 *   $boards              : map key=>label
 *   $designations        : map key=>label
 *   $institute_types     : map key=>label
 *   $institute_subtypes  : map [type=>map[subkey=>label]]  (cascading)
 *
 * The form is laid out as a 3-step wizard:
 *   Step 1 — General info       (সাধারণ তথ্য)        : contact person
 *   Step 2 — Institution info   (প্রতিষ্ঠানের তথ্য)  : school + location
 *   Step 3 — Other info         (অন্যান্য তথ্য)      : domain + package + terms
 *
 * If JS is disabled the wizard degrades to a long scrollable form.
 */
$suffixes      = isset($domain_suffixes) && is_array($domain_suffixes) && $domain_suffixes
    ? $domain_suffixes
    : ['smartschool.bd'];
$pickedSuffix  = set_value('domain_suffix') ?: $suffixes[0];
$divisions     = $divisions          ?? [];
$boards        = $boards             ?? [];
$designations  = $designations       ?? [];
$instTypes     = $institute_types    ?? [];
$instSubtypes  = $institute_subtypes ?? [];

$opt = function (array $map, $current, $placeholder) {
    $h = '<option value="">' . $placeholder . '</option>';
    foreach ($map as $k => $label) {
        $sel = ((string)$current === (string)$k) ? ' selected' : '';
        $h .= '<option value="' . html_escape($k) . '"' . $sel . '>' . $label . '</option>';
    }
    return $h;
};

// Pre-rendered sub-type dropdowns, keyed by parent institute_type.
// The cascading JS unhides the one that matches the picked parent.
$subtypeJson = json_encode($instSubtypes, JSON_UNESCAPED_UNICODE);
?>
<?php if (validation_errors()): ?>
  <div class="bd-error">
    <strong>Please fix:</strong>
    <?= validation_errors('<ul><li>', '</li></ul>'); ?>
  </div>
<?php endif; ?>

<!-- Step indicator -->
<div class="bd-stepper" data-bd-stepper>
  <div class="bd-stepper-item is-active" data-bd-step-indicator="1">
    <span class="bd-stepper-num">1</span>
    <span class="bd-stepper-label">
      <strong>General info</strong>
      <small lang="bn">সাধারণ তথ্য</small>
    </span>
  </div>
  <div class="bd-stepper-line"></div>
  <div class="bd-stepper-item" data-bd-step-indicator="2">
    <span class="bd-stepper-num">2</span>
    <span class="bd-stepper-label">
      <strong>Institution info</strong>
      <small lang="bn">প্রতিষ্ঠানের তথ্য</small>
    </span>
  </div>
  <div class="bd-stepper-line"></div>
  <div class="bd-stepper-item" data-bd-step-indicator="3">
    <span class="bd-stepper-num">3</span>
    <span class="bd-stepper-label">
      <strong>Other info</strong>
      <small lang="bn">অন্যান্য তথ্য</small>
    </span>
  </div>
</div>

<!-- ============ Step 1 — General info (সাধারণ তথ্য) ============ -->
<div class="bd-step is-active" data-bd-step="1">
  <p class="bd-step-helper" lang="bn">সঠিক তথ্য দিয়ে পূরণ করুন</p>

  <div class="bd-grid">
    <div class="bd-section bd-section--pink">
      <span class="bd-section-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </span>
      <span class="bd-section-title">Contact person<small lang="bn">যোগাযোগের তথ্য</small></span>
    </div>
    <div class="bd-field">
      <label class="bd-label">Your name <span class="req">*</span></label>
      <input class="bd-input" name="owner_name" value="<?= set_value('owner_name'); ?>"
             placeholder="Headmaster / Principal" required>
    </div>
    <div class="bd-field">
      <label class="bd-label">Designation</label>
      <select class="bd-select" name="designation">
        <?= $opt($designations, set_value('designation'), '— Select —'); ?>
      </select>
    </div>

    <div class="bd-field">
      <label class="bd-label">WhatsApp / Mobile <span class="req">*</span></label>
      <input class="bd-input" name="owner_phone" value="<?= set_value('owner_phone'); ?>"
             placeholder="01XXXXXXXXX" required>
    </div>
    <div class="bd-field">
      <label class="bd-label">Official email <span class="req">*</span></label>
      <input class="bd-input" type="email" name="owner_email" value="<?= set_value('owner_email'); ?>"
             placeholder="you@school.edu.bd" required>
    </div>
  </div>

  <div class="bd-step-nav">
    <span></span>
    <button type="button" class="bd-btn bd-btn--ghost" data-bd-next="2">
      পরবর্তী &nbsp;·&nbsp; Next
      <span class="bd-btn-arrow" aria-hidden="true">→</span>
    </button>
  </div>
</div>

<!-- ============ Step 2 — Institution info (প্রতিষ্ঠানের তথ্য) ============ -->
<div class="bd-step" data-bd-step="2">
  <p class="bd-step-helper" lang="bn">সঠিক তথ্য দিয়ে পূরণ করুন</p>

  <div class="bd-grid">
    <div class="bd-section">
      <span class="bd-section-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
      </span>
      <span class="bd-section-title">Institution<small lang="bn">প্রতিষ্ঠানের তথ্য</small></span>
    </div>
    <div class="bd-field">
      <label class="bd-label">School name <span class="req">*</span></label>
      <input class="bd-input" name="school_name" value="<?= set_value('school_name'); ?>"
             placeholder="e.g. Dhaka Model School" required>
    </div>
    <div class="bd-field">
      <label class="bd-label">School name (বাংলা)</label>
      <input class="bd-input" name="school_name_bn" value="<?= set_value('school_name_bn'); ?>"
             placeholder="ঢাকা মডেল স্কুল">
    </div>

    <div class="bd-field">
      <label class="bd-label">EIIN / EMIS code</label>
      <input class="bd-input" name="eiin_code" value="<?= set_value('eiin_code'); ?>"
             placeholder="6-digit EIIN (optional)" maxlength="32">
    </div>
    <div class="bd-field">
      <label class="bd-label">Type of institute <span class="req">*</span></label>
      <select class="bd-select" name="institute_type" id="bd-institute-type" required>
        <?= $opt($instTypes, set_value('institute_type'), '— Select —'); ?>
      </select>
    </div>

    <!-- Cascading sub-type field — only shown when "school" is picked.
         Server-side validation in Signup::_render_variant() discards a
         posted value that doesn't match the picked parent type. -->
    <div class="bd-field bd-field--span-2 bd-field--subtype" data-bd-subtype-wrap hidden>
      <label class="bd-label">What type of school? <span class="req">*</span>
        <small lang="bn"> (স্কুলের ধরন)</small>
      </label>
      <select class="bd-select" name="institute_subtype" id="bd-institute-subtype">
        <option value="">— Select —</option>
      </select>
    </div>

    <div class="bd-section bd-section--amber">
      <span class="bd-section-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </span>
      <span class="bd-section-title">Academic &amp; Location<small lang="bn">শিক্ষাবোর্ড ও ঠিকানা</small></span>
    </div>
    <div class="bd-field">
      <label class="bd-label">Education board</label>
      <select class="bd-select" name="education_board">
        <?= $opt($boards, set_value('education_board'), '— Select —'); ?>
      </select>
    </div>
    <div class="bd-field">
      <label class="bd-label">Division</label>
      <select class="bd-select" name="division_id" id="bd-division">
        <option value="">— Select —</option>
        <?php $selDiv = set_value('division_id'); foreach ($divisions as $d): ?>
          <option value="<?= (int)$d->id; ?>" <?= ((string)$selDiv === (string)$d->id) ? 'selected' : ''; ?>>
            <?= html_escape($d->name); ?> <?= $d->bn_name ? ' / ' . $d->bn_name : ''; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="bd-field">
      <label class="bd-label">District</label>
      <select class="bd-select" name="district_id" id="bd-district" data-current="<?= set_value('district_id'); ?>">
        <option value="">— Select division first —</option>
      </select>
    </div>
    <div class="bd-field">
      <label class="bd-label">Upazila / Thana</label>
      <select class="bd-select" name="upazila_id" id="bd-upazila" data-current="<?= set_value('upazila_id'); ?>">
        <option value="">— Select district first —</option>
      </select>
    </div>
  </div>

  <div class="bd-step-nav">
    <button type="button" class="bd-btn bd-btn--ghost bd-btn--back" data-bd-prev="1">
      <span class="bd-btn-arrow bd-btn-arrow--rev" aria-hidden="true">←</span>
      পূর্ববর্তী &nbsp;·&nbsp; Back
    </button>
    <button type="button" class="bd-btn bd-btn--ghost" data-bd-next="3">
      পরবর্তী &nbsp;·&nbsp; Next
      <span class="bd-btn-arrow" aria-hidden="true">→</span>
    </button>
  </div>
</div>

<!-- ============ Step 3 — Other info (অন্যান্য তথ্য) ============ -->
<div class="bd-step" data-bd-step="3">
  <p class="bd-step-helper" lang="bn">সঠিক তথ্য দিয়ে পূরণ করুন</p>

  <div class="bd-section bd-section--green bd-section--standalone" style="margin-top: 4px;">
    <span class="bd-section-icon" aria-hidden="true">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg>
    </span>
    <span class="bd-section-title">Website domain<small lang="bn">আপনার ওয়েবসাইটের ঠিকানা</small></span>
  </div>
  <div class="bd-field bd-field-subdomain" style="margin-top: 8px;" data-bd-suffix-count="<?= count($suffixes); ?>" data-bd-pickone>
    <label class="bd-label">Short name / prefix for website domain <span class="req">*</span></label>
    <?php if (count($suffixes) > 1): ?>
      <p class="bd-subdomain-pick-one" lang="bn">
        <span class="bd-pick-icon" aria-hidden="true">❖</span>
        ওয়েবসাইট নাম নিচের যেকোন <strong>একটা</strong> পূরণ করুন প্রতিষ্ঠানের নামের সংক্ষিপ্ত রুপ দিয়ে
      </p>
      <div class="bd-subdomain-double">
        <?php $sub_old = [
            'subdomain_smartschool.bd' => set_value('subdomain_smartschool_bd'),
            'subdomain_institution.bd' => set_value('subdomain_institution_bd'),
        ]; ?>
        <?php foreach ($suffixes as $i => $sfx): ?>
          <?php $name = 'subdomain_' . str_replace('.', '_', $sfx); ?>
          <div class="bd-subdomain bd-subdomain--row" data-bd-suffix-row="<?= html_escape($sfx); ?>">
            <span class="bd-fix">www.</span>
            <input name="<?= html_escape($name); ?>"
                   pattern="[a-z0-9_-]{3,64}"
                   value="<?= html_escape(set_value($name)); ?>"
                   placeholder="example: dkmschool"
                   autocomplete="off"
                   maxlength="64"
                   data-bd-pickone-input
                   data-bd-suffix="<?= html_escape($sfx); ?>">
            <span class="bd-fix bd-fix--suffix">.<?= html_escape($sfx); ?></span>
            <span class="bd-availability" data-bd-availability hidden></span>
          </div>
          <?php if ($i === 0 && count($suffixes) > 1): ?>
            <span class="bd-or-badge bd-or-badge--vertical" aria-hidden="true">OR</span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="bd-availability-panel" data-bd-availability-panel hidden>
        <p class="bd-availability-msg" data-bd-availability-msg></p>
        <div class="bd-availability-suggestions" data-bd-availability-suggestions hidden>
          <span class="bd-availability-label">Try one of these:</span>
          <div class="bd-availability-chips" data-bd-availability-chips></div>
        </div>
      </div>
    <?php else: ?>
      <div class="bd-subdomain" data-bd-suffix-row="<?= html_escape($suffixes[0]); ?>">
        <span class="bd-fix">www.</span>
        <input name="subdomain" pattern="[a-z0-9_-]{3,64}" value="<?= set_value('subdomain'); ?>"
               placeholder="example: dkmschool" required autocomplete="off" maxlength="64"
               data-bd-subdomain
               data-bd-pickone-input
               data-bd-suffix="<?= html_escape($suffixes[0]); ?>">
        <span class="bd-fix bd-fix--suffix">.<?= html_escape($suffixes[0]); ?></span>
        <span class="bd-availability" data-bd-availability hidden></span>
        <input type="hidden" name="domain_suffix" value="<?= html_escape($suffixes[0]); ?>">
      </div>
      <div class="bd-availability-panel" data-bd-availability-panel hidden>
        <p class="bd-availability-msg" data-bd-availability-msg></p>
        <div class="bd-availability-suggestions" data-bd-availability-suggestions hidden>
          <span class="bd-availability-label">Try one of these:</span>
          <div class="bd-availability-chips" data-bd-availability-chips></div>
        </div>
      </div>
    <?php endif; ?>
    <small class="bd-hint">Lowercase letters, numbers, hyphens. Min 3 characters. <strong>Fill in exactly one</strong> — leave the other blank.</small>
    <p class="bd-hint bd-pickone-error" data-bd-pickone-error hidden style="color:#d6336c;font-weight:600;"></p>
    <!-- Live preview chip: updated in JS as the user types in either input.
         Mirrors the value being checked + the AJAX availability result. -->
    <div class="bd-domain-preview bd-domain-preview--empty" data-bd-preview>
      <span class="bd-domain-preview-label" lang="bn">আপনার ওয়েবসাইট হবে :</span>
      <span class="bd-domain-preview-url" data-bd-preview-url>your-name.<?= html_escape($suffixes[0]); ?></span>
      <span class="bd-domain-preview-status" data-bd-preview-status aria-live="polite"></span>
    </div>
  </div>

  <?php if (!empty($with_package) && !empty($packages)): ?>
    <div class="bd-field" style="margin-top: 14px;">
      <label class="bd-label bd-label-plans">Choose your plan <span class="req">*</span></label>
      <div class="bd-plans">
        <?php foreach ($packages as $p): ?>
          <label class="bd-plan">
            <input type="radio" name="package_id" value="<?= (int)$p->id; ?>"
                   <?= $p->is_default_trial ? 'checked' : ''; ?>>
            <strong><?= html_escape($p->name); ?></strong>
            <div class="bd-price">
              ৳<?= number_format((float)$p->price_bdt, 0); ?>
              <small><?= $p->billing_period === 'yearly' ? '/ yr' : '/ mo'; ?></small>
            </div>
            <small><?= html_escape($p->description); ?></small>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="bd-field" style="margin-top: 12px;">
    <label class="bd-label">Anything else? <small style="opacity:.7">(optional)</small></label>
    <textarea class="bd-input" name="notes" rows="2"
              placeholder="e.g. 350 students, two campuses, need parent SMS"><?= set_value('notes'); ?></textarea>
  </div>

  <label class="bd-check">
    <input type="checkbox" name="terms_accept" value="1" required>
    <span>I agree to the
      <a href="<?= base_url('terms'); ?>" target="_blank">Terms of Service</a>
      and <a href="<?= base_url('privacy'); ?>" target="_blank">Privacy Policy</a>.
    </span>
  </label>

  <p class="bd-hint" data-bd-extra-warn hidden style="color:#d6336c;font-weight:600;">
    Multiple subdomain fields detected. Only one will be used — please reload the page if this looks unexpected.
  </p>

  <div class="bd-step-nav bd-submit-row">
    <button type="button" class="bd-btn bd-btn--ghost bd-btn--back" data-bd-prev="2">
      <span class="bd-btn-arrow bd-btn-arrow--rev" aria-hidden="true">←</span>
      পূর্ববর্তী &nbsp;·&nbsp; Back
    </button>
    <button type="submit" name="submit" value="apply" class="bd-btn">
      সাবমিট করুন &nbsp;&middot;&nbsp;
      <?= !empty($with_package) ? 'Start trial' : 'Submit signup'; ?>
      <span class="bd-btn-arrow" aria-hidden="true">→</span>
    </button>
    <span class="bd-progress" data-bd-progress data-complete="false">
      <span class="bd-progress-ring" aria-hidden="true"></span>
      <span class="bd-progress-label">0% complete</span>
    </span>
  </div>
</div>

<script>
window.BD_INSTITUTE_SUBTYPES = <?= $subtypeJson; ?>;
window.BD_PRESELECTED_INSTITUTE_TYPE    = <?= json_encode(set_value('institute_type')); ?>;
window.BD_PRESELECTED_INSTITUTE_SUBTYPE = <?= json_encode(set_value('institute_subtype')); ?>;
</script>

<script>
(function(){
  // ============ 3-step wizard navigation ============
  var stepEls    = document.querySelectorAll('[data-bd-step]');
  var indicators = document.querySelectorAll('[data-bd-step-indicator]');
  if (!stepEls.length) return;

  function activateStep(n){
    n = String(n);
    stepEls.forEach(function(el){
      el.classList.toggle('is-active', el.getAttribute('data-bd-step') === n);
    });
    indicators.forEach(function(el){
      var k = el.getAttribute('data-bd-step-indicator');
      el.classList.toggle('is-active', k === n);
      el.classList.toggle('is-done',  Number(k) < Number(n));
    });
    // Smooth-scroll back to the top of the wizard.
    var top = document.querySelector('.bd-stepper');
    if (top && top.scrollIntoView) top.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function validateStep(n){
    var panel = document.querySelector('[data-bd-step="' + n + '"]');
    if (!panel) return true;
    var bad = null;
    Array.prototype.forEach.call(panel.querySelectorAll('[required]'), function(el){
      if (bad) return;
      if (el.type === 'checkbox') {
        if (!el.checked) bad = el;
      } else if (!String(el.value || '').trim()) {
        bad = el;
      }
    });
    if (bad) {
      bad.focus();
      try { bad.reportValidity(); } catch(_e){}
      return false;
    }
    return true;
  }

  document.querySelectorAll('[data-bd-next]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var current = btn.closest('[data-bd-step]').getAttribute('data-bd-step');
      if (!validateStep(current)) return;
      activateStep(btn.getAttribute('data-bd-next'));
    });
  });
  document.querySelectorAll('[data-bd-prev]').forEach(function(btn){
    btn.addEventListener('click', function(){
      activateStep(btn.getAttribute('data-bd-prev'));
    });
  });
  // Click an indicator to jump back to a completed step (but never forward
  // without going through validate).
  indicators.forEach(function(el){
    el.addEventListener('click', function(){
      var target = Number(el.getAttribute('data-bd-step-indicator'));
      var current = Number(document.querySelector('[data-bd-step].is-active').getAttribute('data-bd-step'));
      if (target < current) activateStep(target);
    });
  });

  // ============ Cascading institute_type → sub-type ============
  var typeEl    = document.getElementById('bd-institute-type');
  var subEl     = document.getElementById('bd-institute-subtype');
  var subWrap   = document.querySelector('[data-bd-subtype-wrap]');
  var subMap    = window.BD_INSTITUTE_SUBTYPES || {};
  var preSub    = window.BD_PRESELECTED_INSTITUTE_SUBTYPE || '';

  function refreshSubtype(){
    if (!typeEl || !subEl || !subWrap) return;
    var t      = typeEl.value;
    var subs   = subMap[t] || null;
    if (!subs || !Object.keys(subs).length) {
      subWrap.hidden = true;
      subEl.required = false;
      subEl.innerHTML = '<option value="">— Select —</option>';
      subEl.value = '';
      return;
    }
    subWrap.hidden = false;
    subEl.required = true;
    var html = '<option value="">— Select —</option>';
    Object.keys(subs).forEach(function(k){
      var lbl = subs[k];
      var sel = (String(preSub) === String(k)) ? ' selected' : '';
      html += '<option value="' + k + '"' + sel + '>' + lbl + '</option>';
    });
    subEl.innerHTML = html;
  }
  if (typeEl) {
    typeEl.addEventListener('change', function(){
      preSub = ''; // reset preselect once the user changes parent type
      refreshSubtype();
    });
    refreshSubtype();
  }
})();
</script>

<script>
(function(){
  // Cascading Division -> District -> Upazila dropdowns (vanilla JS).
  var BASE = <?= json_encode(rtrim(base_url(), '/') . '/signup'); ?>;
  var divEl = document.getElementById('bd-division');
  var disEl = document.getElementById('bd-district');
  var upaEl = document.getElementById('bd-upazila');
  if (!divEl || !disEl || !upaEl) return;

  function fillOptions(el, rows, placeholder, preselect){
    el.innerHTML = '';
    var opt = document.createElement('option');
    opt.value = ''; opt.textContent = placeholder; el.appendChild(opt);
    rows.forEach(function(r){
      var o = document.createElement('option');
      o.value = r.id;
      o.textContent = r.name + (r.bn_name ? ' / ' + r.bn_name : '');
      if (preselect && String(preselect) === String(r.id)) o.selected = true;
      el.appendChild(o);
    });
  }

  function loadJson(url, cb){
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4) {
        if (xhr.status >= 200 && xhr.status < 300) {
          try { cb(JSON.parse(xhr.responseText) || []); }
          catch(e){ cb([]); }
        } else { cb([]); }
      }
    };
    xhr.send();
  }

  function loadDistricts(divisionId, preselect){
    if (!divisionId) {
      fillOptions(disEl, [], '— Select division first —', null);
      fillOptions(upaEl, [], '— Select district first —', null);
      return;
    }
    loadJson(BASE + '/ajax_districts/' + encodeURIComponent(divisionId), function(rows){
      fillOptions(disEl, rows, '— Select district —', preselect);
      if (preselect) loadUpazilas(preselect, upaEl.dataset.current);
      else fillOptions(upaEl, [], '— Select district first —', null);
    });
  }
  function loadUpazilas(districtId, preselect){
    if (!districtId) {
      fillOptions(upaEl, [], '— Select district first —', null);
      return;
    }
    loadJson(BASE + '/ajax_upazilas/' + encodeURIComponent(districtId), function(rows){
      fillOptions(upaEl, rows, '— Select upazila —', preselect);
    });
  }

  divEl.addEventListener('change', function(){ loadDistricts(divEl.value, null); });
  disEl.addEventListener('change', function(){ loadUpazilas(disEl.value, null); });
  if (divEl.value) loadDistricts(divEl.value, disEl.dataset.current);
})();
</script>
