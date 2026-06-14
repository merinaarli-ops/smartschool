<?php
/**
 * Shared form fields used by the "Add subject" modal and the per-row
 * "Edit subject" modals on /saas/subject_catalog.
 *
 * Expected vars:
 *   $row           : array — current values (use empty defaults when adding)
 *   $level_options : array<level_key => label>
 */

$typeOptions = [
    'Theory'    => 'Theory',
    'Practical' => 'Practical',
    'Optional'  => 'Optional',
    'Mandatory' => 'Mandatory',
];

?>
<div class="form-group">
  <label class="col-md-3 control-label">Subject name <span class="required">*</span></label>
  <div class="col-md-9">
    <input type="text" name="name" class="form-control" required
           value="<?= html_escape($row['name'] ?? '') ?>"
           placeholder="e.g. Higher Mathematics (HSC) — Theory">
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Code <span class="required">*</span></label>
  <div class="col-md-3">
    <input type="text" name="subject_code" class="form-control" required
           value="<?= html_escape($row['subject_code'] ?? '') ?>"
           placeholder="e.g. 265">
  </div>
  <label class="col-md-2 control-label">Type</label>
  <div class="col-md-4">
    <?= form_dropdown('subject_type', $typeOptions, $row['subject_type'] ?? 'Theory',
        "class='form-control'") ?>
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Level</label>
  <div class="col-md-4">
    <?php
      $opts = ['' => '— select a level —'];
      foreach ($level_options as $lk => $label) $opts[$lk] = $label;
    ?>
    <?= form_dropdown('level_key', $opts, $row['level_key'] ?? '', "class='form-control sc-level-key'") ?>
  </div>
  <label class="col-md-2 control-label">Level label</label>
  <div class="col-md-3">
    <input type="text" name="level_name" class="form-control sc-level-name"
           value="<?= html_escape($row['level_name'] ?? '') ?>"
           placeholder="auto-filled from level">
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Class numerics</label>
  <div class="col-md-4">
    <input type="text" name="class_numerics" class="form-control"
           value="<?= html_escape($row['class_numerics'] ?? '') ?>"
           placeholder="e.g. 9,10">
    <span class="help-block" style="margin-bottom:0;">Comma list of class.numeric values that this subject applies to. Used as a label on the import preview — doesn't constrain anything.</span>
  </div>
  <label class="col-md-2 control-label">Sort order</label>
  <div class="col-md-3">
    <input type="number" name="sort_order" class="form-control"
           value="<?= (int)($row['sort_order'] ?? 0) ?>">
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Author / source</label>
  <div class="col-md-9">
    <input type="text" name="subject_author" class="form-control"
           value="<?= html_escape($row['subject_author'] ?? 'NCTB Bangladesh') ?>"
           placeholder="NCTB Bangladesh">
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Notes</label>
  <div class="col-md-9">
    <textarea name="notes" rows="2" class="form-control"
              placeholder="Optional helper text shown under the subject name in the list"><?= html_escape($row['notes'] ?? '') ?></textarea>
  </div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">Active</label>
  <div class="col-md-9">
    <label style="font-weight:400;">
      <input type="checkbox" name="is_active" value="1"
        <?= (int)($row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      Visible to new tenants and pushed by "Push to all tenants"
    </label>
  </div>
</div>

<script>
(function () {
  // Auto-fill the level_name field from the level_key dropdown when the
  // operator hasn't manually overridden it.
  var labels = <?= json_encode($level_options, JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('.sc-level-key').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var form = sel.closest('form'); if (!form) return;
      var labelInput = form.querySelector('.sc-level-name');
      if (!labelInput) return;
      if (!labelInput.value.trim()) labelInput.value = labels[sel.value] || '';
    });
  });
})();
</script>
