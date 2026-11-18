<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Support;

final class ExceptionReportMailSanitizer
{
    private const string REDACTED = '[redacted]';

    /**
     * @var array<int, string>
     */
    private const array SENSITIVE_KEYWORDS = [
        'authorization',
        'cookie',
        'password',
        'passwd',
        'secret',
        'session',
        'signature',
        'token',
        'apikey',
        'api_key',
        'accesskey',
        'access_key',
        'privatekey',
        'private_key',
    ];

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
            'console' => $this->sanitizeArray($this->arrayValue(data_get($report, 'console', [])), 'console', $unsafePaths),
            'user' => $this->sanitizeArray($this->arrayValue(data_get($report, 'user', [])), 'user', $unsafePaths),
            'digest' => $this->sanitizeArray($this->arrayValue(data_get($report, 'digest', [])), 'digest', $unsafePaths),
            'trace' => $this->sanitizeBlock(data_get($report, 'trace'), 'trace', $unsafePaths),
        ];

        $sanitized['unsafe'] = [
            'detected' => $unsafePaths !== [],
            'paths' => array_values(array_unique($unsafePaths)),
        ];

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitizeLogContext(array $context): array
    {
        $unsafePaths = [];

        return $this->sanitizeArray($context, 'context', $unsafePaths);
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
        $sanitized = $this->redactSensitiveValue($sanitized, $path);

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
        $sanitized = $this->redactSensitiveValue($sanitized, $path);

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

    private function redactSensitiveValue(string $value, string $path): string
    {
        if ($this->isSensitivePath($path)) {
            return $value === '' || $value === 'n/a' ? $value : self::REDACTED;
        }

        // Arbitrary URL path segments can carry bearer credentials even when
        // nothing names them as secrets; keep the host for diagnosis and let the
        // query string fall through to the named-value redaction below.
        $value = preg_replace('~(https?://[^/\s?#]+)(?:/[^\s?#]*)?~i', '$1/' . self::REDACTED, $value) ?? '';

        $value = preg_replace(
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
            '$1 ' . self::REDACTED,
            $value,
        ) ?? '';

        $value = $this->redactEmailAddresses($value);

        $value = preg_replace(
            '/(?<=[?&;\s])((?:api[_-]?key|access[_-]?key|private[_-]?key|password|passwd|secret|session|signature|token)=)[^&;\s]+/i',
            '$1' . self::REDACTED,
            $value,
        ) ?? '';

        return preg_replace(
            '/\b((?:api[_-]?key|access[_-]?key|private[_-]?key|password|passwd|secret|session|signature|token)\s*[:=]\s*)[^\s,;&]+/i',
            '$1' . self::REDACTED,
            $value,
        ) ?? '';
    }

    /**
     * Any local-part@host address, however it is written: accented and non-Latin addresses,
     * internationalised and single-label hosts, quoted local parts, IP-literal and dotted-IP hosts,
     * JSON \uXXXX escapes, and the @ percent-encoded (%40, %2540), as an HTML entity, escaped with
     * a backslash, as \u0040, or as a full-width or small form. A host name must contain a letter, so a version
     * constraint such as core@1.0 stays readable. Every repetition is bounded or possessive and each
     * start is anchored by a lookbehind, so the scan stays linear, and the replacement contains no separator, so a second
     * pass changes nothing. Text that is not valid UTF-8 cannot be searched with the Unicode
     * pattern; it is withheld rather than sent unscanned.
     */
    private function redactEmailAddresses(string $value): string
    {
        $escape = '\\\\u[0-9A-Fa-f]{4}';
        // Repetitions are possessive: none needs to give characters back, and a backtracking
        // repetition over a long run exhausts the PCRE JIT stack. A local part therefore cannot
        // contain % or the @ escape, which would swallow an encoded separator.
        $localChar = '(?:[\p{L}\p{M}\p{N}._+-]|\\\\u(?!0040)[0-9A-Fa-f]{4})';
        $local = '(?:"[^"\\\\\r\n]{1,64}"|' . $localChar . '++)';
        $separator = '(?:@|%(?:25)?40|\x{FF20}|\x{FE6B}|&\#0*64;|&\#[xX]0*40;|&commat;|\\\\@|\\\\u0040)';
        $label = '(?:[\p{L}\p{M}\p{N}]|' . $escape . ')(?:[\p{L}\p{M}\p{N}-]|' . $escape . ')*+';
        // The first host character that is not a digit, dot or hyphen must be a letter.
        $namedHost = '(?=[\p{N}.-]*+(?:\p{L}|\p{M}|\\\\u))' . $label . '(?:\.' . $label . ')*+';
        $ipHost = '\[[0-9A-Za-z:.]{2,64}\]|\d{1,3}(?:\.\d{1,3}){3}(?!\d)';

        $redacted = preg_replace(
            '/(?<![\p{L}\p{M}\p{N}._+-])' . $local . $separator . '(?:' . $ipHost . '|' . $namedHost . ')/u',
            '[redacted-email]',
            $value,
        );

        if (! is_string($redacted)) {
            return self::REDACTED;
        }

        // A query value under an address-like key is the person's address whatever its encoding.
        return preg_replace(
            '/(?<=[?&;\s])((?:e-?mail(?:[_-]?address)?|mail)=)[^&;\s`\'"]+/i',
            '$1' . self::REDACTED,
            $redacted,
        ) ?? self::REDACTED;
    }

    private function isSensitivePath(string $path): bool
    {
        $normalizedPath = strtolower(str_replace(['-', '_', '.'], '', $path));

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (str_contains($normalizedPath, str_replace('_', '', $keyword))) {
                return true;
            }
        }

        return false;
    }
}
