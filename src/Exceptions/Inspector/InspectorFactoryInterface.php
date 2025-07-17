<?php

namespace LaraGram\Surge\Exceptions\Inspector;

interface InspectorFactoryInterface
{
    /**
     * @param \Throwable $exception
     * @return InspectorInterface
     */
    public function create($exception);
}
