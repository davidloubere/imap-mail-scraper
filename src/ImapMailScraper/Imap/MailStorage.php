<?php

namespace ImapMailScraper\Imap;

use ImapMailScraper\Imap\Exception\MailStorageException;
use ImapMailScraper\Mail\MessageData;

class MailStorage
{
    const CRITERIA_SEARCH_NEW = 'NEW';
    const CRITERIA_SEARCH_UNSEEN = 'UNSEEN';

    const FLAG_MESSAGE_ANSWERED = '\\Answered';
    const FLAG_MESSAGE_DELETED = '\\Deleted';
    const FLAG_MESSAGE_DRAFT = '\\Draft';
    const FLAG_MESSAGE_SEEN = '\\Seen';
    const FLAG_MESSAGE_FLAGGED = '\\Flagged';

    const ENCODING_TYPE_BASE64 = 'BASE64';
    const ENCODING_TYPE_ISO_8859_1 = 'ISO-8859-1';
    const ENCODING_TYPE_ISO_8859_15 = 'ISO-8859-15';
    const ENCODING_TYPE_UTF8 = 'UTF-8';
    const ENCODING_TYPE_WINDOWS_1252 = 'windows-1252';

    const PART_CHARSET_DEFAULT = 'default';
    const PART_SUBTYPE_HTML = 'HTML';
    const PART_SUBTYPE_PLAIN = 'PLAIN';

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var string
     */
    protected $outputEncoding;

    /**
     * @param array  $config
     * @param string $outputEncoding
     *
     * @throws \ImapMailScraper\Imap\Exception\MailStorageException
     */
    public function __construct(array $config, $outputEncoding = self::ENCODING_TYPE_UTF8)
    {
        if (!isset($config['host']) || !isset($config['username']) || !isset($config['password']) || !isset($config['port']) || !isset($config['security'])) {
            throw new MailStorageException('Bad configuration.');
        }

        if (!in_array($config['security'], ['ssl'], true)) {
            throw new MailStorageException(sprintf('Security "%s" configuration not supported.'), $config['security']);
        }

        $mailbox = '{'.$config['host'].':'.$config['port'].'/imap/'.$config['security'].'}INBOX';
        if (isset($config['inbox_folder'])) {
            $mailbox .= '/'.$config['inbox_folder'];
        }

        $options = OP_READONLY;
        if (isset($config['options'])) {
            $options = $config['options'];
        }

        $this->stream = $this->open($mailbox, $config, $options);

        if ($this->isSupportedCharset($outputEncoding) === false) {
            throw new MailStorageException(sprintf('Output encoding "%s" not supported.', $outputEncoding));
        }

        $this->outputEncoding = $outputEncoding;
    }

    /**
     * @param string $criteria
     *
     * @return array
     */
    public function getMessagesByCriteria($criteria = self::CRITERIA_SEARCH_UNSEEN)
    {
        $messages = [];

        $messagesUids = imap_sort($this->stream, SORTDATE, 1, SE_UID, $criteria);

        foreach ($messagesUids as $messageUid) {
            $messages[] = $this->getMessageData($messageUid);
        }

        return $messages;
    }

    /**
     * @param string $messageUid
     *
     * @return \ImapMailScraper\Mail\MessageData
     *
     * @throws \ImapMailScraper\Imap\Exception\MailStorageException
     */
    public function getMessageData($messageUid)
    {
        $headerInfo = imap_headerinfo($this->stream, imap_msgno($this->stream, $messageUid));

        if ($headerInfo === false) {
            throw new MailStorageException(sprintf('Unable to read header of message "%s"', $messageUid));
        }

        $bodies = $this->getBodies($messageUid);

        if (empty($bodies)) {
            error_log(sprintf(get_called_class().': no body found for message "%s"', $messageUid));
        }

        $messageData = new MessageData();
        $messageData->setUid($messageUid);
        $messageData->setCharset($this->outputEncoding);
        $messageData->setDate($this->getDate($headerInfo));
        $messageData->setFrom($this->getFrom($headerInfo));
        $messageData->setSubject($this->getSubject($headerInfo));
        $messageData->setBodies($bodies);

        return $messageData;
    }

    /**
     * @param array $uids
     *
     * @return bool
     */
    public function flagMessagesAsRead(array $uids)
    {
        $flag = addslashes(implode(' ', [self::FLAG_MESSAGE_SEEN, self::FLAG_MESSAGE_FLAGGED]));

        return imap_setflag_full($this->stream, implode(',', $uids), $flag, ST_UID);
    }

    /**
     * @param array  $uids
     * @param string $folder
     *
     * @return bool
     */
    public function moveMessages(array $uids, $folder)
    {
        return imap_mail_move($this->stream, implode(',', $uids), 'INBOX/'.$folder, CP_UID);
    }

    public function close()
    {
        imap_close($this->stream);
    }

    /**
     * @param string $mailbox
     * @param array  $config
     * @param int    $options
     *
     * @return resource
     *
     * @throws \ImapMailScraper\Imap\Exception\MailStorageException
     */
    protected function open($mailbox, array $config, $options)
    {
        $stream = imap_open(
            $mailbox,
            $config['username'],
            $config['password'],
            $options
        );

        if ($stream === false) {
            throw new MailStorageException(imap_last_error());
        }

        return $stream;
    }

    /**
     * @param string $mimeHeaderString
     *
     * @return string
     */
    protected function decodeMimeHeaderString($mimeHeaderString)
    {
        $decodedString = '';

        foreach (imap_mime_header_decode($mimeHeaderString) as $part) {
            if (isset($part->charset) && $this->requiresConvertEncoding($part->charset)) {
                $decodedString .= $this->convertEncoding($part->text, $part->charset);
            } else {
                $decodedString .= $part->text;
            }
        }

        return $decodedString;
    }

    /**
     * @param object $headerInfo
     *
     * @return \DateTime
     */
    protected function getDate($headerInfo)
    {
        if (!isset($headerInfo->date)) {
            throw new MailStorageException('Field "date" not found in header info.');
        }

        return new \DateTime($headerInfo->date);
    }

    /**
     * @param object $headerInfo
     *
     * @return array
     *
     * @throws \ImapMailScraper\Imap\Exception\MailStorageException
     */
    protected function getFrom($headerInfo)
    {
        $from = [];

        if (!isset($headerInfo->from)) {
            throw new MailStorageException('Field "from" not found in header info.');
        }

        if (count($headerInfo->from) !== 1) {
            throw new MailStorageException('Field "from" unexpected value in header info.');
        }

        foreach ($headerInfo->from[0] as $k => $v) {
            $from[$k] = $this->decodeMimeHeaderString($v);
        }

        return $from;
    }

    /**
     * @param object $headerInfo
     * 
     * @return string
     */
    protected function getSubject($headerInfo)
    {
        if (!isset($headerInfo->subject)) {
            throw new MailStorageException('Field "subject" not found in header info.');
        }

        return $this->decodeMimeHeaderString($headerInfo->subject);
    }

    /**
     * @param mixed       $structure
     * @param null|string $section
     * @param array       $parts
     *
     * @return array
     */
    protected function getParts($structure, $section = null, &$parts = [])
    {
        if (isset($structure->parts) && is_array($structure->parts)) {
            $partNumber = 0;

            foreach ($structure->parts as $part) {
                ++$partNumber;

                $subSection = ($section === null) ? "$partNumber" : "$section.$partNumber";

                $parts[$subSection] = $part;

                $this->getParts($part, $subSection, $parts);
            }
        } else {
            if ($section === null) {
                $section = '1';
            }

            if (!isset($parts[$section])) {
                $parts[$section] = $structure;
            }
        }

        return $parts;
    }

    /**
     * @param string $messageUid
     *
     * @return array
     */
    protected function getBodies($messageUid)
    {
        $bodies = [];

        $structure = imap_fetchstructure($this->stream, $messageUid, FT_UID);

        $parts = $this->getParts($structure);

        foreach ($parts as $section => $part) {
            if (isset($part->type) && $part->type == TYPETEXT) {
                $allowedSubtypes = [
                    self::PART_SUBTYPE_HTML,
                    self::PART_SUBTYPE_PLAIN,
                ];

                if (isset($part->subtype) && in_array($part->subtype, $allowedSubtypes, true)) {
                    $body = imap_fetchbody($this->stream, $messageUid, $section, FT_UID);

                    if (isset($part->encoding)) {
                        switch ($part->encoding) {
                            case ENCQUOTEDPRINTABLE:
                                $body = imap_qprint($body);
                                break;
                        }
                    }

                    $charset = null;

                    if (isset($part->parameters)) {
                        foreach ($part->parameters as $parameter) {
                            if (isset($parameter->attribute) && isset($parameter->value)) {
                                switch ($parameter->attribute) {
                                    case 'charset':
                                        $charset = $parameter->value;
                                        break;
                                }
                            }
                        }
                    }

                    if ($this->requiresConvertEncoding($charset)) {
                        $body = $this->convertEncoding($body, $charset);
                    }

                    $bodies[] = [
                        'section' => $section,
                        'type' => $part->subtype,
                        'content' => $body,
                        'part' => $part,
                    ];
                }
            }
        }

        return $bodies;
    }

    /**
     * @param string $charset
     *
     * @return bool
     */
    protected function requiresConvertEncoding($charset)
    {
        return strtolower($charset) !== self::PART_CHARSET_DEFAULT && strtolower($charset) !== strtolower($this->outputEncoding);
    }

    /**
     * @param string $string
     * @param string $charset
     *
     * @return mixed|string
     */
    protected function convertEncoding($string, $charset)
    {
        if (self::isSupportedCharset($charset) === false) {
            error_log(sprintf(get_called_class().': charset "%s" not supported', $charset));

            return $string;
        }

        return mb_convert_encoding($string, $this->outputEncoding, $charset);
    }

    /**
     * @param string $charset
     *
     * @return bool
     */
    protected static function isSupportedCharset($charset)
    {
        return in_array(strtolower($charset), array_map('strtolower', self::getEncodings()), true);
    }

    /**
     * @return array
     */
    protected static function getEncodings()
    {
        return [
            self::ENCODING_TYPE_BASE64,
            self::ENCODING_TYPE_ISO_8859_1,
            self::ENCODING_TYPE_ISO_8859_15,
            self::ENCODING_TYPE_UTF8,
            self::ENCODING_TYPE_WINDOWS_1252,
        ];
    }
}
