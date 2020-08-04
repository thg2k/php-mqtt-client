<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_CONNACK
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 2;

    /**
     * ...
     *
     * @var bool
     */
    public $sessionPresent = false;

    /**
     * ...
     *
     * @var int
     */
    public $reasonCode = 0;

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.2.2  CONNACK Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.2.2.1  Connect Acknowledge Flags
        $this->encodedHeader->byte($this->sessionPresent ? 1 : 0);

        // 3.2.2.3  CONNACK Properties
        $this->encodedProperties = new DataEncoder();

        // 3.2.2.3.2  Session Expiry Interval
        if ($this->sessionExpiration !== null) {
            $this->encodedProperties->byte(0x11)->uint32($this->sessionExpiration);
            // $this->encodedProperties .= chr(0x11) . DataEncoder::uint32($this->sessionExpiration);
        }

        // 3.2.2.3.3  Receive Maximum
        if ($this->receiveMaximum !== null) {
            $this->encodedProperties->byte(0x21)->uint16($this->receiveMaximum);
        }

        // 3.2.2.3.4  Maximum QoS
        if ($this->maximumQoS !== null) {
            $this->encodedProperties->byte(0x24)->uint8($this->maximumQoS, 0, 1);
        }

        // 3.2.2.3.5  Retain Available
        if ($this->retainAvailable !== null) {
            $this->encodedProperties->byte(0x25)->byte($this->retainAvailable ? 1 : 0);
        }

        // 3.2.2.3.6  Maximum Packet Size
        if ($this->maximumPacketSize !== null) {
            $this->encodedProperties->byte(0x27)->uint32($this->maximumPacketSize);
        }

        // 3.2.2.3.6  Assigned Client Identifier
        if ($this->assignedClientIdentifier !== null) {
            $this->encodedProperties->byte(0x12)->utf8string($this->assignedClientIdentifier, 1, 23); // FIXME ??
        }

        // 3.2.2.3.8  Topic Alias Maximum
        if ($this->maximumTopicAliases !== null) {
            $this->maximumTopicAliases->byte(0x22)->uint16($this->maximumTopicAliases);
        }

        // 3.2.2.3.11  Wildcard Subscription Available
        if ($this->supportsWildcardSubscriptions !== null) {
            $this->encodedProperties->byte(0x28)->byte($this->supportsWildcardSubscriptions ? 1 : 0);
        }

        // 3.2.2.3.12  Subscription Identifiers Available
        if ($this->supportsSubscriptionIdentifiers !== null) {
            $this->encodedProperties->byte(0x29)->byte($this->supportsSubscriptionIdentifiers ? 1 : 0);
        }

        // 3.2.2.3.13  Shared Subscription Available
        if ($this->supportsSharedSubscriptions !== null) {
            $this->encodedProperties .= chr(0x2a) . DataEncoder::byte($this->supportsSharedSubscriptions ? 1 : 0);
        }

        // 3.2.2.3.14  Server Keep Alive
        if ($this->keepAlive !== null) {
            $this->encodedProperties .= chr(0x13) . DataEncoder::uint16($this->keepAlive);
        }

        // 3.2.2.3.15  Response Information
        if ($this->responseInformation !== null) {
            $this->encodedProperties .= chr(0x1a) . DataEncoder::utf8string($this->responseInformation);
        }

        // 3.2.2.3.16  Server Reference
        if ($this->serverReference !== null) {
            $this->encodedProperties .= chr(0x1c) . DataEncoder::utf8string($this->serverReference);
        }

        // 3.2.2.3.17  Authentication Method
        if ($this->authenticationMethod !== null) {
            $this->encodedProperties->byte(0x15)->utf8string($this->authenticationMethod);
        }

        // 3.2.2.3.18  Authentication Data
        if ($this->authenticationData !== null) {
            $this->encodedProperties->byte(0x16)->utf8string($this->authenticationData);
        }

        // 3.2.2.3.9  Reason String
        // #discardable
        if ($this->reasonString !== null) {
            $this->markDiscardableProperty();
            $this->encodedProperties->byte(0x1f)->utf8string($this->reasonString);
        }

        // 3.2.2.3.10  User Property
        // #discardable
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            // We need to cast this one as it slips through PHP type checking.
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.2.3  CONNACK Payload
        // No payload.
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(DataDecoder $packet): void
    {
        // 3.2.1  CONNACK Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Bad packet flags");
        }

        // 3.2.2  CONNACK Variable Header

        // 3.2.2.1  Connect Acknowledge Flags
        $ackFlags = $packet->byte();
        if ($ackFlags & ~0x01)
            throw MalformedPacketException(
                "Illegal value for Connection Acknowledge Flags",
                "MQTT-3.2.2-1");

        // 3.2.2.1.1  Session Present
        $this->sessionPresent = (bool) ($ackFlags & 0x01);

        // 3.2.2.2  Connect Reason Code
        $this->reasonCode = $packet->byte();

        // 3.2.2.3  CONNACK Properties

        // 3.2.2.3.1  Property Length
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining() > 0) {
            $propId = $packet->byte($props);
            switch ($propId) {

            // 3.2.2.3.2  Session Expiry Interval
            case 0x11:
                $this->assertNullProperty($this->sessionExpiration, 'Session Expiry Interval');
                $this->sessionExpiration =
                    $packet->uint32();
                break;

            // 3.2.2.3.3  Receive Maximum
            case 0x21:
                $this->assertNullProperty($this->receiveMaximum, 'Receive Maximum');
                $this->receiveMaximum =
                    $this->assertIntegerValue($packet->uint16(), 1, null, 'Receive Maximum');
                break;

            // 3.2.2.3.4  Maximum QoS
            case 0x24:
                $this->assertNullProperty($this->maximumQoS, 'Maximum QoS');
                $this->maximumQoS =
                    $this->assertIntegerValue($packet->uint8(), 0, 1, 'Maximum QoS');
                break;

            // 3.2.2.3.5  Retain Available
            case 0x25:
                $this->assertNullProperty($this->supportsRetain, 'Retain Available');
                $this->supportsRetain =
                    (bool) $this->assertIntegerValue($packet->uint8(), 0, 1, 'Retain Available');
                break;

            // 3.2.2.3.6  Maximum Packet Size
            case 0x27:
                $this->assertNullProperty($this->maximumPacketSize, 'Maximum Packet Size');
                $this->maximumPacketSize =
                    $packet->uint32();
                break;

            // 3.2.2.3.7  Assigned Client Identifier
            case 0x12:
                $this->assertNullProperty($this->assignedClientIdentifier, 'Assigned Client Identifier');
                $this->assignedClientIdentifier =
                    $this->assertStringLength($packet->utf8string(), 1, 23, 'Assigned Client Identifier');
                break;

            default:
                // How to handle?
            }
        }
        $packet->chunkRelease();
    }
}
