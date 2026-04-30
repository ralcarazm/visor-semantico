<?php
declare(strict_types=1);

namespace KnowledgeMap\Support;

final class UriHelper
{
    public static function isUri(string $value): bool
    {
        return (bool) preg_match('/^https?:\/\//i', $value);
    }

    public static function shortLabel(string $uri): string
    {
        $uri = trim($uri);

        if ($uri === '') {
            return '';
        }

        $wikidataId = self::wikidataIdFromUri($uri);
        if ($wikidataId !== null) {
            return $wikidataId;
        }

        $clean = preg_replace('/[#\/]+$/', '', $uri) ?: $uri;
        $posHash = strrpos($clean, '#');
        $posSlash = strrpos($clean, '/');
        $pos = max($posHash === false ? -1 : $posHash, $posSlash === false ? -1 : $posSlash);

        if ($pos >= 0 && $pos < strlen($clean) - 1) {
            $clean = substr($clean, $pos + 1);
        }

        $clean = rawurldecode($clean);
        $clean = str_replace(['_', '-'], ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean) ?: $clean;

        return trim($clean);
    }

    public static function predicateLabel(string $predicate, array $configuredLabels = []): string
    {
        if (isset($configuredLabels[$predicate])) {
            return (string) $configuredLabels[$predicate];
        }

        return self::shortLabel($predicate);
    }

    public static function literalNodeId(string $predicate, string $literal): string
    {
        return 'literal:' . sha1($predicate . '|' . strtolower(trim($literal)));
    }

    public static function wikidataIdFromUri(string $uri): ?string
    {
        if (preg_match('~wikidata\.org/(?:entity|wiki)/(Q\d+)~i', $uri, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function looksLikeImageUrl(string $value): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i', $value)
            || str_contains($value, 'commons.wikimedia.org/wiki/Special:FilePath/');
    }
}
