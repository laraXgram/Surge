<?php

namespace LaraGram\Surge\Commands\Writer\Contracts;

use LaraGram\Surge\Exceptions\Inspector\Frame;

interface RenderableOnWriterEditor
{
    /**
     * Returns the frame to be used on the Writer Editor.
     */
    public function toWriterEditor(): Frame;
}
