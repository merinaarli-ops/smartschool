<section class="panel">
<?php if (get_permission('subject', 'is_add') && !empty($nctb_total_count)): ?>
	<div style="padding: 16px 18px; border-bottom: 1px solid #eee; background: linear-gradient(135deg,#eef6ff 0%,#f5fbff 100%);">
		<div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
			<div style="font-size:36px; line-height:1; color:#0a73c1;"><i class="fas fa-magic"></i></div>
			<div style="flex:1; min-width: 220px;">
				<div style="font-size:16px; font-weight:700; color:#22354a;">One-click NCTB import — all subjects, all classes</div>
				<div style="color:#52708c; font-size:13px; margin-top:2px;">
					Adds the full NCTB Bangladesh catalogue (<strong><?=(int)$nctb_total_count?></strong> subjects across Primary, JSC, SSC, HSC + streams), creates the standard classes (Class 1 … Class 12), a default Section A, and wires every subject to every matching class — all in one click. Already-present rows are skipped, so re-running is safe.
				</div>
				<?php if (!empty($nctb_missing_count)): ?>
					<div style="margin-top:6px;"><span class="label label-warning"><?=(int)$nctb_missing_count?> subjects still missing on this branch</span></div>
				<?php else: ?>
					<div style="margin-top:6px;"><span class="label label-success">All NCTB subjects already imported — click below to re-sync class assignments.</span></div>
				<?php endif; ?>
			</div>
			<?php echo form_open('subject/import_nctb_all', array('style' => 'margin:0;', 'onsubmit' => "return confirm('Import the full NCTB catalogue and wire every subject to every class on this branch?')")); ?>
				<button type="submit" class="btn btn-primary btn-lg" style="font-weight:700; padding: 12px 20px;" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Importing">
					<i class="fas fa-cloud-download-alt"></i> Import all NCTB subjects
				</button>
			<?php echo form_close(); ?>
		</div>
	</div>
<?php endif; ?>
	<div class="tabs-custom">
		<ul class="nav nav-tabs">
			<li class="active">
				<a href="#list" data-toggle="tab"><i class="fas fa-list-ul"></i> <?=translate('subject_list')?></a>
			</li>
<?php if (get_permission('subject', 'is_add')): ?>
			<li>
				<a href="#create" data-toggle="tab"><i class="far fa-edit"></i> <?=translate('create_subject')?></a>
			</li>
			<li>
				<a href="#nctb_import" data-toggle="tab"><i class="fas fa-cloud-download-alt"></i> Import NCTB</a>
			</li>
<?php endif; ?>
		</ul>
		<div class="tab-content">
			<div id="list" class="tab-pane active">
				<table class="table table-bordered table-hover mb-none table-export">
					<thead>
						<tr>
							<th width="60"><?=translate('sl')?></th>
							<th><?=translate('branch')?></th>
							<th><?=translate('subject_name')?></th>
							<th><?=translate('subject_code')?></th>
							<th><?=translate('subject_type')?></th>
							<th><?=translate('subject_author')?></th>
							<th><?=translate('action')?></th>
						</tr>
					</thead>
					<tbody>
						<?php 
						$count = 1;
						foreach($subjectlist as $row):
						?>
						<tr>
							<td><?php echo $count++ ;?></td>
							<td><?php echo $row['branch_name'];?></td>
							<td><?php echo $row['name'];?></td>
							<td><?php echo $row['subject_code'];?></td>
							<td><?php echo $row['subject_type'];?></td>
							<td><?php echo $row['subject_author'];?></td>
							<td class="action">
							<?php if (get_permission('subject', 'is_edit')): ?>
								<!-- subject update link -->
								<a href="<?php echo base_url('subject/edit/' . $row['id']);?>" class="btn btn-circle btn-default icon" >
									<i class="fas fa-pen-nib"></i>
								</a>
							<?php endif; if (get_permission('subject', 'is_delete')): ?>
								<!-- delete link -->
								<?php echo btn_delete('subject/delete/' . $row['id']);?>
							<?php endif; ?>
							</td>
						</tr>
						<?php endforeach;?>
					</tbody>
				</table>
			</div>
<?php if (get_permission('subject', 'is_add')): ?>
			<div class="tab-pane" id="create">
				<?php echo form_open('subject/save', array('class' => 'form-horizontal form-bordered frm-submit'));?>
					<?php if (is_superadmin_loggedin()): ?>
						<div class="form-group">
							<label class="control-label col-md-3"><?=translate('branch')?> <span class="required">*</span></label>
							<div class="col-md-6">
								<?php
									$arrayBranch = $this->app_lib->getSelectList('branch');
									echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' data-width='100%'
									data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
								?>
								<span class="error"></span>
							</div>
						</div>
					<?php endif; ?>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('subject_name')?> <span class="required">*</span></label>
						<div class="col-md-6">
							<input type="text" class="form-control" name="name" />
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('subject_code')?> <span class="required">*</span></label>
						<div class="col-md-6">
							<input type="text" class="form-control" name="subject_code" />
							<span class="error"></span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('subject_author')?></label>
						<div class="col-md-6">
							<input type="text" class="form-control" name="subject_author" />
						</div>
					</div>
					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('subject_type')?> <span class="required">*</span></label>
						<div class="col-md-6 mb-md">
						<?php
							$subjectArray = array(
								'Theory' => 'Theory',
								'Practical' => 'Practical',
								'Optional' => 'Optional',
								'Mandatory' => 'Mandatory'
							);
							echo form_dropdown("subject_type", $subjectArray, set_value("subject_type"), "class='form-control populate' data-plugin-selectTwo data-width='100%'
							data-minimum-results-for-search='Infinity' ");
						?>
						<span class="error"></span>
						</div>
					</div>
					<footer class="panel-footer">
						<div class="row">
							<div class="col-md-offset-3 col-md-2">
								<button type="submit" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
									<i class="fas fa-plus-circle"></i> <?=translate('save')?>
								</button>
							</div>
						</div>
					</footer>
				<?php echo form_close(); ?>
			</div>
			<div class="tab-pane" id="nctb_import">
				<div class="alert alert-info" style="margin-bottom:18px;">
					<strong>Import NCTB Bangladesh subjects.</strong>
					One click adds the full NCTB catalogue (Primary, JSC, SSC + streams, HSC + streams) to this branch's subject list, so you don't have to type Bangla, English, Mathematics, ICT, Physics, Chemistry, … by hand. Re-running is safe — subjects that already exist are skipped.
					<?php if (!empty($nctb_total_count)): ?>
						<div style="margin-top:8px;">
							<span class="label label-default"><?=(int)$nctb_total_count?> NCTB subjects in catalogue</span>
							<?php if (!empty($nctb_missing_count)): ?>
								<span class="label label-warning"><?=(int)$nctb_missing_count?> missing on this branch</span>
							<?php elseif (!empty($nctb_total_count)): ?>
								<span class="label label-success">All NCTB subjects already imported</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php echo form_open('subject/import_nctb', array('class' => 'form-horizontal form-bordered', 'id' => 'frm-nctb-import', 'onsubmit' => "return confirm('Import the selected NCTB subject levels into this branch?')")); ?>
				<div class="form-group">
					<label class="col-md-3 control-label">Levels to import</label>
					<div class="col-md-9">
						<p class="help-block" style="margin-top:0">Leave them all checked to import everything (recommended). Uncheck the levels you don't need (e.g. a primary-only school can skip SSC/HSC).</p>
						<?php foreach (($nctb_catalog['levels'] ?? []) as $levelKey => $level): ?>
							<details style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;background:#fafafa;">
								<summary style="cursor:pointer;font-weight:600;">
									<label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;margin:0;">
										<input type="checkbox" name="levels[]" value="<?=html_escape($levelKey)?>" checked>
										<?=html_escape($level['name'] ?? $levelKey)?>
										<span class="label label-default" style="font-weight:500;"><?=count($level['subjects'] ?? [])?> subjects</span>
									</label>
								</summary>
								<div style="margin-top:8px;">
									<small class="text-muted">Applies to class numeric values: <strong><?=html_escape(implode(', ', $level['classes'] ?? []))?></strong></small>
									<table class="table table-condensed" style="margin-top:6px;margin-bottom:0;">
										<thead><tr><th>Subject</th><th width="100">Code</th><th width="120">Type</th></tr></thead>
										<tbody>
											<?php foreach (($level['subjects'] ?? []) as $s): ?>
												<tr>
													<td><?=html_escape($s['name'] ?? '')?></td>
													<td><code><?=html_escape($s['code'] ?? '')?></code></td>
													<td><?=html_escape($s['type'] ?? 'Theory')?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
				<footer class="panel-footer">
					<div class="row">
						<div class="col-md-offset-3 col-md-4">
							<button type="submit" class="btn btn-primary" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Importing">
								<i class="fas fa-cloud-download-alt"></i> Import selected NCTB subjects
							</button>
							<span class="help-block" style="display:inline-block;margin-left:10px;">Already-present subjects are skipped.</span>
						</div>
					</div>
				</footer>
				<?php echo form_close(); ?>
			</div>
<?php endif; ?>
		</div>
	</div>
</section>