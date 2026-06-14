<style type="text/css">
    #print {
        margin-bottom: 20px;
        margin-top: 0px;
        padding: 2px 15px;
        font-size: 14px;
        font-weight: 500;
    }
</style>

<!-- Main Container Starts -->
<div class="container px-md-0 main-container">
    <p><?php echo $page_data['description']; ?></p>
    <?php echo form_open('home/examResultsPrintFn', array('class' => 'printIn')); ?>
    <div class="box2 form-box">
        <div class="row">
            <div class="col-md-4 mb-sm">
                <div class="form-group">
                    <label class="control-label"> <?=translate('exam')?> <span class="required">*</span></label>
                    <?php
                        $array = array();
                        $result = $this->home_model->getExamList($branchID);
                        if (count($result)) {
                            $array[''] = translate('select');
                            foreach ($result as $row) {
                                if ($row['term_id'] != 0) {
                                    $term = $this->db->select('name')->where('id', $row['term_id'])->get('exam_term')->row()->name;
                                    $name = $row['name'] . ' (' . $term . ')';
                                } else {
                                    $name = $row['name'];
                                }
                                $array[$row['id']] = $name;
                            }
                        } else {
                            $array[0] = translate('no_information_available');
                        }

                        echo form_dropdown("exam_id", $array, set_value('exam_id'), "class='form-control' data-plugin-selectTwo");
                    ?>
                    <span class="error"></span>
                </div>
            </div>
            <div class="col-md-4 mb-sm">
                <div class="form-group">
                    <label class="control-label"> <?=translate('academic_year')?> <span class="required">*</span></label>
                        <?php
                        $arrayYear = array("" => translate('select'));
                        $years = $this->db->get('schoolyear')->result();
                        foreach ($years as $year) {
                            $arrayYear[$year->id] = $year->school_year;
                        }
                        // Default to whatever year the topbar selector is on
                        // (falls back to global_settings.session_id when nothing's
                        // been picked). Using $global_config['session_id'] alone
                        // pinned this dropdown to the stale row in global_settings,
                        // so the public lookup defaulted to the wrong (often last)
                        // academic year on multi-tenant subdomains.
                        echo form_dropdown("academic_year_id", $arrayYear, set_value('academic_year_id', get_session_id()), "class='form-control'
                        data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
                        ?>
                    <span class="error"></span>
                </div>
            </div>
            <div class="col-md-4 mb-sm">
                <div class="form-group">
                    <label class="control-label"> <?=translate('class')?> <span class="required">*</span></label>
                    <?php
                        $arrayClass = array("" => translate('select'));
                        $classRows = $this->db->select('id, name')
                            ->where('branch_id', $branchID)
                            ->order_by('id', 'ASC')
                            ->get('class')->result();
                        foreach ($classRows as $row) {
                            $arrayClass[$row->id] = $row->name;
                        }
                        echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "id='class_id' class='form-control' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
                    ?>
                    <span class="error"></span>
                </div>
            </div>
            <div class="col-md-4 mb-sm">
                <div class="form-group">
                    <label class="control-label"> <?=translate('section')?> <span class="required">*</span></label>
                    <select id="section_id" name="section_id" class="form-control" data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                        <option value=""><?=translate('select_class_first')?></option>
                    </select>
                    <span class="error"></span>
                </div>
            </div>
            <div class="col-md-4 mb-sm">
                <div class="form-group">
                    <label class="control-label"> <?=translate('roll')?> <span class="required">*</span></label>
                    <input type="text" class="form-control" name="roll" value="<?=set_value('roll')?>" autocomplete="off" />
                    <span class="error"></span>
                </div>
            </div>
        </div>
        <input type="hidden" name="grade_scale" value="<?php echo $page_data['grade_scale']; ?>">
        <input type="hidden" name="attendance" value="<?php echo $page_data['attendance']; ?>">
        <button type="submit" class="btn btn-1" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing"><i class="fas fa-plus-circle"></i> <?=translate('submit')?></button>
    </div>
    <?php echo form_close(); ?>
    <div class="row">
        <div class="col-md-12">
            <div id="card_holder" style="display: none;">
                <div class="box2 form-box">
                    <button type="button" class="btn btn-1" id="print"><i class="fas fa-print"></i> <?=translate('print')?></button>
                    <div id="card"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Main Container Ends -->

<script type="text/javascript">
    (function bootExamResults() {
        if (!window.jQuery) { return setTimeout(bootExamResults, 30); }
        var $ = window.jQuery;
    $(document).ready(function () {
        $('#class_id').on('change', function () {
            var classID = $(this).val();
            var $section = $('#section_id');
            $section.html('<option value=""><?=translate('loading')?>...</option>');
            if (!classID) {
                $section.html('<option value=""><?=translate('select_class_first')?></option>');
                return;
            }
            $.ajax({
                url: '<?=base_url('home/getSectionByClass')?>',
                type: 'POST',
                data: {class_id: classID, '<?=$this->security->get_csrf_token_name()?>': '<?=$this->security->get_csrf_hash()?>'},
                success: function (html) {
                    $section.html(html);
                }
            });
        });

        $('form.printIn').on('submit', function(e){
            e.preventDefault();
            var btn = $(this).find('[type="submit"]');
            var $this = $(this);
            $("#card_holder").hide();
            $.ajax({
                url: $(this).attr('action'),
                type: "POST",
                data: $(this).serialize(),
                dataType: "json",
                beforeSend: function () {
                    btn.button('loading');
                },
                success: function (data) {
                    $('.error').html("");
                    if (data.status == "fail") {
                        $.each(data.error, function (index, value) {
                            $this.find("[name='" + index + "']").parents('.form-group').find('.error').html(value);
                        });
                        btn.button('reset');
                    } else if (data.status == 0) {
                        btn.button('reset');
                        swal({
                            toast: true,
                            position: 'top-end',
                            type: 'error',
                            title: data.error,
                            confirmButtonClass: 'btn btn-default',
                            buttonsStyling: false,
                            timer: 8000
                        });
                    } else {
                        $('#card').html(data.card_data);
                        $("#card_holder").show(200);
                    }
                },
                error: function () {
                    btn.button('reset');
                    alert("An error occured, please try again");
                },
                complete: function () {
                    btn.button('reset');
                }
            });
        });

        $('#print').on('click', function(e){
            var oContent = document.getElementById('card').innerHTML;
            var frame1 = document.createElement('iframe');
            frame1.name = "frame1";
            frame1.style.position = "absolute";
            frame1.style.top = "-1000000px";
            document.body.appendChild(frame1);
            var frameDoc = frame1.contentWindow ? frame1.contentWindow : frame1.contentDocument.document ? frame1.contentDocument.document : frame1.contentDocument;
            frameDoc.document.open();
            //Create a new HTML document.
            frameDoc.document.write('<html><head><title></title>');
            frameDoc.document.write('<link rel="stylesheet" href="' + base_url + 'assets/vendor/bootstrap/css/bootstrap.min.css">');
            frameDoc.document.write('<link rel="stylesheet" href="' + base_url + 'assets/css/custom-style.css">');
            frameDoc.document.write('<link rel="stylesheet" href="' + base_url + 'assets/css/certificate.css">');
            frameDoc.document.write('</head><body>');
            frameDoc.document.write(oContent);
            frameDoc.document.write('</body></html>');
            frameDoc.document.close();
            setTimeout(function () {
                window.frames["frame1"].focus();
                window.frames["frame1"].print();
                frame1.remove();
            }, 500);
        });
    });
    })();
</script>