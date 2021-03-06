<?php

namespace Flagship\Middleware;

use \Slim\Middleware;

use Flagship\Container;
use Flagship\Event\AbstractEvent;
use Flagship\Event\EventContextFactory;
use Flagship\Event\EventBuilder;
use Flagship\Middleware\Session;
use Flagship\Storage\CookieJar;
use Flagship\Storage\AerospikeNamespace;
use Flagship\Model\User;
use Flagship\Util\Profiler\Profiling;


class UserTracker extends Middleware {

    use Profiling;

    protected $aerospike;
    protected $cookieJar;
    protected $trackingCookie = null;
    protected $centrifuge;
    protected $events;
    protected $routeWhitelist;

    public function __construct(Container $c) {
        $this->cookieJar = $c['cookie.jar'];
        $this->events = $c['context.factory'];
        $this->aerospike = $c['aerospike'];
        $this->routeWhitelist = ['/content/:id', '/click/:stepId', '/admin/tracking'];
        $this->centrifuge = $c;
        $this->setProfiler($c['profiler']);
        $this->setProfilingClass('UserTracker');
    }

    public function call() {
        $this->app->hook('slim.before.dispatch', [$this, 'before']);
        $this->app->hook('slim.after.dispatch', [$this, 'after'], 2);
        $this->next->call();
    }

    public function before() {
        $this->startTiming(__FUNCTION__);
        $route = $this->app->router->getCurrentRoute()->getPattern();
        if (!in_array($route, $this->routeWhitelist)) {
            return false;
        }

        $this->trackingCookie = $this->cookieJar->getOrCreateTracking();
        if (empty($this->trackingCookie)) {
            $this->app->log->warn('Warning tracking cookie is not set');
            return false;
        }

        $ga = $this->checkGACookie();
        $this->user = User::fromAerospike(
            $this->aerospike,
            $this->trackingCookie,
            $ga
        );

        $contexts = $this->events->createFromRequest($this->app->request);

        $build = new EventBuilder();
        $build->setProfiler($this->getProfiler());
        $build
            ->setId($this->centrifuge['random.id'])
            ->setUser($this->user)
            ->setContext($contexts);

        $this->app->environment['user'] = $this->user;
        $this->app->environment['event.builder'] = $build;
        $this->stopTiming(__FUNCTION__);
    }

    public function after () {
        $this->startTiming(__FUNCTION__);
        if(isset($this->trackingCookie)) {
            $this->trackingCookie->incrementVisitCount();
            $this->cookieJar->setTracking($this->trackingCookie);
        }

        if (isset($this->user)) {
            $rc = $this->user->toAerospike($this->aerospike);
        }
        $this->stopTiming(__FUNCTION__);
    }

    // Set google analytics ID on env for segment integration
    protected function checkGACookie() {
        $ga = $this->cookieJar->getCookie('_ga');
        if (isset($ga)) {
            $parts = explode('.', $ga);
            $gaID = $parts[count($parts)-2].'.'.$parts[count($parts)-1];
            return $gaID;
        }
    }
}
