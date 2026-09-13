<?php

// Uploaded files are written to disk by the server instead of PHP's SAPI, so PHP's
// is_uploaded_file() rejects them: https://github.com/spiral/roadrunner/issues/133

namespace LaraGram\Http\Files {
    function is_uploaded_file($filename)
    {
        return true;
    }
}

namespace LaraGram\Http\Factory {
    function is_uploaded_file($filename)
    {
        return true;
    }
}
