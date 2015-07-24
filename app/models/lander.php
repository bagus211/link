<?php

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once __DIR__ . "/product.php";
require_once __DIR__ . "/step.php";
require_once __DIR__ . "/tracking.php";
require_once CENTRIFUGE_APP_ROOT . "/util/iexception.php";
require_once CENTRIFUGE_APP_ROOT . "/util/variant.php";

class LanderNotFoundException extends CustomException {};


class VariantLanderHtml
{
    public $steps;
    public $namespace;
    public $templateFile;
    public $assetDirectory;
    public $rootPath;
    public $tracking;
    public $id;

    public function __construct($id, $namespace, $rootPath, $templateFile, $assetDir, $variants, $steps, $tracking) {
        $this->id = $id;
        $this->namespace = $namespace;
        $this->templateFile = $templateFile;
        $this->assetDirectory = $assetDir;
        $this->steps = $steps;
        $this->rootPath = $rootPath;
        $this->tracking = $tracking;
        $this->variants = new VariantHtml($namespace, $variants);
    }

    public function getTemplate() {
        return $this->namespace . '::' . $this->templateFile;
    }

    public function toArray() {
        $assets = $this->rootPath . '/' . $this->namespace;

        return array(
            'steps' => $this->steps,
            'tracking' => $this->tracking,
            'assets' => $assets,
            'v' => $this->variants
        );
    }
}



class LanderFunctions
{
    public static function fetch($app, $id, $root = null) {
        $res = LanderFunctions::cachedQuery('lander', $app, $id, LanderFunctions::landerSql());
        if ($res == false) {
            throw new LanderNotFoundException("Lander id: {$id} does not exist!", $id);
        }

        $tracking = Tracking::fromPGArray($res['tracking']);
        $products = [];

        if ($res['offer'] == 'adexchange') {
            $params = LanderFunctions::cachedQuery('ae_parameters', $app, $res['param_id'], LanderFunctions::$adExchangeSql);
            $products = AdExchangeProduct::fetchFromAdExchange($app, $params['affiliate_id'], $params['vertical'], $params['country']);
        } elseif ($res['offer'] == 'network') {
            $p1 = LanderFunctions::cachedQuery('product', $app, $res['product1_id'], LanderFunctions::$productSql);
            $p2 = LanderFunctions::cachedQuery('product', $app, $res['product2_id'], LanderFunctions::$productSql);
            $products[] = NetworkProduct::fromArray($p1, $app['PRODUCT_ROOT']);
            $products[] = NetworkProduct::fromArray($p2, $app['PRODUCT_ROOT']);
        }

        $steps = Step::fromProducts($products, $id);
        $rootPath = isset($root) ? $root : $app['LANDER_ROOT'];
        $variants = json_decode($res['variants'], true);
        return new VariantLanderHtml($id, $res['namespace'], $rootPath, $res['template_file'], $res['asset_dir'], $variants, $steps, $tracking);
    }

    public static function cachedQuery($type, $app, $id, $sql) {
        $pool = $app->cache;
        $item = $pool->getItem($type, $id);
        $lander = $item->get();
        if ($item->isMiss()) {
            $app->log->info("Cache miss: ", array($type.'_id' => $id));
            $app->metrics->increment("cache_miss");
            $stmt = $app->db()->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array($id));
            $lander = $stmt->fetch(PDO::FETCH_ASSOC);
            $item->set($lander, OBJ_TTL);
        }
        return $lander;
    }

    public static $adExchangeSql = "SELECT affiliate_id, vertical, country FROM ae_parameters WHERE id = ?";

    public static $productSql = "SELECT id, name, image_url FROM products WHERE id = ?";

    public static function landerSql() {
        $sql = <<<SQL
SELECT l.*, w.namespace, w.template_file, w.asset_dir FROM landers l
INNER JOIN websites w ON (w.id = l.website_id)
WHERE l.id = ?
SQL;
        return $sql;
    }
}

