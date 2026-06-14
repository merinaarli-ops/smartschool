<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Saas — Super-admin controller for the SmartSchool.bd SaaS addon.
 *
 * Routes (referenced by application/views/layout/saas_menu.php):
 *   /saas/school                — list all tenant subscriptions
 *   /saas/pending_request       — pending signup queue
 *   /saas/school_approved       — recently approved signups
 *   /saas/package               — package catalog list
 *   /saas/package_edit/<id?>    — edit / create a package
 *   /saas/settings_general      — saas-level settings
 *   /saas/transactions          — all payments
 *   /saas/approve/<req_id>      — POST: approve a pending signup → creates branch
 *   /saas/reject/<req_id>       — POST: reject a pending signup
 *   /saas/suspend/<branch_id>   — POST: suspend a tenant
 *   /saas/activate/<branch_id>  — POST: re-activate a tenant
 *   /saas/extend/<branch_id>    — POST: extend by N days
 *   /saas/mark_paid/<inv_id>    — POST: mark an invoice paid (manual)
 *
 * @author SmartSchool.bd
 */
class Saas extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
        $this->load->model('branch_model');
        if (!is_superadmin_loggedin()) {
            $this->session->set_userdata('last_page', current_url());
            redirect(base_url(), 'refresh');
        }
    }

    // -------------------------------------------------------------------------
    // School (subscription) list
    // -------------------------------------------------------------------------
    public function index()              { redirect(base_url('saas/school')); }
    public function school()
    {
        $this->data['title']        = translate('school_subscription');
        $this->data['main_menu']    = 'saas';
        $this->data['sub_page']     = 'saas/school';
        $this->data['subscriptions'] = $this->saas_model->getSubscriptions();
        $this->data['packages']     = $this->saas_model->getPackages(true);
        $this->load->view('layout/index', $this->data);
    }

    // -------------------------------------------------------------------------
    // Pending signup requests
    // -------------------------------------------------------------------------
    public function pending_request()
    {
        $this->data['title']    = translate('pending_request');
        $this->data['main_menu']= 'saas';
        $this->data['sub_page'] = 'saas/pending_request';
        $this->data['requests'] = $this->saas_model->getPendingRequests('pending');
        $this->load->view('layout/index', $this->data);
    }

    public function school_approved()
    {
        $this->data['title']    = translate('approved_schools');
        $this->data['main_menu']= 'saas';
        $this->data['sub_page'] = 'saas/school_approved';
        $this->data['requests'] = $this->saas_model->getPendingRequests('approved');
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Approve a pending signup.
     *
     * Two-stage flow:
     *   GET  /saas/approve/<id>  → render the "approve with terms" form
     *                              (package, custom price, validity, renewal mode).
     *   POST /saas/approve/<id>  → actually provision the branch using the
     *                              posted terms.
     */
    public function approve($id = 0)
    {
        $id = (int)$id;
        $req = $this->saas_model->getPendingRequestById($id);
        if (!$req || $req->status !== 'pending') {
            set_alert('error', translate('request_not_found'));
            redirect(base_url('saas/pending_request'));
        }

        // GET → show the terms form, prefilled from the package (if any).
        if ($this->input->method() !== 'post' || $this->input->post('submit') !== 'approve') {
            $this->data['title']     = 'Approve signup — set subscription terms';
            $this->data['main_menu'] = 'saas';
            $this->data['sub_page']  = 'saas/approve_with_terms';
            $this->data['req']       = $req;
            $this->data['packages']  = $this->saas_model->getPackages(false);
            $this->load->view('layout/index', $this->data);
            return;
        }

        // POST → capture terms.
        $terms = [
            'package_id'         => $this->input->post('package_id') !== '' ? (int)$this->input->post('package_id') : null,
            'override_price_bdt' => $this->input->post('override_price_bdt'),
            'validity_days'      => $this->input->post('validity_days'),
            'renewal_mode'       => $this->input->post('renewal_mode') ?: 'auto_invoice',
        ];

        // Create branch row
        $branchData = [
            'name'             => $req->school_name,
            'school_name'      => $req->school_name_bn ?: $req->school_name,
            'email'            => $req->owner_email,
            'mobileno'         => $req->owner_phone,
            'currency'         => 'BDT',
            'symbol'           => '৳',
            'city'             => '',
            'state'            => '',
            'address'          => '',
            'translation'      => 'english',
            'timezone'         => 'Asia/Dhaka',
            'weekends'         => '1',
            'reg_prefix_digit' => 4,
            'status'           => 1,
        ];
        // Inject subdomain column added by 001_branch_extend.sql
        if ($this->db->field_exists('subdomain', 'branch')) {
            $branchData['subdomain'] = $req->subdomain;
        }
        if ($this->db->field_exists('slug', 'branch')) {
            $branchData['slug'] = $req->subdomain;
        }

        // Make sure `branch.institute_type` / `branch.institute_subtype`
        // and `saas_pending_request.institute_subtype` exist, then copy
        // the signup-time choices onto the branch row so the seeder can
        // pick the right class roster + Bangla exam term names.
        try {
            $this->load->library('nctb_subject_seeder');
            $this->nctb_subject_seeder->ensureInstituteTypeColumns();
        } catch (Exception $e) {
            log_message('error', 'ensureInstituteTypeColumns: ' . $e->getMessage());
        }
        $reqInstituteType    = isset($req->institute_type)    ? (string)$req->institute_type    : '';
        $reqInstituteSubtype = isset($req->institute_subtype) ? (string)$req->institute_subtype : '';
        if ($reqInstituteType !== '' && $this->db->field_exists('institute_type', 'branch')) {
            $branchData['institute_type'] = $reqInstituteType;
        }
        if ($reqInstituteSubtype !== '' && $this->db->field_exists('institute_subtype', 'branch')) {
            $branchData['institute_subtype'] = $reqInstituteSubtype;
        }

        $this->db->insert('branch', $branchData);
        $branchId = (int)$this->db->insert_id();

        // Clone the freshly-approved tenant's entire frontend (theme,
        // colours, logos, copyright, home-page layout, menus, services
        // list, …) from the existing template tenant — defaults to
        // `global_settings.cms_default_branch`, falls back to the lowest
        // branch with `cms_active=1`. Only the identity fields
        // (application_title, url_alias, email, mobile_no, address,
        // copyright_text) are rewritten with the new tenant's data so the
        // cloned subdomain still announces itself correctly. Returns the
        // branch id we cloned from, or null when no template was
        // available — in which case we fall back to the legacy
        // placeholder row so the public site still renders.
        $slug      = preg_replace('/[^a-z0-9_-]/', '_', strtolower($req->subdomain ?: $req->school_name));
        $overrides = [
            'application_title' => $req->school_name,
            'url_alias'         => $slug,
            'email'             => $req->owner_email,
            'mobile_no'         => $req->owner_phone,
            'address'           => '',
            'copyright_text'    => '© ' . date('Y') . ' ' . $req->school_name . ' — Powered by SmartSchool.bd',
        ];

        $this->load->model('tenant_provisioning_model');
        $cloned = $this->tenant_provisioning_model->cloneFrontendForTenant($branchId, $overrides);

        if (!$cloned) {
            // No template tenant exists yet (fresh install). Insert a
            // placeholder that populates every NOT NULL column so the row
            // survives MySQL strict mode and the public site renders.
            $this->db->insert('front_cms_setting', array_merge($overrides, [
                'branch_id'            => $branchId,
                'cms_active'           => 1,
                'theme'                => 'red',
                'captcha_status'       => 'disable',
                'recaptcha_site_key'   => '',
                'recaptcha_secret_key' => '',
                'fav_icon'             => '',
                'primary_color'        => '#2e7d32',
            ]));
        }

        // Custom domain mapping — honour the suffix the visitor picked on
        // the signup form (e.g. smartschool.bd vs institution.bd).
        $suffix = !empty($req->domain_suffix) ? $req->domain_suffix : 'smartschool.bd';
        $this->db->insert('custom_domain', [
            'school_id'   => $branchId,
            'url'         => $req->subdomain . '.' . $suffix,
            'domain_type' => 'subdomain',
            'status'      => 1,
            'notes'       => 'Auto-created by Saas::approve()',
        ]);

        // Subscription assignment — use the operator-captured terms.
        $this->saas_model->assignPackage(
            $branchId,
            $terms['package_id'],
            'trial',
            [
                'override_price_bdt' => $terms['override_price_bdt'],
                'validity_days'      => $terms['validity_days'],
                'renewal_mode'       => $terms['renewal_mode'],
            ]
        );

        // Owner login (staff + login_credential). Without this, the approval
        // creates a tenant nobody can sign in to.
        $ownerCreds = $this->_provision_owner_login($branchId, $req);

        // Seed the NCTB Bangladesh class roster (Play..Twelve), the default
        // Section A, the platform subject catalogue, and the subject_assign
        // rows linking every NCTB subject to every matching class + section
        // so the new tenant logs in to a fully wired class-subject grid
        // without touching anything. Best-effort: if any step fails we
        // still want the approval to go through.
        try {
            $this->load->library('nctb_subject_seeder');
            $this->nctb_subject_seeder->seedEverythingForBranch($branchId, [
                'institute_type'    => $reqInstituteType,
                'institute_subtype' => $reqInstituteSubtype,
            ]);
        } catch (Exception $e) {
            log_message('error', 'NCTB seed failed for branch '
                . $branchId . ': ' . $e->getMessage());
        }

        // Mark request processed
        $this->saas_model->markRequestProcessed($id, 'approved', $branchId);

        // Optional audit_log helper (table is created by 008_audit_log.sql).
        if ($this->db->table_exists('audit_log')) {
            $this->db->insert('audit_log', [
                'branch_id'   => $branchId,
                'actor_id'    => (int)$this->session->userdata('loggedin_userid'),
                'actor_role'  => (int)$this->session->userdata('loggedin_role_id'),
                'action'      => 'tenant.approve',
                'target_type' => 'branch',
                'target_id'   => $branchId,
                'meta'        => json_encode([
                    'subdomain'   => $req->subdomain,
                    'owner_email' => $req->owner_email,
                    'package_id'  => (int)$req->package_id,
                    'request_id'  => $id,
                ]),
                'ip'          => $this->input->ip_address(),
                'user_agent'  => substr((string)$this->input->user_agent(), 0, 255),
            ]);
        }

        // Best-effort Telegram notification to the platform admin.
        $this->load->helper('saas_notify');
        @saas_notify_signup_approved($req, $branchId);

        $msg = translate('school_approved_successfully')
             . ' (' . $req->subdomain . '.' . $suffix . ')';
        if ($ownerCreds) {
            $msg .= ' | Owner login → username: ' . html_escape($ownerCreds['username'])
                  . ' · temp password: ' . html_escape($ownerCreds['password'])
                  . ' (relay to owner; ask them to reset it on first login).';
        }
        set_alert('success', $msg);
        redirect(base_url('saas/school'));
    }

    /**
     * Provision the owner-side staff + login_credential rows for a
     * freshly-created tenant. Idempotent: if a login_credential already
     * exists for the owner_email on this branch we leave it alone.
     *
     * Returns ['username' => …, 'password' => …] on a new credential or
     * null if we couldn't create one (caller falls back to a soft warning).
     */
    private function _provision_owner_login($branchId, $req)
    {
        if (empty($req->owner_email)) return null;

        // Skip if a login already exists for this email/username.
        $existing = $this->db
            ->where('username', $req->owner_email);
        if ($this->db->field_exists('branch_id', 'login_credential')) {
            $this->db->where('branch_id', (int)$branchId);
        }
        if ($this->db->count_all_results('login_credential') > 0) {
            return null;
        }

        // Default department + designation rows for the new branch so the
        // existing /employee module renders staff records cleanly.
        $deptId = $this->_ensure_lookup_row('staff_department', [
            'branch_id' => $branchId, 'name' => 'Administration',
        ]);
        $desigId = $this->_ensure_lookup_row('staff_designation', [
            'branch_id' => $branchId, 'name' => 'Owner',
        ]);

        $staffRow = [
            'branch_id'        => $branchId,
            'staff_id'         => substr(app_generate_hash(), 3, 7),
            'name'             => $req->owner_name ?: $req->owner_email,
            'sex'              => 'Male',
            'religion'         => '',
            'blood_group'      => '',
            'birthday'         => null,
            'mobileno'         => (string)$req->owner_phone,
            'present_address'  => '',
            'permanent_address'=> '',
            'photo'            => 'defualt.png',
            'designation'      => $desigId,
            'department'       => $deptId,
            'joining_date'     => date('Y-m-d'),
            'qualification'    => '',
            'experience_details' => '',
            'total_experience' => 0,
            'email'            => $req->owner_email,
        ];
        $this->db->insert('staff', $staffRow);
        $staffId = (int)$this->db->insert_id();

        // Generate a random 12-char password and hash it.
        $rawPassword = $this->_random_password(12);
        $hashed      = $this->app_lib->pass_hashed($rawPassword);

        $loginRow = [
            'username' => $req->owner_email,
            'password' => $hashed,
            'role'     => 2,           // 2 = Branch admin
            'user_id'  => $staffId,
            'active'   => 1,
        ];
        if ($this->db->field_exists('branch_id', 'login_credential')) {
            $loginRow['branch_id'] = (int)$branchId;
        }
        $this->db->insert('login_credential', $loginRow);

        return ['username' => $req->owner_email, 'password' => $rawPassword];
    }

    private function _ensure_lookup_row($table, $row)
    {
        $hit = $this->db->where('branch_id', (int)$row['branch_id'])
                        ->where('name', $row['name'])
                        ->limit(1)
                        ->get($table)->row();
        if ($hit) return (int)$hit->id;
        $this->db->insert($table, $row);
        return (int)$this->db->insert_id();
    }

    private function _random_password($length = 12)
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public function reject($id = 0)
    {
        $this->saas_model->markRequestProcessed((int)$id, 'rejected');
        set_alert('success', translate('request_rejected'));
        redirect(base_url('saas/pending_request'));
    }

    /**
     * Bulk-approve every pending signup whose id is in POST['request_ids[]'].
     * Each row is provisioned through the same Saas::approve() path so the
     * branch + owner login + frontend clone happen for every approved tenant.
     */
    public function bulk_approve()
    {
        $this->require_post();
        $ids = (array)$this->input->post('request_ids');
        $ok = 0; $fail = 0;
        foreach ($ids as $rid) {
            $rid = (int)$rid;
            if ($rid <= 0) continue;
            try {
                $this->_provision_request($rid);
                $ok++;
            } catch (Exception $e) {
                $fail++;
                log_message('error', 'bulk_approve failed for #' . $rid . ': ' . $e->getMessage());
            }
        }
        set_alert($ok ? 'success' : 'error',
            'Bulk approve: ' . $ok . ' approved' . ($fail ? ', ' . $fail . ' failed' : ''));
        redirect(base_url('saas/pending_request'));
    }

    /**
     * Bulk-reject every pending signup whose id is in POST['request_ids[]'].
     */
    public function bulk_reject()
    {
        $this->require_post();
        $ids = (array)$this->input->post('request_ids');
        $ok = 0;
        foreach ($ids as $rid) {
            $rid = (int)$rid;
            if ($rid <= 0) continue;
            $this->saas_model->markRequestProcessed($rid, 'rejected');
            $ok++;
        }
        set_alert('success', 'Bulk reject: ' . $ok . ' rejected');
        redirect(base_url('saas/pending_request'));
    }

    /**
     * Refactored core of approve() so bulk_approve() can re-use it without
     * triggering the redirect path.
     */
    private function _provision_request($id)
    {
        $req = $this->saas_model->getPendingRequestById((int)$id);
        if (!$req || $req->status !== 'pending') return null;

        $branchData = [
            'name'             => $req->school_name,
            'school_name'      => $req->school_name_bn ?: $req->school_name,
            'email'            => $req->owner_email,
            'mobileno'         => $req->owner_phone,
            'currency'         => 'BDT',
            'symbol'           => '৳',
            'city'             => '',
            'state'            => '',
            'address'          => '',
            'translation'      => 'english',
            'timezone'         => 'Asia/Dhaka',
            'weekends'         => '1',
            'reg_prefix_digit' => 4,
            'status'           => 1,
        ];
        if ($this->db->field_exists('subdomain', 'branch')) $branchData['subdomain'] = $req->subdomain;
        if ($this->db->field_exists('slug', 'branch'))      $branchData['slug']      = $req->subdomain;

        // Mirror approve() — carry institute_type/subtype onto the branch
        // row so the seeder picks the right class roster.
        try {
            $this->load->library('nctb_subject_seeder');
            $this->nctb_subject_seeder->ensureInstituteTypeColumns();
        } catch (Exception $e) {
            log_message('error', 'ensureInstituteTypeColumns: ' . $e->getMessage());
        }
        $reqInstituteType    = isset($req->institute_type)    ? (string)$req->institute_type    : '';
        $reqInstituteSubtype = isset($req->institute_subtype) ? (string)$req->institute_subtype : '';
        if ($reqInstituteType !== '' && $this->db->field_exists('institute_type', 'branch')) {
            $branchData['institute_type'] = $reqInstituteType;
        }
        if ($reqInstituteSubtype !== '' && $this->db->field_exists('institute_subtype', 'branch')) {
            $branchData['institute_subtype'] = $reqInstituteSubtype;
        }

        $this->db->insert('branch', $branchData);
        $branchId = (int)$this->db->insert_id();

        $slug      = preg_replace('/[^a-z0-9_-]/', '_', strtolower($req->subdomain ?: $req->school_name));
        $overrides = [
            'application_title' => $req->school_name,
            'url_alias'         => $slug,
            'email'             => $req->owner_email,
            'mobile_no'         => $req->owner_phone,
            'address'           => '',
            'copyright_text'    => '© ' . date('Y') . ' ' . $req->school_name . ' — Powered by SmartSchool.bd',
        ];
        $this->load->model('tenant_provisioning_model');
        $cloned = $this->tenant_provisioning_model->cloneFrontendForTenant($branchId, $overrides);
        if (!$cloned) {
            $this->db->insert('front_cms_setting', array_merge($overrides, [
                'branch_id'            => $branchId,
                'cms_active'           => 1,
                'theme'                => 'red',
                'captcha_status'       => 'disable',
                'recaptcha_site_key'   => '',
                'recaptcha_secret_key' => '',
                'fav_icon'             => '',
                'primary_color'        => '#2e7d32',
            ]));
        }
        $suffix = !empty($req->domain_suffix) ? $req->domain_suffix : 'smartschool.bd';
        $this->db->insert('custom_domain', [
            'school_id'   => $branchId,
            'url'         => $req->subdomain . '.' . $suffix,
            'domain_type' => 'subdomain',
            'status'      => 1,
            'notes'       => 'Auto-created by Saas::_provision_request()',
        ]);
        // Bulk path → use whatever package was on the request (may be null
        // for signup variants A/B); admin can refine terms later via Edit
        // terms on /saas/school.
        $this->saas_model->assignPackage(
            $branchId,
            $req->package_id ? (int)$req->package_id : null,
            'trial'
        );
        $this->_provision_owner_login($branchId, $req);

        // Seed NCTB classes + sections + subjects + subject_assign for the
        // new tenant (best-effort, mirrors the single approve() path).
        try {
            $this->load->library('nctb_subject_seeder');
            $this->nctb_subject_seeder->seedEverythingForBranch($branchId, [
                'institute_type'    => $reqInstituteType,
                'institute_subtype' => $reqInstituteSubtype,
            ]);
        } catch (Exception $e) {
            log_message('error', 'NCTB seed failed for branch '
                . $branchId . ': ' . $e->getMessage());
        }

        $this->saas_model->markRequestProcessed((int)$id, 'approved', $branchId);

        $this->load->helper('saas_notify');
        @saas_notify_signup_approved($req, $branchId);

        return $branchId;
    }

    // -------------------------------------------------------------------------
    // Package CRUD
    // -------------------------------------------------------------------------
    public function package()
    {
        $this->data['title']    = translate('plan');
        $this->data['main_menu']= 'saas';
        $this->data['sub_page'] = 'saas/package';
        $this->data['packages'] = $this->saas_model->getPackages(false);
        $this->load->view('layout/index', $this->data);
    }

    public function package_edit($id = 0)
    {
        $id = (int)$id;
        if ($this->input->post('submit') === 'save') {
            $featuresInput = $this->input->post('features');
            $features = is_array($featuresInput) ? $featuresInput : array_filter(array_map('trim', explode(',', (string)$featuresInput)));

            $data = [
                'code'             => strtolower(trim((string)$this->input->post('code'))),
                'name'             => (string)$this->input->post('name'),
                'price_bdt'        => (float)$this->input->post('price_bdt'),
                'price_usd'        => $this->input->post('price_usd') !== '' && $this->input->post('price_usd') !== null ? (float)$this->input->post('price_usd') : null,
                'billing_period'   => $this->input->post('billing_period') ?: 'monthly',
                'features'         => json_encode(array_values($features)),
                'limits'           => $this->input->post('limits_json') ?: '{}',
                'is_active'        => $this->input->post('is_active')        ? 1 : 0,
                'is_default_trial' => $this->input->post('is_default_trial') ? 1 : 0,
                'trial_days'       => (int)$this->input->post('trial_days'),
                'sort_order'       => (int)$this->input->post('sort_order'),
                'description'      => (string)$this->input->post('description'),
            ];
            // Only persist *_limit columns if the schema actually has them
            // (older 007 schemas stored limits exclusively as JSON in `limits`).
            foreach (['student_limit', 'staff_limit', 'teacher_limit', 'parents_limit'] as $col) {
                if ($this->db->field_exists($col, 'saas_package')) {
                    $val = $this->input->post($col);
                    $data[$col] = ($val !== '' && $val !== null) ? (int)$val : null;
                }
            }
            $newId = $this->saas_model->savePackage($data, $id ?: null);
            set_alert('success', translate('information_has_been_saved_successfully'));
            redirect(base_url('saas/package_edit/' . $newId));
        }

        $this->data['title']    = translate('plan');
        $this->data['main_menu']= 'saas';
        $this->data['sub_page'] = 'saas/package_edit';
        $this->data['pkg']      = $id ? $this->saas_model->getPackageById($id) : null;
        $this->load->view('layout/index', $this->data);
    }

    public function package_delete($id = 0)
    {
        $this->saas_model->deletePackage((int)$id);
        set_alert('success', translate('information_has_been_deleted_successfully'));
        redirect(base_url('saas/package'));
    }

    public function assign_package()
    {
        $branchId  = (int)$this->input->post('branch_id');
        $packageId = (int)$this->input->post('package_id');
        $status    = $this->input->post('status') ?: 'active';
        $this->saas_model->assignPackage($branchId, $packageId, $status);
        set_alert('success', translate('plan_assigned_successfully'));
        redirect(base_url('saas/school'));
    }

    // -------------------------------------------------------------------------
    // Lifecycle actions (all require POST so a stray GET via browser bar
    // doesn't silently mutate subscription state)
    // -------------------------------------------------------------------------
    private function require_post()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('saas/school'));
        }
    }

    /**
     * Render a friendly "please run migration X" banner if a required table is
     * missing, instead of letting the page 500 from the underlying DB error.
     *
     * Returns TRUE if the banner was rendered (and the caller should `return;`).
     * Returns FALSE if the table exists and the caller can proceed normally.
     */
    private function _render_if_table_missing($table, $migrationFile, $pageTitle, $requiredModelFiles = [])
    {
        // 0) Required model files present on disk? (catches the case where the
        //    operator ran the migration but forgot to upload the model file.)
        foreach ($requiredModelFiles as $modelFile) {
            if (!file_exists(APPPATH . 'models/' . $modelFile)) {
                $this->_render_setup_banner(
                    $pageTitle,
                    'missing model file: application/models/' . $modelFile,
                    $migrationFile,
                    $table
                );
                return true;
            }
        }

        // 1) Table exists? Use SHOW TABLES (no information_schema perms needed).
        //    Swallow any DB error and treat as "missing" so we render the banner
        //    instead of letting CI3 die from db_debug=true.
        $exists = false;
        try {
            $sql = 'SHOW TABLES LIKE ' . $this->db->escape($table);
            $q   = @$this->db->query($sql);
            $exists = ($q && $q->num_rows() > 0);
        } catch (\Throwable $e) {
            $exists = false;
        }
        if ($exists) return false;

        $this->_render_setup_banner($pageTitle, 'missing table: ' . $table, $migrationFile, $table);
        return true;
    }

    private function _render_setup_banner($pageTitle, $reason, $migrationFile, $missingTable)
    {
        $this->data['title']         = $pageTitle . ' - setup required';
        $this->data['main_menu']     = 'saas_setting';
        $this->data['sub_page']      = 'saas/_migration_required';
        $this->data['page_title']    = $pageTitle . ' - setup required';
        $this->data['missing_table'] = $missingTable;
        $this->data['reason']        = $reason;
        $this->data['migration_file']= $migrationFile;
        $this->data['migration_url'] = 'https://github.com/academicschoolbd/smartschool/blob/feat/p5-2-billing-foundation/docs/db/migrations/' . $migrationFile;
        $this->load->view('layout/index', $this->data);
    }

    public function suspend($branchId = 0)
    {
        $this->require_post();
        $this->saas_model->setStatus((int)$branchId, 'suspended');
        set_alert('success', translate('suspended'));
        redirect(base_url('saas/school'));
    }

    public function activate($branchId = 0)
    {
        $this->require_post();
        $this->saas_model->setStatus((int)$branchId, 'active');
        set_alert('success', translate('activated'));
        redirect(base_url('saas/school'));
    }

    public function cancel($branchId = 0)
    {
        $this->require_post();
        $this->saas_model->setStatus((int)$branchId, 'cancelled');
        set_alert('success', translate('cancelled'));
        redirect(base_url('saas/school'));
    }

    public function extend($branchId = 0)
    {
        $this->require_post();
        $days = (int)$this->input->post('days');
        if ($days < 1) $days = 30;
        $ok = $this->saas_model->extendPeriod((int)$branchId, $days);
        set_alert($ok ? 'success' : 'error', $ok ? translate('subscription_extended') : translate('subscription_not_found'));
        redirect(base_url('saas/school'));
    }

    /**
     * Edit per-subscription terms (package / override price / validity / renewal mode).
     *
     * GET  /saas/edit_terms/<branch_id>  → form
     * POST /saas/edit_terms/<branch_id>  → save
     */
    public function edit_terms($branchId = 0)
    {
        $branchId = (int)$branchId;
        $sub = $this->saas_model->getSubscriptionByBranch($branchId);
        if (!$sub) {
            set_alert('error', 'Subscription not found for branch #' . $branchId);
            redirect(base_url('saas/school'));
        }

        if ($this->input->method() === 'post' && $this->input->post('submit') === 'save') {
            $opts = [
                'package_id'         => $this->input->post('package_id'),
                'override_price_bdt' => $this->input->post('override_price_bdt'),
                'validity_days'      => $this->input->post('validity_days'),
                'renewal_mode'       => $this->input->post('renewal_mode'),
            ];
            $ok = $this->saas_model->updateTerms($branchId, $opts);
            set_alert($ok ? 'success' : 'error',
                $ok ? 'Subscription terms updated.' : 'No changes were saved.');
            redirect(base_url('saas/school'));
        }

        $branch = $this->db->where('id', $branchId)->get('branch')->row();
        $this->data['title']     = 'Edit subscription terms';
        $this->data['main_menu'] = 'saas';
        $this->data['sub_page']  = 'saas/edit_terms';
        $this->data['branch']    = $branch;
        $this->data['sub']       = $sub;
        $this->data['packages']  = $this->saas_model->getPackages(false);
        $this->load->view('layout/index', $this->data);
    }

    /** Bulk-suspend selected branches (POST branch_ids[]). */
    public function bulk_suspend()
    {
        $this->require_post();
        $ids = (array)$this->input->post('branch_ids');
        $ok = 0;
        foreach ($ids as $bid) {
            $bid = (int)$bid;
            if ($bid <= 0) continue;
            $this->saas_model->setStatus($bid, 'suspended');
            $ok++;
        }
        set_alert('success', 'Bulk suspend: ' . $ok . ' suspended');
        redirect(base_url('saas/school'));
    }

    /** Bulk-activate selected branches (POST branch_ids[]). */
    public function bulk_activate()
    {
        $this->require_post();
        $ids = (array)$this->input->post('branch_ids');
        $ok = 0;
        foreach ($ids as $bid) {
            $bid = (int)$bid;
            if ($bid <= 0) continue;
            $this->saas_model->setStatus($bid, 'active');
            $ok++;
        }
        set_alert('success', 'Bulk activate: ' . $ok . ' activated');
        redirect(base_url('saas/school'));
    }

    /** Bulk-extend selected branches by POST['days'] (default 30, capped 1..3650). */
    public function bulk_extend()
    {
        $this->require_post();
        $ids = (array)$this->input->post('branch_ids');
        $days = (int)$this->input->post('days');
        if ($days < 1)    $days = 30;
        if ($days > 3650) $days = 3650;
        $ok = 0;
        foreach ($ids as $bid) {
            $bid = (int)$bid;
            if ($bid <= 0) continue;
            if ($this->saas_model->extendPeriod($bid, $days)) $ok++;
        }
        set_alert('success', 'Bulk extend: ' . $ok . ' extended by ' . $days . ' day(s)');
        redirect(base_url('saas/school'));
    }

    /**
     * Stream a CSV export of every tenant subscription. Used by the
     * "Export CSV" button on /saas/school.
     */
    public function export_subscriptions()
    {
        $rows = $this->saas_model->getSubscriptions();

        $filename = 'subscriptions-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel doesn't mangle non-ASCII school names.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'branch_id', 'school_name', 'subdomain', 'package', 'price_bdt',
            'status', 'trial_ends_at', 'current_period_start', 'current_period_end',
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->school_id ?? '',
                $r->branch_name ?? '',
                $r->subdomain ?? '',
                $r->package_name ?? '',
                isset($r->price_bdt) ? (float)$r->price_bdt : '',
                $r->status ?? '',
                $r->trial_ends_at ?? '',
                $r->current_period_start ?? '',
                $r->current_period_end ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------
    public function transactions()
    {
        $this->data['title']     = translate('transactions');
        $this->data['main_menu'] = 'saas';
        $this->data['sub_page']  = 'saas/transactions';
        $this->data['invoices']  = $this->saas_model->getAllInvoices();
        $this->data['payments']  = $this->saas_model->getAllPayments();
        $this->load->view('layout/index', $this->data);
    }

    // -------------------------------------------------------------------------
    // Subdomain manager (active / pending / reserved)
    // -------------------------------------------------------------------------
    public function subdomains()
    {
        $this->load->model('saas_setting_model');
        $filter = trim((string)$this->input->get('q'));
        $this->data['title']     = 'Registered subdomains';
        $this->data['main_menu'] = 'saas';
        $this->data['sub_page']  = 'saas/subdomains';
        $this->data['rows']      = $this->saas_model->listAllSubdomains($filter);
        $this->data['filter']    = $filter;
        $this->data['suffixes']  = $this->saas_setting_model->getDomainSuffixes();
        $this->load->view('layout/index', $this->data);
    }

    public function subdomain_reserve()
    {
        $this->require_post();
        $sd     = $this->input->post('subdomain');
        $sfx    = $this->input->post('domain_suffix');
        $reason = $this->input->post('reason');
        $res = $this->saas_model->addReservedSubdomain($sd, $sfx, $reason, (int)$this->session->userdata('loggedin_user_id'));
        set_alert($res['ok'] ? 'success' : 'error', $res['msg']);
        redirect(base_url('saas/subdomains'));
    }

    public function subdomain_add_manual()
    {
        $this->require_post();
        $sd    = $this->input->post('subdomain');
        $sfx   = $this->input->post('domain_suffix');
        $name  = $this->input->post('school_name');
        $email = $this->input->post('owner_email');
        $res = $this->saas_model->manuallyAddSubdomain($sd, $sfx, $name, $email, (int)$this->session->userdata('loggedin_user_id'));
        set_alert($res['ok'] ? 'success' : 'error', $res['msg']);
        if ($res['ok']) redirect(base_url('saas/pending_request'));
        redirect(base_url('saas/subdomains'));
    }

    public function subdomain_delete($composite = '')
    {
        $this->require_post();
        $res = $this->saas_model->deleteSubdomainClaim($composite);
        set_alert($res['ok'] ? 'success' : 'error', $res['msg']);
        redirect(base_url('saas/subdomains'));
    }

    public function mark_paid($invoiceId = 0)
    {
        $this->require_post();
        $ok = $this->saas_model->markInvoicePaid((int)$invoiceId, 'manual', 'admin-manual-' . time());
        set_alert($ok ? 'success' : 'error', $ok ? translate('invoice_marked_paid') : translate('action_failed'));
        redirect(base_url('saas/transactions'));
    }

    public function create_invoice()
    {
        $this->require_post();
        $branchId = (int)$this->input->post('branch_id');
        $amount   = (float)$this->input->post('amount');
        $sub = $this->saas_model->getSubscriptionByBranch($branchId);
        $subId = $sub ? (int)$sub->id : null;
        $invoiceId = $this->saas_model->createInvoice($branchId, $subId, $amount, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));
        set_alert('success', translate('invoice_created'));
        // If the caller asked for a pay page ("go_pay=1"), redirect to it;
        // otherwise fall back to the transactions list as before.
        if ((int)$this->input->post('go_pay') === 1 && $invoiceId > 0) {
            redirect(base_url('billing/pay/' . (int)$invoiceId));
        }
        redirect(base_url('saas/transactions'));
    }

    // -------------------------------------------------------------------------
    // Payment gateways (super-admin)
    // -------------------------------------------------------------------------
    public function payment_gateways()
    {
        $this->load->model('saas_payment_gateway_model');
        $this->data['title']    = 'Payment gateways';
        $this->data['main_menu']= 'saas_setting';
        $this->data['sub_page'] = 'saas/payment_gateways';
        $this->data['gateways'] = $this->saas_payment_gateway_model->getAll();
        $this->load->view('layout/index', $this->data);
    }

    public function payment_gateway_edit($id = 0)
    {
        $this->load->model('saas_payment_gateway_model');
        $row = $this->saas_payment_gateway_model->getById((int)$id);
        if (!$row) {
            set_alert('error', translate('not_found'));
            redirect(base_url('saas/payment_gateways'));
        }
        $schemas = self::_credentials_field_schemas();
        $samples = self::_credentials_samples();
        $existing = $row->credentials_json ? (json_decode($row->credentials_json, true) ?: []) : [];
        $this->data['title']         = 'Edit gateway';
        $this->data['main_menu']     = 'saas_setting';
        $this->data['sub_page']      = 'saas/payment_gateways';
        $this->data['gateway']       = $row;
        $this->data['fields']        = $schemas[$row->code] ?? [];
        $this->data['values']        = $existing;
        $this->data['expected_keys'] = isset($samples[$row->code]['keys']) ? implode(', ', $samples[$row->code]['keys']) : '(none)';
        $this->data['sample_json']   = isset($samples[$row->code]['sample']) ? $samples[$row->code]['sample'] : '{}';
        $this->load->view('layout/index', $this->data);
    }

    public function save_payment_gateway($id = 0)
    {
        $this->require_post();
        $this->load->model('saas_payment_gateway_model');
        $row = $this->saas_payment_gateway_model->getById((int)$id);
        if (!$row) {
            set_alert('error', translate('not_found'));
            redirect(base_url('saas/payment_gateways'));
        }

        // Two save paths:
        //   1. Structured fields (the new per-provider form).
        //   2. Raw JSON (the Advanced panel — kept for power users).
        // The form submits BOTH; if cred[<key>] is non-empty for any key we
        // assemble JSON from it, otherwise we fall back to the raw textarea.
        $schemas = self::_credentials_field_schemas();
        $schema  = $schemas[$row->code] ?? [];
        $structured = $this->input->post('cred');
        $credsForDb = null;

        if (is_array($structured) && !empty($schema)) {
            $assembled = [];
            foreach ($schema as $field) {
                $key = $field['name'];
                $val = isset($structured[$key]) ? trim((string)$structured[$key]) : '';
                if ($val !== '') $assembled[$key] = $val;
            }
            if (!empty($assembled)) {
                $credsForDb = json_encode($assembled, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // Fallback to the raw JSON textarea (Advanced panel) if no structured
        // values were provided.
        if ($credsForDb === null) {
            $credsRaw = trim((string)$this->input->post('credentials_json'));
            if ($credsRaw !== '') {
                $decoded = json_decode($credsRaw, true);
                if (!is_array($decoded)) {
                    set_alert('error', 'Credentials must be a JSON object.');
                    redirect(base_url('saas/payment_gateway_edit/' . (int)$id));
                }
                $credsForDb = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $this->saas_payment_gateway_model->save((int)$id, [
            'is_enabled'        => (int)$this->input->post('is_enabled') === 1 ? 1 : 0,
            'is_sandbox'        => (int)$this->input->post('is_sandbox') === 1 ? 1 : 0,
            'credentials_json'  => $credsForDb,
        ]);
        set_alert('success', translate('saved_successfully'));
        redirect(base_url('saas/payment_gateways'));
    }

    public function toggle_payment_gateway($id = 0)
    {
        $this->require_post();
        $this->load->model('saas_payment_gateway_model');
        $row = $this->saas_payment_gateway_model->getById((int)$id);
        if (!$row) {
            set_alert('error', translate('not_found'));
            redirect(base_url('saas/payment_gateways'));
        }
        $this->saas_payment_gateway_model->setEnabled((int)$id, !$row->is_enabled);
        set_alert('success', $row->is_enabled ? translate('disabled') : translate('enabled'));
        redirect(base_url('saas/payment_gateways'));
    }

    // -------------------------------------------------------------------------
    // Manual payment submissions (super-admin approval queue)
    // -------------------------------------------------------------------------
    public function manual_payments()
    {
        if ($this->_render_if_table_missing(
            'saas_manual_payment_submission',
            '2026-05-22b_manual_payments_and_telegram.sql',
            'Manual payments',
            ['Saas_manual_payment_submission_model.php']
        )) return;

        $this->load->model('saas_manual_payment_submission_model');
        $this->data['title']    = 'Manual payments';
        $this->data['main_menu']= 'saas_setting';
        $this->data['sub_page'] = 'saas/manual_payments';
        $this->data['pending']  = $this->saas_manual_payment_submission_model->getPending();
        $this->data['history']  = $this->saas_manual_payment_submission_model->getAll(100);
        $this->load->view('layout/index', $this->data);
    }

    public function approve_manual_payment($id = 0)
    {
        $this->require_post();
        $this->load->model('saas_manual_payment_submission_model');
        $this->load->helper('saas_notify');
        $sub = $this->saas_manual_payment_submission_model->getById((int)$id);
        if (!$sub) {
            set_alert('error', translate('not_found'));
            redirect(base_url('saas/manual_payments'));
        }
        if ($sub->status !== 'pending') {
            set_alert('error', 'This submission has already been reviewed.');
            redirect(base_url('saas/manual_payments'));
        }
        $inv = $this->saas_model->getInvoiceById((int)$sub->invoice_id);
        if (!$inv) {
            set_alert('error', 'Invoice not found.');
            redirect(base_url('saas/manual_payments'));
        }
        if ($inv->status !== 'paid') {
            $this->saas_model->markInvoicePaid(
                (int)$inv->id,
                'manual',
                'manual-' . $sub->id . '-' . $sub->txn_ref,
                [
                    'submission_id' => $sub->id,
                    'payer_name'    => $sub->payer_name,
                    'payer_phone'   => $sub->payer_phone,
                    'screenshot'    => $sub->screenshot_path,
                ]
            );
        }
        $this->saas_manual_payment_submission_model->setStatus(
            (int)$sub->id,
            'approved',
            (int)$this->session->userdata('loggedin_user_id'),
            trim((string)$this->input->post('review_notes')) ?: null
        );
        $branch = $this->db->where('id', (int)$inv->branch_id)->get('branch')->row();
        saas_notify_payment_paid($inv, $branch, 'manual', $sub->txn_ref);
        set_alert('success', 'Submission approved; invoice marked paid.');
        redirect(base_url('saas/manual_payments'));
    }

    public function reject_manual_payment($id = 0)
    {
        $this->require_post();
        $this->load->model('saas_manual_payment_submission_model');
        $sub = $this->saas_manual_payment_submission_model->getById((int)$id);
        if (!$sub) {
            set_alert('error', translate('not_found'));
            redirect(base_url('saas/manual_payments'));
        }
        if ($sub->status !== 'pending') {
            set_alert('error', 'This submission has already been reviewed.');
            redirect(base_url('saas/manual_payments'));
        }
        $this->saas_manual_payment_submission_model->setStatus(
            (int)$sub->id,
            'rejected',
            (int)$this->session->userdata('loggedin_user_id'),
            trim((string)$this->input->post('review_notes')) ?: null
        );
        set_alert('success', 'Submission rejected.');
        redirect(base_url('saas/manual_payments'));
    }

    // -------------------------------------------------------------------------
    // Notification channel settings (super-admin)
    // -------------------------------------------------------------------------
    public function notifications()
    {
        if ($this->_render_if_table_missing(
            'saas_notification_channel',
            '2026-05-22b_manual_payments_and_telegram.sql',
            'Notifications',
            ['Saas_notification_channel_model.php']
        )) return;

        $this->load->model('saas_notification_channel_model');
        $tg = $this->saas_notification_channel_model->getByCode('telegram');
        $cfg = $this->saas_notification_channel_model->getConfig('telegram');
        $this->data['title']            = 'Notifications';
        $this->data['main_menu']        = 'saas_setting';
        $this->data['sub_page']         = 'saas/notifications';
        $this->data['telegram']         = $tg;
        $this->data['telegram_config']  = $cfg;
        $this->load->view('layout/index', $this->data);
    }

    public function save_notifications()
    {
        $this->require_post();
        $this->load->model('saas_notification_channel_model');
        $isEnabled = (int)$this->input->post('telegram_enabled') === 1;
        $cfg = [
            'bot_token'     => trim((string)$this->input->post('bot_token')),
            'admin_chat_id' => trim((string)$this->input->post('admin_chat_id')),
        ];
        $this->saas_notification_channel_model->save('telegram', $cfg, $isEnabled);
        set_alert('success', translate('saved_successfully'));
        redirect(base_url('saas/notifications'));
    }

    public function test_telegram()
    {
        $this->require_post();
        $this->load->helper('saas_notify');
        $ok = notify_admin_telegram("\xF0\x9F\x94\x94 Test from SmartSchool.bd at " . date('Y-m-d H:i:s') . ".\nIf you can read this, Telegram notifications are wired up correctly.");
        if ($ok) set_alert('success', 'Test message sent. Check the admin Telegram chat.');
        else     set_alert('error', 'Failed to send. Check bot token + chat id, and confirm the bot is enabled.');
        redirect(base_url('saas/notifications'));
    }

    // -------------------------------------------------------------------------
    // SaaS billing settings (super-admin) -- key/value config table.
    // -------------------------------------------------------------------------
    public function billing_settings()
    {
        if ($this->_render_if_table_missing(
            'saas_setting',
            '2026-05-22c_saas_setting.sql',
            'Billing settings',
            ['Saas_setting_model.php']
        )) return;

        $this->load->model('saas_setting_model');
        $this->data['title']     = 'Billing settings';
        $this->data['main_menu'] = 'saas_setting';
        $this->data['sub_page']  = 'saas/billing_settings';
        $this->data['settings']  = $this->saas_setting_model->getAllAsMap();
        $this->load->view('layout/index', $this->data);
    }

    public function save_billing_settings()
    {
        $this->require_post();
        $this->load->model('saas_setting_model');
        $grace = (int)$this->input->post('renewal_grace_days');
        if ($grace < 0)   $grace = 0;
        if ($grace > 365) $grace = 365;
        $this->saas_setting_model->setMany([
            'renewal_grace_days'        => (string)$grace,
            'renewal_email_subject'     => trim((string)$this->input->post('renewal_email_subject')),
            'renewal_email_body'        => (string)$this->input->post('renewal_email_body'),
            'renewal_email_from_name'   => trim((string)$this->input->post('renewal_email_from_name')),
            'renewal_email_from_email'  => trim((string)$this->input->post('renewal_email_from_email')),
            'billing_contact_email'     => trim((string)$this->input->post('billing_contact_email')),
        ]);

        // Allowed signup domain suffixes — newline / comma separated in the form.
        $raw = (string)$this->input->post('signup_domain_suffixes');
        if ($raw !== '') {
            $list = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $this->saas_setting_model->setDomainSuffixes($list);
        }

        set_alert('success', translate('saved_successfully'));
        redirect(base_url('saas/billing_settings'));
    }

    public function run_renewal_cron_now()
    {
        $this->require_post();
        require_once APPPATH . 'libraries/Saas_renewal_runner.php';
        $this->load->model('saas_setting_model');
        $this->load->helper('saas_notify');
        $runner = new Saas_renewal_runner($this);
        $stats  = $runner->run();
        saas_notify_renewal_cron($stats['created'], $stats['emailed'], $stats['skipped']);
        set_alert('success', sprintf(
            'Renewal run finished. Invoices created: %d, emails sent: %d, skipped: %d.',
            $stats['created'], $stats['emailed'], $stats['skipped']
        ));
        redirect(base_url('saas/billing_settings'));
    }

    /**
     * Structured per-provider field schema used by the new payment_gateway_edit view.
     * Each field: name (POST key under cred[]), label, type (text|password|textarea|email|url),
     * required (bool), placeholder (hint), help (one-line description).
     */
    private static function _credentials_field_schemas()
    {
        return [
            'manual' => [
                ['name' => 'bank_name',      'label' => 'Bank / MFS name',         'type' => 'text',     'required' => false, 'placeholder' => 'e.g. Brac Bank, bKash, Nagad'],
                ['name' => 'account_name',   'label' => 'Account name',            'type' => 'text',     'required' => false, 'placeholder' => 'SmartSchool.bd Ltd'],
                ['name' => 'account_number', 'label' => 'Account / wallet number', 'type' => 'text',     'required' => false, 'placeholder' => '0123456789'],
                ['name' => 'routing_number', 'label' => 'Routing / IFSC (optional)','type' => 'text',    'required' => false, 'placeholder' => ''],
                ['name' => 'instructions',   'label' => 'Instructions (shown to tenants)', 'type' => 'textarea', 'required' => false, 'placeholder' => "Wire the invoice amount to the account above. Use your invoice number as the reference. Upload your bank receipt below.", 'help' => 'Free text; appears on the manual pay page.'],
            ],
            'sslcommerz' => [
                ['name' => 'store_id',     'label' => 'Store ID',       'type' => 'text',     'required' => true,  'placeholder' => 'yourstore'],
                ['name' => 'store_passwd', 'label' => 'Store password', 'type' => 'password', 'required' => true,  'placeholder' => ''],
            ],
            'stripe' => [
                ['name' => 'secret_key',      'label' => 'Secret key',       'type' => 'password', 'required' => true,  'placeholder' => 'sk_test_...'],
                ['name' => 'publishable_key', 'label' => 'Publishable key',  'type' => 'text',     'required' => false, 'placeholder' => 'pk_test_...'],
                ['name' => 'webhook_secret',  'label' => 'Webhook secret',   'type' => 'password', 'required' => false, 'placeholder' => 'whsec_...'],
            ],
            'bkash' => [
                ['name' => 'app_key',    'label' => 'App key',    'type' => 'text',     'required' => true],
                ['name' => 'app_secret', 'label' => 'App secret', 'type' => 'password', 'required' => true],
                ['name' => 'username',   'label' => 'Username',   'type' => 'text',     'required' => true],
                ['name' => 'password',   'label' => 'Password',   'type' => 'password', 'required' => true],
            ],
            'nagad' => [
                ['name' => 'merchant_id',     'label' => 'Merchant ID',     'type' => 'text',     'required' => true],
                ['name' => 'merchant_number', 'label' => 'Merchant number', 'type' => 'text',     'required' => true],
                ['name' => 'public_key',      'label' => 'Nagad public key (PEM or base64)',  'type' => 'textarea', 'required' => true],
                ['name' => 'private_key',     'label' => 'Merchant private key (PEM or base64)', 'type' => 'textarea', 'required' => true],
            ],
            'rocket' => [
                ['name' => 'api_base',    'label' => 'API base URL',  'type' => 'url',      'required' => true, 'placeholder' => 'https://...'],
                ['name' => 'merchant_id', 'label' => 'Merchant ID',   'type' => 'text',     'required' => true],
                ['name' => 'api_key',     'label' => 'API key',       'type' => 'text',     'required' => true],
                ['name' => 'secret',      'label' => 'Secret',        'type' => 'password', 'required' => true],
            ],
            'paykureghor' => [
                ['name' => 'api_key',    'label' => 'API key',    'type' => 'text',     'required' => true, 'placeholder' => 'gnXi7etgWNhFyFGZFr...'],
                ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'required' => true],
                ['name' => 'brand_key',  'label' => 'Brand key',  'type' => 'text',     'required' => true, 'help' => 'Pick the brand from your Paykure Ghor dashboard.'],
            ],
        ];
    }

    /** Expected credential keys + a sample placeholder per provider. */
    private static function _credentials_samples()
    {
        return [
            'manual'      => ['keys' => ['instructions'],
                              'sample' => "{\n  \"instructions\": \"Wire BDT amount to A/C 0123456789 (Brac Bank, dhanmondi branch). Email receipt to billing@smartschool.bd.\"\n}"],
            'sslcommerz'  => ['keys' => ['store_id', 'store_passwd'],
                              'sample' => "{\n  \"store_id\": \"\",\n  \"store_passwd\": \"\"\n}"],
            'stripe'      => ['keys' => ['secret_key', 'publishable_key', 'webhook_secret'],
                              'sample' => "{\n  \"secret_key\": \"sk_test_\",\n  \"publishable_key\": \"pk_test_\",\n  \"webhook_secret\": \"whsec_\"\n}"],
            'bkash'       => ['keys' => ['app_key', 'app_secret', 'username', 'password'],
                              'sample' => "{\n  \"app_key\": \"\",\n  \"app_secret\": \"\",\n  \"username\": \"\",\n  \"password\": \"\"\n}"],
            'nagad'       => ['keys' => ['merchant_id', 'merchant_number', 'public_key', 'private_key'],
                              'sample' => "{\n  \"merchant_id\": \"\",\n  \"merchant_number\": \"\",\n  \"public_key\": \"<base64 or PEM>\",\n  \"private_key\": \"<base64 or PEM>\"\n}"],
            'rocket'      => ['keys' => ['api_base', 'merchant_id', 'api_key', 'secret'],
                              'sample' => "{\n  \"api_base\": \"https://\",\n  \"merchant_id\": \"\",\n  \"api_key\": \"\",\n  \"secret\": \"\"\n}"],
            'paykureghor' => ['keys' => ['api_key', 'secret_key', 'brand_key'],
                              'sample' => "{\n  \"api_key\": \"\",\n  \"secret_key\": \"\",\n  \"brand_key\": \"\"\n}"],
        ];
    }

    // -------------------------------------------------------------------------
    // Settings (very lightweight — read from `global_settings`)
    // -------------------------------------------------------------------------
    public function settings_general()
    {
        $this->load->model('tenant_provisioning_model');

        // POST: persist the chosen template branch in
        // `global_settings.cms_default_branch`. From this point onwards
        // every /saas/approve (and every Frontend_Controller self-heal)
        // clones every branch-scoped `front_cms_*` row from this branch
        // onto the new tenant — so the freshly-approved subdomain comes
        // up looking identical to the chosen template.
        if ($this->input->post('submit') === 'save_template_branch') {
            $branchId = (int) $this->input->post('template_branch_id');
            if ($branchId > 0) {
                $ok = $this->tenant_provisioning_model->setConfiguredTemplateBranchId($branchId);
                if ($ok) {
                    set_alert('success', translate('template_branch_saved'));
                } else {
                    set_alert('error', translate('action_failed'));
                }
            } else {
                set_alert('error', translate('please_select_a_branch'));
            }
            redirect(base_url('saas/settings_general'));
        }

        $this->data['title']             = translate('settings');
        $this->data['main_menu']         = 'saas_setting';
        $this->data['sub_page']          = 'saas/settings_general';
        $this->data['branches']          = $this->tenant_provisioning_model->listBranchesForProvisioning();
        $this->data['template_branch_id']= $this->tenant_provisioning_model->getConfiguredTemplateBranchId();
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Super-admin one-click "Re-clone tenant frontend from current
     * template". Wipes every branch-scoped `front_cms_*` row for the
     * target branch, then re-runs Tenant_provisioning_model::cloneFrontendForTenant()
     * so the tenant ends up rendering with the exact same theme /
     * colours / logos / home layout as the configured template branch.
     *
     * Useful for tenants that were approved before the clone helper
     * existed (they currently show the green placeholder), or for
     * pushing template changes out to existing subdomains.
     */
    public function reclone_tenant($branchId = 0)
    {
        $this->require_post();
        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            set_alert('error', translate('action_failed'));
            redirect(base_url('saas/settings_general'));
        }

        $this->load->model('tenant_provisioning_model');

        // Inherit identity overrides from the branch row so the cloned
        // front_cms_setting still announces the tenant's own name /
        // email / phone instead of the template tenant's.
        $branch = $this->db->get_where('branch', array('id' => $branchId))->row();
        if (!$branch) {
            set_alert('error', translate('action_failed'));
            redirect(base_url('saas/settings_general'));
        }
        $slug = $branch->subdomain ?: ($branch->slug ?: ('branch_' . $branchId));
        $name = $branch->name ?: ($branch->school_name ?: ('Branch ' . $branchId));
        $overrides = [
            'application_title' => $name,
            'url_alias'         => preg_replace('/[^a-z0-9_-]/', '_', strtolower($slug)),
            'email'             => (string) ($branch->email ?? ''),
            'mobile_no'         => (string) ($branch->mobileno ?? ''),
            'address'           => (string) ($branch->address ?? ''),
            'copyright_text'    => '© ' . date('Y') . ' ' . $name . ' — Powered by SmartSchool.bd',
        ];

        $cloned = $this->tenant_provisioning_model->recloneFrontendForTenant($branchId, $overrides);

        if ($cloned) {
            set_alert('success', translate('tenant_recloned_from_template'));
        } else {
            set_alert('error', translate('no_template_branch_available'));
        }
        redirect(base_url('saas/settings_general'));
    }

    // -------------------------------------------------------------------------
    // Expiry-notice floating dialog (super-admin)
    //
    // A bilingual "জরুরী বিজ্ঞপ্তি"-style banner that surfaces on every tenant
    // admin page when their subscription enters the configured warning state
    // (past_due / suspended / expiring soon). All copy + colors + the trigger
    // condition are stored in the existing `saas_setting` key/value table so
    // super-admin can edit without touching code or files.
    // -------------------------------------------------------------------------

    /**
     * Defaults seeded into saas_setting on first render. Editing these only
     * affects fresh installs; updates to an already-seeded setting must go
     * through the form so super-admin's edits are not silently overwritten.
     */
    private static function _expiry_notice_defaults()
    {
        return [
            'expiry_notice_enabled'        => '0',
            'expiry_notice_title'          => 'জরুরী বিজ্ঞপ্তি',
            'expiry_notice_body'           => "সম্মানিত সেবা গ্রহণকারী,\nআপনার অবগতির জন্য জানানো যাচ্ছে যে, অদ্যাবধি ওয়েবসাইট তথ্যবহুল হালনাগাদ না করায় আগামী ১৪৪ ঘন্টার মধ্যে আপনার প্রতিষ্ঠানের অনুকূলে ব্যবহৃত এই ডোমেইন ও সাইট এড্রেস সহ নিবন্ধন বাতিল হয়ে যাবে।\n\nএমতাবস্থায় আপনার ডাটা ক্ষতিগ্রস্থ হলে ওয়েবসাইট সেবা দপ্তর দায়ী থাকবে না।",
            'expiry_notice_support_email'  => 'support@smartschool.bd',
            'expiry_notice_website'        => 'www.smartschool.bd',
            'expiry_notice_trigger'        => 'any_problem',  // past_due | suspended | expiring_soon | any_problem
            'expiry_notice_days_before'    => '7',
            'expiry_notice_bg_color'       => '#8B1F1F',
            'expiry_notice_text_color'     => '#FFFFFF',
            'expiry_notice_position'       => 'bottom-right', // bottom-right | bottom-left | center
            'expiry_notice_show_to_users'  => 'admin',        // admin | admin_teacher | everyone
            // Action buttons (dialog is non-dismissible — tenant must use these to resolve).
            'expiry_notice_payment_url'      => 'subscription',
            'expiry_notice_payment_label'    => 'Login & Pay',
            'expiry_notice_whatsapp_number'  => '',
            'expiry_notice_whatsapp_label'   => 'WhatsApp Support',
            'expiry_notice_whatsapp_message' => 'Hello, my school {school_name} subscription expires on {expire_date}. Please help me renew.',
        ];
    }

    public function expiry_notice()
    {
        if ($this->_render_if_table_missing(
            'saas_setting',
            '2026-05-22c_saas_setting.sql',
            'Expiry notice',
            ['Saas_setting_model.php']
        )) return;

        $this->load->model('saas_setting_model');

        // Seed defaults for any key the operator has never saved before so
        // the form fields all render with sensible values on first visit.
        $existing = $this->saas_setting_model->getAllAsMap();
        foreach (self::_expiry_notice_defaults() as $k => $v) {
            if (!array_key_exists($k, $existing)) {
                $this->saas_setting_model->set($k, $v);
                $existing[$k] = $v;
            }
        }

        $this->data['title']     = 'Expiry notice';
        $this->data['main_menu'] = 'saas_setting';
        $this->data['sub_page']  = 'saas/expiry_notice';
        $this->data['settings']  = $existing;
        $this->load->view('layout/index', $this->data);
    }

    public function save_expiry_notice()
    {
        $this->require_post();
        $this->load->model('saas_setting_model');

        $allowedTriggers  = ['past_due', 'suspended', 'expiring_soon', 'any_problem'];
        $allowedPositions = ['bottom-right', 'bottom-left', 'center'];
        $allowedAudience  = ['admin', 'admin_teacher', 'everyone'];

        $trigger  = $this->input->post('expiry_notice_trigger');
        $position = $this->input->post('expiry_notice_position');
        $audience = $this->input->post('expiry_notice_show_to_users');
        if (!in_array($trigger, $allowedTriggers, true))   $trigger  = 'any_problem';
        if (!in_array($position, $allowedPositions, true)) $position = 'bottom-right';
        if (!in_array($audience, $allowedAudience, true))  $audience = 'admin';

        $days = (int)$this->input->post('expiry_notice_days_before');
        if ($days < 0)   $days = 0;
        if ($days > 365) $days = 365;

        $hex = function ($val, $fallback) {
            $v = trim((string)$val);
            return preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $v) ? $v : $fallback;
        };

        // Sanitize WhatsApp number to digits only (no +, spaces or dashes — wa.me
        // requires the international format with no leading +).
        $waNumber = preg_replace('/\D+/', '', (string)$this->input->post('expiry_notice_whatsapp_number'));

        $this->saas_setting_model->setMany([
            'expiry_notice_enabled'          => $this->input->post('expiry_notice_enabled') ? '1' : '0',
            'expiry_notice_title'            => trim((string)$this->input->post('expiry_notice_title')),
            'expiry_notice_body'             => (string)$this->input->post('expiry_notice_body'),
            'expiry_notice_support_email'    => trim((string)$this->input->post('expiry_notice_support_email')),
            'expiry_notice_website'          => trim((string)$this->input->post('expiry_notice_website')),
            'expiry_notice_trigger'          => $trigger,
            'expiry_notice_days_before'      => (string)$days,
            'expiry_notice_bg_color'         => $hex($this->input->post('expiry_notice_bg_color'),   '#8B1F1F'),
            'expiry_notice_text_color'       => $hex($this->input->post('expiry_notice_text_color'), '#FFFFFF'),
            'expiry_notice_position'         => $position,
            'expiry_notice_show_to_users'    => $audience,
            'expiry_notice_payment_url'      => trim((string)$this->input->post('expiry_notice_payment_url')),
            'expiry_notice_payment_label'    => trim((string)$this->input->post('expiry_notice_payment_label')),
            'expiry_notice_whatsapp_number'  => $waNumber,
            'expiry_notice_whatsapp_label'   => trim((string)$this->input->post('expiry_notice_whatsapp_label')),
            'expiry_notice_whatsapp_message' => (string)$this->input->post('expiry_notice_whatsapp_message'),
        ]);
        set_alert('success', translate('saved_successfully') ?: 'Saved');
        redirect(base_url('saas/expiry_notice'));
    }

    /**
     * Standalone preview — renders the dialog in a tiny page so super-admin
     * can eyeball the styling without a live tenant in a triggering state.
     */
    public function preview_expiry_notice()
    {
        $this->load->model('saas_setting_model');
        $this->data['settings'] = $this->saas_setting_model->getAllAsMap();
        $this->load->view('saas/_expiry_notice_preview', $this->data);
    }

    // -------------------------------------------------------------------------
    // Subject catalogue — global super-admin-managed list of subjects that
    // is copied onto every new tenant's branch-scoped `subject` table.
    //
    // The NCTB Bangladesh starter pack lives in
    // application/config/nctb_subjects.php; the operator imports it once
    // (idempotent) and can then add / edit / disable / delete rows.
    // -------------------------------------------------------------------------
    public function subject_catalog()
    {
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();

        $level = (string)$this->input->get('level');
        $rows  = $this->nctb_subject_seeder->getCatalogRows($level !== '' ? ['level_key' => $level] : []);

        // Build a "rows per level" map so the view can render a sticky
        // sidebar with counts.
        $levelCounts = [];
        foreach ($this->nctb_subject_seeder->getCatalogRows() as $r) {
            $lk = (string)($r['level_key'] ?? '');
            if (!isset($levelCounts[$lk])) {
                $levelCounts[$lk] = ['name' => (string)($r['level_name'] ?? $lk), 'count' => 0, 'active' => 0];
            }
            $levelCounts[$lk]['count']++;
            if ((int)$r['is_active'] === 1) $levelCounts[$lk]['active']++;
        }

        $this->data['title']             = 'Subject catalogue';
        $this->data['main_menu']         = 'saas_setting';
        $this->data['sub_page']          = 'saas/subject_catalog';
        $this->data['rows']              = $rows;
        $this->data['level_filter']      = $level;
        $this->data['level_options']     = $this->nctb_subject_seeder->levelOptions();
        $this->data['level_counts']      = $levelCounts;
        $this->data['total_count']       = $this->nctb_subject_seeder->countCatalogRows();
        $this->data['php_pack_total']    = count($this->nctb_subject_seeder->flatList());
        $this->load->view('layout/index', $this->data);
    }

    /**
     * POST /saas/subject_catalog_save — create or update a single row.
     */
    public function subject_catalog_save()
    {
        $this->require_post();
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();

        $id   = (int)$this->input->post('id');
        $name = trim((string)$this->input->post('name'));
        $code = trim((string)$this->input->post('subject_code'));
        $type = trim((string)$this->input->post('subject_type')) ?: 'Theory';

        if ($name === '' || $code === '') {
            set_alert('error', 'Subject name and code are both required.');
            redirect(base_url('saas/subject_catalog'));
            return;
        }

        $row = [
            'name'           => $name,
            'subject_code'   => $code,
            'subject_type'   => $type,
            'level_key'      => trim((string)$this->input->post('level_key')),
            'level_name'     => trim((string)$this->input->post('level_name')),
            'class_numerics' => trim((string)$this->input->post('class_numerics')),
            'subject_author' => trim((string)$this->input->post('subject_author')) ?: 'NCTB Bangladesh',
            'notes'          => (string)$this->input->post('notes'),
            'is_active'      => $this->input->post('is_active') ? 1 : 0,
            'sort_order'     => (int)$this->input->post('sort_order'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->db->where('id', $id)->update('subject_catalog', $row);
            set_alert('success', 'Subject updated.');
        } else {
            // Unique-key collision = silent skip with a friendly message.
            $clash = $this->db->where('name', $name)
                              ->where('subject_code', $code)
                              ->where('subject_type', $type)
                              ->count_all_results('subject_catalog');
            if ($clash > 0) {
                set_alert('info', 'Subject "' . html_escape($name) . '" already exists with the same code + type.');
            } else {
                $this->db->insert('subject_catalog', $row);
                set_alert('success', 'Subject added to the platform catalogue.');
            }
        }
        redirect(base_url('saas/subject_catalog'));
    }

    public function subject_catalog_delete($id = 0)
    {
        $this->require_post();
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();
        $id = (int)$id;
        if ($id > 0) {
            $this->db->where('id', $id)->delete('subject_catalog');
            set_alert('success', 'Subject removed from the platform catalogue.');
        }
        redirect(base_url('saas/subject_catalog'));
    }

    public function subject_catalog_toggle($id = 0)
    {
        $this->require_post();
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();
        $id = (int)$id;
        $row = $this->db->where('id', $id)->get('subject_catalog')->row_array();
        if ($row) {
            $this->db->where('id', $id)->update('subject_catalog', [
                'is_active'  => (int)$row['is_active'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        redirect(base_url('saas/subject_catalog'));
    }

    /**
     * POST /saas/subject_catalog_import_nctb — copy the NCTB starter pack
     * from application/config/nctb_subjects.php into the catalogue table.
     * Idempotent — subjects already in the catalogue are skipped.
     */
    public function subject_catalog_import_nctb()
    {
        $this->require_post();
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();

        $options = [];
        $levels  = $this->input->post('levels');
        if (is_array($levels) && !empty($levels)) {
            $options['levels'] = array_values(array_filter(array_map('strval', $levels)));
        }

        $r = $this->nctb_subject_seeder->importPhpCatalogToTable($options);
        $msg = sprintf(
            'NCTB import: %d subject%s added to the catalogue · %d skipped (already present).',
            $r['inserted'], $r['inserted'] === 1 ? '' : 's', $r['skipped']
        );
        set_alert($r['inserted'] > 0 ? 'success' : 'info', $msg);
        redirect(base_url('saas/subject_catalog'));
    }

    /**
     * POST /saas/subject_catalog_push — sync the active catalogue rows
     * to every existing tenant. Useful when the super-admin added
     * subjects after some tenants were already provisioned.
     */
    public function subject_catalog_push()
    {
        $this->require_post();
        $this->load->library('nctb_subject_seeder');
        $this->nctb_subject_seeder->ensureCatalogTable();

        $totals = $this->nctb_subject_seeder->pushCatalogToAllBranches();
        $msg = sprintf(
            'Pushed catalogue to %d tenant%s · %d subject%s added · %d already present.',
            $totals['branches'], $totals['branches'] === 1 ? '' : 's',
            $totals['inserted'], $totals['inserted'] === 1 ? '' : 's',
            $totals['skipped']
        );
        set_alert($totals['inserted'] > 0 ? 'success' : 'info', $msg);
        redirect(base_url('saas/subject_catalog'));
    }
}
