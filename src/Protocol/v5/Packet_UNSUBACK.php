<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Unsubscribe acknowledgement packet
 */
class Packet_UNSUBACK
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 11;

    /**
     * Packet identifier
     *
     * @var int
     */
    private $packetIdentifier = 0;

    /**
     * Reason codes
     *
     * @var array<int>
     */
    public $reasonCodes = array();

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
    public $userProperties;

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.9.1  SUBACK Fixed Header
        // Empty.

        // 3.9.2  SUBACK Variable Header
        $this->encodedHeader = new DataEncoder();
        $this->encodedHeader->uint16($packetIdentifier, 1);

        // 3.11.2.1  UNSUBACK Properties
        $this->encodedProperties = new DataEncoder();

        // 3.11.2.1.2  Reason String
        // #discardable
        if ($this->reasonMessage !== null) {
            $this->markDiscardableProperty();
            $this->encodedProperties->byte(0x1f)->utf8string($this->reasonMessage);
        }

        // 3.11.2.1.3  User Property
        // #discardable
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.11.3  SUBACK Payload
        $this->encodedPayload = new DataEncoder();
        foreach ($this->reasonCodes as $reasonCode) {
            $this->encodedPayload->byte($reasonCode);
        }
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(DataDecoder $packet): void
    {
        // 3.11.1  UNSUBACK Fixed Header
        if (Protocol::$strictMode && ($this->packetFlags != 0)) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.11.2  UNSUBACK Variable Header
        $this->packetIdentifier =
            $this->assertIntegerValue($packet->uint16(), 1, null, 'Packet Identifier');

        // 3.11.2.1  UNSUBACK Properties
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.11.2.1.2  Reason String
            case 0x1f:
                $this->assertNullProperty($this->reasonMessage, 'Reason String');
                $this->reasonMessage = $packet->utf8string();
                break;

            // 3.11.2.1.3  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            default:
                throw new MalformedPacketException(sprintf("Invalid property code 0x%02x", $propId));
            }
        }
        $packet->chunkRelease();

        // 3.11.3  UNSUBACK Payload
        while ($packet->remaining()) {
            $this->reasonCodes[] = $packet->byte();
        }
    }
}
