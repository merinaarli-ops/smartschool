<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Saas_model — DB access for the SmartSchool.bd SaaS layer.
 * Tables: saas_package, saas_subscriptions, saas_pending_request, invoice, saas_payment.
 *
 * @author SmartSchool.bd
 */
class Saas_model extends CI_Model
{
    // -------------------------------------------------------------------------
    // Packages
    // -------------------------------------------------------------------------
    public function getPackages($onlyActive = false)
    {
        $this->db->order_by('sort_order', 'asc');
        if ($onlyActive) $this->db->where('is_active', 1);
        return $this->db->get('saas_package')->result();
    }

    public function getPackageById($id)
    {
        return $this->db->where('id', (int)$id)->get('saas_package')->row();
    }

    public function getPackageByCode($code)
    {
        return $this->db->where('code', $code)->get('saas_package')->row();
    }

    public function savePackage($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int)$id)->update('saas_package', $data);
            return (int)$id;
        }
        $this->db->insert('saas_package', $data);
        return (int)$this->db->insert_id();
    }

    public function deletePackage($id)
    {
        return $this->db->where('id', (int)$id)->delete('saas_package');
    }

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------
    public function getSubscriptions()
    {
        $this->db->select('s.*, b.name AS branch_name, b.subdomain, sp.code AS package_code, sp.name AS package_name, sp.price_bdt');
        $this->db->from('saas_subscriptions s');
        $this->db->join('branch b',         'b.id = s.school_id', 'left');
        $this->db->join('saas_package sp',  'sp.id = s.package_id', 'left');
        $this->db->order_by('s.id', 'desc');
        return $this->db->get()->result();
    }

    public function getSubscriptionByBranch($branchId)
    {
        // s.* already pulls the new override_price_bdt / validity_days /
        // renewal_mode columns when present; LEFT JOIN keeps the row when
        // package_id is NULL (no-package tenants).
        $this->db->select('s.*, sp.code AS package_code, sp.name AS package_name, sp.price_bdt, sp.features, sp.limits, sp.student_limit, sp.staff_limit, sp.teacher_limit, sp.parents_limit');
        $this->db->from('saas_subscriptions s');
        $this->db->join('saas_package sp', 'sp.id = s.package_id', 'left');
        $this->db->where('s.school_id', (int)$branchId);
        return $this->db->get()->row();
    }

    /**
     * Create or refresh the subscription row for a branch.
     *
     * @param int      $branchId
     * @param int|null $packageId   Pass null for "no package" tenants (the
     *                               super-admin set custom terms manually).
     * @param string   $status      trial | active | …
     * @param array    $opts        Optional overrides:
     *                                 - override_price_bdt (float|null)
     *                                 - validity_days      (int|null)  forces a fixed period
     *                                 - renewal_mode       (string)    auto_invoice | contact_admin | disabled
     */
    public function assignPackage($branchId, $packageId, $status = 'active', array $opts = [])
    {
        $pkg = null;
        if ($packageId) {
            $pkg = $this->getPackageById($packageId);
            if (!$pkg) $pkg = null;
        }

        // Derive the period length. Priority: opts.validity_days → trial_days
        // (if package marks itself as trial) → billing_period (monthly/yearly)
        // → 30-day default for the no-package case.
        $trialEnds = null;
        $periodEnd = null;
        if (!empty($opts['validity_days'])) {
            $days = max(1, (int)$opts['validity_days']);
            $periodEnd = date('Y-m-d', strtotime('+' . $days . ' days'));
        } elseif ($pkg && $pkg->is_default_trial && (int)$pkg->trial_days > 0) {
            $trialEnds = date('Y-m-d', strtotime('+' . (int)$pkg->trial_days . ' days'));
            $periodEnd = $trialEnds;
        } elseif ($pkg) {
            $months = $pkg->billing_period === 'yearly' ? 12 : 1;
            $periodEnd = date('Y-m-d', strtotime('+' . $months . ' months'));
        } else {
            // No package + no validity_days override → default to 30 days so
            // the row has a sensible expiry and the cron can still kick in.
            $periodEnd = date('Y-m-d', strtotime('+30 days'));
        }

        $row = [
            'school_id'             => (int)$branchId,
            'package_id'            => $packageId ? (int)$packageId : null,
            'status'                => $status,
            'trial_ends_at'         => $trialEnds,
            'current_period_start'  => date('Y-m-d'),
            'current_period_end'    => $periodEnd,
            'expire_date'           => $periodEnd,
            'updated_at'            => date('Y-m-d H:i:s'),
        ];

        // Only persist the *new* override columns if the schema actually has
        // them (i.e. the 2026-05-23 migration has been applied). Keeps the
        // model backwards-compatible.
        if ($this->db->field_exists('override_price_bdt', 'saas_subscriptions')) {
            $row['override_price_bdt'] = isset($opts['override_price_bdt']) && $opts['override_price_bdt'] !== ''
                ? (float)$opts['override_price_bdt'] : null;
        }
        if ($this->db->field_exists('validity_days', 'saas_subscriptions')) {
            $row['validity_days'] = !empty($opts['validity_days']) ? (int)$opts['validity_days'] : null;
        }
        if ($this->db->field_exists('renewal_mode', 'saas_subscriptions')) {
            $mode = isset($opts['renewal_mode']) ? (string)$opts['renewal_mode'] : 'auto_invoice';
            if (!in_array($mode, ['auto_invoice','contact_admin','disabled'], true)) $mode = 'auto_invoice';
            $row['renewal_mode'] = $mode;
        }

        $existing = $this->db->where('school_id', (int)$branchId)->get('saas_subscriptions')->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update('saas_subscriptions', $row);
            return (int)$existing->id;
        }
        $this->db->insert('saas_subscriptions', $row);
        return (int)$this->db->insert_id();
    }

    /**
     * Patch only the operator-editable terms on an existing subscription —
     * used by /saas/edit_terms (the inline form on the schools list).
     */
    public function updateTerms($branchId, array $opts)
    {
        $sub = $this->db->where('school_id', (int)$branchId)->get('saas_subscriptions')->row();
        if (!$sub) return false;

        $upd = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('package_id', $opts)) {
            $upd['package_id'] = $opts['package_id'] !== '' && $opts['package_id'] !== null
                ? (int)$opts['package_id'] : null;
        }
        if (array_key_exists('override_price_bdt', $opts)
            && $this->db->field_exists('override_price_bdt', 'saas_subscriptions')) {
            $upd['override_price_bdt'] = $opts['override_price_bdt'] !== '' && $opts['override_price_bdt'] !== null
                ? (float)$opts['override_price_bdt'] : null;
        }
        if (array_key_exists('validity_days', $opts)
            && $this->db->field_exists('validity_days', 'saas_subscriptions')) {
            $upd['validity_days'] = $opts['validity_days'] !== '' && $opts['validity_days'] !== null
                ? (int)$opts['validity_days'] : null;
        }
        if (array_key_exists('renewal_mode', $opts)
            && $this->db->field_exists('renewal_mode', 'saas_subscriptions')) {
            $mode = (string)$opts['renewal_mode'];
            if (in_array($mode, ['auto_invoice','contact_admin','disabled'], true)) {
                $upd['renewal_mode'] = $mode;
            }
        }

        return $this->db->where('school_id', (int)$branchId)
            ->update('saas_subscriptions', $upd);
    }

    /** Read the subscription's effective renewal mode, defaulting safely. */
    public function getRenewalMode($branchId)
    {
        if (!$this->db->field_exists('renewal_mode', 'saas_subscriptions')) {
            return 'auto_invoice';
        }
        $row = $this->db->select('renewal_mode')->where('school_id', (int)$branchId)
            ->get('saas_subscriptions')->row();
        return $row ? $row->renewal_mode : 'auto_invoice';
    }

    public function setStatus($branchId, $status)
    {
        return $this->db->where('school_id', (int)$branchId)
            ->update('saas_subscriptions', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function extendPeriod($branchId, $days)
    {
        $sub = $this->db->where('school_id', (int)$branchId)->get('saas_subscriptions')->row();
        if (!$sub) return false;
        $base = $sub->current_period_end ?: date('Y-m-d');
        $new  = date('Y-m-d', strtotime($base . ' +' . (int)$days . ' days'));
        return $this->db->where('school_id', (int)$branchId)->update('saas_subscriptions', [
            'current_period_end' => $new,
            'expire_date'        => $new,
            'status'             => 'active',
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Pending signup requests
    // -------------------------------------------------------------------------
    public function getPendingRequests($status = null)
    {
        $this->db->select('pr.*, sp.code AS package_code, sp.name AS package_name');
        $this->db->from('saas_pending_request pr');
        $this->db->join('saas_package sp', 'sp.id = pr.package_id', 'left');
        if ($status) $this->db->where('pr.status', $status);
        $this->db->order_by('pr.id', 'desc');
        return $this->db->get()->result();
    }

    public function getPendingRequestById($id)
    {
        return $this->db->where('id', (int)$id)->get('saas_pending_request')->row();
    }

    public function savePendingRequest($data)
    {
        $this->db->insert('saas_pending_request', $data);
        return (int)$this->db->insert_id();
    }

    public function markRequestProcessed($id, $status, $branchId = null)
    {
        $upd = [
            'status'       => $status,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
        if ($branchId) $upd['branch_id'] = (int)$branchId;
        return $this->db->where('id', (int)$id)->update('saas_pending_request', $upd);
    }

    public function isSubdomainTaken($subdomain)
    {
        $reserved = ['www','admin','api','app','mail','signup','test','dev','staging','support','help','docs','blog'];
        $sd = strtolower(trim($subdomain));
        if (in_array($sd, $reserved, true)) return true;
        $existsInBranch  = $this->db->where('subdomain', $sd)->count_all_results('branch') > 0;
        $existsInPending = $this->db->where('subdomain', $sd)->where('status', 'pending')->count_all_results('saas_pending_request') > 0;
        $this->ensureReservedSubdomainTable();
        $existsInReserved = $this->db->where('subdomain', $sd)->count_all_results('saas_reserved_subdomain') > 0;
        return $existsInBranch || $existsInPending || $existsInReserved;
    }

    /**
     * Suggest up to N alternative subdomain candidates given a taken name.
     * Picks short, memorable, available variants:
     *   - <name>bd
     *   - <name><yyyy>
     *   - <name>-school
     *   - <name>online
     *   - <name>1, <name>2 … as fallback
     */
    public function suggestSubdomains($taken, $count = 3)
    {
        $sd = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($taken)));
        if ($sd === '') return [];
        $yyyy = date('Y');
        $candidates = [
            $sd . 'bd',
            $sd . $yyyy,
            $sd . '-school',
            $sd . 'online',
            $sd . '-bd',
            $sd . 'edu',
            $sd . '24',
        ];
        for ($i = 1; $i <= 9 && count($candidates) < 20; $i++) {
            $candidates[] = $sd . $i;
        }
        $picked = [];
        foreach ($candidates as $c) {
            if (strlen($c) < 3 || strlen($c) > 64) continue;
            if (!$this->isSubdomainTaken($c)) {
                $picked[] = $c;
                if (count($picked) >= $count) break;
            }
        }
        return $picked;
    }

    // -------------------------------------------------------------------------
    // Reserved + registered subdomain management (admin panel)
    // -------------------------------------------------------------------------

    /**
     * Auto-create the saas_reserved_subdomain table on first reference.
     * Used to ring-fence brand names / system slugs from being claimed
     * by signups. Idempotent (CREATE TABLE IF NOT EXISTS).
     */
    public function ensureReservedSubdomainTable()
    {
        if ($this->db->table_exists('saas_reserved_subdomain')) return;
        $this->db->query("CREATE TABLE IF NOT EXISTS `saas_reserved_subdomain` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `subdomain` VARCHAR(64) NOT NULL,
            `domain_suffix` VARCHAR(64) NOT NULL DEFAULT '',
            `reason` VARCHAR(255) NOT NULL DEFAULT '',
            `created_by` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_reserved_subdomain` (`subdomain`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Combined list of every subdomain known to the system:
     *   - active tenants in `branch`
     *   - pending signups in `saas_pending_request`
     *   - reserved entries in `saas_reserved_subdomain`
     * Returns rows shaped:
     *   { id, subdomain, suffix, status, name, owner, source, created_at, branch_id, request_id, reserved_id }
     */
    public function listAllSubdomains($filter = '')
    {
        $this->ensureReservedSubdomainTable();
        $rows = [];

        // Active branches.
        // Column name differs across deployments: newer SaaS dumps use `status`
        // (tinyint 1=active / 0=suspended) while some older installs ship an
        // `active` column. Pick whichever exists so this query doesn't 500.
        $branchStatusCol = $this->db->field_exists('status', 'branch')
            ? 'status'
            : ($this->db->field_exists('active', 'branch') ? 'active' : null);
        $sel = 'b.id AS branch_id, b.subdomain, b.name, b.email AS owner, b.created_at';
        if ($branchStatusCol) $sel .= ', b.' . $branchStatusCol . ' AS branch_status';
        $this->db->select($sel);
        $this->db->from('branch b');
        $this->db->where("(b.subdomain IS NOT NULL AND b.subdomain <> '')");
        $branches = $this->db->get()->result();
        foreach ($branches as $b) {
            $isActive = $branchStatusCol ? ((int)$b->branch_status === 1) : true;
            $rows[] = (object)[
                'id'         => 'b-' . $b->branch_id,
                'subdomain'  => $b->subdomain,
                'suffix'     => '',
                'status'     => $isActive ? 'active' : 'suspended',
                'name'       => $b->name,
                'owner'      => $b->owner,
                'source'     => 'branch',
                'created_at' => $b->created_at,
                'branch_id'  => (int)$b->branch_id,
                'request_id' => null,
                'reserved_id'=> null,
            ];
        }

        // Pending signup requests
        $hasSuffix = $this->db->field_exists('domain_suffix', 'saas_pending_request');
        $sel = 'pr.id AS request_id, pr.subdomain, pr.school_name AS name, pr.owner_email AS owner, pr.status, pr.created_at';
        if ($hasSuffix) $sel .= ', pr.domain_suffix';
        $this->db->select($sel)->from('saas_pending_request pr');
        $reqs = $this->db->get()->result();
        foreach ($reqs as $r) {
            if (empty($r->subdomain)) continue;
            $rows[] = (object)[
                'id'         => 'r-' . $r->request_id,
                'subdomain'  => $r->subdomain,
                'suffix'     => $hasSuffix ? ($r->domain_suffix ?? '') : '',
                'status'     => 'pending',
                'name'       => $r->name,
                'owner'      => $r->owner,
                'source'     => 'signup_request',
                'created_at' => $r->created_at,
                'branch_id'  => null,
                'request_id' => (int)$r->request_id,
                'reserved_id'=> null,
            ];
        }

        // Reserved
        $reserved = $this->db->order_by('subdomain', 'asc')->get('saas_reserved_subdomain')->result();
        foreach ($reserved as $rv) {
            $rows[] = (object)[
                'id'         => 'rsv-' . $rv->id,
                'subdomain'  => $rv->subdomain,
                'suffix'     => $rv->domain_suffix ?? '',
                'status'     => 'reserved',
                'name'       => $rv->reason ?: 'Reserved by admin',
                'owner'      => '',
                'source'     => 'reserved',
                'created_at' => $rv->created_at,
                'branch_id'  => null,
                'request_id' => null,
                'reserved_id'=> (int)$rv->id,
            ];
        }

        // Optional case-insensitive filter
        if ($filter !== '') {
            $needle = strtolower($filter);
            $rows = array_values(array_filter($rows, function($r) use ($needle){
                return strpos(strtolower($r->subdomain), $needle) !== false
                    || strpos(strtolower((string)$r->name), $needle) !== false
                    || strpos(strtolower((string)$r->owner), $needle) !== false;
            }));
        }

        // Sort: newest first
        usort($rows, function($a, $b){
            return strcmp((string)$b->created_at, (string)$a->created_at);
        });
        return $rows;
    }

    /**
     * Add a reserved subdomain (ring-fence a brand / system slug).
     * Returns ['ok'=>bool, 'msg'=>string, 'id'=>int|null]
     */
    public function addReservedSubdomain($subdomain, $suffix = '', $reason = '', $userId = 0)
    {
        $this->ensureReservedSubdomainTable();
        $sd = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string)$subdomain)));
        if (strlen($sd) < 3 || strlen($sd) > 64) {
            return ['ok' => false, 'msg' => 'Subdomain must be 3–64 chars: a-z, 0-9, _ , -', 'id' => null];
        }
        if ($this->db->where('subdomain', $sd)->count_all_results('saas_reserved_subdomain') > 0) {
            return ['ok' => false, 'msg' => 'Already in the reserved list.', 'id' => null];
        }
        $this->db->insert('saas_reserved_subdomain', [
            'subdomain'     => $sd,
            'domain_suffix' => trim((string)$suffix),
            'reason'        => trim((string)$reason),
            'created_by'    => (int)$userId,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'msg' => 'Reserved.', 'id' => (int)$this->db->insert_id()];
    }

    public function deleteReservedSubdomain($id)
    {
        return $this->db->where('id', (int)$id)->delete('saas_reserved_subdomain');
    }

    /**
     * Hard-delete a subdomain claim. Accepts a composite id of the shape
     * "b-<branch_id>" | "r-<request_id>" | "rsv-<reserved_id>".
     * For active branches we DO NOT cascade-delete the tenant data — we only
     * clear the `subdomain` column so the slug becomes free again. The branch
     * row itself stays so the admin can re-assign or audit it.
     */
    public function deleteSubdomainClaim($composite)
    {
        $composite = (string)$composite;
        if (preg_match('/^b-(\d+)$/', $composite, $m)) {
            $this->db->where('id', (int)$m[1])->update('branch', ['subdomain' => null]);
            return ['ok' => true, 'msg' => 'Subdomain cleared from branch (tenant data preserved).'];
        }
        if (preg_match('/^r-(\d+)$/', $composite, $m)) {
            // Pending requests can be safely deleted outright.
            $this->db->where('id', (int)$m[1])->delete('saas_pending_request');
            return ['ok' => true, 'msg' => 'Pending request removed.'];
        }
        if (preg_match('/^rsv-(\d+)$/', $composite, $m)) {
            $this->deleteReservedSubdomain((int)$m[1]);
            return ['ok' => true, 'msg' => 'Reserved entry removed.'];
        }
        return ['ok' => false, 'msg' => 'Unknown subdomain claim id.'];
    }

    /**
     * Admin "manually add" — create a stub branch row so the slug is claimed.
     * The admin can then fill the school details normally via the existing
     * approve / branch-create flow. This is a lightweight reservation that
     * still shows up in the active-branch list.
     */
    public function manuallyAddSubdomain($subdomain, $suffix, $name, $email = '', $userId = 0)
    {
        $sd = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string)$subdomain)));
        if (strlen($sd) < 3 || strlen($sd) > 64) {
            return ['ok' => false, 'msg' => 'Subdomain must be 3–64 chars: a-z, 0-9, _ , -', 'id' => null];
        }
        if ($this->isSubdomainTaken($sd)) {
            return ['ok' => false, 'msg' => 'Subdomain is already taken.', 'id' => null];
        }
        // Insert into saas_pending_request as a manual entry — keeps the existing
        // approval/provisioning flow working unchanged.
        $row = [
            'school_name' => trim((string)$name) ?: $sd,
            'subdomain'   => $sd,
            'owner_name'  => 'Manual entry',
            'owner_email' => trim((string)$email),
            'owner_phone' => '',
            'status'      => 'pending',
            'notes'       => 'Created manually by admin (user_id ' . (int)$userId . ').',
        ];
        if ($this->db->field_exists('domain_suffix', 'saas_pending_request')) {
            $row['domain_suffix'] = trim((string)$suffix);
        }
        $this->db->insert('saas_pending_request', $row);
        return ['ok' => true, 'msg' => 'Manual signup request created — approve it from the pending queue.', 'id' => (int)$this->db->insert_id()];
    }

    // -------------------------------------------------------------------------
    // Invoices & payments
    // -------------------------------------------------------------------------
    public function getInvoicesForBranch($branchId)
    {
        return $this->db->where('branch_id', (int)$branchId)
            ->order_by('id', 'desc')
            ->get('invoice')->result();
    }

    public function getAllInvoices()
    {
        $this->db->select('i.*, b.name AS branch_name, b.subdomain');
        $this->db->from('invoice i');
        $this->db->join('branch b', 'b.id = i.branch_id', 'left');
        $this->db->order_by('i.id', 'desc');
        return $this->db->get()->result();
    }

    public function getInvoiceById($id)
    {
        return $this->db->where('id', (int)$id)->get('invoice')->row();
    }

    public function createInvoice($branchId, $subscriptionId, $amount, $periodStart, $periodEnd, $dueDate = null)
    {
        $no = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(100, 9999), 4, '0', STR_PAD_LEFT);
        $row = [
            'branch_id'        => (int)$branchId,
            'subscription_id'  => (int)$subscriptionId,
            'invoice_no'       => $no,
            'period_start'     => $periodStart,
            'period_end'       => $periodEnd,
            'amount'           => $amount,
            'currency'         => 'BDT',
            'status'           => 'open',
            'due_date'         => $dueDate ?: date('Y-m-d', strtotime('+7 days')),
        ];
        $this->db->insert('invoice', $row);
        return (int)$this->db->insert_id();
    }

    public function markInvoicePaid($invoiceId, $provider = 'manual', $providerTxnId = null, $raw = null)
    {
        $inv = $this->getInvoiceById($invoiceId);
        if (!$inv || $inv->status === 'paid') return false;

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int)$invoiceId)->update('invoice', [
            'status'  => 'paid',
            'paid_at' => $now,
        ]);
        $this->db->insert('saas_payment', [
            'invoice_id'      => (int)$invoiceId,
            'branch_id'       => (int)$inv->branch_id,
            'amount'          => $inv->amount,
            'currency'        => $inv->currency,
            'provider'        => $provider,
            'provider_txn_id' => $providerTxnId,
            'status'          => 'succeeded',
            'paid_at'         => $now,
            'raw_response'    => $raw ? (is_string($raw) ? $raw : json_encode($raw)) : null,
        ]);
        // Extend the subscription by one period
        $sub = $this->db->where('id', (int)$inv->subscription_id)->get('saas_subscriptions')->row();
        if ($sub) {
            $pkg = $this->getPackageById($sub->package_id);
            $days = ($pkg && $pkg->billing_period === 'yearly') ? 365 : 30;
            $this->extendPeriod($sub->school_id, $days);
        }
        return true;
    }

    public function getPaymentsForBranch($branchId)
    {
        return $this->db->where('branch_id', (int)$branchId)
            ->order_by('id', 'desc')
            ->get('saas_payment')->result();
    }

    public function getAllPayments()
    {
        $this->db->select('p.*, b.name AS branch_name, b.subdomain, i.invoice_no');
        $this->db->from('saas_payment p');
        $this->db->join('branch b',  'b.id = p.branch_id', 'left');
        $this->db->join('invoice i', 'i.id = p.invoice_id', 'left');
        $this->db->order_by('p.id', 'desc');
        return $this->db->get()->result();
    }

    // -------------------------------------------------------------------------
    // Audit log
    // -------------------------------------------------------------------------
    /**
     * Recent audit_log rows for a single branch, newest first. Returns []
     * when the audit_log table does not exist yet (the 008 migration has
     * not been applied on this install).
     */
    public function getAuditLogForBranch($branchId, $limit = 100)
    {
        if (!$this->db->table_exists('audit_log')) return [];
        $branchId = (int)$branchId;
        $limit    = max(1, (int)$limit);
        $this->db->select('al.*, lc.username AS actor_username');
        $this->db->from('audit_log al');
        $this->db->join('login_credential lc', 'lc.user_id = al.actor_id', 'left');
        $this->db->where('al.branch_id', $branchId);
        $this->db->order_by('al.id', 'desc');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }

    // -------------------------------------------------------------------------
    // Usage stats per branch (for the dashboard widget)
    // -------------------------------------------------------------------------
    public function getUsageStats($branchId)
    {
        // `student` has no branch_id column; per-branch scope lives on
        // `enroll`. Count distinct active (non-alumni) students for this
        // branch via an explicit COUNT(DISTINCT student_id) — CI's QB
        // `distinct()` + `count_all_results()` combo emits a subquery that
        // some MariaDB versions reject and surfaces as a fatal in
        // count_all_results (see DB_query_builder line 1430).
        $branchId = (int) $branchId;

        $row = $this->db
            ->select('COUNT(DISTINCT student_id) AS cnt', false)
            ->where('branch_id', $branchId)
            ->where('is_alumni', 0)
            ->get('enroll')
            ->row();
        $studentCount = $row ? (int) $row->cnt : 0;

        $staffCount = (int) $this->db
            ->where('branch_id', $branchId)
            ->count_all_results('staff');

        return ['students' => $studentCount, 'staff' => $staffCount];
    }
}