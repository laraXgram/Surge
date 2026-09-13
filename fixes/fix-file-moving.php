<?php

// Uploaded files are written to disk by the server instead of PHP's SAPI, so PHP's
// move_uploaded_file() refuses them: https://github.com/spiral/roadrunner-laravel/issues/43

namespace LaraGram\Http\Files {
    function move_uploaded_file($from, $to)
    {
        return \is_file($from) && \rename($from, $to);
    }
}
