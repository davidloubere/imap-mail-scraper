<?php

namespace ImapMailScraper;

use ImapMailScraper\Parse\Parser;
use ImapMailScraper\Mail\MessageData;

class Scraper
{
    const BODY_CONTENT_TYPE_HTML = 'HTML';
    const BODY_CONTENT_TYPE_PLAIN = 'PLAIN';

    /**
     * @var \ImapMailScraper\Parse\Parser
     */
    protected $parser;

    /**
     * @param \ImapMailScraper\Parse\Parser $parser
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param \ImapMailScraper\Mail\MessageData $messageData
     * @param $followRedirects
     *
     * @return array
     */
    public function getLinks(MessageData $messageData, $followRedirects)
    {
        $links = [];

        foreach ($messageData->getBodies() as $body) {
            if (isset($body['type']) && $body['type'] === self::BODY_CONTENT_TYPE_HTML) {
                $links = $this->parser->getLinks($body['content'], $followRedirects);
            }
        }

        return $links;
    }
}
