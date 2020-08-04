<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;

/**
 * MQTT v5.0 - Client request to connect to Server
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
    public $reasonCode = 0x00;

    /**
     * Session expiration in seconds
     *
     * @var ?int
     */
    public $sessionExpiryInterval;

    /**
     * Reason string
     *
     * @var ?string
     */
    public $reasonString;

    /**
     * User properties
     *
     * @var array<array<string>>
     */
    public $userProperties;

    /**
     * Server reference
     *
     * @var string
     */
    public $serverReference;

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): string
    {
        /// 3.14.2  DISCONNECT Variable Header
        // $this->_encoded_header = "";

        // 3.14.2.1  Disconnect Reason Code
        $this->encodedHeader .= DataEncoder::byte($this->reasonCode);

        // 3.14.2.2  DISCONNECT Properties
        // $this->_encoded_props = "";

        // 3.14.2.2.2  Session Expiry Interval
        if ($this->sessionExpiryInterval !== null) {
            $this->encodedProperties .= chr(0x11) . DataEncoder::uint32($this->sessionExpiryInterval);
        }

        // 3.14.2.2.3  Reason String
        $this->markDiscardableProperty();
        if ($this->reasonString !== null) {
            $props .= chr(0x1f) . DataEncoder::utf8string($this->reasonString);
        }

        // 3.14.2.2.4  User Property
        $this->markDiscardableProperty();
        foreach ($this->userProperties as $userProperty) {
            $props .= chr(0x26) . DataEncoder::utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.14.2.2.5  Server Reference
        $this->markDiscardableProperty($props);
        if ($this->serverReference !== null) {
            $props .= chr(0x1c) . DataEncoder::utf8string($this->serverReference);
        }
        $this->protocol->assemblyPacketOptionalProps($props);

        // 3.14.3  DISCONNECT Payload
        $payload = "";

        return $this->packetAssembly($header, $props, $payload);
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $packet): void
    {
        $this->reasonCode = DataDecoder::byte($packet);

        $propsLength = DataDecoder::varint($packet);
        $props = DataDecoder::raw($packet, $propsLength);

        while (strlen($props) > 0) {
            $propId = DataDecoder::byte($props);
            switch ($propId) {
            case 0x11:
                if ($this->sessionExpiryInterval !== null)
                    throw new MalformedPacketException("Duplicated property 'sessionExpiryInterval'");
                $this->sessionExpiryInterval = DataDecoder::uint32($packet);
                break;

            case 0x1f:
                if ($this->reasonString !== null)
                    throw new MalformedPacketException("Duplicated property 'reasonString'");
                $this->reasonString = DataDecoder::utf8string($packet);
                break;

            case 0x26:
                $this->_userProperties[] = DataDecoder::utf8pair($packet);
                break;
            }
        }
    }
}
