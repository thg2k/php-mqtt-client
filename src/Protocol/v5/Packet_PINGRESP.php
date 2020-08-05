<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Ping response packet
 */
class Packet_PINGRESP
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 13;

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.13.1  PINGRESP Fixed Header
        // Empty.

        // 3.13.2  PINGRESP Variable Header
        // Empty.

        // 3.13.3  PINGRESP Payload
        // Empty.
    }

    /**
     * @inheritdoc
     */
    public function decodeInternal(DataDecoder $packet): void
    {
        // 3.13.1  PINGRESP Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.13.2  PINGRESP Variable Header
        // Empty.

        // 3.13.3  PINGRESP Payload
        // FIXME: assert empty?
    }
}
