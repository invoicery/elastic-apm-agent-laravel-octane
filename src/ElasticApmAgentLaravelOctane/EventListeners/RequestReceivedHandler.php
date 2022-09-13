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
        $manager = $event->sandbox->make(OctaneApmManager::class);

        $manager->beginTransaction($event->request->method() . ' ' . $this->getRouteUri($event), 'request');
        $manager->beginAndStoreSpan('RequestResponse', 'request');
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
        /** @var Router $router */
        $router = $event->sandbox->make('router');

        try {
            return $router->getRoutes()->match($event->request)->uri();
        } catch (Throwable $throwable) {
            // Merge OPTIONS URIs without defined routes
            if ($event->request->method() === 'OPTIONS') {
                return $this->getOptionsRouteUri($event->request->path());
            }

            // If the route does not exist, then simply return the path
            return $event->request->path();
        }
    }

    private function getOptionsRouteUri(string $path): string 
    {
        $path = preg_replace('/\/[0-9]+\//', '/{id}/', $path);
        return preg_replace('/\/[0-9]+$/', '/{id}', $path);
    }
}
