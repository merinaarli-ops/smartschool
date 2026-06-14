<?php
/**
 * Quick Bulk Import -- single page.
 *
 * Entity dropdown + file upload + per-entity sample download links + the
 * per-row import report from the last submission.
 */
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <?php echo form_open_multipart(base_url('bulk_admission/index'), array('class' => 'form-horizontal form-bordered')); ?>
                <header class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-file-archive"></i> <?php echo translate('quick_bulk_import'); ?>
                    </h4>
                </header>
                <div class="panel-body">
                    <div class="alert alert-info mb-md">
                        <strong><?php echo translate('instruction'); ?>:</strong><br/>
                        1. <?php echo translate('quick_bulk_import_help_1'); ?><br/>
                        2. <?php echo translate('quick_bulk_import_help_2'); ?> &nbsp;
                           <code>.csv</code> &nbsp; <code>.txt</code> <em>(<?php echo translate('tab_separated'); ?>)</em> &nbsp; <code>.xlsx</code><br/>
                        3. <?php echo translate('quick_bulk_import_help_3'); ?><br/>
                        4. <?php echo translate('quick_bulk_import_help_4'); ?>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-md-3"><?php echo translate('type'); ?> <span class="required">*</span></label>
                        <div class="col-md-6">
                            <?php
                                echo form_dropdown(
                                    'entity',
                                    array('' => translate('select')) + $entity_list,
                                    set_value('entity', isset($selected) ? $selected : ''),
                                    "class='form-control' id='entity' data-plugin-selectTwo data-width='100%' required"
                                );
                            ?>
                            <span class="error"><?php echo form_error('entity'); ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-md-3"><?php echo translate('sample_file'); ?></label>
                        <div class="col-md-9 mt-sm">
                            <?php foreach ($entity_list as $key => $label): ?>
                                <div class="mb-xs">
                                    <strong style="display:inline-block;min-width:120px"><?php echo $label; ?>:</strong>
                                    <a class="btn btn-default btn-xs" href="<?php echo base_url('bulk_admission/sample/' . $key . '/csv'); ?>">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </a>
                                    <a class="btn btn-default btn-xs" href="<?php echo base_url('bulk_admission/sample/' . $key . '/txt'); ?>">
                                        <i class="fas fa-file-alt"></i> TXT
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            <small class="text-muted">
                                <?php echo translate('xlsx_sample_note'); ?>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-md-3"><?php echo translate('upload'); ?> <span class="required">*</span></label>
                        <div class="col-md-6 mb-lg">
                            <input type="file" name="userfile" class="dropify" data-height="140" data-allowed-file-extensions="csv txt tsv xlsx" />
                            <?php echo form_error('userfile', '<label class="error">', '</label>'); ?>
                        </div>
                    </div>
                </div>
                <footer class="panel-footer">
                    <div class="row">
                        <div class="col-md-offset-3 col-md-3">
                            <button type="submit" name="submit" value="bulk_import" class="btn btn-default btn-block">
                                <i class="fas fa-upload"></i> <?php echo translate('import'); ?>
                            </button>
                        </div>
                    </div>
                </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (!empty($report) && isset($report['entity'])): ?>
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-clipboard-list"></i> <?php echo translate('result'); ?>
                </h4>
            </header>
            <div class="panel-body">
                <p>
                    <strong><?php echo translate('type'); ?>:</strong>
                    <?php echo isset($entity_list[$report['entity']]) ? $entity_list[$report['entity']] : $report['entity']; ?>
                    &nbsp;|&nbsp;
                    <strong><?php echo translate('total'); ?>:</strong> <?php echo (int) $report['total']; ?>
                    &nbsp;|&nbsp;
                    <strong style="color:#5cb85c"><?php echo translate('created'); ?>:</strong> <?php echo (int) $report['created']; ?>
                    &nbsp;|&nbsp;
                    <strong style="color:#d9534f"><?php echo translate('skipped'); ?>:</strong> <?php echo count($report['skipped']); ?>
                </p>

                <?php if (!empty($report['skipped'])): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed mb-none">
                        <thead>
                            <tr>
                                <th style="width:80px"><?php echo translate('row'); ?></th>
                                <th><?php echo translate('name'); ?></th>
                                <th><?php echo translate('reason'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($report['skipped'] as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['row']; ?></td>
                                <td><?php echo html_escape($row['name']); ?></td>
                                <td><?php echo html_escape($row['reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($report['expected'])): ?>
                <div class="alert alert-warning mt-md">
                    <strong><?php echo translate('expected_columns'); ?>:</strong>
                    <code><?php echo html_escape(implode(', ', $report['expected'])); ?></code>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
