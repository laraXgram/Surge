<?php

namespace LaraGram\Surge;

use LaraGram\Contracts\Bot\Kernel;
use LaraGram\Foundation\Application;
use LaraGram\Request\Request;
use LaraGram\Listening\Listen;
use LaraGram\Surge\Events\RequestHandled;
use LaraGram\Surge\Events\RequestReceived;
use LaraGram\Surge\Events\RequestTerminated;
use LaraGram\Request\Response;

class ApplicationGateway
{
    use DispatchesEvents;

    public function __construct(protected Application $app, protected Application $sandbox)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request): Response
    {
        $this->dispatchEvent($this->sandbox, new RequestReceived($this->app, $this->sandbox, $request));

        return tap($this->sandbox->make(Kernel::class)->handle($request), function ($response) use ($request) {
            $this->dispatchEvent($this->sandbox, new RequestHandled($this->sandbox, $request, $response));
        });
    }

    /**
     * "Shut down" the application after a request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->sandbox->make(Kernel::class)->terminate($request, $response);

        $this->dispatchEvent($this->sandbox, new RequestTerminated($this->app, $this->sandbox, $request, $response));

        $listen = $request->listen();

        if ($listen instanceof Listen && method_exists($listen, 'flushController')) {
            $listen->flushController();
        }
    }
}
