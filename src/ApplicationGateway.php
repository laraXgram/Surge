<?php

namespace LaraGram\Surge;

use LaraGram\Contracts\Bot\Kernel;
use LaraGram\Contracts\Http\Kernel as HttpKernel;
use LaraGram\Foundation\Application;
use LaraGram\Http\Request as HttpRequest;
use LaraGram\Listening\Listen;
use LaraGram\Surge\Events\RequestHandled;
use LaraGram\Surge\Events\RequestReceived;
use LaraGram\Surge\Events\RequestTerminated;

class ApplicationGateway
{
    use DispatchesEvents;

    public function __construct(protected Application $app, protected Application $sandbox)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     * @return \LaraGram\Request\Response|\LaraGram\Http\Response
     */
    public function handle($request)
    {
        $this->dispatchEvent($this->sandbox, new RequestReceived($this->app, $this->sandbox, $request));

        $kernel = $request instanceof HttpRequest ? HttpKernel::class : Kernel::class;

        return tap($this->sandbox->make($kernel)->handle($request), function ($response) use ($request) {
            $this->dispatchEvent($this->sandbox, new RequestHandled($this->sandbox, $request, $response));
        });
    }

    /**
     * "Shut down" the application after a request.
     */
    public function terminate($request, $response): void
    {
        if ($request instanceof HttpRequest) {
            $this->sandbox->make(HttpKernel::class)->terminate($request, $response);

            $this->dispatchEvent($this->sandbox, new RequestTerminated($this->app, $this->sandbox, $request, $response));

            return;
        }

        $this->sandbox->make(Kernel::class)->terminate($request, $response);

        $this->dispatchEvent($this->sandbox, new RequestTerminated($this->app, $this->sandbox, $request, $response));

        $listen = $request->listen();

        if ($listen instanceof Listen && method_exists($listen, 'flushController')) {
            $listen->flushController();
        }
    }
}
