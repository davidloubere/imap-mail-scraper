<?php

namespace ImapMailScraper\Imap\Exception;

class MailStorageException extends \RuntimeException
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct(get_called_class().': '.$message, $code, $previous);
    }
}
