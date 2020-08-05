<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

/**
 * MQTT packet inspector
 */
class PacketInspector
{
    public static function packetDebugString(Packet $packet): string
    {
        $rpacket = new \ReflectionObject($packet);

        $buffer = "[MQTT v5 " . $rpacket->getShortName() . "]";
        foreach ($packet as $propname => $propvalue) {
            $buffer .= sprintf("\n%30s = %s", $propname, json_encode($propvalue, JSON_UNESCAPED_SLASHES));
        }

        return $buffer;
    }
}
