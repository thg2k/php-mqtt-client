<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

class DataDecoder
{
    // FIXME: to implement


    public static function byte(&$buffer): int
    {
        if (strlen($buffer) < 1)
            throw new MalformedPacketException("Out of bound");

        $value = ord(substr($buffer, 0, 1));
        $buffer = substr($buffer, 1);

        return $value;
    }

    public static function uint16(&$buffer): int
    {
    }

    public static function uint32(&$buffer): int
    {
        if (strlen($buffer) < 4)
            throw new MalformedPacketException("Packet incomplete: Cannot read 4 bytes");

        list(, $value) = unpack("N", $buffer);
        $buffer = substr($buffer, 4);

        return $value;
    }

    public static function utf8string(&$buffer): string
    {
        if (strlen($buffer) < 2)
            throw new DataException("Cannot read 4 bytes");

        list(, $size) = unpack("n", $buffer);

        if (strlen($buffer) < 2 + $size)
            throw new DataException("Cannot read 4 bytes");

        $value = substr($buffer, 2, $size);
        $buffer = substr($buffer, 2 + $size);

        return $value;
    }

    public static function utf8pair(&$buffer): array
    {
        $value = array(
            self::utf8string($buffer),
            self::utf8string($buffer)
        );

        return $value;
    }
}
