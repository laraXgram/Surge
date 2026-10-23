<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

/**
 * A relay whose reads block, so frames may not be pulled ahead of time.
 */
interface BlockingRelayInterface extends RelayInterface
{
    //
}
