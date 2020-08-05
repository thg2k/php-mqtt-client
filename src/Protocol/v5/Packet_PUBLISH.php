<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Publish message packet
 */
class Packet_PUBLISH
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 3;

    /**
     * Duplicate packet
     *
     * @var bool
     */
    public $dup = false;

    /**
     * Quality of Service
     *
     * @var int
     */
    public $qos = 0;

    /**
     * Message retain
     *
     * @var bool
     */
    public $retain = false;

    /**
     * Packet identifier
     *
     * @var ?int
     */
    public $packetIdentifier;

    /**
     * Topic
     *
     * @var string
     */
    public $topic = "";

    /**
     * Message format
     *
     * @var ?int
     */
    public $messageFormat;

    /**
     * Message expiration
     *
     * @var ?int
     */
    public $messageExpiration;

    /**
     * Topic alias
     *
     * @var ?int
     */
    public $topicAlias;

    /**
     * Response topic
     *
     * @var ?string
     */
    public $responseTopic;

    /**
     * Correlation data
     *
     * @var ?string
     */
    public $correlationData;

    /**
     * User properties
     *
     * @var array<array<string>>
     */
    public $userProperties = array();

    /**
     * List of subscription identifiers
     *
     * @var array<int>
     */
    public $subscriptionIdentifiers = array();

    /**
     * Content type
     *
     * @var ?string
     */
    public $contentType;

    /**
     * Content
     *
     * @var string
     */
    public $content = "";

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): void
    {
        // 3.3.1  PUBLISH Fixed Header
        $this->packetFlags =
            ($this->dup ? 0x08 : 0) |
            (($this->qos & 0x03) << 1) |
            ($this->retain ? 0x01 : 0);

        // 3.3.2  PUBLISH Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.3.2.1  Topic Name
        $this->encodedHeader->utf8string($this->topic);

        // 3.3.2.2  Packet Identifier
        if ($this->qos) {
            $this->encodedHeader->uint16($this->packetIdentifier);
        }

        // 3.3.2.3  PUBLISH Properties
        $this->encodedProperties = new DataEncoder();

        // 3.3.2.3.2  Payload Format Indicator
        if ($this->messageFormat !== null) {
            $this->encodedProperties->byte(0x01)->byte($this->messageFormat, 0, 1);
        }

        // 3.3.2.3.3  Message Expiry Interval
        if ($this->messageExpiration !== null) {
            $this->encodedProperties->byte(0x02)->uint32($this->messageExpiration);
        }

        // 3.3.2.3.4  Topic Alias
        if ($this->topicAlias !== null) {
            $this->encodedProperties->byte(0x23)->uint16($this->topicAlias, 1);
        }

        // 3.3.2.3.5  Response Topic
        if ($this->responseTopic !== null) {
            $this->encodedProperties->byte(0x08)->utf8string($this->responseTopic);
        }

        // 3.3.2.3.6  Correlation Data
        if ($this->correlationData !== null) {
            $this->encodedProperties->byte(0x09)->binary($this->correlationData);
        }

        // 3.3.2.3.7  User Property
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.3.2.3.8  Subscription Identifier
        foreach ($this->subscriptionIdentifiers as $subscriptionIdentifier) {
            $this->encodedProperties->byte(0x0b)->varint($subscriptionIdentifier);
        }

        // 3.3.2.3.9  Content Type
        if ($this->contentType !== null) {
            $this->encodedProperties->byte(0x03)->utf8string($this->contentType);
        }

        // 3.3.3  PUBLISH Payload
        $this->encodedPayload = new DataEncoder();
        $this->encodedPayload->raw($this->content);
    }

    /**
     * @inheritdoc
     */
    public function decodeInternal(DataDecoder $packet): void
    {
        // 3.3.1  PUBLISH Fixed Header
        $this->retain = (bool) ($this->packetFlags & 0x01);
        $this->qos = (($this->packetFlags >> 1) & 0x03); // FIXME: check for value 3?
        $this->dup = (bool) ($this->packetFlags & 0x08);

        // 3.3.2  PUBLISH Variable Header

        // 3.3.2.1  Topic Name
        $this->topic = $packet->utf8string();

        // 3.3.2.2  Packet Identifier
        $this->packetIdentifier = $packet->uint16();

        // 3.3.2.3  PUBLISH Properties
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.3.2.3.2  Payload Format Indicator
            case 0x01:
                $this->assertNullProperty($this->messageFormat, 'Payload Format Indicator');
                $this->messageFormat =
                    $this->assertIntegerValue($packet->byte(), 0, 1, 'Payload Format Indicator');
                break;

            // 3.3.2.3.3  Message Expiry Interval
            case 0x02:
                $this->assertNullProperty($this->messageExpiration, 'Message Expiry Interval');
                $this->messageExpiration =
                    $packet->uint32();
                break;

            // 3.3.2.3.4  Topic Alias
            case 0x23:
                $this->assertNullProperty($this->topicAlias, 'Topic Alias');
                $this->topicAlias =
                    $this->assertIntegerValue($packet->uint16(), 1, null, 'Topic Alias');
                break;

            // 3.3.2.3.5  Response Topic
            case 0x08:
                $this->assertNullProperty($this->responseTopic, 'Response Topic');
                $this->responseTopic =
                    $packet->utf8string();
                break;

            // 3.3.2.3.6  Correlation Data
            case 0x09:
                $this->assertNullProperty($this->correlationData, 'Correlation Data');
                $this->correlationData =
                    $packet->binary();
                break;

            // 3.3.2.3.7  User Property
            case 0x26:
                $this->userProperties[] =
                    $packet->utf8pair();
                break;

            // 3.3.2.3.8  Subscription Identifier
            case 0x0b:
                $this->subscriptionIdentifiers[] =
                    $packet->varint();
                break;

            // 3.3.2.3.9  Content Type
            case 0x03:
                $this->assertNullProperty($this->contentType, 'Content Type');
                $this->contentType =
                    $packet->utf8string();
                break;

            default: // FIXME ?
                throw new MalformedPacketException(sprintf("Invalid property id '0x%02x'", $propId));
            }
        }
        $packet->chunkRelease();

        $this->content = $packet->raw(null);
    }
}
