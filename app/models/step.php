<?php

require_once dirname(dirname(__DIR__)) . '/config.php';
require CENTRIFUGE_ROOT . '/vendor/autoload.php';
use League\Url\Url;


class Step
{
    protected $id;
    protected $p;
    protected $baseUri = 'base2.php';
    protected $rootUrl;
    protected $landerId;

    public function __construct($id, $product, $landerId = null, $rootUrl = null) {
        $this->id = $id;
        $this->p = $product;
        $this->landerId = $landerId;
        if (isset($rootUrl)) {
            $this->rootUrl = $rootUrl;
        } else {
            // Meh... not sure if this is good
            $this->rootUrl = CLICK_URL;
        }
    }

    public static function fromProducts($products, $landerId = null) {
        $c = 1;
        $steps = array();
        foreach ($products as $r) {
            $steps[$c] = new Step($c, $r, $landerId);
            $c++;
        }
        return $steps;
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->p->getName();
    }

    public function getUrl() {
        $currentQuery = Url::createFromServer($_SERVER)->getQuery()->toArray();
        $url = null;

        if (isset($this->rootUrl)) {
            $url = Url::createFromUrl($this->rootUrl);
            $currentQuery['id'] = $this->id;
        } else {
            $url = Url::createFromServer($_SERVER);
            $url->setPath($this->baseUri);
            $currentQuery['step'] = $this->id;
            if (isset($this->landerId)) {
                $currentQuery['lander'] = $this->landerId;
            }
        }

        $url->getQuery()->modify($currentQuery);
        return $url;
    }

    public function getImageUrl() {
        return $this->p->getImageUrl();
    }
}
