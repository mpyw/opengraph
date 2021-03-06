<?php

namespace Mpyw\OpenGraph\Test;

use DateTimeInterface;
use Mpyw\OpenGraph\Consumer;
use Mpyw\OpenGraph\Exceptions\UnexpectedValueException;
use PHPUnit\Framework\TestCase;

class ConsumerTest extends TestCase
{
    /**
     * Checks crawler to read basic properties.
     */
    public function testLoadHtmlBasics(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:description" content="Description">
<meta property="og:determiner" content="auto">
<meta property="og:locale" content="en_GB">
<meta property="og:locale:alternate" content="en_US">
<meta property="og:locale:alternate" content="de_AT">
<meta property="og:rich_attachment" content="True">
<meta property="og:see_also" content="https://github.com/fusonic/fusonic-linq">
<meta property="og:see_also" content="https://github.com/fusonic/fusonic-spreadsheetexport">
<meta property="og:site_name" content="Site name">
<meta property="og:title" content="Title">
<meta property="og:updated_time" content="2014-07-20T17:51:00+02:00">
<meta property="og:url" content="https://github.com/fusonic/fusonic-opengraph">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content, 'about:blank');

        $this->assertEquals('Description', $res->description);
        $this->assertEquals('auto', $res->determiner);
        $this->assertEquals('en_GB', $res->locale);
        $this->assertContains('en_US', $res->localeAlternate);
        $this->assertContains('de_AT', $res->localeAlternate);
        $this->assertTrue($res->richAttachment);
        $this->assertContains('https://github.com/fusonic/fusonic-linq', $res->seeAlso);
        $this->assertContains('https://github.com/fusonic/fusonic-spreadsheetexport', $res->seeAlso);
        $this->assertEquals('Site name', $res->siteName);
        $this->assertEquals('Title', $res->title);
        $this->assertTrue($res->updatedTime instanceof DateTimeInterface);
        $this->assertEquals('https://github.com/fusonic/fusonic-opengraph', $res->url);
    }

    /**
     * Checks crawler not to use fallback if disabled even if no OG data is provided.
     */
    public function testLoadHtmlFallbacksOff(): void
    {
        $content = <<<'LONG'
<html>
<head>
<title>Title</title>
<meta property="description" content="Description">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content, 'about:blank');

        $this->assertNull($res->description);
        $this->assertNull($res->title);
        $this->assertNull($res->url);
    }

    /**
     * Checks crawler to correctly use fallback elements when activated.
     */
    public function testLoadHtmlFallbacksOn(): void
    {
        $content = <<<'LONG'
<html>
<head>
<title>Title</title>
<meta property="description" content="Description">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();
        $consumer->useFallbackMode = true;

        $res = $consumer->loadHtml($content, 'about:blank');

        $this->assertEquals('Description', $res->description);
        $this->assertEquals('Title', $res->title);
        $this->assertEquals('about:blank', $res->url);
    }

    /**
     * Checks crawler to correctly use fallback elements when activated.
     */
    public function testLoadHtmlCanonicalLinkFallbacksOn(): void
    {
        $content = <<<'LONG'
<html>
<head>
<title>Title</title>
<meta property="description" content="Description">
<link rel="canonical" href="https://github.com/fusonic/opengraph">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();
        $consumer->useFallbackMode = true;

        $res = $consumer->loadHtml($content, 'about:blank');

        $this->assertEquals('Description', $res->description);
        $this->assertEquals('Title', $res->title);
        $this->assertEquals('https://github.com/fusonic/opengraph', $res->url);
    }

    /**
     * Checks crawler to handle arrays of elements with child-properties like described in the
     * Open Graph documentation (http://ogp.me/#array).
     */
    public function testLoadHtmlArrayHandling(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:image" content="http://example.com/rock.jpg">
<meta property="og:image:width" content="300">
<meta property="og:image:height" content="300">
<meta property="og:image" content="http://example.com/rock2.jpg">
<meta property="og:image" content="http://example.com/rock3.jpg">
<meta property="og:image:height" content="1000">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(3, count($res->images));
        $this->assertEquals('http://example.com/rock.jpg', $res->images[0]->url);
        $this->assertEquals(300, $res->images[0]->width);
        $this->assertEquals(300, $res->images[0]->height);
        $this->assertEquals('http://example.com/rock2.jpg', $res->images[1]->url);
        $this->assertNull($res->images[1]->width);
        $this->assertNull($res->images[1]->height);
        $this->assertEquals('http://example.com/rock3.jpg', $res->images[2]->url);
        $this->assertNull($res->images[2]->width);
        $this->assertEquals(1000, $res->images[2]->height);
    }

    public function testLoadHtmlImages(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:image" content="http://example.com/rock.jpg">
<meta property="og:image:secure_url" content="https://example.com/rock.jpg">
<meta property="og:image:width" content="300">
<meta property="og:image:height" content="300">
<meta property="og:image:type" content="image/jpg">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(1, count($res->images));
        $this->assertEquals('http://example.com/rock.jpg', $res->images[0]->url);
        $this->assertEquals('https://example.com/rock.jpg', $res->images[0]->secureUrl);
        $this->assertEquals(300, $res->images[0]->width);
        $this->assertEquals(300, $res->images[0]->height);
        $this->assertEquals('image/jpg', $res->images[0]->type);
    }

    public function testLoadHtmlVideos(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:video" content="http://example.com/rock.ogv">
<meta property="og:video:secure_url" content="https://example.com/rock.ogv">
<meta property="og:video:width" content="300">
<meta property="og:video:height" content="300">
<meta property="og:video:type" content="video/ogv">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(1, count($res->videos));
        $this->assertEquals('http://example.com/rock.ogv', $res->videos[0]->url);
        $this->assertEquals('https://example.com/rock.ogv', $res->videos[0]->secureUrl);
        $this->assertEquals(300, $res->videos[0]->width);
        $this->assertEquals(300, $res->videos[0]->height);
        $this->assertEquals('video/ogv', $res->videos[0]->type);
    }

    public function testLoadHtmlAudios(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:audio" content="http://example.com/rock.mp3">
<meta property="og:audio:secure_url" content="https://example.com/rock.mp3">
<meta property="og:audio:type" content="audio/mp3">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(1, count($res->audios));
        $this->assertEquals('http://example.com/rock.mp3', $res->audios[0]->url);
        $this->assertEquals('https://example.com/rock.mp3', $res->audios[0]->secureUrl);
        $this->assertEquals('audio/mp3', $res->audios[0]->type);
    }

    public function testCrawlHtmlImageExceptionDebugOff(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:image:width" content="300">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(0, count($res->images));
    }

    public function testCrawlHtmlImageExceptionDebugOn(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $content = <<<'LONG'
<html>
<head>
<meta property="og:image:width" content="300">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();
        $consumer->debug = true;

        $res = $consumer->loadHtml($content);
    }

    public function testCrawlHtmlVideoExceptionDebugOff(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:video:width" content="300">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(0, count($res->videos));
    }

    public function testCrawlHtmlVideoExceptionDebugOn(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $content = <<<'LONG'
<html>
<head>
<meta property="og:video:width" content="300">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();
        $consumer->debug = true;

        $res = $consumer->loadHtml($content);
    }

    public function testCrawlHtmlAudioExceptionDebugOff(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:audio:secure_url" content="300">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals(0, count($res->audios));
    }

    public function testCrawlHtmlAudioExceptionDebugOn(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $content = <<<'LONG'
<html>
<head>
<meta property="og:audio:type" content="audio/mp3">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();
        $consumer->debug = true;

        $res = $consumer->loadHtml($content);
    }

    public function testLoadHtmlSpecialCharacters(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta property="og:title" content="Apples &amp; Bananas - just &quot;Fruits&quot;">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals('Apples & Bananas - just "Fruits"', $res->title);
    }

    public function testReadMetaName(): void
    {
        $content = <<<'LONG'
<html>
<head>
<meta name="og:title" content="A 'name' attribute instead of 'property'">
</head>
<body></body>
</html>
LONG;

        $consumer = new Consumer();

        $res = $consumer->loadHtml($content);

        $this->assertEquals("A 'name' attribute instead of 'property'", $res->title);
    }

    public function testLoadHtmlEmpty(): void
    {
        $content = '   ';

        (new Consumer())->loadHtml($content);

        $this->assertTrue(true);
    }
}
