<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_SUBSCRIBE
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 14;

    /**
     * Packet identifier
     *
     * @var int
     */
    private $packetIdentifier;

    /**
     * Subscription identifier
     *
     * Range: 1 to 268,435,455
     * Default: empty
     *
     * @var int
     */
    private $subscriptionIdentifier;

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
        // SUBSCRIBE packet has fixed packet flags
        $this->packetFlags = 0b0010;

        // 3.8.2  SUBSCRIBE Variable Header
        $this->encodedHeader .= DataEncoder::uint32($this->packetIdentifier);

        // 3.8.2.1  SUBSCRIBE Properties

        // 3.8.2.1.2  Subscription Identifier
        if ($this->subscriptionIdentifier !== null) {
            $this->encodedProperties .= chr(0x0b) . DataEncoder::varint($this->subscriptionIdentifier);
        }

        // 3.8.2.1.3  User Property
        // $this->markDiscardableProperty(); // FIXME: forgotten in the specs or intentional?
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties .= chr(0x26) . DataEncoder::utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.8.3  SUBSCRIBE Payload
        foreach ($this->subscriptions as $subscription) {
            // Topic filter
            $this->encodedPayload .= DataEncoder::utf8pair($subscription->topicFilter);

            // 3.8.3.1  Subscription Options
            $this->encodedPayload .= DataEncoder::byte(
                ($subscription->maxQoS & 0x03) |
                ($subscription->noLocal ? 1 << 2 : 0) |
                ($subscription->retainAsPublished ? 1 << 3 : 0) |
                (($subscription->retainHandling & 0x03) << 4)
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $packet): void
    {
        $this->packetIdentifier = DataDecoder::uint16($packet);

        $propsLength = DataDecoder::varint($packet);
        $props = DataDecoder::raw($packet, $propsLength);

        while (strlen($props) > 0) {
            $propId = DataDecoder::byte($props);
            switch ($propId) {
            case 0x0b:
                if ($this->subscriptionIdentifier !== null)
                    throw new MalformedPacketException("Duplicated property 'subscriptionIdentifier'");
                $this->subscriptionIdentifier = DataDecoder::varint($props);
                if ($this->subscriptionIdentifier == 0)
                    throw new MalformedPacketException("Invalid value of property 'subscriptionIdentifier'");
                break;

            case 0x26:
                $this->_userProperties[] = DataDecoder::utf8pair($props);
                break;
            }
        }

        // 3.8.3  SUBSCRIBE Payload
        while (strlen($packet) > 0) {
            $subscription = new Subscription();

            $topicFilter = DataDecoder::utf8pair($packet);

            // 3.8.3.1  Subscription options
            $options = DataDecoder::byte($packet);
            $subscription->maxQoS            =        ($options & 0x03);
            $subscription->noLocal           = (bool) ($options & 1 << 2);
            $subscription->retainAsPublished = (bool) ($options & 1 << 3);
            $subscription->retainHandling    =        (($options >> 4) & 0x03);

            $this->subscriptions[] = $subscription;
        }
    }
}
