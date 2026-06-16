<?php

namespace App\Support;

final class CargoPackagesLabelFormatter
{
    public static function countLabel(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        $mod100 = $count % 100;
        $mod10 = $count % 10;
        $suffix = ($mod100 > 10 && $mod100 < 20) || $mod10 === 0 || $mod10 >= 5
            ? 'мест'
            : ($mod10 === 1 ? 'место' : 'места');

        return $count.' '.$suffix;
    }

    public static function packTypeLabel(mixed $cargo): string
    {
        if (! is_object($cargo)) {
            return '';
        }

        foreach (['pack_type_label', 'packing_type'] as $field) {
            $value = trim((string) ($cargo->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
