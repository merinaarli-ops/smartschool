<?php
$origEnabled = $settings['expiry_notice_enabled'] ?? '0';

$title         = (string)($settings['expiry_notice_title'] ?? 'জরুরী বিজ্ঞপ্তি');
$bodyRaw       = (string)($settings['expiry_notice_body']  ?? '');
$bg            = (string)($settings['expiry_notice_bg_color']   ?? '#8B1F1F');
$fg            = (string)($settings['expiry_notice_text_color'] ?? '#FFFFFF');
$position      = (string)($settings['expiry_notice_position']   ?? 'bottom-right');
$supportEmail  = (string)($settings['expiry_notice_support_email'] ?? '');
$website       = (string)($settings['expiry_notice_website']       ?? '');
$payUrl        = (string)($settings['expiry_notice_payment_url']   ?? 'subscription');
$payLabel      = (string)($settings['expiry_notice_payment_label'] ?? 'Login & Pay');
$waNumber      = preg_replace('/\D+/', '', (string)($settings['expiry_notice_whatsapp_number'] ?? ''));
$waLabel       = (string)($settings['expiry_notice_whatsapp_label']   ?? 'WhatsApp Support');
$waMessageRaw  = (string)($settings['expiry_notice_whatsapp_message'] ?? '');

$placeholders = [
    '{school_name}'   => '[Preview School]',
    '{expire_date}'   => date('Y-m-d', strtotime('+3 days')),
    '{days_left}'     => '3',
    '{support_email}' => $supportEmail,
    '{website}'       => $website,
];
$body      = strtr($bodyRaw, $placeholders);
$bodyHtml  = nl2br(html_escape($body));
$waMessage = strtr($waMessageRaw, $placeholders);
$waUrl     = $waNumber !== ''
    ? ('https://wa.me/' . $waNumber . ($waMessage !== '' ? ('?text=' . rawurlencode($waMessage)) : ''))
    : '';

if ($payUrl !== '' && !preg_match('~^https?://~i', $payUrl)) {
    $payUrl = base_url($payUrl);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Expiry notice — preview</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
body { margin: 0; padding: 24px; background: #f3f4f6; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
.preview-note { max-width: 900px; margin: 0 auto 16px; padding: 12px 16px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.preview-stage-wrap { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; }
.preview-stage {
    position: relative; background: #e5e7eb; border-radius: 8px; overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
}
.preview-stage.desktop { width: 720px;  height: 480px; }
.preview-stage.tablet  { width: 540px;  height: 700px; max-width: 100%; }
.preview-stage.phone   { width: 360px;  height: 700px; max-width: 100%; }
.preview-stage h3 { position: absolute; top: 6px; left: 10px; margin: 0; font-size: 12px; color: #6b7280; z-index: 1; }

.ssbd-expiry-card {
    position: absolute; width: 360px; max-width: calc(100% - 24px); border-radius: 12px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.35); padding: 0; overflow: hidden;
    border: 4px solid rgba(255,255,255,0.18); font-size: 14px; line-height: 1.5;
}
.ssbd-expiry-card.bottom-right { bottom: 16px; right: 16px; }
.ssbd-expiry-card.bottom-left  { bottom: 16px; left: 16px; }
.ssbd-expiry-card.center        { top: 50%; left: 50%; transform: translate(-50%, -50%); }
.ssbd-expiry-card.phone-mode {
    position: absolute; left: 0; right: 0; bottom: 0; top: auto;
    width: 100%; max-width: 100%; border-radius: 14px 14px 0 0; border-width: 2px 0 0 0;
}
.ssbd-head { padding: 12px 16px; background: rgba(0,0,0,0.18); font-weight: 700; display: flex; align-items: center; gap: 8px; }
.ssbd-body { padding: 14px 16px 12px; max-height: 60%; overflow-y: auto; }
.ssbd-actions { padding: 0 14px 14px; display: flex; gap: 8px; flex-wrap: wrap; }
.ssbd-actions.phone-mode { flex-direction: column; }
.ssbd-btn {
    flex: 1 1 140px; display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 10px 14px; font-weight: 600; font-size: 14px; border: 0; border-radius: 8px;
    text-decoration: none !important; cursor: pointer; line-height: 1.2;
}
.ssbd-actions.phone-mode .ssbd-btn { flex-basis: auto; width: 100%; padding: 12px 14px; font-size: 15px; }
.ssbd-btn-pay { background: #ffd166; color: #1f1f1f; }
.ssbd-btn-wa  { background: #25D366; color: #fff; }
.ssbd-foot { padding: 0 16px 14px; font-size: 12.5px; }
.ssbd-expiry-meta { margin-top: 4px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.25); opacity: 0.9; }
.ssbd-expiry-card a { color: inherit; text-decoration: underline; font-weight: 600; }
</style>
</head>
<body>
  <div class="preview-note">
    <strong>Preview</strong> — non-dismissible dialog as it will appear to a tenant.
    Live setting: enabled=<code><?=$origEnabled?></code>,
    position=<code><?=html_escape($position)?></code>,
    payment URL=<code><?=html_escape($payUrl ?: '—')?></code>,
    WhatsApp=<code><?=$waNumber ? '+'.html_escape($waNumber) : 'not configured'?></code>.
    <?php if ($origEnabled !== '1'): ?>
      <br><span style="color:#b04040">Note: the dialog is currently <b>disabled</b> on the live settings — tenants will not see it until you toggle it on and save.</span>
    <?php endif; ?>
  </div>

  <div class="preview-stage-wrap">

    <!-- Desktop / live position -->
    <div class="preview-stage desktop">
      <h3>Desktop (<?=html_escape($position)?>)</h3>
      <?php $renderCard = function ($posCls, $stackButtons = false) use ($bg, $fg, $title, $bodyHtml, $payUrl, $payLabel, $waUrl, $waLabel, $supportEmail, $website) { ?>
        <div class="ssbd-expiry-card <?=$posCls?>" style="background: <?=html_escape($bg)?>; color: <?=html_escape($fg)?>;">
          <div class="ssbd-head"><i class="fas fa-exclamation-triangle"></i> <span><?=html_escape($title)?></span></div>
          <div class="ssbd-body"><?=$bodyHtml?></div>
          <?php if ($payUrl || $waUrl): ?>
          <div class="ssbd-actions <?=$stackButtons ? 'phone-mode' : ''?>">
            <?php if ($payUrl): ?>
              <a href="<?=html_escape($payUrl)?>" class="ssbd-btn ssbd-btn-pay">
                <i class="fas fa-credit-card"></i> <span><?=html_escape($payLabel)?></span>
              </a>
            <?php endif; ?>
            <?php if ($waUrl): ?>
              <a href="<?=html_escape($waUrl)?>" class="ssbd-btn ssbd-btn-wa" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> <span><?=html_escape($waLabel)?></span>
              </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($supportEmail || $website): ?>
          <div class="ssbd-foot">
            <div class="ssbd-expiry-meta">
              <?php if ($supportEmail): ?><div><i class="fas fa-envelope"></i> <a href="mailto:<?=html_escape($supportEmail)?>"><?=html_escape($supportEmail)?></a></div><?php endif; ?>
              <?php if ($website):      ?>
                <?php $href = preg_match('~^https?://~i', $website) ? $website : ('https://' . ltrim($website, '/')); ?>
                <div><i class="fas fa-globe"></i> <a href="<?=html_escape($href)?>" target="_blank" rel="noopener"><?=html_escape($website)?></a></div>
              <?php endif; ?>
              <div style="margin-top:4px;"><small><i class="far fa-clock"></i> 3 day(s) remaining (preview)</small></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      <?php };
      $renderCard($position);
      ?>
    </div>

    <!-- Tablet -->
    <div class="preview-stage tablet">
      <h3>Tablet (<?=html_escape($position)?>)</h3>
      <?php $renderCard($position); ?>
    </div>

    <!-- Phone -->
    <div class="preview-stage phone">
      <h3>Phone (forced bottom sticky)</h3>
      <?php $renderCard('phone-mode', true); ?>
    </div>

  </div>
</body>
</html>
