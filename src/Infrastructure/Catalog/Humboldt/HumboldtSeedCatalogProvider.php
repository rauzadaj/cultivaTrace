<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog\Humboldt;

use App\Application\Catalog\SeedCatalog\SeedCatalogEntry;
use App\Application\Catalog\SeedCatalog\SeedCatalogProvider;

final class HumboldtSeedCatalogProvider implements SeedCatalogProvider
{
    private const BASE_URL = 'https://humboldtseedcompany.com';
    private const SITEMAP_URL = self::BASE_URL . '/page-sitemap.xml';
    private const VENDOR = 'Humboldt Seed Company';
    private const MARKETS = ['california', 'canada'];

    /** @var list<string> */
    private const URL_EXCLUSIONS = [
        '/about/',
        '/contact/',
        '/faq/',
        '/learn/',
        '/news/',
        '/privacy-policy/',
        '/retailers/',
        '/wholesale/',
        '/gallery/',
        '/cultivation-guide/',
        '/growing-101/',
        '/grow-your-own-garden/',
        '/how-to-germinate-cannabis-seeds/',
        '/cannabis-culture/',
        '/cannabis-genetics/',
        '/strain-genetics/',
        '/pheno-hunt/',
        '/seed-catalog/',
        '/humboldt-seed-company-2025-catalog/',
        '/de/',
        '/feminized-seeds/',
        '/autoflower-seeds/',
        '/regularseeds/',
        '/triploid-cannabis-seeds/',
        '/cannabis-seeds/',
        '/garden-seeds/',
        '/california-state-fair/',
        '/cheap-cannabis-seeds/',
    ];

    /**
     * @return list<SeedCatalogEntry>
     */
    public function fetchEntries(): array
    {
        $productUrls = $this->collectCandidateUrls();
        $entries = [];

        foreach ($productUrls as $productUrl) {
            $page = $this->fetchPageBySlug($this->extractSlug($productUrl));

            if (null === $page) {
                continue;
            }

            $entry = $this->hydrateEntry($page);

            if (null !== $entry) {
                $entries[] = $entry;
            }
        }

        usort(
            $entries,
            static fn (SeedCatalogEntry $left, SeedCatalogEntry $right): int => strcmp($left->name, $right->name),
        );

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function collectCandidateUrls(): array
    {
        return $this->extractSitemapUrls($this->fetch(self::SITEMAP_URL));
    }

    /** @param array<string, mixed> $page */
    private function hydrateEntry(array $page): ?SeedCatalogEntry
    {
        $title = $this->sanitizeTitle($this->stringValue($page['title']['rendered'] ?? ''));
        $content = $this->stringValue($page['content']['rendered'] ?? '');

        if (
            '' === $title
            || '' === $content
            || !str_contains($content, 'Shop US')
            || str_contains($title, 'Garden Seeds')
            || str_contains($content, 'Garden Seeds')
        ) {
            return null;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $description = $this->extractDescription($xpath, $content);
        $genetics = $this->extractGenetics($content, $title);
        $imageUrl = $this->extractImageUrl($xpath, $title);
        $path = parse_url($this->stringValue($page['link'] ?? ''), PHP_URL_PATH);
        $slug = trim((string) $path, '/');

        if ('' === $description || '' === $genetics || '' === $imageUrl || '' === $slug) {
            return null;
        }

        $type = $this->extractType($content);
        $summary = $this->extractSummary($xpath);
        $flavor = $this->extractFlavorProfile($xpath);
        $thc = $this->extractThcRange($summary);

        return new SeedCatalogEntry(
            code: $this->buildCode($slug),
            name: $title,
            vendor: self::VENDOR,
            genetics: $genetics,
            description: $description,
            imageUrl: $imageUrl,
            sourceUrl: $this->stringValue($page['link'] ?? ''),
            sourceModifiedAt: $this->stringValue($page['modified_gmt'] ?? ''),
            markets: self::MARKETS,
            metadata: array_filter([
                'source' => 'humboldtseedcompany.com',
                'type' => $type,
                'summary' => $summary,
                'thcRange' => $thc,
                'flavorProfile' => $flavor,
                'legalFit' => [
                    'california' => 'Named strain catalogs are not whitelisted by California law; seeds and immature plants must come from licensed sources.',
                    'canada' => 'Named strain catalogs are not whitelisted federally; adults may grow up to four plants per residence where provincial law permits.',
                ],
            ], static fn (mixed $value): bool => match (true) {
                is_array($value) => [] !== $value,
                default => null !== $value && '' !== $value,
            }),
        );
    }

    /**
     * @return list<string>
     */
    private function extractSitemapUrls(string $sitemapXml): array
    {
        preg_match_all('#<loc>(https://humboldtseedcompany\.com/[^<]+)</loc>#i', $sitemapXml, $matches);

        $urls = [];

        foreach ($matches[1] ?? [] as $url) {
            if (!$this->isCandidateProductUrl($url)) {
                continue;
            }

            $urls[$url] = true;
        }

        return array_keys($urls);
    }

    private function isCandidateProductUrl(string $url): bool
    {
        if (!preg_match('#^https://humboldtseedcompany\.com/[^/]+/?$#i', $url)) {
            return false;
        }

        if ($url === self::BASE_URL . '/') {
            return false;
        }

        if ($this->isExcludedUrl($url)) {
            return false;
        }

        return true;
    }

    private function isExcludedUrl(string $url): bool
    {
        foreach (self::URL_EXCLUSIONS as $excludedPath) {
            if (str_ends_with($url, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function fetchPageBySlug(string $slug): ?array
    {
        $endpoint = sprintf('%s/wp-json/wp/v2/pages?slug=%s', self::BASE_URL, rawurlencode($slug));
        $payload = json_decode($this->fetch($endpoint), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload) || [] === $payload || !isset($payload[0]) || !is_array($payload[0])) {
            return null;
        }

        return $payload[0];
    }

    private function extractSlug(string $url): string
    {
        return trim((string) parse_url($url, PHP_URL_PATH), '/');
    }

    private function sanitizeTitle(string $title): string
    {
        $normalized = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5));

        $normalized = preg_replace('/\s+Cannabis Seeds$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+Cannabis Strain$/i', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function extractDescription(\DOMXPath $xpath, string $content): string
    {
        /** @var \DOMNodeList<\DOMElement> $paragraphs */
        $paragraphs = $xpath->query('//div[contains(@class, "fusion-text-1")]//p');

        foreach ($paragraphs as $paragraph) {
            $text = $this->normalizeWhitespace($paragraph->textContent ?? '');

            if ($text !== '' && !str_starts_with($text, 'Our seeds are available')) {
                return $text;
            }
        }

        if (preg_match('/<meta name="description" content="([^"]+)"/i', $content, $matches) === 1) {
            return $this->normalizeWhitespace(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
        }

        return '';
    }

    private function extractGenetics(string $content, string $name): string
    {
        $plainText = $this->normalizeWhitespace(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5)));

        $labeledPatterns = [
            '/(?:Parentals|GENETICS|Genetics|Parentage)\s*:\s*(.+?)(?:\s+(?:Ind\s*\/\s*Sat|%?\s*Indica\/Sativa|TYPE|Photoperiodic|Flowering|Available|Smell|Flavors|Effects|Terpene|Grower|Growers|Performance|Average Cannabinoids)|$)/i',
            '/(?:Parentals|GENETICS|Genetics|Parentage)\s*-\s*(.+?)(?:\s+(?:Ind\s*\/\s*Sat|%?\s*Indica\/Sativa|TYPE|Photoperiodic|Flowering|Available|Smell|Flavors|Effects|Terpene|Grower|Growers|Performance|Average Cannabinoids)|$)/i',
        ];

        foreach ($labeledPatterns as $pattern) {
            if (preg_match($pattern, $plainText, $matches) === 1) {
                return $this->normalizeGenetics($matches[1]);
            }
        }

        $patterns = [
            '/born from crossing\s+(.+?)(?:\.|\bThat\b|\bInitial\b)/i',
            '/created by crossing\s+(.+?)(?:\.|\bIt\b|\bThis\b)/i',
            '/is a cross of\s+(.+?)(?:\.|\bThe\b|\bThis\b)/i',
            '/is a cross between\s+(.+?)(?:\.|\bThe\b|\bThis\b)/i',
            '/result of combining\s+(.+?)(?:\.|\bThis\b|\bThe\b)/i',
            '/offspring of\s+(.+?)(?:\.|\bThis\b|\bThe\b)/i',
            '/combines the yield.*?of\s+(.+?)\s+with\s+(.+?)(?:\.|\bOur\b|\bThis\b)/i',
            '/genetic line includes\s+(.+?)(?:\.|\bIt\b)/i',
            '/believed to be a blend of\s+(.+?)(?:\.|\bIts\b)/i',
            '/originates from a genetic blend of\s+(.+?)(?:\.|\bThis\b)/i',
            '/is a .*? that was born from\s+(.+?)(?:\.|\bJust\b)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plainText, $matches) === 1) {
                if (isset($matches[2]) && is_string($matches[2]) && '' !== trim($matches[2])) {
                    return $this->normalizeGenetics(sprintf('%s x %s', $matches[1], $matches[2]));
                }

                return $this->normalizeGenetics($matches[1]);
            }
        }

        if (preg_match('/authentic\s+([A-Za-z0-9#\-\s]+?)\s+genetic/i', $plainText, $matches) === 1) {
            return $this->normalizeGenetics($matches[1]);
        }

        return sprintf('%s lineage not explicitly normalized from the source page.', $name);
    }

    private function extractImageUrl(\DOMXPath $xpath, string $title): string
    {
        /** @var \DOMNodeList<\DOMElement> $images */
        $images = $xpath->query('//img');

        foreach ($images as $image) {
            $src = trim((string) $image->getAttribute('src'));
            $alt = $this->normalizeWhitespace($image->getAttribute('alt'));

            if (
                $src !== ''
                && str_starts_with($src, 'https://humboldtseedcompany.com/wp-content/uploads/')
                && ($alt === '' || stripos($alt, $title) !== false || stripos($alt, 'Cannabis') !== false)
                && stripos($src, 'logo') === false
                && stripos($src, 'Icons-Final') === false
            ) {
                return $src;
            }
        }

        return '';
    }

    private function extractType(string $content): ?string
    {
        if (preg_match('/>(Feminized(?: Cannabis)? Seeds|Autoflower(?: Cannabis)? Seeds|Regular(?: Cannabis)? Seeds|Triploid(?: Cannabis)? Seeds)/i', $content, $matches) === 1) {
            return $this->normalizeWhitespace(strip_tags($matches[1]));
        }

        return null;
    }

    private function extractSummary(\DOMXPath $xpath): ?string
    {
        /** @var \DOMNodeList<\DOMElement> $headings */
        $headings = $xpath->query('//div[contains(@class, "fusion-title-1")]//p[1]');

        if (false === $headings || 0 === $headings->length) {
            return null;
        }

        return $this->normalizeWhitespace($headings->item(0)?->textContent ?? '');
    }

    private function extractFlavorProfile(\DOMXPath $xpath): ?string
    {
        /** @var \DOMNodeList<\DOMElement> $headings */
        $headings = $xpath->query('//div[contains(@class, "fusion-title-1")]//p[2]');

        if (false === $headings || 0 === $headings->length) {
            return null;
        }

        return $this->normalizeWhitespace($headings->item(0)?->textContent ?? '');
    }

    private function extractThcRange(?string $summary): ?string
    {
        if (null === $summary) {
            return null;
        }

        if (preg_match('/(?:Avg\.\s*THC|THC)\s*([0-9]+(?:-[0-9]+)?%)/i', $summary, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function buildCode(string $slug): string
    {
        $base = strtoupper(str_replace('-', '_', $slug));

        return substr(sprintf('HSC_%s', $base), 0, 64);
    }

    private function normalizeGenetics(string $value): string
    {
        $value = $this->normalizeWhitespace($value);
        $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+[|,;:-]\s*$/', '', $value) ?? $value;

        return trim($value);
    }

    private function normalizeWhitespace(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'ignore_errors' => true,
                'header' => [
                    'User-Agent: CultivaTrace Catalog Importer/1.0',
                    'Accept: application/json, text/html, application/xml;q=0.9, */*;q=0.8',
                ],
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            throw new \RuntimeException(sprintf('Failed to fetch remote source: %s', $url));
        }

        return $response;
    }
}
