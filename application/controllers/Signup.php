<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Signup — Public self-service signup form for new tenants.
 *
 * Routes:
 *   /signup            — default variant (currently 'a')
 *   /signup/a          — Variant A: no package selection (split-panel hero)
 *   /signup/b          — Variant B: no package selection (centered card)
 *   /signup/c          — Variant C: with package selection (split-panel hero)
 *   /signup/d          — Variant D: with package selection (centered card)
 *   /signup/thanks     — confirmation page
 *   /signup/check_subdomain/<sd> — JSON: {available: bool}
 *
 * Approval flow:
 *   1. Visitor fills /signup/X → row inserted into `saas_pending_request`
 *      with `signup_variant` = X.
 *   2. Super-admin reviews at /saas/pending_request.
 *   3. Super-admin clicks Approve → if variant was a/b (no package), the
 *      approve dialog asks for package + custom price + validity + renewal
 *      mode. For c/d the package on the request is used as the default.
 *
 * @author SmartSchool.bd
 */
class Signup extends MY_Controller
{
    /** Variants that include the package selector on the public form. */
    private const PACKAGE_VARIANTS    = ['c', 'd'];
    /** Variants that omit the package selector (admin sets terms at approval). */
    private const NO_PACKAGE_VARIANTS = ['a', 'b'];

    /** Set when the visitor smuggles an array into a subdomain_* field. */
    private $_pickone_array_seen = false;
    /** Set when the visitor fills more than one subdomain_* input. */
    private $_pickone_multi      = false;

    /** Static dropdown options surfaced on the public signup form. */
    public static function education_boards()
    {
        return [
            'dhaka'      => 'Dhaka Board',
            'chittagong' => 'Chittagong Board',
            'comilla'    => 'Comilla Board',
            'rajshahi'   => 'Rajshahi Board',
            'jessore'    => 'Jessore Board',
            'sylhet'     => 'Sylhet Board',
            'barisal'    => 'Barisal Board',
            'dinajpur'   => 'Dinajpur Board',
            'mymensingh' => 'Mymensingh Board',
            'madrasah'   => 'Madrasah Board (BMEB)',
            'technical'  => 'Technical Board (BTEB)',
            'bou'        => 'Bangladesh Open University',
            'other'      => 'Other / Private',
        ];
    }

    public static function designations()
    {
        return [
            'principal'      => 'Principal',
            'headmaster'     => 'Headmaster',
            'vice_principal' => 'Vice Principal',
            'asst_hm'        => 'Assistant Headmaster',
            'director'       => 'Director / Proprietor',
            'admin_officer'  => 'Admin Officer',
            'it_admin'       => 'IT / System Admin',
            'other'          => 'Other',
        ];
    }

    public static function institute_types()
    {
        return [
            'school'         => 'School / স্কুল',
            'college'        => 'College / কলেজ',
            'school_college' => 'School &amp; College / স্কুল ও কলেজ',
            'madrasah'       => 'Madrasah / মাদ্রাসা',
            'technical'      => 'Technical / কারিগরি',
            'kg'             => 'Kindergarten / কেজি',
            'university'     => 'University / বিশ্ববিদ্যালয়',
            'other'          => 'Other / অন্যান্য',
        ];
    }

    /**
     * Sub-type options surfaced when the user picks "School" on the
     * signup form. Three rosters: Primary (1-5), High School (6-10),
     * Primary + High School (1-10). Each one steers the auto-setup
     * seeder (Saas::approve → Nctb_subject_seeder::seedEverythingForBranch)
     * toward the matching subset of NCTB classes + Bangla exam terms.
     */
    public static function institute_subtypes()
    {
        return [
            'school' => [
                'primary'      => 'প্রাথমিক (Primary)',
                'high_school'  => 'মাধ্যমিক (High School)',
                'primary_high' => 'প্রাথমিক ও মাধ্যমিক (Primary + High School)',
            ],
        ];
    }

    /**
     * Look up the configured sub-type map for a given institute type.
     * Returns [] when the type has no sub-types — view layer hides the
     * second dropdown entirely in that case.
     */
    public static function institute_subtypes_for($instituteType)
    {
        $map = self::institute_subtypes();
        $key = strtolower(trim((string)$instituteType));
        return $map[$key] ?? [];
    }

    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
        $this->load->model('saas_setting_model');
        $this->load->model('bd_geo_model');
        $this->load->library('form_validation');
    }

    /** Default landing — uses variant A. */
    public function index()
    {
        return $this->_render_variant('a');
    }

    public function a() { return $this->_render_variant('a'); }
    public function b() { return $this->_render_variant('b'); }
    public function c() { return $this->_render_variant('c'); }
    public function d() { return $this->_render_variant('d'); }

    /**
     * AJAX subdomain availability check.
     *
     * Endpoint: POST /signup/check_subdomain
     * Body:     subdomain=<slug>&suffix=<smartschool.bd|institution.bd>
     *
     * Response JSON:
     *   { ok: bool, available: bool, reason: string|null, suggestions: string[] }
     */
    public function check_subdomain()
    {
        $this->output->set_content_type('application/json; charset=utf-8');

        $sd     = strtolower(trim((string)$this->input->post('subdomain')));
        $suffix = strtolower(trim((string)$this->input->post('suffix')));
        $sd     = preg_replace('/[^a-z0-9_-]/', '', $sd);

        $allowedSuffixes = $this->saas_setting_model->getDomainSuffixes();
        if ($suffix !== '' && !in_array($suffix, $allowedSuffixes, true)) {
            return $this->output->set_output(json_encode([
                'ok'          => false,
                'available'   => false,
                'reason'      => 'Unknown domain extension.',
                'suggestions' => [],
            ]));
        }
        if (strlen($sd) < 3) {
            return $this->output->set_output(json_encode([
                'ok'          => true,
                'available'   => false,
                'reason'      => 'Too short — minimum 3 characters.',
                'suggestions' => [],
            ]));
        }
        if (strlen($sd) > 64) {
            return $this->output->set_output(json_encode([
                'ok'          => true,
                'available'   => false,
                'reason'      => 'Too long — maximum 64 characters.',
                'suggestions' => [],
            ]));
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $sd)) {
            return $this->output->set_output(json_encode([
                'ok'          => true,
                'available'   => false,
                'reason'      => 'Use lowercase letters, numbers, hyphen or underscore only.',
                'suggestions' => [],
            ]));
        }

        $taken = $this->saas_model->isSubdomainTaken($sd);
        if ($taken) {
            return $this->output->set_output(json_encode([
                'ok'          => true,
                'available'   => false,
                'reason'      => 'This subdomain is already taken or reserved.',
                'suggestions' => $this->saas_model->suggestSubdomains($sd, 3),
            ]));
        }
        return $this->output->set_output(json_encode([
            'ok'          => true,
            'available'   => true,
            'reason'      => null,
            'suggestions' => [],
        ]));
    }

    /** Shared submit + render path for all 4 variants. */
    private function _render_variant($variant)
    {
        $variant = in_array($variant, ['a','b','c','d'], true) ? $variant : 'a';
        $withPackage = in_array($variant, self::PACKAGE_VARIANTS, true);

        $allowedSuffixes = $this->saas_setting_model->getDomainSuffixes();

        if ($this->input->post('submit') === 'apply') {
            // ---- "Pick one" subdomain resolution ----
            // The form now renders one input per allowed suffix
            // (e.g. `subdomain_smartschool_bd` + `subdomain_institution_bd`).
            // Exactly one must be filled. We resolve the winner into the
            // canonical (subdomain, domain_suffix) pair before running the
            // rest of the validation rules.
            $perSuffixValues = [];
            $filledSuffixes  = [];
            foreach ($allowedSuffixes as $sfx) {
                $field = 'subdomain_' . str_replace('.', '_', $sfx);
                $raw   = $_POST[$field] ?? '';
                if (is_array($raw)) {
                    // Array smuggling — kill it dead.
                    $_POST[$field] = '';
                    $raw = '';
                    $this->_pickone_array_seen = true;
                }
                $val = strtolower(trim((string)$raw));
                $perSuffixValues[$sfx] = $val;
                if ($val !== '') $filledSuffixes[] = $sfx;
            }

            // Legacy single-input fallback (count($allowedSuffixes) === 1 OR
            // an older variant that still posts `subdomain` + `domain_suffix`).
            if (!$filledSuffixes && !empty($_POST['subdomain'])) {
                $legacySub = strtolower(trim((string)$_POST['subdomain']));
                $legacySfx = strtolower(trim((string)($_POST['domain_suffix'] ?? '')));
                if ($legacySub !== '' && in_array($legacySfx, $allowedSuffixes, true)) {
                    $perSuffixValues[$legacySfx] = $legacySub;
                    $filledSuffixes[] = $legacySfx;
                }
            }

            if (count($filledSuffixes) > 1) {
                // Both filled — pick-one rule violated.
                $_POST['subdomain']     = '';
                $_POST['domain_suffix'] = '';
                $this->_pickone_multi = true;
            } elseif (count($filledSuffixes) === 1) {
                $winner = $filledSuffixes[0];
                $_POST['subdomain']     = $perSuffixValues[$winner];
                $_POST['domain_suffix'] = $winner;
            } else {
                $_POST['subdomain']     = '';
                $_POST['domain_suffix'] = '';
            }

            // ---- form_validation rules ----
            if (!empty($this->_pickone_array_seen)) {
                $this->form_validation->set_rules('subdomain', 'Subdomain', 'callback__reject_array_subdomain');
            } elseif (!empty($this->_pickone_multi)) {
                $this->form_validation->set_rules('subdomain', 'Subdomain', 'callback__reject_multi_subdomain');
            }
            $this->form_validation->set_rules('school_name',  'School Name',      'required|max_length[255]');
            $this->form_validation->set_rules('subdomain',    'Subdomain',        'required|min_length[3]|max_length[64]|alpha_dash|callback_unique_subdomain');
            $this->form_validation->set_rules('owner_name',   'Your Name',        'required|max_length[255]');
            $this->form_validation->set_rules('owner_email',  'Email',            'required|valid_email');
            $this->form_validation->set_rules('owner_phone',  'Phone',            'required|max_length[64]');
            $this->form_validation->set_rules('domain_suffix','Domain extension', 'required|callback_valid_suffix');
            if ($withPackage) {
                $this->form_validation->set_rules('package_id', 'Plan', 'required|integer');
            }
            $this->form_validation->set_rules('terms_accept', 'Terms of Service', 'required');

            if ($this->form_validation->run() === true) {
                $picked = strtolower(trim((string)$this->input->post('domain_suffix')));
                if (!in_array($picked, $allowedSuffixes, true)) {
                    $picked = $allowedSuffixes[0];
                }

                // Make sure the institute_subtype column exists on
                // saas_pending_request before we try to persist into it.
                // ensureInstituteTypeColumns() is idempotent + best-effort.
                try {
                    $this->load->library('nctb_subject_seeder');
                    $this->nctb_subject_seeder->ensureInstituteTypeColumns();
                } catch (Exception $e) {
                    log_message('error', 'ensureInstituteTypeColumns: ' . $e->getMessage());
                }
                $data = [
                    'school_name'    => $this->input->post('school_name'),
                    'school_name_bn' => $this->input->post('school_name_bn'),
                    'subdomain'      => strtolower(trim($this->input->post('subdomain'))),
                    'owner_name'     => $this->input->post('owner_name'),
                    'owner_email'    => $this->input->post('owner_email'),
                    'owner_phone'    => $this->input->post('owner_phone'),
                    'package_id'     => $withPackage ? (int)$this->input->post('package_id') : null,
                    'status'         => 'pending',
                    'notes'          => $this->input->post('notes'),
                ];
                // Optional new fields — only persisted when the 2026-05-23b
                // migration has added the columns.
                // Only persist a sub-type when it is one of the valid keys
                // for the picked institute_type — silently drop anything
                // else so unverified POST data can't pollute the row.
                $rawType    = trim((string)$this->input->post('institute_type'));
                $rawSubtype = trim((string)$this->input->post('institute_subtype'));
                $subAllowed = self::institute_subtypes_for($rawType);
                if (!isset($subAllowed[$rawSubtype])) {
                    $rawSubtype = '';
                }

                $extraFields = [
                    'eiin_code'         => trim((string)$this->input->post('eiin_code')),
                    'designation'       => trim((string)$this->input->post('designation')),
                    'education_board'   => trim((string)$this->input->post('education_board')),
                    'institute_type'    => $rawType,
                    'institute_subtype' => $rawSubtype,
                    'division_id'       => $this->input->post('division_id') !== '' ? (int)$this->input->post('division_id') : null,
                    'district_id'       => $this->input->post('district_id') !== '' ? (int)$this->input->post('district_id') : null,
                    'upazila_id'        => $this->input->post('upazila_id')  !== '' ? (int)$this->input->post('upazila_id')  : null,
                ];
                foreach ($extraFields as $col => $val) {
                    if ($this->db->field_exists($col, 'saas_pending_request')) {
                        $data[$col] = $val;
                    }
                }
                if ($this->db->field_exists('domain_suffix', 'saas_pending_request')) {
                    $data['domain_suffix'] = $picked;
                }
                // signup_variant column only added by the 2026-05-23 migration.
                if ($this->db->field_exists('signup_variant', 'saas_pending_request')) {
                    $data['signup_variant'] = $variant;
                }
                $id = $this->saas_model->savePendingRequest($data);

                // Best-effort Telegram heads-up to the platform admin.
                $this->load->helper(['url', 'saas_notify']);
                $req = (object)array_merge($data, ['id' => (int)$id]);
                @saas_notify_signup_pending($req);

                // Stash a short submission summary so /signup/thanks can
                // greet the visitor by school name, surface their picked
                // subdomain, and pre-fill the WhatsApp message.
                $summary = [
                    'school_name'    => $data['school_name'],
                    'school_name_bn' => $data['school_name_bn'],
                    'subdomain'      => $data['subdomain'],
                    'domain_suffix'  => $picked,
                    'owner_name'     => $data['owner_name'],
                    'owner_email'    => $data['owner_email'],
                    'owner_phone'    => $data['owner_phone'],
                ];
                $this->session->set_flashdata('signup_summary', $summary);

                redirect(base_url('signup/thanks'));
            }
        }

        $this->data['variant']         = $variant;
        $this->data['with_package']    = $withPackage;
        $this->data['packages']        = $withPackage ? $this->saas_model->getPackages(true) : [];
        $this->data['domain_suffixes'] = $allowedSuffixes;
        $this->data['divisions']       = $this->bd_geo_model->divisions();
        $this->data['boards']          = self::education_boards();
        $this->data['designations']    = self::designations();
        $this->data['institute_types']    = self::institute_types();
        $this->data['institute_subtypes'] = self::institute_subtypes();
        $this->data['title']           = 'Sign up — SmartSchool.bd';

        // View name maps directly to variant (split-panel: a/c, centered: b/d).
        $view = 'signup/variant_' . $variant;
        $this->load->view($view, $this->data);
    }

    public function thanks()
    {
        $this->data['title']   = 'অভিনন্দন! Application received — SmartSchool.bd';
        $this->data['summary'] = $this->session->flashdata('signup_summary') ?: [];

        // Contact card values — dedicated `signup_contact_*` keys take
        // precedence; we fall back to the existing expiry-notice keys so
        // the cards render with sensible values on day one.
        $get = function ($key, $default = '') {
            return (string)$this->saas_setting_model->get($key, $default);
        };
        $waNumber = preg_replace('/\D+/', '', $get('signup_contact_whatsapp',
            $get('expiry_notice_whatsapp_number', '')));
        $phone    = $get('signup_contact_phone', '');
        $email    = $get('signup_contact_email', $get('expiry_notice_support_email', 'support@smartschool.bd'));
        $fbUrl    = $get('signup_contact_facebook', '');

        $this->data['contact'] = [
            'whatsapp' => $waNumber,
            'phone'    => $phone,
            'email'    => $email,
            'facebook' => $fbUrl,
        ];

        $this->load->view('signup/thanks', $this->data);
    }

    /** AJAX: list districts under a given division. */
    public function ajax_districts($divisionId = 0)
    {
        header('Content-Type: application/json');
        echo json_encode($this->bd_geo_model->districts((int)$divisionId));
    }

    /** AJAX: list upazilas under a given district. */
    public function ajax_upazilas($districtId = 0)
    {
        header('Content-Type: application/json');
        echo json_encode($this->bd_geo_model->upazilas((int)$districtId));
    }

    // -- form_validation callback
    public function unique_subdomain($sd)
    {
        if ($this->saas_model->isSubdomainTaken($sd)) {
            $this->form_validation->set_message('unique_subdomain', 'This subdomain is already taken or reserved.');
            return false;
        }
        return true;
    }

    // -- form_validation callback
    public function valid_suffix($suffix)
    {
        $allowed = $this->saas_setting_model->getDomainSuffixes();
        if (!in_array(strtolower(trim((string)$suffix)), $allowed, true)) {
            $this->form_validation->set_message('valid_suffix', 'Please pick a valid domain extension.');
            return false;
        }
        return true;
    }

    /**
     * form_validation callback — block submissions where a `subdomain_*`
     * field arrived as an array (someone bypassing the pick-one UI by
     * hand-crafting the POST body).
     */
    public function _reject_array_subdomain($_)
    {
        $this->form_validation->set_message(
            '_reject_array_subdomain',
            'Please pick exactly one subdomain — leave the other field blank.'
        );
        return false;
    }

    /**
     * form_validation callback — block submissions where BOTH suffix
     * inputs were filled. The user must pick exactly one.
     */
    public function _reject_multi_subdomain($_)
    {
        $this->form_validation->set_message(
            '_reject_multi_subdomain',
            'Please fill in only ONE of the two website-name boxes — not both.'
        );
        return false;
    }
}
