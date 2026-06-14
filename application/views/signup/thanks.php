<?php
/**
 * Post-signup celebration page.
 *
 * Expected $data keys (set by Signup::thanks()):
 *   $title     — page title
 *   $summary   — array: school_name, school_name_bn, subdomain,
 *                domain_suffix, owner_name, owner_email, owner_phone
 *   $contact   — array: whatsapp (digits only), phone, email, facebook
 */
$summary = isset($summary) && is_array($summary) ? $summary : [];
$contact = isset($contact) && is_array($contact) ? $contact : [];

$schoolName   = trim((string)($summary['school_name']    ?? ''));
$schoolNameBn = trim((string)($summary['school_name_bn'] ?? ''));
$subdomain    = trim((string)($summary['subdomain']      ?? ''));
$suffix       = trim((string)($summary['domain_suffix']  ?? 'smartschool.bd'));
$ownerName    = trim((string)($summary['owner_name']     ?? ''));
$ownerEmail   = trim((string)($summary['owner_email']    ?? ''));
$ownerPhone   = trim((string)($summary['owner_phone']    ?? ''));

$fullHost = $subdomain !== '' ? ($subdomain . '.' . $suffix) : '';
$fullUrl  = $fullHost !== '' ? ('https://' . $fullHost) : '';

$waNumber = preg_replace('/\D+/', '', (string)($contact['whatsapp'] ?? ''));
$phone    = trim((string)($contact['phone']    ?? ''));
$email    = trim((string)($contact['email']    ?? 'support@smartschool.bd'));
$fbUrl    = trim((string)($contact['facebook'] ?? ''));

// Pre-filled WhatsApp message — short & Bangla-friendly.
$waMessage = 'আস্সালামু আলাইকুম, আমি ' . ($ownerName !== '' ? $ownerName : 'একজন ব্যবহারকারী')
    . ' — সবেমাত্র SmartSchool.bd-এ' . ($schoolName !== '' ? (' ' . $schoolName . ' এর জন্য') : '')
    . ' নিবন্ধন করেছি। দয়া করে সহায়তা করুন।';
$waLink = $waNumber !== ''
    ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waMessage)
    : '';

$mailSubject = 'SmartSchool.bd signup — ' . ($schoolName !== '' ? $schoolName : 'new application');
$mailLink    = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($mailSubject);
$telLink     = $phone !== '' ? ('tel:' . preg_replace('/[^0-9+]/', '', $phone)) : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= html_escape($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tiro+Bangla:ital@0;1&family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php $this->load->view('signup/_styles'); ?>
<style>
/* ============================================================
   Signup success — celebratory layout on top of _styles.php
   ============================================================ */
.bd-success-shell {
  padding: 28px 16px 80px;
  max-width: 880px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}
.bd-brand-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-weight: 800;
  font-size: 1.05rem;
  color: var(--bd-text);
  margin-bottom: 24px;
  letter-spacing: .2px;
}
.bd-brand-row .dot {
  width: 10px; height: 10px; border-radius: 999px;
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  box-shadow: 0 0 0 4px rgba(79,70,229,.12);
}

/* ---- Hero card ---- */
.bd-hero {
  position: relative;
  background: var(--bd-card-bg);
  border: 1px solid var(--bd-card-border);
  border-radius: var(--bd-radius);
  box-shadow: var(--bd-card-shadow-lg);
  padding: 44px 28px 36px;
  text-align: center;
  overflow: hidden;
}
.bd-hero::before {
  content: "";
  position: absolute; inset: 0;
  background:
    radial-gradient(60% 70% at 50% -10%, rgba(79,70,229,.16) 0%, transparent 60%),
    radial-gradient(45% 60% at 100% 100%, rgba(8,145,178,.14) 0%, transparent 60%),
    radial-gradient(45% 60% at 0% 100%, rgba(219,39,119,.12) 0%, transparent 60%);
  pointer-events: none;
}
.bd-hero > * { position: relative; }

.bd-check {
  width: 96px; height: 96px;
  margin: 0 auto 18px;
  border-radius: 999px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  display: flex; align-items: center; justify-content: center;
  box-shadow:
    0 18px 40px -10px rgba(5, 150, 105, .55),
    0 0 0 8px rgba(16, 185, 129, .12);
  animation: bd-check-pop .7s var(--bd-ease) both;
}
.bd-check svg { width: 52px; height: 52px; color: #fff; }
.bd-check svg path {
  stroke-dasharray: 60;
  stroke-dashoffset: 60;
  animation: bd-check-draw .6s .3s var(--bd-ease) forwards;
}
@keyframes bd-check-pop {
  0%   { transform: scale(0);    opacity: 0; }
  60%  { transform: scale(1.12); opacity: 1; }
  100% { transform: scale(1);    opacity: 1; }
}
@keyframes bd-check-draw {
  to { stroke-dashoffset: 0; }
}

.bd-headline {
  font-family: var(--bd-font-bn);
  font-size: clamp(2.6rem, 7vw, 4.4rem);
  font-weight: 700;
  line-height: 1.05;
  margin: 6px 0 4px;
  background: linear-gradient(120deg, #4f46e5 0%, #0891b2 45%, #059669 75%, #db2777 100%);
  background-size: 200% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: bd-shimmer 6s linear infinite;
  letter-spacing: -.5px;
}
@keyframes bd-shimmer {
  0%   { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}
.bd-headline-en {
  font-family: var(--bd-font-en);
  font-weight: 800;
  font-size: clamp(1rem, 2.4vw, 1.25rem);
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bd-text-muted);
  margin-bottom: 14px;
}
.bd-lede {
  font-family: var(--bd-font-bn);
  font-size: clamp(1.05rem, 2.6vw, 1.25rem);
  color: var(--bd-text-soft);
  margin: 6px auto 4px;
  max-width: 620px;
  line-height: 1.7;
}
.bd-lede-en {
  font-family: var(--bd-font-en);
  font-size: .98rem;
  color: var(--bd-text-muted);
  margin: 0 auto 22px;
  max-width: 560px;
}
.bd-lede strong { color: var(--bd-accent); font-weight: 700; }

/* ---- Domain pill ---- */
.bd-domain-pill {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
  padding: 10px 14px 10px 16px;
  background: linear-gradient(135deg, rgba(79,70,229,.08), rgba(8,145,178,.08));
  border: 1px dashed var(--bd-accent);
  border-radius: var(--bd-radius-pill);
  font-family: var(--bd-font-en);
  color: var(--bd-text);
  font-weight: 600;
  font-size: .98rem;
  max-width: 100%;
  word-break: break-all;
}
.bd-domain-pill .lbl {
  font-size: .72rem;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: var(--bd-text-muted);
  font-weight: 700;
  padding-right: 8px;
  border-right: 1px solid rgba(15,23,42,.12);
}
.bd-domain-pill .host { color: var(--bd-accent); }
.bd-domain-pill .copy-btn {
  appearance: none; border: 0; background: var(--bd-accent); color: #fff;
  font-weight: 600; font-size: .78rem; letter-spacing: .4px;
  padding: 6px 10px; border-radius: 999px; cursor: pointer;
  transition: transform .15s var(--bd-ease), background .15s var(--bd-ease);
}
.bd-domain-pill .copy-btn:hover { background: #4338ca; transform: translateY(-1px); }
.bd-domain-pill .copy-btn.copied { background: var(--bd-accent-3); }

/* ---- Timeline ---- */
.bd-timeline {
  margin-top: 32px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}
.bd-step {
  background: var(--bd-card-bg);
  border: 1px solid var(--bd-card-border);
  border-radius: var(--bd-radius-sm);
  box-shadow: var(--bd-card-shadow-sm);
  padding: 18px 16px 16px;
  text-align: left;
  position: relative;
  transition: transform .2s var(--bd-ease), box-shadow .2s var(--bd-ease);
}
.bd-step:hover { transform: translateY(-2px); box-shadow: var(--bd-card-shadow); }
.bd-step-num {
  position: absolute; top: -14px; left: 16px;
  width: 28px; height: 28px; border-radius: 999px;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: .9rem; color: #fff;
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  box-shadow: 0 4px 10px -2px rgba(79,70,229,.5);
}
.bd-step h4 {
  margin: 6px 0 4px;
  font-family: var(--bd-font-en);
  font-size: 1rem;
  font-weight: 700;
  color: var(--bd-text);
}
.bd-step h4[lang="bn"] { font-family: var(--bd-font-bn); font-weight: 700; font-size: 1.05rem; }
.bd-step p {
  margin: 0;
  font-size: .88rem;
  color: var(--bd-text-muted);
  line-height: 1.55;
}

/* ---- Section heading chip (matches signup form chips) ---- */
.bd-section-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 36px auto 14px;
  padding: 6px 14px;
  background: var(--bd-accent-soft);
  border-radius: 999px;
  font-family: var(--bd-font-en);
  font-weight: 700;
  font-size: .82rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bd-accent);
}
.bd-section-chip .dotx {
  width: 6px; height: 6px; border-radius: 999px;
  background: var(--bd-accent);
}

/* ---- Contact cards ---- */
.bd-contacts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-top: 6px;
}
.bd-contact {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: var(--bd-radius-sm);
  background: var(--bd-card-bg);
  border: 1px solid var(--bd-card-border);
  box-shadow: var(--bd-card-shadow-sm);
  color: var(--bd-text);
  text-decoration: none;
  transition: transform .2s var(--bd-ease), box-shadow .2s var(--bd-ease), border-color .2s var(--bd-ease);
  position: relative;
  overflow: hidden;
}
.bd-contact:hover {
  transform: translateY(-3px);
  box-shadow: var(--bd-card-shadow);
  text-decoration: none;
}
.bd-contact .ico {
  width: 48px; height: 48px;
  flex: 0 0 48px;
  border-radius: 14px;
  display: inline-flex; align-items: center; justify-content: center;
  color: #fff;
}
.bd-contact .ico svg { width: 24px; height: 24px; }
.bd-contact .meta { min-width: 0; }
.bd-contact .meta .ttl {
  font-family: var(--bd-font-en);
  font-weight: 700; font-size: .92rem;
  color: var(--bd-text);
  display: block;
  letter-spacing: .2px;
}
.bd-contact .meta .val {
  font-size: .86rem; color: var(--bd-text-muted);
  word-break: break-all;
  display: block;
}
.bd-contact .meta .sub-bn {
  font-family: var(--bd-font-bn);
  font-size: .82rem; color: var(--bd-text-muted);
  display: block;
  margin-top: 2px;
}

/* WhatsApp — green emphasis */
.bd-contact--wa {
  background: linear-gradient(135deg, #ffffff 0%, #ecfdf5 100%);
  border-color: #a7f3d0;
}
.bd-contact--wa .ico { background: linear-gradient(135deg, #25D366, #128C7E); }
.bd-contact--wa:hover { border-color: #34d399; }
.bd-contact--wa::after {
  content: "Chat now";
  position: absolute; top: 12px; right: 12px;
  font-size: .68rem; letter-spacing: 1px;
  font-weight: 800;
  background: #10b981; color: #fff;
  padding: 3px 8px; border-radius: 999px;
}

/* Phone — indigo */
.bd-contact--phone .ico { background: linear-gradient(135deg, #4f46e5, #6366f1); }
/* Email — cyan */
.bd-contact--mail  .ico { background: linear-gradient(135deg, #0891b2, #06b6d4); }
/* Messenger — purple/blue */
.bd-contact--fb    .ico { background: linear-gradient(135deg, #2563eb, #7c3aed); }

/* ---- CTA bar ---- */
.bd-cta-row {
  margin-top: 28px;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
}
.bd-btn {
  appearance: none; border: 0; cursor: pointer;
  font-family: var(--bd-font-en);
  font-size: .98rem;
  font-weight: 700;
  padding: 12px 22px;
  border-radius: 999px;
  text-decoration: none;
  display: inline-flex; align-items: center; gap: 8px;
  transition: transform .15s var(--bd-ease), box-shadow .15s var(--bd-ease), background .15s var(--bd-ease);
}
.bd-btn--primary {
  background: linear-gradient(135deg, var(--bd-accent), var(--bd-accent-2));
  color: #fff;
  box-shadow: 0 8px 20px -8px rgba(79,70,229,.6);
}
.bd-btn--primary:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -10px rgba(79,70,229,.7); text-decoration: none; }
.bd-btn--ghost {
  background: #fff;
  color: var(--bd-text);
  border: 1px solid var(--bd-card-border);
}
.bd-btn--ghost:hover { border-color: var(--bd-accent); color: var(--bd-accent); text-decoration: none; }

/* ---- Footer note ---- */
.bd-footer-note {
  margin-top: 30px;
  text-align: center;
  font-size: .85rem;
  color: var(--bd-text-muted);
}
.bd-footer-note span[lang="bn"] {
  display: block; margin-top: 4px;
  color: var(--bd-text-soft);
}

/* ---- Confetti canvas ---- */
#bd-confetti {
  position: fixed; inset: 0;
  pointer-events: none;
  z-index: 9999;
}

@media (max-width: 640px) {
  .bd-hero { padding: 32px 18px 28px; border-radius: 14px; }
  .bd-timeline { grid-template-columns: 1fr; }
}
@media (prefers-reduced-motion: reduce) {
  .bd-headline { animation: none; }
  .bd-check { animation: none; }
  .bd-check svg path { animation: none; stroke-dashoffset: 0; }
}
</style>
</head>
<body>
<canvas id="bd-confetti" aria-hidden="true"></canvas>

<div class="bd-shell bd-shell--aurora">
  <div class="bd-success-shell">

    <div class="bd-brand-row">
      <span class="dot"></span>
      SmartSchool.bd
    </div>

    <section class="bd-hero">
      <div class="bd-check" aria-hidden="true">
        <svg viewBox="0 0 52 52" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 27 L23 36 L39 18"></path>
        </svg>
      </div>

      <div class="bd-headline-en">Application received</div>
      <h1 class="bd-headline" lang="bn">অভিনন্দন!</h1>

      <p class="bd-lede" lang="bn">
        <?php if ($schoolName !== ''): ?>
          আপনার প্রতিষ্ঠান <strong><?= html_escape($schoolNameBn !== '' ? $schoolNameBn : $schoolName); ?></strong> এর নিবন্ধন আবেদন গ্রহণ করা হয়েছে।
        <?php else: ?>
          আপনার আবেদন সফলভাবে গ্রহণ করা হয়েছে।
        <?php endif; ?>
        আমরা ১ কর্মদিবসের মধ্যে আপনার সাথে যোগাযোগ করব।
      </p>
      <p class="bd-lede-en">
        <?php if ($schoolName !== ''): ?>
          We&rsquo;ve received the application for <strong><?= html_escape($schoolName); ?></strong> and will review it within 1 business day. Login details will be emailed to
          <strong><?= html_escape($ownerEmail !== '' ? $ownerEmail : 'you'); ?></strong> once approved.
        <?php else: ?>
          We&rsquo;ll review your application within 1 business day and email you the login link for your school once it&rsquo;s ready.
        <?php endif; ?>
      </p>

      <?php if ($fullHost !== ''): ?>
        <div class="bd-domain-pill" role="status">
          <span class="lbl">Your domain</span>
          <span class="host" id="bd-host"><?= html_escape($fullHost); ?></span>
          <button type="button" class="copy-btn" id="bd-copy-host" aria-label="Copy domain to clipboard">Copy</button>
        </div>
      <?php endif; ?>

      <!-- What happens next -->
      <div class="bd-timeline" aria-label="What happens next">
        <div class="bd-step">
          <span class="bd-step-num">1</span>
          <h4 lang="bn">যাচাই</h4>
          <h4>We review</h4>
          <p>Our team verifies your details and reserves <strong><?= html_escape($fullHost !== '' ? $fullHost : 'your subdomain'); ?></strong> for your school.</p>
        </div>
        <div class="bd-step">
          <span class="bd-step-num">2</span>
          <h4 lang="bn">যোগাযোগ</h4>
          <h4>We contact you</h4>
          <p>We&rsquo;ll call <?php if ($ownerPhone !== ''): ?><strong><?= html_escape($ownerPhone); ?></strong> or <?php endif; ?>WhatsApp you to confirm the package &amp; payment terms.</p>
        </div>
        <div class="bd-step">
          <span class="bd-step-num">3</span>
          <h4 lang="bn">চালু</h4>
          <h4>You go live</h4>
          <p>Your school website &amp; login link arrive in your inbox &mdash; usually within 24 hours.</p>
        </div>
      </div>
    </section>

    <!-- Contact cards -->
    <div style="text-align:center;">
      <span class="bd-section-chip"><span class="dotx"></span> Need help right now?</span>
    </div>

    <div class="bd-contacts">

      <?php if ($waLink !== ''): ?>
        <a class="bd-contact bd-contact--wa" href="<?= html_escape($waLink); ?>" target="_blank" rel="noopener">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="currentColor">
              <path d="M19.11 17.36c-.27-.13-1.6-.79-1.84-.88s-.43-.13-.61.13-.7.88-.86 1.06-.32.2-.59.07a7.36 7.36 0 0 1-2.16-1.34 8.17 8.17 0 0 1-1.5-1.86c-.16-.27 0-.41.12-.55s.27-.32.4-.48a1.85 1.85 0 0 0 .27-.45.5.5 0 0 0 0-.47c-.07-.13-.61-1.47-.84-2s-.45-.45-.61-.45h-.52a1 1 0 0 0-.73.34A3.05 3.05 0 0 0 8.7 12.6a5.29 5.29 0 0 0 1.11 2.81 12.13 12.13 0 0 0 4.65 4.11 15.43 15.43 0 0 0 1.55.57 3.72 3.72 0 0 0 1.71.11 2.79 2.79 0 0 0 1.84-1.3 2.27 2.27 0 0 0 .16-1.3c-.07-.13-.25-.2-.52-.34zM16.03 4a11.94 11.94 0 0 0-10.13 18.33L4 28l5.79-1.83A11.95 11.95 0 1 0 16.03 4zm0 21.86a9.85 9.85 0 0 1-5.02-1.37l-.36-.21-3.43 1.08 1.1-3.35-.23-.36a9.91 9.91 0 1 1 7.94 4.21z"/>
            </svg>
          </span>
          <span class="meta">
            <span class="ttl">WhatsApp Support</span>
            <span class="val">+<?= html_escape($waNumber); ?></span>
            <span class="sub-bn" lang="bn">এক ট্যাপে চ্যাট শুরু করুন</span>
          </span>
        </a>
      <?php endif; ?>

      <?php if ($telLink !== ''): ?>
        <a class="bd-contact bd-contact--phone" href="<?= html_escape($telLink); ?>">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </span>
          <span class="meta">
            <span class="ttl">Call us</span>
            <span class="val"><?= html_escape($phone); ?></span>
            <span class="sub-bn" lang="bn">সরাসরি ফোন করুন</span>
          </span>
        </a>
      <?php endif; ?>

      <a class="bd-contact bd-contact--mail" href="<?= html_escape($mailLink); ?>">
        <span class="ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="m3 7 9 6 9-6"/>
          </svg>
        </span>
        <span class="meta">
          <span class="ttl">Email</span>
          <span class="val"><?= html_escape($email); ?></span>
          <span class="sub-bn" lang="bn">ইমেইল করুন</span>
        </span>
      </a>

      <?php if ($fbUrl !== ''): ?>
        <a class="bd-contact bd-contact--fb" href="<?= html_escape($fbUrl); ?>" target="_blank" rel="noopener">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.04 2 11.04c0 2.85 1.46 5.4 3.78 7.06V22l3.46-1.9c.92.26 1.88.4 2.76.4 5.52 0 10-4.04 10-9.04S17.52 2 12 2zm1 12.18-2.55-2.72L5.66 14l5.18-5.5 2.6 2.72L18.34 8l-5.34 6.18z"/>
            </svg>
          </span>
          <span class="meta">
            <span class="ttl">Messenger</span>
            <span class="val">Chat on Facebook</span>
            <span class="sub-bn" lang="bn">মেসেঞ্জারে কথা বলুন</span>
          </span>
        </a>
      <?php endif; ?>
    </div>

    <div class="bd-cta-row">
      <a class="bd-btn bd-btn--primary" href="<?= html_escape(base_url()); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Back to home
      </a>
      <a class="bd-btn bd-btn--ghost" href="<?= html_escape(base_url('login')); ?>">
        Already have an account? Sign in
      </a>
    </div>

    <p class="bd-footer-note">
      &mdash; SmartSchool.bd
      <span lang="bn">আপনার স্কুলকে ডিজিটাল করার পথে আমরা আছি।</span>
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script>
(function () {
  // Respect reduced-motion preferences — no confetti, no shimmer.
  var prefersReduce = false;
  try { prefersReduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  catch (_) {}

  // ---- Confetti blast ----
  if (window.confetti && !prefersReduce) {
    var canvas = document.getElementById('bd-confetti');
    var fire = window.confetti.create(canvas, { resize: true, useWorker: true });

    var palette = ['#4f46e5', '#0891b2', '#059669', '#db2777', '#f59e0b', '#10b981'];

    // Big initial blast from the centre-top.
    fire({
      particleCount: 160,
      spread: 120,
      startVelocity: 55,
      origin: { x: 0.5, y: 0.35 },
      colors: palette
    });

    // Two side-cannon bursts a beat later.
    setTimeout(function () {
      fire({ particleCount: 90, angle: 60,  spread: 70, origin: { x: 0,   y: 0.7 }, colors: palette });
      fire({ particleCount: 90, angle: 120, spread: 70, origin: { x: 1,   y: 0.7 }, colors: palette });
    }, 350);

    // Gentle trailing burst from the top centre to round it off.
    setTimeout(function () {
      fire({ particleCount: 80, spread: 100, startVelocity: 40, origin: { x: 0.5, y: 0.2 }, colors: palette });
    }, 900);
  }

  // ---- Copy-to-clipboard for the domain pill ----
  var copyBtn = document.getElementById('bd-copy-host');
  var hostEl  = document.getElementById('bd-host');
  if (copyBtn && hostEl) {
    copyBtn.addEventListener('click', function () {
      var text = hostEl.textContent.trim();
      var done = function () {
        copyBtn.classList.add('copied');
        copyBtn.textContent = 'Copied';
        setTimeout(function () {
          copyBtn.classList.remove('copied');
          copyBtn.textContent = 'Copy';
        }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () {
          // Fallback below.
          fallbackCopy(text); done();
        });
      } else {
        fallbackCopy(text); done();
      }
    });
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
  }
})();
</script>
</body>
</html>
