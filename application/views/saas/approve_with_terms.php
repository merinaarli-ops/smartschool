<?php /**
 * Approve-with-terms form.
 *
 * Shown when super-admin clicks the Approve button on /saas/pending_request.
 * Lets the admin pick / override the package, price, validity, renewal mode
 * before provisioning the tenant.
 */
$pkg = null;
if (!empty($req->package_id)) {
    foreach ($packages as $p) {
        if ((int)$p->id === (int)$req->package_id) { $pkg = $p; break; }
    }
}
$defaultDays = $pkg ? ($pkg->is_default_trial && (int)$pkg->trial_days > 0
                        ? (int)$pkg->trial_days
                        : ($pkg->billing_period === 'yearly' ? 365 : 30))
                    : 30;
?>
<div class="row">
  <div class="col-lg-8 col-md-10 mx-auto">
    <div class="card mt-3">
      <div class="card-header">
        <strong>Approve signup &mdash; set subscription terms</strong>
      </div>
      <div class="card-body">

        <table class="table table-sm mb-3" style="background:#f9fbfd">
          <tr><th style="width:180px">School name</th>
              <td><?= html_escape($req->school_name) ?>
                  <?= $req->school_name_bn ? ' &middot; <span class="text-muted">' . html_escape($req->school_name_bn) . '</span>' : '' ?></td></tr>
          <tr><th>Subdomain</th>
              <td><code><?= html_escape($req->subdomain) ?>.<?= html_escape($req->domain_suffix ?? 'smartschool.bd') ?></code></td></tr>
          <tr><th>Owner</th>
              <td><?= html_escape($req->owner_name) ?> &middot;
                  <?= html_escape($req->owner_email) ?> &middot;
                  <?= html_escape($req->owner_phone) ?></td></tr>
          <?php if (!empty($req->signup_variant)): ?>
          <tr><th>Signup variant</th><td><code><?= html_escape($req->signup_variant) ?></code></td></tr>
          <?php endif; ?>
          <?php if (!empty($req->eiin_code) || !empty($req->institute_type) || !empty($req->education_board)): ?>
          <tr><th>Institute</th>
              <td>
                <?php if (!empty($req->institute_type)): ?>
                  <?= html_escape(ucwords(str_replace('_', ' ', $req->institute_type))) ?>
                <?php endif; ?>
                <?php if (!empty($req->education_board)): ?>
                  &middot; Board: <?= html_escape(ucfirst($req->education_board)) ?>
                <?php endif; ?>
                <?php if (!empty($req->eiin_code)): ?>
                  &middot; EIIN: <code><?= html_escape($req->eiin_code) ?></code>
                <?php endif; ?>
              </td></tr>
          <?php endif; ?>
          <?php if (!empty($req->designation)): ?>
          <tr><th>Designation</th><td><?= html_escape(ucwords(str_replace('_', ' ', $req->designation))) ?></td></tr>
          <?php endif; ?>
          <?php
            $loc = [];
            if (!empty($req->division_id)) {
                $r = $this->db->get_where('bd_division', ['id' => (int)$req->division_id])->row();
                if ($r) $loc[] = $r->name;
            }
            if (!empty($req->district_id)) {
                $r = $this->db->get_where('bd_district', ['id' => (int)$req->district_id])->row();
                if ($r) $loc[] = $r->name;
            }
            if (!empty($req->upazila_id)) {
                $r = $this->db->get_where('bd_upazila', ['id' => (int)$req->upazila_id])->row();
                if ($r) $loc[] = $r->name;
            }
          ?>
          <?php if ($loc): ?>
          <tr><th>Location</th><td><?= html_escape(implode(' &rsaquo; ', $loc)) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($req->notes)): ?>
          <tr><th>Notes</th><td><?= nl2br(html_escape($req->notes)) ?></td></tr>
          <?php endif; ?>
        </table>

        <?= form_open(base_url('saas/approve/' . (int)$req->id)) ?>
        <input type="hidden" name="submit" value="approve">

        <div class="form-group">
          <label><strong>Package</strong></label>
          <select name="package_id" class="form-control">
            <option value="">— No package (custom terms only) —</option>
            <?php foreach ($packages as $p): ?>
              <option value="<?= (int)$p->id ?>"
                <?= (!empty($req->package_id) && (int)$req->package_id === (int)$p->id) ? 'selected' : '' ?>>
                <?= html_escape($p->name) ?>
                — ৳<?= number_format((float)$p->price_bdt, 0) ?>
                <?= $p->billing_period === 'yearly' ? '/yr' : '/mo' ?>
                <?= $p->is_default_trial ? ' (trial)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">
            Leave blank for signup variants A / B (no public plan was picked) — the
            override price below acts as the effective price for this tenant.
          </small>
        </div>

        <div class="row">
          <div class="form-group col-md-6">
            <label><strong>Override price (BDT)</strong></label>
            <input type="number" step="0.01" min="0" name="override_price_bdt"
                   class="form-control" placeholder="<?= $pkg ? number_format((float)$pkg->price_bdt, 2) : '0.00' ?>">
            <small class="text-muted">Leave blank to use the package price.</small>
          </div>
          <div class="form-group col-md-6">
            <label><strong>Validity (days)</strong></label>
            <input type="number" min="1" max="3650" name="validity_days"
                   class="form-control" placeholder="<?= (int)$defaultDays ?>">
            <small class="text-muted">Leave blank to use the package billing period.</small>
          </div>
        </div>

        <div class="form-group">
          <label><strong>Renewal mode</strong></label>
          <div>
            <label class="radio-inline" style="margin-right:18px">
              <input type="radio" name="renewal_mode" value="auto_invoice" checked>
              <strong>Auto-invoice</strong> &mdash; cron creates an invoice + emails pay link
            </label>
            <label class="radio-inline" style="margin-right:18px">
              <input type="radio" name="renewal_mode" value="contact_admin">
              <strong>Contact admin</strong> &mdash; tenant sees "Please contact us to renew"
            </label>
            <label class="radio-inline">
              <input type="radio" name="renewal_mode" value="disabled">
              <strong>Disabled</strong> &mdash; never auto-renew
            </label>
          </div>
        </div>

        <hr>
        <button type="submit" class="btn btn-success">
          Approve &amp; provision tenant
        </button>
        <a href="<?= base_url('saas/pending_request') ?>" class="btn btn-default">Cancel</a>
        <?= form_close() ?>

      </div>
    </div>
  </div>
</div>
