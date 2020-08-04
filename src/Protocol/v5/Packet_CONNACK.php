<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\Packet;
use PhpMqtt\Protocol\Message;
use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_CONNECT
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

        // 3.2.2.1  Connect Acknowledge Flags
        $this->encodedHeader .= DataEncoder::byte($this->sessionPresent ? 1 : 0);

        // 3.2.2.3  CONNACK Properties
        $this->encodedProperties = "";

        // 3.2.2.3.1  Property Length
        // Calculated during packet assembly.

        // 3.2.2.3.2  Session Expiry Interval
        if ($this->sessionExpiration !== null) {
            $this->encodedProperties .= chr(0x11) . DataEncoder::uint32($this->sessionExpiration);
        }

        // 3.2.2.3.3  Receive Maximum
        if ($this->receiveMaximum !== null)
            $this->encodedProperties .= chr(0x21) . DataEncoder::uint16($this->receiveMaximum);

        // 3.2.2.3.4  Maximum QoS
        if ($this->maximumQoS !== null) {
            $this->encodedProperties .= chr(0x24) . DataEncoder::uint8($this->maximumQoS, 0, 1);
        }

        // 3.2.2.3.5  Retain Available
        if ($this->retainAvailable !== null) {
            $this->encodedProperties .= chr(0x25) . DataEncoder::byte($this->retainAvailable ? 1 : 0);
        }

        // 3.2.2.3.6  Maximum Packet Size
        if ($this->maximumPacketSize !== null) {
            $this->encodedProperties .= chr(0x27) . DataEncoder::uint32($this->maximumPacketSize);
        }

        // 3.2.2.3.6  Assigned Client Identifier
        if ($this->assignedClientIdentifier !== null) {
            $this->encodedProperties .= chr(0x12) . DataEncoder::utf8string($this->assignedClientIdentifier, 1, 23); // FIXME ??
        }

        // 3.2.2.3.8  Topic Alias Maximum
        if ($this->maximumTopicAliases !== null) {
            $this->maximumTopicAliases .= chr(0x22) . DataEncoder::uint16($this->maximumTopicAliases);
        }

        // 3.2.2.3.9  Reason String
        if ($this->reasonString !== null) {
            $this->markDiscardablePropertyStart();
            $this->encodedProperties .= chr(0x1f) . DataEncoder::utf8string($this->reasonString);
            $this->markDiscardablePropertyEnd();
        }

        // 3.2.2.3.10  User Property
        $this->markDiscardablePropertyStart();
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties .= chr(0x26) . DataEncoder::utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }
        $this->markDiscardablePropertyEnd();

        // 3.2.2.3.11  Wildcard Subscription Available
        if ($this->supportsWildcardSubscriptions !== null) {
            $this->encodedProperties .= chr(0x28) . DataEncoder::byte($this->supportsWildcardSubscriptions ? 1 : 0);
        }

        // 3.2.2.3.12  Subscription Identifiers Available
        if ($this->supportsSubscriptionIdentifiers !== null) {
            $this->encodedProperties .= chr(0x29) . DataEncoder::byte($this->supportsSubscriptionIdentifiers ? 1 : 0);
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
            $this->encodedProperties .= chr(0x15) . DataEncoder::utf8string($this->authenticationMethod);
        }

        // 3.2.2.3.18  Authentication Data
        if ($this->authenticationData !== null) {
            $this->encodedProperties .= chr(0x16) . DataEncoder::utf8string($this->authenticationData);
        }

        // 3.2.3  CONNACK Payload
        // This packet has no payload.
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $packet): void
    {
        // 3.2.1  CONNACK Fixed Header
        if ($this->packetFlags != 0) {
            throw new MalformedPacketException("Bad packet flags");
        }

        // 3.2.2  CONNACK Variable Header

        // 3.2.2.1  Connect Acknowledge Flags
        $ackFlags = DataDecoder::byte($packet);
        if ($ackFlags & ~0x01)
            throw MalformedPacketException(
                "Illegal value for Connection Acknowledge Flags",
                "MQTT-3.2.2-1");

        // 3.2.2.1.1  Session Present
        $this->sessionPresent = (bool) ($ackFlags & 0x01);

        // 3.2.2.2  Connect Reason Code
        $this->reasonCode = DataDecoder::byte($packet);

        // 3.2.2.3  CONNACK Properties

        // 3.2.2.3.1  Property Length
        $propsLength = DataDecoder::varint($packet);
        $props = DataDecoder::raw($packet, $propsLength);

        while (strlen($props) > 0) {
            $propId = DataDecoder::byte($props);
            switch ($propId) {

            // 3.2.2.3.2  Session Expiry Interval
            case 0x11:
                $this->assertNullProperty($this->sessionExpiration, 'Session Expiry Interval');
                $this->sessionExpiration = DataDecoder::uint32($props);
                break;

            // 3.2.2.3.3  Receive Maximum
            case 0x21:
                $this->assertNullProperty($this->receiveMaximum, 'Receive Maximum');
                $this->receiveMaximum = DataDecoder::uint16($props, 1);
                break;

            // 3.2.2.3.4  Maximum QoS
            case 0x24:
                $this->assertNullProperty($this->maximumQoS, 'Maximum QoS');
                $this->maximumQoS = DataDecoder::uint8($props, 0, 1);
                break;

            // 3.2.2.3.5  Retain Available
            case 0x25:
                $this->assertNullProperty($this->supportsRetain, 'Retain Available');
                $this->supportsRetain = (bool) Decoder::uint8($props, 0, 1);
                break;

            // 3.2.2.3.6  Maximum Packet Size
            case 0x27:
                $this->assertNullProperty($this->maximumPacketSize, 'Maximum Packet Size');
                $this->maximumPacketSize = DataDecoder::uint32($props);
                break;

            // 3.2.2.3.7  Assigned Client Identifier
            case 0x12:
                $this->assertNullProperty($this->assignedClientIdentifier, 'Assigned Client Identifier');
                $this->assignedClientIdentifier = DataDecoder::utf8string($props, 1, 23); // FIXME ?
                break;

            default:
                // How to handle?
            }
        }
    }
}
