<?php

declare(strict_types=1);

namespace LaraGram\Surge\RoadRunner\Factory;

use Override;
use LaraGram\Http\Factory\StreamInterface;
use LaraGram\Http\Factory\UploadedFileFactoryInterface;
use LaraGram\Http\Factory\UploadedFileInterface;

use const UPLOAD_ERR_OK;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            $size = $stream->getSize();
        }

        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
