<?php
namespace Icicle\File\Exception;

class FileTaskException extends FileException
{
    public function __construct($message, \Exception $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
