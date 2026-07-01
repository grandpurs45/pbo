<?php

declare(strict_types=1);

final class StartupConfig
{
    /**
     * @return array{order:int|null,up:int|null,down:int|null,raw:string}
     */
    public static function parse(?string $startup): array
    {
        $result = [
            'order' => null,
            'up' => null,
            'down' => null,
            'raw' => $startup ?? '',
        ];

        if ($startup === null || trim($startup) === '') {
            return $result;
        }

        foreach (explode(',', $startup) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($value === null || !array_key_exists($key, $result)) {
                continue;
            }

            $result[$key] = is_numeric($value) ? (int) $value : null;
        }

        return $result;
    }

    public static function build(?int $order, ?int $up, ?int $down): string
    {
        $parts = [];

        if ($order !== null) {
            $parts[] = 'order=' . max(0, $order);
        }

        if ($up !== null) {
            $parts[] = 'up=' . max(0, $up);
        }

        if ($down !== null) {
            $parts[] = 'down=' . max(0, $down);
        }

        return implode(',', $parts);
    }
}
