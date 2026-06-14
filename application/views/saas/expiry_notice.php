<section class="panel">
<header class="panel-heading">
  <h2 class="panel-title">
    Expiry notice
    <small>
      Non-dismissible floating dialog shown on tenant admin pages when their subscription is in trouble.
      Super-admin controls every field below.
    </small>
  </h2>
</header>

<?=form_open(base_url('saas/save_expiry_notice'), ['class' => 'form-horizontal']);?>
<div class="panel-body">

<div class="form-group">
  <label class="col-md-3 control-label">Enable</label>
  <div class="col-md-9">
    <label class="switch" style="display:inline-flex;align-items:center;gap:8px;">
      <input type="checkbox" name="expiry_notice_enabled" value="1"
        <?=(($settings['expiry_notice_enabled'] ?? '0') === '1') ? 'checked' : ''?>>
      <span>Show the floating dialog to affected tenants</span>
    </label>
    <p class="help-block">
      When off, no dialog is rendered regardless of subscription state.
      The dialog has <b>no close button</b> — tenant must use the action
      buttons below to resolve the issue.
    </p>
  </div>
</div>

<hr>
<h4>When to show</h4>

<div class="form-group">
  <label class="col-md-3 control-label">Trigger</label>
  <div class="col-md-9">
    <?php $trig = $settings['expiry_notice_trigger'] ?? 'any_problem'; ?>
    <select name="expiry_notice_trigger" class="form-control" style="max-width:360px">
      <option value="past_due"      <?=$trig==='past_due'?'selected':''?>>Past due only (status = past_due)</option>
      <option value="suspended"     <?=$trig==='suspended'?'selected':''?>>Suspended only (status = suspended)</option>
      <option value="expiring_soon" <?=$trig==='expiring_soon'?'selected':''?>>Trial / period expiring within N days</option>
      <option value="any_problem"   <?=$trig==='any_problem'?'selected':''?>>Any problem (past_due OR suspended OR expiring soon)</option>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Days before expiry</label>
  <div class="col-md-9">
    <input type="number" name="expiry_notice_days_before" min="0" max="365" class="form-control" style="max-width:120px"
      value="<?=html_escape($settings['expiry_notice_days_before'] ?? '7')?>">
    <p class="help-block">Used by the "Expiring soon" / "Any problem" triggers — number of days remaining at which the dialog starts appearing.</p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Show to</label>
  <div class="col-md-9">
    <?php $aud = $settings['expiry_notice_show_to_users'] ?? 'admin'; ?>
    <select name="expiry_notice_show_to_users" class="form-control" style="max-width:360px">
      <option value="admin"         <?=$aud==='admin'?'selected':''?>>Branch admins only</option>
      <option value="admin_teacher" <?=$aud==='admin_teacher'?'selected':''?>>Branch admins + teachers</option>
      <option value="everyone"      <?=$aud==='everyone'?'selected':''?>>Everyone (admins, teachers, students, parents)</option>
    </select>
    <p class="help-block">Super-admin never sees the dialog (it would obscure the management UI).</p>
  </div>
</div>

<hr>
<h4>Content</h4>

<div class="form-group">
  <label class="col-md-3 control-label">Title</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_title" class="form-control" maxlength="255"
      value="<?=html_escape($settings['expiry_notice_title'] ?? 'জরুরী বিজ্ঞপ্তি')?>">
    <p class="help-block">Bangla and English both work.</p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Body</label>
  <div class="col-md-9">
    <textarea name="expiry_notice_body" rows="10" class="form-control" style="font-family:inherit;"><?=html_escape($settings['expiry_notice_body'] ?? '')?></textarea>
    <p class="help-block">
      Plain text. Newlines render as line breaks. Placeholders auto-replaced at render time:
      <code>{school_name}</code>
      <code>{expire_date}</code>
      <code>{days_left}</code>
      <code>{support_email}</code>
      <code>{website}</code>
    </p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Support email</label>
  <div class="col-md-9">
    <input type="email" name="expiry_notice_support_email" class="form-control" style="max-width:360px"
      value="<?=html_escape($settings['expiry_notice_support_email'] ?? '')?>">
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Website URL</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_website" class="form-control" style="max-width:360px"
      value="<?=html_escape($settings['expiry_notice_website'] ?? '')?>">
  </div>
</div>

<hr>
<h4>Action buttons</h4>

<div class="form-group">
  <label class="col-md-3 control-label">Pay-now URL</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_payment_url" class="form-control"
      value="<?=html_escape($settings['expiry_notice_payment_url'] ?? 'subscription')?>">
    <p class="help-block">
      Where the "Pay" button takes the tenant. Use a relative path (e.g. <code>subscription</code>)
      or a full URL. Default <code>subscription</code> — the existing tenant-side billing page.
    </p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Pay-now button label</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_payment_label" class="form-control" style="max-width:360px"
      value="<?=html_escape($settings['expiry_notice_payment_label'] ?? 'Login & Pay')?>">
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">WhatsApp number</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_whatsapp_number" class="form-control" style="max-width:360px"
      placeholder="8801XXXXXXXXX (international format, no + or spaces)"
      value="<?=html_escape($settings['expiry_notice_whatsapp_number'] ?? '')?>">
    <p class="help-block">
      Country code first, no spaces or <code>+</code>. Example for Bangladesh: <code>8801712345678</code>.
      Leave empty to hide the WhatsApp button.
    </p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">WhatsApp button label</label>
  <div class="col-md-9">
    <input type="text" name="expiry_notice_whatsapp_label" class="form-control" style="max-width:360px"
      value="<?=html_escape($settings['expiry_notice_whatsapp_label'] ?? 'WhatsApp Support')?>">
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">WhatsApp prefilled message</label>
  <div class="col-md-9">
    <textarea name="expiry_notice_whatsapp_message" rows="3" class="form-control"><?=html_escape($settings['expiry_notice_whatsapp_message'] ?? '')?></textarea>
    <p class="help-block">
      The text that appears in WhatsApp when the tenant clicks the button.
      Same placeholders supported as the body.
    </p>
  </div>
</div>

<hr>
<h4>Style</h4>

<div class="form-group">
  <label class="col-md-3 control-label">Position</label>
  <div class="col-md-9">
    <?php $pos = $settings['expiry_notice_position'] ?? 'bottom-right'; ?>
    <select name="expiry_notice_position" class="form-control" style="max-width:240px">
      <option value="bottom-right" <?=$pos==='bottom-right'?'selected':''?>>Bottom-right (floating)</option>
      <option value="bottom-left"  <?=$pos==='bottom-left'?'selected':''?>>Bottom-left (floating)</option>
      <option value="center"       <?=$pos==='center'?'selected':''?>>Center (modal overlay, blocks page)</option>
    </select>
    <p class="help-block">On phones (&lt;520px) all three positions render as a full-width sticky card.</p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Background color</label>
  <div class="col-md-9">
    <input type="color" name="expiry_notice_bg_color" class="form-control" style="max-width:120px;padding:2px;height:34px;"
      value="<?=html_escape($settings['expiry_notice_bg_color'] ?? '#8B1F1F')?>">
    <p class="help-block">Default <code>#8B1F1F</code> (deep red) — matches the screenshot.</p>
  </div>
</div>

<div class="form-group">
  <label class="col-md-3 control-label">Text color</label>
  <div class="col-md-9">
    <input type="color" name="expiry_notice_text_color" class="form-control" style="max-width:120px;padding:2px;height:34px;"
      value="<?=html_escape($settings['expiry_notice_text_color'] ?? '#FFFFFF')?>">
  </div>
</div>

</div>
<footer class="panel-footer text-right">
  <a href="<?=base_url('saas/preview_expiry_notice')?>" target="_blank" class="btn btn-default">
    <i class="far fa-eye"></i> Preview
  </a>
  <button type="submit" class="btn btn-primary">
    <i class="fas fa-save"></i> Save
  </button>
</footer>
<?=form_close();?>
</section>
