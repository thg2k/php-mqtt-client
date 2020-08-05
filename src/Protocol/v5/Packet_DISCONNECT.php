<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;

/**
 * MQTT v5.0 - Disconnect notification packet
 */
class Packet_DISCONNECT
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 14;

    /**
     * Reason code
     *
     * @var int
     */
    public $reasonCode = 0;

    /**
     * Server reference
     *
     * @var string
     */
    public $serverReference;

    /**
     * Session expiration in seconds
     *
     * @var ?int
     */
    public $sessionExpiration;

    /**
     * Reason string
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
    protected function encodeInternal(): string
    {
        // 3.14.1  DISCONNECT Fixed Header
        // Empty.

        // 3.14.2  DISCONNECT Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.14.2.1  Disconnect Reason Code
        $this->encodedHeader->byte($this->reasonCode);

        // 3.14.2.2  DISCONNECT Properties
        $this->encodedProperties = new DataEncoder();

        // 3.14.2.2.2  Session Expiry Interval
        if ($this->sessionExpiration !== null) {
            $this->encodedProperties->byte(0x11)->uint32($this->sessionExpiration);
        }

        // 3.14.2.2.5  Server Reference
        if ($this->serverReference !== null) {
            $this->encodedProperties->byte(0x1c)->utf8string($this->serverReference);
        }

        // 3.14.2.2.3  Reason String
        // #discardable
        if ($this->reasonString !== null) {
            $this->markDiscardableProperty();
            $this->encodedProperties->byte(0x1f)->utf8string($this->reasonString);
        }

        // 3.14.2.2.4  User Property
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.14.3  DISCONNECT Payload
        // Empty.
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $packet): void
    {
        // 3.14.1  DISCONNECT Fixed Header
        if (Protocol::$strictMode && ($this->packetFlags != 0)) {
            throw new MalformedPacketException("Invalid packet flags");
        }

        // 3.14.2  DISCONNECT Variable Header

        // 3.14.2.1  Disconnect Reason Code
        $this->reasonCode = $packet->byte();

        // 3.14.2.2  DISCONNECT Properties
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.14.2.2.2  Session Expiry Interval
            case 0x11:
                $this->assertNullProperty($this->sessionExpiration, 'Session Expiry Interval');
                $this->sessionExpiration = $packet->uint32();
                break;

            // 3.14.2.2.3  Reason String
            case 0x1f:
                $this->assertNullProperty($this->reasonMessage, 'Reason String');
                $this->reasonMessage = $packet->utf8string();
                break;

            // 3.14.2.2.4  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            // 3.14.2.2.5  Server Reference
            case 0x1c:
                $this->assertNullProperty($this->serverReference, 'Server Reference');
                $this->serverReference = $packet->utf8string();
                break;

            default:
                // FIXME: bad?
            }
        }

        // 3.14.3  DISCONNECT Payload
        // FIXME: assert empty?
    }
}
