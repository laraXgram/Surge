<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

enum SocketType: string
{
    case TCP = 'tcp';
    case UNIX = 'unix';
}
