<?php

namespace ImapMailScraper\Crawl;

use GuzzleHttp;
use ImapMailScraper\Crawl\Exception\CrawlerException;
use ImapMailScraper\Parse\Parser;

class Crawler
{
    const REDIRECTS_MAX = 10;
    const REDIRECTS_CONNECT_TIMEOUT = 5;
    const REDIRECTS_TIMEOUT = 5;

    /**
     * @var \ImapMailScraper\Parse\Parser
     */
    protected $parser;

    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleHttpClient;

    /**
     * @param \ImapMailScraper\Parse\Parser $parser
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;

        $this->guzzleHttpClient = new GuzzleHttp\Client();
    }

    /**
     * @param string    $url
     * @param bool|true $includeProbable
     *
     * @return array
     *
     * @throws \ImapMailScraper\Crawl\Exception\CrawlerException
     */
    public function followRedirects($url, $includeProbable = true)
    {
        $urls = [
            'source' => $url,
            'effective' => null,
        ];

        try {
            $res = $this->guzzleHttpClient->request('GET', $url, [
                'allow_redirects' => [
                    'track_redirects' => true,
                    'max' => self::REDIRECTS_MAX,
                    'connect_timeout' => self::REDIRECTS_CONNECT_TIMEOUT,
                    'timeout' => self::REDIRECTS_TIMEOUT,
                ],
            ]);
        } catch (\Exception $e) {
            throw new CrawlerException(sprintf('Unable to get URL "%s": %s', $url, $e->getMessage()));
        }

        $urlEffective = array_pop(
            explode(', ', $res->getHeaderLine('X-Guzzle-Redirect-History'))
        );

        $urls['effective'] = empty($urlEffective) ? $url : $urlEffective;

        if ($includeProbable === true) {
            $probableUrls = [];

            $html = $res->getBody()->getContents();

            $metaRedirectUrl = $this->parser->parseMetaRedirectUrl($html);
            if ($metaRedirectUrl !== false) {
                $probableUrls[] = $metaRedirectUrl;
            }

            $scriptRedirectUrls = $this->parser->parseScriptRedirectUrl($html);
            if (!empty($scriptRedirectUrls)) {
                $probableUrls = array_merge($probableUrls, $scriptRedirectUrls);
            }

            if (!empty($probableUrls)) {
                $urls['probable'] = $probableUrls;
            }
        }

        return $urls;
    }
}
