<?php

namespace LaraGram\Surge\Exceptions;

use Exception;
use LaraGram\Contracts\Support\Renderable;
use LaraGram\Http\VarDumper\Cloner\VarCloner;
use LaraGram\Http\VarDumper\Dumper\HtmlDumper;

class DdException extends Exception implements Renderable
{
    public function __construct(public array $vars)
    {
        $this->message = json_encode($vars);
    }

    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render()
    {
        $dump = function ($var) {
            $data = (new VarCloner())->cloneVar($var)->withMaxDepth(3);

            return (string) (new HtmlDumper(false))->dump($data, true, [
                'maxDepth' => 3,
                'maxStringLength' => 160,
            ]);
        };

        return collect($this->vars)->map($dump)->implode('');
    }
}
