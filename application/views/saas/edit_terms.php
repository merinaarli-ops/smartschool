<?php /**
 * Edit per-subscription terms.
 * Variables: $branch, $sub, $packages
 */
$override = isset($sub->override_price_bdt) ? $sub->override_price_bdt : '';
$valDays  = isset($sub->validity_days) ? $sub->validity_days : '';
$mode     = isset($sub->renewal_mode) ? $sub->renewal_mode : 'auto_invoice';
?>
<div class="row">
  <div class="col-lg-8 col-md-10 mx-auto">
    <div class="card mt-3">
      <div class="card-header">
        <strong>Edit subscription terms</strong>
        — <?= html_escape($branch->name ?? ('Branch #' . (int)$sub->school_id)) ?>
      </div>
      <div class="card-body">

        <table class="table table-sm mb-3" style="background:#f9fbfd">
          <tr><th style="width:200px">Current period end</th>
              <td><?= html_escape((string)($sub->current_period_end ?: '—')) ?></td></tr>
          <tr><th>Current status</th>
              <td><span class="label label-default"><?= html_escape((string)$sub->status) ?></span></td></tr>
          <tr><th>Current package</th>
              <td><?= html_escape((string)($sub->package_name ?? '— none —')) ?></td></tr>
          <tr><th>Current override price</th>
              <td><?= $override !== '' && $override !== null
                        ? '৳' . number_format((float)$override, 2)
                        : '<span class="text-muted">— uses package price —</span>' ?></td></tr>
          <tr><th>Current validity</th>
              <td><?= $valDays ? (int)$valDays . ' day(s)' : '<span class="text-muted">— uses package billing period —</span>' ?></td></tr>
          <tr><th>Current renewal mode</th>
              <td><code><?= html_escape((string)$mode) ?></code></td></tr>
        </table>

        <?= form_open(base_url('saas/edit_terms/' . (int)$sub->school_id)) ?>
        <input type="hidden" name="submit" value="save">

        <div class="form-group">
          <label><strong>Package</strong></label>
          <select name="package_id" class="form-control">
            <option value="">— No package (custom terms only) —</option>
            <?php foreach ($packages as $p): ?>
              <option value="<?= (int)$p->id ?>"
                <?= (!empty($sub->package_id) && (int)$sub->package_id === (int)$p->id) ? 'selected' : '' ?>>
                <?= html_escape($p->name) ?> — ৳<?= number_format((float)$p->price_bdt, 0) ?>
                <?= $p->billing_period === 'yearly' ? '/yr' : '/mo' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <div class="form-group col-md-6">
            <label><strong>Override price (BDT)</strong></label>
            <input type="number" step="0.01" min="0" name="override_price_bdt"
                   class="form-control"
                   value="<?= $override !== '' && $override !== null ? html_escape((string)$override) : '' ?>"
                   placeholder="0.00">
            <small class="text-muted">Leave blank to use the package price.</small>
          </div>
          <div class="form-group col-md-6">
            <label><strong>Validity (days)</strong></label>
            <input type="number" min="1" max="3650" name="validity_days"
                   class="form-control"
                   value="<?= $valDays ? (int)$valDays : '' ?>"
                   placeholder="">
            <small class="text-muted">Leave blank to use the package billing period.</small>
          </div>
        </div>

        <div class="form-group">
          <label><strong>Renewal mode</strong></label>
          <div>
            <?php $modes = [
              'auto_invoice'  => ['Auto-invoice',  'cron creates an invoice + emails pay link'],
              'contact_admin' => ['Contact admin', 'tenant sees a "please contact us" panel'],
              'disabled'      => ['Disabled',      'never auto-renew; subscription stays active until manually changed'],
            ]; ?>
            <?php foreach ($modes as $k => [$lbl, $desc]): ?>
              <label class="radio-inline" style="margin-right:18px">
                <input type="radio" name="renewal_mode" value="<?= $k ?>" <?= $mode === $k ? 'checked' : '' ?>>
                <strong><?= $lbl ?></strong> &mdash; <span class="text-muted"><?= $desc ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <hr>
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="<?= base_url('saas/school') ?>" class="btn btn-default">Cancel</a>
        <?= form_close() ?>

      </div>
    </div>
  </div>
</div>
