<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Publish acknowledgement (QoS 1)
 */
class Packet_PUBACK
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 4;

    /**
     * Packet identifier
     *
     * @var int
     */
    public $packetIdentifier = 0;

    /**
     * Reason code
     *
     * @var ?int
     */
    public $reasonCode;

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
        // 3.4.1  PUBACK Fixed Header
        // Empty.

        // 3.4.2  PUBACK Variable Header
        $this->encodedHeader = new DataEncoder();
        $this->encodedHeader->uint16($this->packetIdentifier);

        // 3.4.2.1  PUBACK Reason Code
        $this->encodedHeader->byte($this->reasonCode);

        // 3.4.2.2  PUBACK Properties
        $this->encodedProperties = new DataEncoder();

        // 3.4.2.2.2  Reason String
        // #discardable
        if ($this->reasonMessage !== null) {
            $this->markDiscardableProperty();
            $this->encodedProperties->byte(0x1f)->utf8string($this->reasonMessage);
        }

        // 3.4.2.2.3  User Property
        // #discardable
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (String) ($userProperty[1] ?? null));
        }

        // 3.4.3  PUBACK Payload
        // Empty.
    }

    /**
     * @inheritdoc
     */
    public function decodeInternal(DataDecoder $packet): void
    {
        // 3.4.1  PUBACK Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.4.2  PUBACK Variable Header
        $this->packetIdentifier = $packet->uint16();

        // 3.4.2.1  PUBACK Reason Code
        if (!$packet->remaining()) {
            return;
        }
        $this->reasonCode = $packet->byte();

        // 3.4.2.2  PUBACK Properties
        if (!$packet->remaining()) {
            return;
        }
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.4.2.2.2  Reason String
            case 0x1f:
                $this->assertNullProperty($this->reasonMessage, 'Reason String');
                $this->reasonMessage = $packet->utf8string();
                break;

            // 3.4.2.2.3  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            default: // FIXME
            }
        }
        $packet->chunkRelease();

        // 3.4.3  PUBREC Payload
        // FIXME: assert empty payload?
    }

    public function __toString()
    {
        $parts = array();
        $parts[] = "pktId=" . $this->packetIdentifier;
        if ($this->reasonCode !== null) {
            $parts[] = sprintf("rCode=0x%02x", $this->packetIdentifier);
        }
        if ($this->reasonMessage !== null) {
            $parts[] = sprintf("rMessage='%s'", $this->reasonMessage);
        }
        foreach ($this->userProperties as $userProperty) {
            $parts[] = sprintf("userProp['%s']='%s'",
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }
        return "PUBACK" . (count($parts) ? " (" . implode(", ", $parts) . ")" : "");
    }
}
