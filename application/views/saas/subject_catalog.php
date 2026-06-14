<?php
/**
 * Saas → Subject catalogue
 *
 * Super-admin-managed global list of subjects. Each row is copied onto
 * every newly-approved tenant's branch-scoped `subject` table; the
 * "Push to all tenants" button propagates additions/edits to existing
 * tenants too.
 *
 * Expected vars (from Saas::subject_catalog):
 *   $rows           : array<array{id,name,subject_code,subject_type,level_key,level_name,class_numerics,subject_author,notes,is_active,sort_order}>
 *   $level_filter   : string
 *   $level_options  : array<level_key => label>
 *   $level_counts   : array<level_key => ['name','count','active']>
 *   $total_count    : int
 *   $php_pack_total : int   (number of subjects in application/config/nctb_subjects.php)
 */
?>
<section class="panel">
  <header class="panel-heading">
    <h2 class="panel-title">Subject catalogue (platform-wide)</h2>
    <p class="panel-subtitle" style="margin: 4px 0 0; font-size: 12.5px; color: #999;">
      One master list of subjects, copied onto every new tenant on approval. Import the NCTB Bangladesh starter pack once,
      then edit / add / disable / delete to taste. Existing tenants can pull the latest catalogue from their own
      <code>/subject</code> page, or you can push to everyone in one click.
    </p>
  </header>

  <div class="panel-body">

    <!-- ----------  Top toolbar  ---------- -->
    <div class="row" style="margin-bottom: 14px; gap: 8px; display: flex; flex-wrap: wrap; align-items: stretch;">
      <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <span class="label label-default" style="font-size: 12px;"><?= (int)$total_count ?> in catalogue</span>
        <span class="label label-primary" style="font-size: 12px;"><?= (int)$php_pack_total ?> in NCTB starter pack</span>
      </div>
      <div style="margin-left:auto;display:flex;gap:6px;align-items:stretch;flex-wrap:wrap;">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sc-import-modal">
          <i class="fas fa-cloud-download-alt"></i> Import NCTB starter pack
        </button>
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sc-add-modal">
          <i class="fa fa-plus"></i> Add subject
        </button>
        <?= form_open('saas/subject_catalog_push', [
            'style'    => 'display:inline;margin:0;',
            'onsubmit' => "return confirm('Push the active catalogue subjects to every existing tenant? Already-present subjects are skipped.')"
        ]) ?>
          <button type="submit" class="btn btn-warning" <?= $total_count > 0 ? '' : 'disabled' ?>>
            <i class="fa fa-broadcast-tower"></i> Push to all tenants
          </button>
        <?= form_close() ?>
      </div>
    </div>

    <!-- ----------  Level filter pills  ---------- -->
    <?php if (!empty($level_counts)): ?>
    <div style="margin-bottom: 14px; display:flex; gap:6px; flex-wrap:wrap;">
      <a href="<?= base_url('saas/subject_catalog') ?>"
         class="btn btn-xs <?= $level_filter === '' ? 'btn-primary' : 'btn-default' ?>">
        All (<?= (int)$total_count ?>)
      </a>
      <?php foreach ($level_counts as $lk => $info): ?>
        <a href="<?= base_url('saas/subject_catalog?level=' . urlencode($lk)) ?>"
           class="btn btn-xs <?= $level_filter === $lk ? 'btn-primary' : 'btn-default' ?>">
          <?= html_escape($info['name']) ?>
          <span class="badge"><?= (int)$info['count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ----------  Table  ---------- -->
    <?php if (empty($rows)): ?>
      <div class="alert alert-info" style="margin: 18px 0;">
        <strong>No subjects in the catalogue yet.</strong>
        Click <strong>Import NCTB starter pack</strong> above to seed the platform with the 114-row NCTB Bangladesh catalogue (Primary, JSC, SSC + streams, HSC + streams), then edit / extend as needed.
      </div>
    <?php else: ?>
      <table class="table table-bordered table-hover table-condensed mb-none table-export">
        <thead>
          <tr>
            <th width="40">#</th>
            <th>Subject name</th>
            <th width="90">Code</th>
            <th width="100">Type</th>
            <th>Level</th>
            <th width="90">Classes</th>
            <th width="80">Active</th>
            <th width="170" class="no-sort">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($rows as $r): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td>
                <strong><?= html_escape($r['name']) ?></strong>
                <?php if (!empty($r['notes'])): ?>
                  <br><small class="text-muted"><?= html_escape($r['notes']) ?></small>
                <?php endif; ?>
              </td>
              <td><code><?= html_escape($r['subject_code']) ?></code></td>
              <td><?= html_escape($r['subject_type']) ?></td>
              <td><?= html_escape($r['level_name']) ?> <br><small class="text-muted"><?= html_escape($r['level_key']) ?></small></td>
              <td><small><?= html_escape($r['class_numerics']) ?></small></td>
              <td>
                <?php if ((int)$r['is_active'] === 1): ?>
                  <span class="label label-success">Active</span>
                <?php else: ?>
                  <span class="label label-default">Disabled</span>
                <?php endif; ?>
              </td>
              <td class="action">
                <button type="button" class="btn btn-circle btn-default icon"
                        data-toggle="modal" data-target="#sc-edit-<?= (int)$r['id'] ?>"
                        title="Edit">
                  <i class="fas fa-pen-nib"></i>
                </button>
                <?= form_open('saas/subject_catalog_toggle/' . (int)$r['id'], [
                    'style' => 'display:inline;margin:0;',
                ]) ?>
                  <button type="submit" class="btn btn-circle btn-default icon"
                          title="<?= (int)$r['is_active'] === 1 ? 'Disable' : 'Enable' ?>">
                    <i class="fas <?= (int)$r['is_active'] === 1 ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                  </button>
                <?= form_close() ?>
                <?= form_open('saas/subject_catalog_delete/' . (int)$r['id'], [
                    'style'    => 'display:inline;margin:0;',
                    'onsubmit' => "return confirm('Delete \"" . addslashes(html_escape($r['name'])) . "\" from the platform catalogue? This won\\'t touch any tenant\\'s existing subjects.')"
                ]) ?>
                  <button type="submit" class="btn btn-circle btn-default icon" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                <?= form_close() ?>
              </td>
            </tr>

            <!-- per-row edit modal -->
            <div class="modal fade" id="sc-edit-<?= (int)$r['id'] ?>" tabindex="-1" role="dialog">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <?= form_open('saas/subject_catalog_save', ['class' => 'form-horizontal']) ?>
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Edit subject</h4>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <?php $this->load->view('saas/_subject_catalog_form_fields', [
                        'row'           => $r,
                        'level_options' => $level_options,
                    ]); ?>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-default" type="button" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save</button>
                  </div>
                  <?= form_close() ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>


<!-- ----------  Add modal  ---------- -->
<div class="modal fade" id="sc-add-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <?= form_open('saas/subject_catalog_save', ['class' => 'form-horizontal']) ?>
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Add subject</h4>
      </div>
      <div class="modal-body">
        <?php $this->load->view('saas/_subject_catalog_form_fields', [
            'row'           => [
                'name' => '', 'subject_code' => '', 'subject_type' => 'Theory',
                'level_key' => '', 'level_name' => '', 'class_numerics' => '',
                'subject_author' => 'NCTB Bangladesh', 'notes' => '',
                'is_active' => 1, 'sort_order' => 0,
            ],
            'level_options' => $level_options,
        ]); ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" type="button" data-dismiss="modal">Cancel</button>
        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Add subject</button>
      </div>
      <?= form_close() ?>
    </div>
  </div>
</div>


<!-- ----------  Import NCTB modal  ---------- -->
<div class="modal fade" id="sc-import-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <?= form_open('saas/subject_catalog_import_nctb', [
          'class'    => 'form-horizontal',
          'onsubmit' => "return confirm('Import the selected NCTB levels into the platform catalogue?')"
      ]) ?>
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Import NCTB Bangladesh starter pack</h4>
      </div>
      <div class="modal-body">
        <p class="text-muted">
          The NCTB pack ships <?= (int)$php_pack_total ?> subjects across Primary, Junior Secondary, SSC (Core + Science / Business / Humanities) and HSC (Core + Science / Business / Humanities). Pick the levels you want — anything already in the catalogue is skipped, so re-running is safe.
        </p>
        <?php foreach ($level_options as $lk => $label): ?>
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-weight:500;">
            <input type="checkbox" name="levels[]" value="<?= html_escape($lk) ?>" checked>
            <?= html_escape($label) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" type="button" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit"><i class="fas fa-cloud-download-alt"></i> Import to catalogue</button>
      </div>
      <?= form_close() ?>
    </div>
  </div>
</div>
