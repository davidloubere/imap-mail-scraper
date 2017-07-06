<?php

namespace ImapMailScraper\Parse;

use ImapMailScraper\Crawl\Crawler;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

class Parser
{
    /**
     * @var \Symfony\Component\Validator\ValidatorInterface
     */
    private $validator;

    /**
     * @var \ImapMailScraper\Crawl\Crawler
     */
    private $crawler;

    public function __construct()
    {
        $this->validator = Validation::createValidator();

        $this->crawler = new Crawler($this);
    }

    /**
     * @param string    $html
     * @param bool|true $followRedirects
     *
     * @return array
     */
    public function getLinks($html, $followRedirects = false)
    {
        $links = [];

        $followedRedirects = [];

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);//FIXME

        /* @var \DOMElement $aNode */
        foreach ($dom->getElementsByTagName('a') as $aNode) {
            if ($aNode->hasAttribute('href')) {
                $href = $sourceUrl = $aNode->getAttribute('href');

                if (!$this->isValidUrl($href)) {
                    continue;
                }

                $url = $href;

                if ($followRedirects === true) {
                    $hrefHash = md5($href);

                    if (isset($followedRedirects[$hrefHash])) {
                        $urls = null;

                        $url = $followedRedirects[$hrefHash];
                    } else {
                        $urls = $this->crawler->followRedirects($href);

                        $url = $urls['effective'];

                        $followedRedirects[$hrefHash] = $url;
                    }
                }

                $urlHash = md5($url);

                if (!isset($links[$urlHash])) {
                    $links[$urlHash] = [
                        'url' => $url,
                    ];

                    if (isset($urls['probable']) && !empty($urls['probable'])) {
                        $links[$urlHash]['redirects'] = $urls['probable'];
                    }
                }

                $text = trim($aNode->textContent);

                $texts = isset($links[$urlHash]['texts']) ? $links[$urlHash]['texts'] : [];

                if (!empty($text) && !in_array($text, $texts, true)) {
                    $links[$urlHash]['texts'][] = $text;
                }

                /* @var \DOMElement $imgNode */
                foreach ($aNode->getElementsByTagName('img') as $imgNode) {
                    if ($imgNode->hasAttribute('src')) {
                        $src = trim($imgNode->getAttribute('src'));

                        $srcs = isset($links[$urlHash]['images']) ? $links[$urlHash]['images'] : [];

                        if (!empty($src) && !in_array($src, $srcs, true) && $this->isValidUrl($src)) {
                            $links[$urlHash]['images'][] = $src;
                        }
                    }
                }
            }
        }

        usort($links, function ($a, $b) {
            $countElements = function ($link) {
                $textsCount = isset($link['texts']) ? count($link['texts']) : 0;
                $imagesCount = isset($link['images']) ? count($link['images']) : 0;

                return $textsCount + $imagesCount;
            };

            return $countElements($b) - $countElements($a);
        });

        return $links;
    }

    /**
     * string $html.
     *
     * @return bool|mixed
     */
    public function parseMetaRedirectUrl($html)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);//FIXME

        if ($dom->nodeType === XML_HTML_DOCUMENT_NODE) {
            foreach ($dom->getElementsByTagName('meta') as $metaNode) {
                if ($metaNode instanceof \DOMElement && $metaNode->hasAttribute('http-equiv')) {
                    $metaNodeContent = $metaNode->getAttribute('content');

                    $metaNodeContentParams = (explode('; ', $metaNodeContent));

                    foreach ($metaNodeContentParams as $metaNodeContentParam) {
                        $pattern = '/^url=/i';

                        if (preg_match($pattern, $metaNodeContentParam)) {
                            $url = preg_replace($pattern, '', $metaNodeContentParam);

                            $url = $this->cleanUrl($url);

                            if ($this->isValidUrl($url)) {
                                return $url;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * string $html.
     *
     * @return array
     */
    public function parseScriptRedirectUrl($html)
    {
        $urls = [];

        $patterns = [
            '/(self|top|window)\.location\s*=\s*\'(.*?)\';?/',
            '/(self|top|window)\.location\s*=\s*"(.*?)";?/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                if (isset($matches[2]) && is_array($matches[2]) && !empty($matches[2])) {
                    foreach ($matches[2] as $url) {
                        $url = $this->cleanUrl($url);

                        if ($this->isValidUrl($url)) {
                            $urls[] = $url;
                        }
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function cleanUrl($url)
    {
        $url = str_replace('$', '', $url);

        $url = stripslashes($url);

        $url = trim($url, "\"' \t\n\r\0\x0B");

        return $url;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    protected function isValidUrl($url)
    {
        return count($this->validator->validate($url, [new Assert\Url()])) === 0;
    }
}
