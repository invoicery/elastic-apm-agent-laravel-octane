<?php

namespace Cego\ElasticApmAgentLaravelOctane\EventListeners;

use Throwable;
use Illuminate\Routing\Router;
use Laravel\Octane\Events\RequestReceived;
use Cego\ElasticApmAgentLaravelOctane\OctaneApmManager;

class RequestReceivedHandler
{
    /**
     * Handle the event.
     *
     * @param  RequestReceived  $event
     *
     * @return void
     */
    public function handle(RequestReceived $event): void
    {
        /** @var OctaneApmManager $manager */
        $manager = $event->app->make(OctaneApmManager::class);

        $routeUri = $this->getRouteUri($event);

        // Don't care about OPTIONS requests
        if ($event->request->method() === 'OPTIONS') {
            $manager->disable();
        }

        $manager->beginTransaction($event->request->method() . ' /' . $routeUri, 'request');
    }

    /**
     * Returns the request route uri
     *
     * @param RequestReceived $event
     *
     * @return string
     */
    private function getRouteUri(RequestReceived $event): string
    {
        if ($event->request->method() === 'OPTIONS') {
            // Merge OPTIONS URIs
            return $this->getOptionsRouteUri($event->request->path());
        }

        /** @var Router $router */
        $router = $event->sandbox->make('router');

        try {
            $routeUri = $router->getRoutes()->match($event->request)->uri();
            return $routeUri === "/" ? "" : $routeUri;
        } catch (Throwable $throwable) {
            // If the route does not exist, then simply return the path
            return $event->request->path();
        }
    }

    private function getOptionsRouteUri(string $path): string 
    {
        $path = preg_replace('/\/[0-9]+\//', '/{id}/', $path);
        $path = preg_replace('/\/[0-9]+$/', '/{id}', $path);
        // Ignore URIs with random strings and UUIDs that are hard to merge in a consistent way
        return mb_strlen($path) > 50 ? 'unhandled' : $path;
    }
}
