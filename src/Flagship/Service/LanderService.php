<?php

namespace Flagship\Service;

use Flagship\Model\Lander;
use Flagship\Model\Website;
use Flagship\Model\Geo;
use Flagship\Util\Profiler\Profiling;

class LanderService {

    use Profiling;

    public $namespace = "landers";

    protected $db;
    protected $offers;

    public function __construct($db, $offers) {
        $this->db = $db;
        $this->offers = $offers;
        $this->setProfilingClass('LanderService');
    }

    public function fetch($id) {
        $this->startTiming(__FUNCTION__, $id);
        $row = $this->db->fetch($this->namespace, $id, self::SQL_SELECT);
        $l = $this->fromRow($row);
        $this->stopTiming(__FUNCTION__, $id);
        return $l;
    }

    public function fromRow($row) {
        if (!$row) {
            return false;
        }

        $website = $this->websiteFromArray($row);
        $offers = $this->offers->fetch(
            $row['offer'],
            $row['param_id'],
            $row['product1_id'],
            $row['product2_id']
        );
        $variants = json_decode($row['variants'], true);
        $geo = $this->fetchGeo($row['geo_id']);


        return new Lander(
            $row['id'],
            $website,
            $offers,
            $variants,
            $row['notes'],
            $geo
        );
    }

    public function websiteFromArray($res) {
        return new Website(
            $res['website_id'],
            $res['website_name'],
            $res['namespace'],
            $res['asset_dir'],
            $res['template_file']
        );
    }

    const SQL_BASE = <<<SQL
        SELECT l.*, w.id AS website_id, w.name AS website_name, w.namespace, w.template_file, w.asset_dir FROM landers l
        INNER JOIN websites w ON (w.id = l.website_id)
SQL;

    const SQL_SELECT = self::SQL_BASE . " WHERE l.id = ?";

    // Changing because this is only in admin interface
    const SQL_SELECT_ALL = self::SQL_BASE . " WHERE l.active = TRUE ORDER BY id DESC";

    public function fetchAll($pretty = false) {
        $rows = $this->db->uncachedFetchAll(self::SQL_SELECT_ALL);
        return array_map(function ($x) use ($pretty) {
            $r = $this->fromRow($x);
            return ($pretty) ?  $this->prettyLander($r) : $r;
        }, $rows);
    }

    public function fetchAllWebsites() {
        $sql = "SELECT id AS website_id, name AS website_name, namespace, template_file, asset_dir FROM websites";
        $rows = $this->db->uncachedFetchAll($sql);
        return array_map(function ($x) {
            return $this->websiteFromArray($x);
        }, $rows);
    }

    public function fetchAllGeos() {
        $sql = "SELECT id, name, country, locale, data, variables FROM geos ORDER BY id ASC";
        $rows = $this->db->uncachedFetchAll($sql);
        return array_map(function ($x) {
            return $this->geoFromArray($x);
        }, $rows);
    }

    public function fetchGeo($id) {
        $sql = "SELECT id, name, country, locale, data, variables FROM geos WHERE id = ?";
        return $this->geoFromArray(
            $this->db->fetch('geolang', $id, $sql)
        );
    }

    public function geoFromArray($x) {
        $d = json_decode($x['data'], true);
        $v = json_decode($x['variables'], true);
        return new Geo(
            $x['id'], $x['name'], $x['country'], $x['locale'], $d, $v
        );
    }

    public function insert($arr) {
        // Remove data from different offers
        if ($arr['notes'] === '') {
            unset($arr['notes']);
        }

        if (isset($arr['variants'])) {
            $vars = array_flip($arr['variants']);
            unset($vars['default']);
            if (count($vars) > 0) {
                $arr['variants'] = json_encode(array_flip($vars));
            } else {
                unset($arr['variants']);
            }
        }

        if($arr['offer'] == 'adexchange') {
            unset($arr['product1_id']);
            unset($arr['product2_id']);
        } elseif ($arr['offer'] == 'network') {
            unset($arr['param_id']);
        }

        if (empty($arr['active'])) {
            $arr['active'] = true;
        }
        return $this->db->insertArray($this->namespace, $arr);
    }


    protected function prettyLander(Lander $lander) {
        $row = array(
            'ID' => $lander->id,
            'Notes' => $lander->notes
        );
        $row['Type'] = $lander->offers[1]->product->source;
        $row['Product 1'] = $lander->offers[1]->getName();
        $row['Product 2'] = $lander->offers[2]->getName();
        $row['Website'] = $lander->website->name;
        $row['Geo'] = $lander->geo->name;
        $row['Variants'] = json_encode($lander->variants);
        $row['Active'] = 'True';
        return $row;
    }

}
