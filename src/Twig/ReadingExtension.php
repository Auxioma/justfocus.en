<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ReadingExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('reading_time', [$this, 'calculateReadingTime']),
        ];
    }

    public function calculateReadingTime(string $text): string
    {
        $words = str_word_count(strip_tags($text));
        $minutes = ceil($words / 200); // 200 mots par minute en moyenne

        return $minutes.' min read';
    }
}
