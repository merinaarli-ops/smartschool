<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Bd_geo_model — read-only access to bd_division / bd_district / bd_upazila
 * lookup tables seeded by the 2026-05-23b migration.
 *
 * All methods return arrays of stdClass {id, name, bn_name} sorted by name.
 * If any of the tables is missing (migration not yet run on this env) the
 * accessors return [] so the signup form degrades gracefully.
 *
 * @author SmartSchool.bd
 */
class Bd_geo_model extends CI_Model
{
    public function divisions()
    {
        if (!$this->db->table_exists('bd_division')) return [];
        return $this->db->select('id, name, bn_name')
            ->order_by('name', 'asc')
            ->get('bd_division')->result();
    }

    public function districts($divisionId)
    {
        if (!$this->db->table_exists('bd_district')) return [];
        return $this->db->select('id, name, bn_name')
            ->where('division_id', (int)$divisionId)
            ->order_by('name', 'asc')
            ->get('bd_district')->result();
    }

    public function upazilas($districtId)
    {
        if (!$this->db->table_exists('bd_upazila')) return [];
        return $this->db->select('id, name, bn_name')
            ->where('district_id', (int)$districtId)
            ->order_by('name', 'asc')
            ->get('bd_upazila')->result();
    }
}
