<?php

namespace LaraGram\Surge\Exceptions\Inspector;

class InspectorFactory implements InspectorFactoryInterface
{
    /**
     * @param \Throwable $exception
     * @return InspectorInterface
     */
    public function create($exception)
    {
        return new Inspector($exception, $this);
    }
}
