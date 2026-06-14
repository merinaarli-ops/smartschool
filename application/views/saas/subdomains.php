<?php
/**
 * Saas → Subdomains
 *
 * Super-admin view of every subdomain claim known to the system:
 *   - Active tenants (branch.subdomain)
 *   - Pending signup requests (saas_pending_request.subdomain)
 *   - Reserved entries (saas_reserved_subdomain.subdomain)
 *
 * Expected vars (from Saas::subdomains):
 *   $rows      : array<stdClass>
 *   $filter    : string (active search query)
 *   $suffixes  : string[]
 */
?>
<section class="panel">
  <header class="panel-heading">
    <h2 class="panel-title">Registered subdomains</h2>
    <p class="panel-subtitle" style="margin: 4px 0 0; font-size: 12.5px; color: #999;">
      Active tenants, pending signup requests, and reserved brand-name entries — all visible from one place.
      Delete frees the slug. Reserve to ring-fence a brand. Manually add to create a tenant on behalf of a school.
    </p>
  </header>
  <div class="panel-body">

    <div class="row" style="margin-bottom: 14px; gap: 8px; display: flex; flex-wrap: wrap; align-items: stretch;">
      <form method="get" action="<?= base_url('saas/subdomains'); ?>" style="flex: 1 1 280px; display: flex; gap: 6px;">
        <input type="text" name="q" value="<?= html_escape($filter ?? ''); ?>" class="form-control" placeholder="Search subdomain, school name, or owner…">
        <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
        <?php if (!empty($filter)): ?>
          <a class="btn btn-default" href="<?= base_url('saas/subdomains'); ?>">Clear</a>
        <?php endif; ?>
      </form>
      <div style="display: flex; gap: 6px; align-items: stretch;">
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sd-manual-modal">
          <i class="fa fa-plus"></i> Manually add
        </button>
        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#sd-reserve-modal">
          <i class="fa fa-lock"></i> Reserve a name
        </button>
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <p class="text-muted" style="padding: 18px 0;">No subdomains found<?= !empty($filter) ? ' for "' . html_escape($filter) . '"' : ''; ?>.</p>
    <?php else: ?>
      <table class="table table-bordered table-hover table-condensed mb-none table-export" id="tbl-subdomains">
        <thead>
          <tr>
            <th>#</th>
            <th>Subdomain</th>
            <th>Status</th>
            <th>School / Reason</th>
            <th>Owner</th>
            <th>Created</th>
            <th class="no-sort">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php $i = 1; foreach ($rows as $r): ?>
          <?php
            $statusClass = [
              'active'    => 'success',
              'pending'   => 'warning',
              'reserved'  => 'info',
              'suspended' => 'danger',
            ][$r->status] ?? 'default';
          ?>
          <tr>
            <td><?= $i++; ?></td>
            <td>
              <strong><?= html_escape($r->subdomain); ?></strong><?php if (!empty($r->suffix)) echo '<small style="color:#999;">.' . html_escape($r->suffix) . '</small>'; ?>
            </td>
            <td><span class="label label-<?= $statusClass; ?>"><?= html_escape($r->status); ?></span></td>
            <td><?= html_escape($r->name); ?></td>
            <td><?php if (!empty($r->owner)): ?><a href="mailto:<?= html_escape($r->owner); ?>"><?= html_escape($r->owner); ?></a><?php else: ?>&mdash;<?php endif; ?></td>
            <td><?= html_escape($r->created_at); ?></td>
            <td>
              <?php if ($r->source === 'branch'): ?>
                <a class="btn btn-default btn-sm" href="<?= base_url('saas/school'); ?>" title="View tenant"><i class="fa fa-eye"></i></a>
              <?php elseif ($r->source === 'signup_request'): ?>
                <a class="btn btn-default btn-sm" href="<?= base_url('saas/approve/' . (int)$r->request_id); ?>" title="Approve request"><i class="fa fa-check"></i></a>
              <?php endif; ?>
              <?= form_open('saas/subdomain_delete/' . urlencode($r->id), ['style' => 'display:inline-block; margin:0;', 'onsubmit' => "return confirm('Free up subdomain \\'" . addslashes($r->subdomain) . "\\'? This will " . ($r->source === 'branch' ? 'clear it from the active tenant' : ($r->source === 'reserved' ? 'remove the reservation' : 'delete the pending request')) . ".')"]); ?>
                <button type="submit" class="btn btn-danger btn-sm" title="Delete / free up"><i class="fa fa-trash"></i></button>
              <?= form_close(); ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<!-- Manual add modal -->
<div class="modal fade" id="sd-manual-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <?= form_open('saas/subdomain_add_manual', ['class' => 'modal-content']); ?>
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Manually add a subdomain</h4>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="margin-top: 0;">
          Creates a pending signup request that you can immediately approve from the pending queue.
          The slug is reserved while pending so no one else can claim it.
        </p>
        <div class="form-group">
          <label>Subdomain *</label>
          <div class="input-group">
            <span class="input-group-addon">www.</span>
            <input type="text" name="subdomain" required pattern="[a-z0-9_-]{3,64}" class="form-control" placeholder="example: dkmschool">
            <span class="input-group-addon">
              <select name="domain_suffix" class="form-control" style="border:0; background:transparent;">
                <?php foreach ($suffixes as $sfx): ?>
                  <option value="<?= html_escape($sfx); ?>">.<?= html_escape($sfx); ?></option>
                <?php endforeach; ?>
              </select>
            </span>
          </div>
        </div>
        <div class="form-group">
          <label>School name</label>
          <input type="text" name="school_name" class="form-control" placeholder="e.g. Dhaka Model School">
        </div>
        <div class="form-group">
          <label>Owner email</label>
          <input type="email" name="owner_email" class="form-control" placeholder="owner@school.edu.bd">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Create pending request</button>
      </div>
    <?= form_close(); ?>
  </div>
</div>

<!-- Reserve name modal -->
<div class="modal fade" id="sd-reserve-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <?= form_open('saas/subdomain_reserve', ['class' => 'modal-content']); ?>
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Reserve a subdomain</h4>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="margin-top: 0;">
          Ring-fence a brand name or system slug so it cannot be claimed via signup.
          Reserved names appear as "already taken" on the public signup form.
        </p>
        <div class="form-group">
          <label>Subdomain *</label>
          <div class="input-group">
            <span class="input-group-addon">www.</span>
            <input type="text" name="subdomain" required pattern="[a-z0-9_-]{3,64}" class="form-control" placeholder="e.g. government, nctb, demo">
            <span class="input-group-addon">
              <select name="domain_suffix" class="form-control" style="border:0; background:transparent;">
                <option value="">(any extension)</option>
                <?php foreach ($suffixes as $sfx): ?>
                  <option value="<?= html_escape($sfx); ?>">.<?= html_escape($sfx); ?></option>
                <?php endforeach; ?>
              </select>
            </span>
          </div>
        </div>
        <div class="form-group">
          <label>Reason (optional)</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Reserved brand name / for internal demo">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning"><i class="fa fa-lock"></i> Reserve</button>
      </div>
    <?= form_close(); ?>
  </div>
</div>

<script>
$(function(){
  if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#tbl-subdomains')) {
    $('#tbl-subdomains').DataTable({pageLength: 25, order:[[5, 'desc']]});
  }
});
</script>
