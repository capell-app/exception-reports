<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Support;

final class ExceptionReportMailSanitizer
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function sanitize(array $report): array
    {
        $unsafePaths = [];

        $sanitized = [
            'subject' => $this->sanitizeScalar(data_get($report, 'subject'), 'subject', $unsafePaths),
            'source' => $this->sanitizeScalar(data_get($report, 'source'), 'source', $unsafePaths),
            'summary' => $this->sanitizeArray($this->arrayValue(data_get($report, 'summary', [])), 'summary', $unsafePaths),
            'request' => $this->sanitizeArray($this->arrayValue(data_get($report, 'request', [])), 'request', $unsafePaths),
            'user' => $this->sanitizeArray($this->arrayValue(data_get($report, 'user', [])), 'user', $unsafePaths),
            'trace' => $this->sanitizeBlock(data_get($report, 'trace'), 'trace', $unsafePaths),
        ];

        $sanitized['unsafe'] = [
            'detected' => $unsafePaths !== [],
            'paths' => array_values(array_unique($unsafePaths)),
        ];

        return $sanitized;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<int, string>  $unsafePaths
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values, string $path, array &$unsafePaths): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = (string) $key;
            $childPath = $path . '.' . $normalizedKey;
            $sanitized[$normalizedKey] = is_array($value)
                ? $this->sanitizeArray($value, $childPath, $unsafePaths)
                : $this->sanitizeScalar($value, $childPath, $unsafePaths);
        }

        return $sanitized;
    }

    /**
     * @param  array<int, string>  $unsafePaths
     */
    private function sanitizeScalar(mixed $value, string $path, array &$unsafePaths): string
    {
        $original = $this->stringValue($value);
        $sanitized = $this->stripUnsafeHtml($original);
        $sanitized = preg_replace('/[^\P{C}\t]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';
        $sanitized = trim(str_replace('|', '/', $sanitized));

        if ($sanitized !== $original) {
            $unsafePaths[] = $path;
        }

        return $sanitized === '' ? 'n/a' : $sanitized;
    }

    /**
     * @param  array<int, string>  $unsafePaths
     */
    private function sanitizeBlock(mixed $value, string $path, array &$unsafePaths): string
    {
        $original = $this->stringValue($value);
        $sanitized = $this->stripUnsafeHtml($original);
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);
        $sanitized = preg_replace('/[^\P{C}\n\t]+/u', '', $sanitized) ?? '';

        if ($sanitized !== $original) {
            $unsafePaths[] = $path;
        }

        return trim($sanitized);
    }

    private function stripUnsafeHtml(string $value): string
    {
        if (preg_match('/<[^>]+>/', $value) !== 1) {
            return $value;
        }

        $value = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math|template|form|noscript|canvas|audio|video|applet)\b[^>]*>.*?</\1>#is',
            '',
            $value,
        ) ?? '';

        return strip_tags($value);
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : 'n/a';
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
