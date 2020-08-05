<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Ping request packet
 */
class Packet_PINGREQ
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 12;

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.12.1  PINGREQ Fixed Header
        // Empty.

        // 3.12.2  PINGREQ Variable Header
        // Empty.

        // 3.12.3  PINGREQ Payload
        // Empty.
    }

    /**
     * @inheritdoc
     */
    public function decodeInternal(DataDecoder $packet): void
    {
        // 3.12.1  PINGREQ Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.12.2  PINGREQ Variable Header
        // Empty.

        // 3.12.3  PINGREQ Payload
        // FIXME: assert empty?
    }
}
