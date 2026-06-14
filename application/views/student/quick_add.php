<?php
/**
 * Quick Admission form.
 *
 * Minimal admission UI — only Class, Section, Roll, Full Name are asked here.
 * Register no., username, password and every other student field are populated
 * server-side with safe defaults; they can be edited from the regular student
 * profile screen afterwards.
 *
 * Superadmin UX mirrors create_admission: first pick a branch, then the rest
 * of the form is shown. For everyone else the branch comes from the
 * logged-in user so this branch-picker step is skipped.
 */
?>

<?php if (is_superadmin_loggedin()): ?>
<section class="panel">
    <header class="panel-heading">
        <h4 class="panel-title"><?=translate('select_ground')?></h4>
    </header>
    <?php echo form_open($this->uri->uri_string(), array('class' => 'validate')); ?>
    <div class="panel-body">
        <div class="row mb-sm">
            <div class="col-md-offset-3 col-md-6">
                <div class="form-group">
                    <label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
                    <?php
                        $arrayBranch = $this->app_lib->getSelectList('branch');
                        echo form_dropdown(
                            'branch_id',
                            $arrayBranch,
                            set_value('branch_id', isset($branch_id) ? $branch_id : ''),
                            "class='form-control' data-plugin-selectTwo data-width='100%'"
                        );
                    ?>
                </div>
            </div>
        </div>
    </div>
    <footer class="panel-footer">
        <div class="row">
            <div class="col-md-offset-10 col-md-2">
                <button type="submit" name="search" value="1" class="btn btn-default btn-block">
                    <i class="fas fa-filter"></i> <?=translate('filter')?>
                </button>
            </div>
        </div>
    </footer>
    <?php echo form_close(); ?>
</section>
<?php endif; ?>

<?php if (!empty($branch_id)): ?>
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="far fa-edit"></i> <?php echo translate('quick_admission'); ?>
                </h4>
            </header>
            <?php
                // class="frm-submit-data-msg" wires this form to the existing
                // AJAX helper in app.fn.js: POSTs to /student/quick_save,
                // toastr-shows the success message and stays on the page (no
                // server-side redirect, no full page reload). Avoids the
                // session_id / branch_id drift that broke profile/view
                // redirects on tenant subdomains.
                echo form_open(base_url('student/quick_save'), array(
                    'class' => 'form-horizontal frm-submit-data-msg',
                    'id'    => 'quick_admission_form',
                ));
            ?>
                <?php if (is_superadmin_loggedin()): ?>
                    <input type="hidden" name="branch_id" value="<?php echo $branch_id ?>">
                <?php endif; ?>
                <div class="panel-body">

                    <div class="alert alert-info mb-md">
                        <i class="fas fa-info-circle"></i>
                        <?php echo translate('quick_admission_help'); ?>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label"><?php echo translate('class'); ?> <span class="required">*</span></label>
                        <div class="col-md-7">
                            <?php
                                $arrayClass = $this->app_lib->getClass($branch_id);
                                echo form_dropdown(
                                    'class_id',
                                    $arrayClass,
                                    set_value('class_id'),
                                    "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' required data-plugin-selectTwo data-width='100%'"
                                );
                            ?>
                            <span class="error"><?php echo form_error('class_id'); ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label"><?php echo translate('section'); ?> <span class="required">*</span></label>
                        <div class="col-md-7">
                            <?php
                                $arraySection = $this->app_lib->getSections(set_value('class_id'), false);
                                echo form_dropdown(
                                    'section_id',
                                    $arraySection,
                                    set_value('section_id'),
                                    "class='form-control' id='section_id' required data-plugin-selectTwo data-width='100%'"
                                );
                            ?>
                            <span class="error"><?php echo form_error('section_id'); ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label"><?php echo translate('roll'); ?></label>
                        <div class="col-md-7">
                            <input type="text" class="form-control" name="roll" value="<?php echo set_value('roll'); ?>" placeholder="<?php echo translate('roll'); ?>" />
                            <span class="error"><?php echo form_error('roll'); ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label"><?php echo translate('first_name'); ?> &amp; <?php echo translate('last_name'); ?> <span class="required">*</span></label>
                        <div class="col-md-7">
                            <input type="text" class="form-control" name="full_name" value="<?php echo set_value('full_name'); ?>" placeholder="<?php echo translate('first_name') . ' ' . translate('last_name'); ?>" />
                            <span class="error"><?php echo form_error('full_name'); ?></span>
                        </div>
                    </div>

                </div>
                <div class="panel-footer">
                    <div class="row">
                        <div class="col-md-offset-3 col-md-7">
                            <button class="btn btn-default" type="submit" name="submit" value="quick_save">
                                <i class="fas fa-plus-circle"></i> <?php echo translate('save'); ?>
                            </button>
                            <a class="btn btn-default" href="<?php echo base_url('student/view'); ?>">
                                <?php echo translate('cancel'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php echo form_close(); ?>
        </section>
    </div>
</div>

<script>
// Clear the Quick Admission form after a successful AJAX save so the user can
// type the next student straight away. Hook into ajaxComplete instead of
// patching the generic `frm-submit-data-msg` handler in app.fn.js so this
// behaviour stays scoped to this page.
jQuery(function ($) {
    $(document).on('ajaxComplete', function (event, xhr, settings) {
        if (!settings || !settings.url || settings.url.indexOf('/student/quick_save') === -1) {
            return;
        }
        var response;
        try { response = JSON.parse(xhr.responseText); }
        catch (e) { return; }
        if (response && response.status === 'success') {
            var $form = $('#quick_admission_form');
            $form.find('input[name="full_name"]').val('').focus();
            $form.find('input[name="roll"]').val('');
            // Class / section deliberately left selected so the next student
            // goes into the same place.
        }
    });
});
</script>
<?php endif; ?>
