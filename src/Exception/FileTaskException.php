<?php
namespace Icicle\File\Exception;

class FileTaskException extends FileException
{
    public function __construct(string $message, \Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
