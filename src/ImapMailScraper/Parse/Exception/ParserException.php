<?php

namespace ImapMailScraper\Parse\Exception;

class ParserException extends \RuntimeException
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct(get_called_class().': '.$message, $code, $previous);
    }
}
