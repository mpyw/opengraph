<?php

if (!$loader = @include __DIR__ . '/../vendor/autoload.php') {
    die('You must set up the project dependencies, run the following commands:' . PHP_EOL .
        'curl -s http://getcomposer.org/installer | php' . PHP_EOL .
        'php composer.phar install' . PHP_EOL);
}

// Create a new crawler
$crawler = new Mpyw\OpenGraph\Consumer();

// Crawl the desired URL and retrieve a Mpyw\OpenGraph\Object in response
$object = $crawler->loadHtml(file_get_contents('https://github.com'));

var_dump($object);
