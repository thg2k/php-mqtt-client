<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Unsubscribe request packet
 */
class Packet_UNSUBSCRIBE
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 10;

    /**
     * Packet identifier
     *
     * @var int
     */
    private $packetIdentifier;

    /**
     * ...
     *
     * @var array<string>
     */
    public $topicFilters;

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
        // 3.10.1  UNSUBSCRIBE Fixed Header
        $this->packetFlags = 0b0010;

        // 3.10.2  UNSUBSCRIBE Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.10.2.1  UNSUBSCRIBE Properties
        $this->unsubscribeProperties = new DataEncoder();

        // 3.10.2.1.2  User Property
        // $this->markDiscardableProperty(); // FIXME: forgotten in the specs or intentional?
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties .= chr(0x26) . DataEncoder::utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.10.3  UNSUBSCRIBE Payload
        $this->encodedPayload = new DataEncoder();
        foreach ($this->topicFilters as $topicFilter) {
            $this->encodedPayload .= DataEncoder::utf8string($topicFilter);
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
