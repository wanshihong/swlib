<?php

namespace Swlib\Admin\Fields;

class RangeField extends TextField
{
    public string $templateForm = 'fields/form/range.twig';

    public function __construct(string $field, string $label)
    {
        parent::__construct($field, $label);
        $this->addJsFile('/admin/js/field-range.js');
        $this->setFormFormat(static fn(mixed $value): array => self::normalizeDisplayValue($value));
        $this->setFormRequestAfter(static fn(mixed $value): string => self::normalizeFormValue($value));
    }

    public static function normalizeDisplayValue(mixed $value): array
    {
        if (is_array($value)) {
            $start = $value['start'] ?? $value[0] ?? '';
            $end = $value['end'] ?? $value[1] ?? '';
        } else {
            $parts = preg_split('/\s*,\s*/', trim((string)$value));
            $start = $parts[0] ?? '';
            $end = $parts[1] ?? '';
        }

        if (is_numeric($start) && is_numeric($end) && (float)$start > (float)$end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => is_numeric($start) ? self::normalizeScalar($start) : (string)$start,
            'end' => is_numeric($end) ? self::normalizeScalar($end) : (string)$end,
        ];
    }

    public static function normalizeFormValue(mixed $value): string
    {
        $display = self::normalizeDisplayValue($value);
        return trim((string)$display['start']) . ',' . trim((string)$display['end']);
    }

    private static function normalizeScalar(mixed $value): int|float
    {
        $string = trim((string)$value);
        return str_contains($string, '.') ? (float)$string : (int)$string;
    }
}
