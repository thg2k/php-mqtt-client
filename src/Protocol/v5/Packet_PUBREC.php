<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Publish received packet (QoS 2 delivery part 1)
 */
class Packet_PUBREC
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 5;

    /**
     * Packet identifier
     *
     * @var int
     */
    public $packetIdentifier = 0;

    /**
     * Reason code
     *
     * @var int
     */
    public $reasonCode = 0;

    /**
     * Reason message
     *
     * @var ?string
     */
    public $reasonMessage;

    /**
     * User properties
     *
     * @var array<array<string>>
     */
    public $userProperties = array();

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.5.1  PUBREC Fixed Header
        // Empty.

        // 3.5.2  PUBREC Variable Header
        $this->encodedHeader = new DataEncoder();
        $this->encodedHeader->uint16($this->packetIdentifier);

        // 3.5.2.1  PUBREC Reason Code
        $this->encodedHeader->byte($this->reasonCode);

        // 3.5.2.2  PUBREC Properties
        $this->encodedProperties = new DataEncoder();

        // 3.5.2.2.2  Reason String
        // #discardable
        if ($this->reasonMessage !== null) {
            $this->markDiscardableProperty();
            $this->encodedProperties->byte(0x1f)->utf8string($this->reasonMessage);
        }

        // 3.5.2.2.3  User Property
        // #discardable
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (String) ($userProperty[1] ?? null));
        }

        // 3.5.3  PUBREC Payload
        // Empty.
    }

    /**
     * @inheritdoc
     */
    public function decodeInternal(DataDecoder $packet): void
    {
        // 3.5.1  PUBREC Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.5.2  PUBREC Variable Header
        $this->packetIdentifier = $packet->uint16();

        // 3.5.2.1  PUBREC Reason Code
        $this->reasonCode = $packet->byte();

        // 3.5.2.2  PUBREC Properties
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.5.2.2.2  Reason String
            case 0x1f:
                $this->assertNullProperty($this->reasonMessage, 'Reason String');
                $this->reasonMessage = $packet->utf8string();
                break;

            // 3.5.2.2.3  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            default: // FIXME
            }
        }
        $packet->chunkRelease();

        // 3.5.3  PUBREC Payload
        // FIXME: assert empty payload?
    }
}
