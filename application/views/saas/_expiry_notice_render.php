<?php
/**
 * Non-dismissible "জরুরী বিজ্ঞপ্তি"-style floating dialog. Surfaces on every
 * tenant admin page when the current branch's subscription matches the
 * configured trigger.
 *
 * Pulls config from the saas_setting key/value table. Renders NOTHING when:
 *   - settings table is missing
 *   - expiry_notice_enabled !== '1'
 *   - viewer is super-admin (would obscure the management UI)
 *   - viewer's role is not in the configured audience
 *   - tenant has no subscription row or it doesn't match the trigger
 *
 * No application changes required to install — this file is loaded once
 * from application/views/layout/index.php right before </body>.
 */

$CI =& get_instance();
if (function_exists('is_superadmin_loggedin') && is_superadmin_loggedin()) return;

if (!$CI->db->table_exists('saas_setting')) return;

$CI->load->model('saas_setting_model');
$s = $CI->saas_setting_model->getAllAsMap();

if (($s['expiry_notice_enabled'] ?? '0') !== '1') return;

// Audience gate.
$audience = $s['expiry_notice_show_to_users'] ?? 'admin';
$role     = (int)$CI->session->userdata('loggedin_role_id');
$showHere = false;
if ($audience === 'everyone') {
    $showHere = true;
} elseif ($audience === 'admin_teacher') {
    $showHere = in_array($role, [2, 3], true)
        || (function_exists('is_teacher_loggedin') && is_teacher_loggedin());
} else { // 'admin'
    $showHere = $role === 2 || (function_exists('is_admin_loggedin') && is_admin_loggedin());
}
if (!$showHere) return;

// Resolve current branch + subscription.
$branchId = method_exists($CI->application_model, 'get_branch_id')
    ? (int)$CI->application_model->get_branch_id()
    : 0;
if ($branchId <= 0) return;

$CI->load->model('saas_model');
$sub = $CI->saas_model->getSubscriptionByBranch($branchId);
if (!$sub) return;

// Compute days_left.
$expireRaw = $sub->current_period_end ?: ($sub->expire_date ?: null);
$daysLeft  = null;
if ($expireRaw) {
    $ts = strtotime($expireRaw);
    if ($ts !== false) {
        $now = strtotime(date('Y-m-d') . ' 00:00:00');
        $daysLeft = (int)floor(($ts - $now) / 86400);
    }
}

// Trigger gate.
$trigger    = $s['expiry_notice_trigger'] ?? 'any_problem';
$daysBefore = (int)($s['expiry_notice_days_before'] ?? '7');
$status     = (string)$sub->status;
$isPastDue   = $status === 'past_due';
$isSuspended = $status === 'suspended';
$isCancelled = $status === 'cancelled';
$isExpiring  = $daysLeft !== null && $daysLeft <= $daysBefore;

$fire = false;
switch ($trigger) {
    case 'past_due':       $fire = $isPastDue; break;
    case 'suspended':      $fire = $isSuspended || $isCancelled; break;
    case 'expiring_soon':  $fire = $isExpiring; break;
    case 'any_problem':
    default:
        $fire = $isPastDue || $isSuspended || $isCancelled || $isExpiring;
        break;
}
if (!$fire) return;

// Resolve placeholders (used by body + WhatsApp message).
$schoolName = method_exists($CI->application_model, 'getBranchData')
    ? (string)($CI->application_model->getBranchData('name') ?? '')
    : '';
$placeholders = [
    '{school_name}'   => $schoolName,
    '{expire_date}'   => $expireRaw ?: '',
    '{days_left}'     => $daysLeft !== null ? (string)$daysLeft : '',
    '{support_email}' => (string)($s['expiry_notice_support_email'] ?? ''),
    '{website}'       => (string)($s['expiry_notice_website'] ?? ''),
];

$body     = strtr((string)($s['expiry_notice_body'] ?? ''), $placeholders);
$bodyHtml = nl2br(html_escape($body));

$title         = (string)($s['expiry_notice_title'] ?? 'জরুরী বিজ্ঞপ্তি');
$bg            = (string)($s['expiry_notice_bg_color']   ?? '#8B1F1F');
$fg            = (string)($s['expiry_notice_text_color'] ?? '#FFFFFF');
$position      = (string)($s['expiry_notice_position']   ?? 'bottom-right');
$supportEmail  = (string)($s['expiry_notice_support_email'] ?? '');
$website       = (string)($s['expiry_notice_website']       ?? '');

// Build action button URLs.
$payUrl = trim((string)($s['expiry_notice_payment_url'] ?? 'subscription'));
if ($payUrl !== '' && !preg_match('~^https?://~i', $payUrl)) {
    $payUrl = base_url($payUrl);
}
$payLabel = (string)($s['expiry_notice_payment_label'] ?? 'Login & Pay');

$waNumber  = preg_replace('/\D+/', '', (string)($s['expiry_notice_whatsapp_number'] ?? ''));
$waLabel   = (string)($s['expiry_notice_whatsapp_label']   ?? 'WhatsApp Support');
$waMessage = strtr((string)($s['expiry_notice_whatsapp_message'] ?? ''), $placeholders);
$waUrl     = $waNumber !== ''
    ? ('https://wa.me/' . $waNumber . ($waMessage !== '' ? ('?text=' . rawurlencode($waMessage)) : ''))
    : '';
?>

<style>
.ssbd-expiry-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 99998;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.ssbd-expiry-card {
    position: fixed;
    z-index: 99999;
    width: 420px;
    max-width: calc(100vw - 32px);
    border-radius: 12px;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
    overflow: hidden;
    font-family: inherit;
    border: 4px solid rgba(255, 255, 255, 0.18);
    box-sizing: border-box;
}
.ssbd-expiry-card.ssbd-bottom-right { bottom: 24px; right: 24px; }
.ssbd-expiry-card.ssbd-bottom-left  { bottom: 24px; left: 24px; }
.ssbd-expiry-card.ssbd-center {
    position: relative;
    margin: 0 auto;
    max-width: min(420px, 95vw);
}
.ssbd-expiry-card .ssbd-head {
    padding: 14px 18px;
    background: rgba(0, 0, 0, 0.18);
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.3;
}
.ssbd-expiry-card .ssbd-body {
    padding: 16px 18px 14px;
    font-size: 14px;
    line-height: 1.55;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    max-height: 50vh;
    overflow-y: auto;
}
.ssbd-expiry-card .ssbd-actions {
    padding: 0 14px 14px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ssbd-expiry-card .ssbd-btn {
    flex: 1 1 140px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    font-weight: 600;
    font-size: 14px;
    border: 0;
    border-radius: 8px;
    text-decoration: none !important;
    cursor: pointer;
    transition: transform 0.06s ease, filter 0.12s ease;
    line-height: 1.2;
}
.ssbd-expiry-card .ssbd-btn:hover { filter: brightness(1.05); }
.ssbd-expiry-card .ssbd-btn:active { transform: translateY(1px); }
.ssbd-btn-pay { background: #ffd166; color: #1f1f1f; }
.ssbd-btn-wa  { background: #25D366; color: #fff; }

.ssbd-expiry-card .ssbd-foot {
    padding: 0 18px 16px;
    font-size: 13px;
}
.ssbd-expiry-card a { color: inherit; text-decoration: underline; font-weight: 600; }
.ssbd-expiry-meta {
    margin-top: 4px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.25);
    font-size: 12.5px;
    opacity: 0.9;
}

/* Phone — full-width sticky bottom card, stacked buttons. Applies to ALL
   positions because a tiny floating card is impossible to read on a phone. */
@media (max-width: 520px) {
    .ssbd-expiry-card,
    .ssbd-expiry-card.ssbd-bottom-right,
    .ssbd-expiry-card.ssbd-bottom-left,
    .ssbd-expiry-card.ssbd-center {
        position: fixed;
        left: 0; right: 0; bottom: 0; top: auto;
        margin: 0;
        width: 100%;
        max-width: 100%;
        border-radius: 14px 14px 0 0;
        border-width: 2px 0 0 0;
        max-height: 92vh;
        overflow-y: auto;
        padding-bottom: max(0px, env(safe-area-inset-bottom));
    }
    .ssbd-expiry-overlay { padding: 0; align-items: flex-end; }
    .ssbd-expiry-card .ssbd-actions { flex-direction: column; }
    .ssbd-expiry-card .ssbd-btn { flex-basis: auto; width: 100%; padding: 12px 14px; font-size: 15px; }
    .ssbd-expiry-card .ssbd-head { font-size: 15px; padding: 12px 16px; }
    .ssbd-expiry-card .ssbd-body { font-size: 13.5px; padding: 14px 16px 10px; max-height: 45vh; }
    .ssbd-expiry-card .ssbd-foot { padding: 0 16px 14px; font-size: 12px; }
}

/* Tablet — slightly wider card on bottom-right/left so Bangla wraps well. */
@media (min-width: 521px) and (max-width: 820px) {
    .ssbd-expiry-card { width: 380px; }
    .ssbd-expiry-card.ssbd-bottom-right { bottom: 16px; right: 16px; }
    .ssbd-expiry-card.ssbd-bottom-left  { bottom: 16px; left: 16px; }
}
</style>

<?php if ($position === 'center'): ?>
<div class="ssbd-expiry-overlay" id="ssbd-expiry-overlay" role="dialog" aria-modal="true" aria-labelledby="ssbd-expiry-title">
  <div class="ssbd-expiry-card ssbd-center"
       style="background: <?=html_escape($bg)?>; color: <?=html_escape($fg)?>;">
<?php else: ?>
  <div class="ssbd-expiry-card ssbd-<?=html_escape($position)?>" id="ssbd-expiry-card"
       role="dialog" aria-labelledby="ssbd-expiry-title"
       style="background: <?=html_escape($bg)?>; color: <?=html_escape($fg)?>;">
<?php endif; ?>
    <div class="ssbd-head" id="ssbd-expiry-title">
      <i class="fas fa-exclamation-triangle"></i>
      <span><?=html_escape($title)?></span>
    </div>
    <div class="ssbd-body"><?=$bodyHtml?></div>

    <?php if ($payUrl !== '' || $waUrl !== ''): ?>
    <div class="ssbd-actions">
      <?php if ($payUrl !== ''): ?>
        <a href="<?=html_escape($payUrl)?>" class="ssbd-btn ssbd-btn-pay">
          <i class="fas fa-credit-card"></i>
          <span><?=html_escape($payLabel)?></span>
        </a>
      <?php endif; ?>
      <?php if ($waUrl !== ''): ?>
        <a href="<?=html_escape($waUrl)?>" class="ssbd-btn ssbd-btn-wa" target="_blank" rel="noopener">
          <i class="fab fa-whatsapp"></i>
          <span><?=html_escape($waLabel)?></span>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($supportEmail || $website || $daysLeft !== null): ?>
    <div class="ssbd-foot">
      <div class="ssbd-expiry-meta">
        <?php if ($supportEmail): ?>
          <div>
            <i class="fas fa-envelope"></i>
            <a href="mailto:<?=html_escape($supportEmail)?>"><?=html_escape($supportEmail)?></a>
          </div>
        <?php endif; ?>
        <?php if ($website): ?>
          <div>
            <i class="fas fa-globe"></i>
            <?php
              $linkHref = preg_match('~^https?://~i', $website) ? $website : ('https://' . ltrim($website, '/'));
            ?>
            <a href="<?=html_escape($linkHref)?>" target="_blank" rel="noopener"><?=html_escape($website)?></a>
          </div>
        <?php endif; ?>
        <?php if ($daysLeft !== null): ?>
          <div style="margin-top:6px;">
            <small>
              <i class="far fa-clock"></i>
              <?php if ($daysLeft >= 0): ?>
                <?=$daysLeft?> day(s) remaining · expires <?=html_escape($expireRaw)?>
              <?php else: ?>
                Expired <?=abs($daysLeft)?> day(s) ago (<?=html_escape($expireRaw)?>)
              <?php endif; ?>
            </small>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
<?php if ($position === 'center'): ?>
</div>
<?php endif; ?>
