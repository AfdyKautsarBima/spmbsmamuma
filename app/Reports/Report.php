<?php

namespace App\Reports;

/**
 * Satu laporan: judul, beberapa bagian (tabel), dan catatan.
 * Sel boleh berupa nilai biasa, atau hasil Report::rp(), Report::num(), Report::link().
 * Tipe kolom: text | int | rp.
 */
class Report
{
    /** @var list<array{title: ?string, columns: list<array{label: string, type: string}>, rows: list<list<mixed>>, footer: ?list<mixed>}> */
    public array $sections = [];

    /** @param list<string> $notes */
    public function __construct(public string $title, public string $subtitle = '', public array $notes = [])
    {
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  list<list<mixed>>  $rows
     * @param  list<mixed>|null  $footer
     */
    public function section(?string $title, array $columns, array $rows, ?array $footer = null): static
    {
        $this->sections[] = ['title' => $title, 'columns' => $columns, 'rows' => $rows, 'footer' => $footer];

        return $this;
    }

    public static function col(string $label, string $type = 'text'): array
    {
        return ['label' => $label, 'type' => $type];
    }

    public static function rp(int $amount): array
    {
        return ['v' => $amount, 't' => 'rp'];
    }

    public static function num(int $number): array
    {
        return ['v' => $number, 't' => 'int'];
    }

    public static function link(string|int $text, string $url, string $type = 'text'): array
    {
        return ['v' => $text, 't' => $type, 'url' => $url];
    }

    /** Samakan bentuk sel: ['v' => nilai, 't' => tipe, 'url' => ?tautan]. */
    public static function cell(mixed $value, string $columnType = 'text'): array
    {
        if (is_array($value)) {
            return $value + ['t' => $columnType, 'url' => null];
        }

        return ['v' => $value, 't' => $columnType, 'url' => null];
    }

    /** Teks siap tampil di layar atau cetakan. */
    public static function display(array $cell): string
    {
        $v = $cell['v'] ?? null;

        if ($v === null || $v === '') {
            return '';
        }

        return match ($cell['t'] ?? 'text') {
            'rp'    => 'Rp '.number_format((int) $v, 0, ',', '.'),
            'int'   => number_format((int) $v, 0, ',', '.'),
            default => (string) $v,
        };
    }

    public static function isNumeric(array $cell): bool
    {
        return in_array($cell['t'] ?? 'text', ['rp', 'int'], true) && is_numeric($cell['v'] ?? null);
    }
}
