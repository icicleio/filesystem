<?php
namespace Icicle\File\Exception;

class FileException extends \Exception implements Exception
{
    public function __construct($message, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

