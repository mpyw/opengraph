<?php

namespace Mpyw\OpenGraph;

use DOMElement;
use GuzzleHttp\Client;
use Mpyw\OpenGraph\Objects\ObjectBase;
use Mpyw\OpenGraph\Objects\Website;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Consumer that extracts Open Graph data from either a URL or a HTML string.
 */
class Consumer
{
    /**
     * When enabled, crawler will read content of title and meta description if no
     * Open Graph data is provided by target page.
     *
     * @var bool
     */
    public $useFallbackMode = false;

    /**
     * When enabled, crawler will throw exceptions for some crawling errors like unexpected
     * Open Graph elements.
     *
     * @var bool
     */
    public $debug = false;

    /**
     * Fetches HTML content from the given URL and then crawls it for Open Graph data.
     *
     * @param  string     $url URL to be crawled.
     * @return ObjectBase
     */
    public function loadUrl(string $url): ObjectBase
    {
        // Fetch HTTP content using Guzzle
        $response = (new Client())->get($url);
        return $this->loadHtml((string)$response->getBody(), $url);
    }

    /**
     * @param  string      $html        HTML string, usually whole content of crawled web resource.
     * @param  null|string $fallbackUrl URL to use when fallback mode is enabled.
     * @return ObjectBase
     */
    public function loadHtml(string $html, ?string $fallbackUrl = null): ObjectBase
    {
        $crawler = $this->newCrawler($html);

        $properties = $this->newProperties($crawler);

        $object = $this
            ->newObject($crawler)
            ->assignProperties($properties, $this->debug);

        if ($this->useFallbackMode) {
            $this->fallback($object, $crawler, $fallbackUrl);
        }

        return $object;
    }

    /**
     * @param  Crawler    $crawler
     * @return Property[]
     */
    protected function newProperties(Crawler $crawler): array
    {
        return array_map(
            [$this, 'newProperty'],
            iterator_to_array($crawler->filterXPath('//meta[starts-with(@property, "og:") or starts-with(@name, "og:")]'), false)
        );
    }

    /**
     * @param  string  $content
     * @return Crawler
     */
    protected function newCrawler(string $content): Crawler
    {
        $crawler = new Crawler();
        $crawler->addHTMLContent($content);
        return $crawler;
    }

    /**
     * @param  DOMElement $tag
     * @return Property
     */
    protected function newProperty(DOMElement $tag): Property
    {
        $name = trim($tag->getAttribute('name') ?: $tag->getAttribute('property'));
        $value = trim($tag->getAttribute('content'));
        return new Property($name, $value);
    }

    /**
     * @param  Crawler    $crawler
     * @return ObjectBase
     */
    protected function newObject(Crawler $crawler): ObjectBase
    {
        switch ($crawler->evaluate('normalize-space(//meta[@property="og:type" or @name="og:type"]/@content)')[0] ?? null) {
            default:
                return new Website();
        }
    }

    /**
     * @param  ObjectBase  $object
     * @param  Crawler     $crawler
     * @param  null|string $fallbackUrl
     * @return static
     */
    protected function fallback(ObjectBase $object, Crawler $crawler, ?string $fallbackUrl = null): self
    {
        return $this
            ->fallbackForUrl($object, $crawler, $fallbackUrl)
            ->fallbackForTitle($object, $crawler)
            ->fallbackForDescription($object, $crawler);
    }

    /**
     * @param  ObjectBase  $object
     * @param  Crawler     $crawler
     * @param  null|string $fallbackUrl
     * @return static
     */
    protected function fallbackForUrl(ObjectBase $object, Crawler $crawler, ?string $fallbackUrl = null): self
    {
        $object->url = $object->url
            ?: $crawler->evaluate('normalize-space(//link[@rel="canonical"]/@href)')[0]
            ?? null
            ?: $fallbackUrl
            ?: $object->url;

        return $this;
    }

    /**
     * @param  ObjectBase $object
     * @param  Crawler    $crawler
     * @return static
     */
    protected function fallbackForTitle(ObjectBase $object, Crawler $crawler): self
    {
        $object->title = $object->title
            ?: $crawler->evaluate('normalize-space(//title)')[0]
            ?? $crawler->evaluate('normalize-space(//h1)')[0]
            ?? $crawler->evaluate('normalize-space(//h2)')[0]
            ?? null
            ?: $object->title;

        return $this;
    }

    /**
     * @param  ObjectBase $object
     * @param  Crawler    $crawler
     * @return static
     */
    protected function fallbackForDescription(ObjectBase $object, Crawler $crawler): self
    {
        $object->description = $object->description
            ?: $crawler->evaluate('normalize-space(//meta[@property="description" or @name="description"]/@content)')[0]
            ?? $crawler->evaluate('normalize-space(//p)')[0]
            ?? null
            ?: $object->description;

        return $this;
    }
}
