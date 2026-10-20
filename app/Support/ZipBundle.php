<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads stored and deflate zip entries without the zip extension.
 */
final class ZipBundle
{
    /**
     * @return list<array{name: string, contents: string}>
     */
    public static function read(string $binary): array
    {
        $eocd = strrpos($binary, "PK\x05\x06");
        if ($eocd === false || strlen($binary) < $eocd + 22) {
            throw new RuntimeException('bad zip');
        }

        $total = self::u16($binary, $eocd + 10);
        $offset = self::u32($binary, $eocd + 16);
        if ($offset === 0xFFFFFFFF) {
            throw new RuntimeException('zip64');
        }

        $files = [];
        $pos = $offset;
        for ($i = 0; $i < $total; $i++) {
            if (substr($binary, $pos, 4) !== "PK\x01\x02") {
                throw new RuntimeException('bad directory');
            }
            $method = self::u16($binary, $pos + 10);
            $compressed = self::u32($binary, $pos + 20);
            $nameLength = self::u16($binary, $pos + 28);
            $extraLength = self::u16($binary, $pos + 30);
            $commentLength = self::u16($binary, $pos + 32);
            $localOffset = self::u32($binary, $pos + 42);
            $name = substr($binary, $pos + 46, $nameLength);
            $pos += 46 + $nameLength + $extraLength + $commentLength;

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if ($method !== 0 && $method !== 8) {
                throw new RuntimeException('unsupported method');
            }

            $localNameLength = self::u16($binary, $localOffset + 26);
            $localExtraLength = self::u16($binary, $localOffset + 28);
            $dataStart = $localOffset + 30 + $localNameLength + $localExtraLength;
            $data = substr($binary, $dataStart, $compressed);
            $contents = $method === 0 ? $data : gzinflate($data);
            if ($contents === false) {
                throw new RuntimeException('inflate failed');
            }
            $files[] = ['name' => $name, 'contents' => $contents];
        }

        return $files;
    }

    private static function u16(string $binary, int $offset): int
    {
        $value = unpack('v', substr($binary, $offset, 2));

        return (int) ($value[1] ?? 0);
    }

    private static function u32(string $binary, int $offset): int
    {
        $value = unpack('V', substr($binary, $offset, 4));

        return (int) ($value[1] ?? 0);
    }
}
