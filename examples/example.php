<?php

$loader = require_once __DIR__.'/../vendor/autoload.php';

use ImapMailScraper\Imap\MailStorage;
use ImapMailScraper\Parse\Parser;
use ImapMailScraper\Scraper;

$data = [];

$followRedirects = true;
$criteria = 'SINCE "30 Jun 2017" BEFORE "01 Jul 2017"';

$mailStorage = new MailStorage([
    'host' => 'mail.example.com',
    'port' => 993,
    'security' => 'ssl',
    'username' => 'username@example.com',
    'password' => '*********',
]);

$messages = $mailStorage->getMessagesByCriteria($criteria);

if (!empty($messages)) {
    $scraper = new Scraper(
        new Parser()
    );

    /* @var \ImapMailScraper\Mail\MessageData $message */
    foreach ($messages as $message) {
        $links = $scraper->getLinks($message, $followRedirects);

        $data[] = [
            'from' => $message->getFrom(),
            'subject' => $message->getSubject(),
            'date' => $message->getDate(),
            'links' => $links,
        ];
    }
}

print_r($data);
