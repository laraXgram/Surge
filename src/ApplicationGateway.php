<?php

namespace LaraGram\Surge;

use LaraGram\Contracts\Bot\Kernel as BotKernel;
use LaraGram\Contracts\Http\Kernel;
use LaraGram\Foundation\Application;
use LaraGram\Http\BaseResponse as Response;
use LaraGram\Http\Request;
use LaraGram\Listening\Listen;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Routing\Route;
use LaraGram\Surge\Events\RequestHandled;
use LaraGram\Surge\Events\RequestReceived;
use LaraGram\Surge\Events\RequestTerminated;
use LaraGram\Surge\Facades\Surge;

class ApplicationGateway
{
    use DispatchesEvents;

    public function __construct(protected Application $app, protected Application $sandbox)
    {
    }

    /**
     * Handle an incoming request.
     *
     * HTTP requests go through the HTTP kernel (routing) and Telegram bot requests
     * through the bot kernel (listening).
     */
    public function handle(Request|BotRequest $request): Response|BotResponse
    {
        if ($request instanceof Request) {
            $request->enableHttpMethodParameterOverride();
        }

        $this->dispatchEvent($this->sandbox, new RequestReceived($this->app, $this->sandbox, $request));

        if ($request instanceof Request) {
            if (Surge::hasRouteFor($request->getMethod(), '/'.$request->path())) {
                return Surge::invokeRoute($request, $request->getMethod(), '/'.$request->path());
            }
        } elseif (Surge::hasListenFor($verb = (string) $request->method(), $pattern = (string) $request->listenValue($verb))) {
            return Surge::invokeListen($request, $verb, $pattern);
        }

        return tap($this->kernel($request)->handle($request), function ($response) use ($request) {
            $this->dispatchEvent($this->sandbox, new RequestHandled($this->sandbox, $request, $response));
        });
    }

    /**
     * "Shut down" the application after a request.
     */
    public function terminate(Request|BotRequest $request, Response|BotResponse $response): void
    {
        $this->kernel($request)->terminate($request, $response);

        $this->dispatchEvent($this->sandbox, new RequestTerminated($this->app, $this->sandbox, $request, $response));

        $route = $request instanceof Request ? $request->route() : $request->listen();

        if (($route instanceof Route || $route instanceof Listen) && method_exists($route, 'flushController')) {
            $route->flushController();
        }
    }

    /**
     * Get the kernel handling the given kind of request.
     *
     * @return \LaraGram\Contracts\Http\Kernel|\LaraGram\Contracts\Bot\Kernel
     */
    protected function kernel(Request|BotRequest $request)
    {
        return $this->sandbox->make($request instanceof Request ? Kernel::class : BotKernel::class);
    }
}
