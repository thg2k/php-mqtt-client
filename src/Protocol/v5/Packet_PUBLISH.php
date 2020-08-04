<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_PUBLISH
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 1;

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
     * Topic
     *
     * @var string
     */
    public $topic = "";

    /**
     * Client username
     *
     * @var ?string = null;
     */
    public $username = null;

    /**
     * Client password
     *
     * @var ?string
     */
    public $password = null;

    /**
     * Clean session flag
     *
     * @var bool
     */
    public $cleanSession = false;

    /**
     * Last will message
     *
     * @var ?Message
     */
    public $will = null;

    /**
     * Keep alive interval
     *
     * @var ?int
     */
    public $keepAlive = null;

    /**
     * ...
     *
     * @var string
     */
    public $clientId = "";

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
        if ($this->qos & 0x03) {
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
            $this->encodedProperties .= chr(0x09) . DataEncoder::binary($this->message->correlationData);
        }

        // 3.3.2.3.7  User Property
        foreach ($this->message->getUserProperties() as $userProperty) {
            $this->encodedProperties .= chr(0x26) . DataEncode::utf8pair($userProperty['key'], $userProperty['value']);
        }

        // 3.3.2.3.8  Subscription Identifier
        foreach ($this->message->
        /* (byte 8) connect flags */
        $flags = 0;
        if ($this->username !== null)
            $flags |= 0x80;
        if ($this->password !== null)
            $flags |= 0x40;
        if ($this->will)
            $flags |= ($this->will->hasRetain() << 5) |
                      ($this->will->getQoS() << 3) |
                      0x04;
        if ($this->clean)
            $flags |= 0x02;
        $header .= chr($flags);

        /* (bytes 9-10) keep alive interval */
        $header .= DataEncoder::uint16($this->keepAlive);

        /* (payload) client id */
        // if ((strlen($this->clientId) < 1) ||
            // (strlen($this->clientId) > 32))
          // throw new ProtocolException("Client ID length must 1-32 bytes long");
        $pkt_payload .= DataEncoder::utf8string($this->clientId);

        /* (payload) username */
        if ($this->username !== null)
            $pkt_payload .= DataEncoder::utf8string($this->username);

        /* (payload) password */
        if ($this->password !== null)
            $pkt_payload .= DataEncoder::utf8string($this->password);

        /* (payload) will topic and message */
        if ($this->will !== null) {
            $pkt_payload .= DataEncoder::utf8string($this->will->getTopic());
            $pkt_payload .= DataEncoder::utf8string($this->will->getMessage());
        }

        foreach ($this->userProperties as $name => $value) {
            $pkt_props .= chr(0x26) . DataEncoder::utf8pair($name, $value);
        }

        /* 3.3.2.3.8  Subscription Identifier */
        if ($this->subscriptionId !== null) {
            assert($this->subscriptionId >= 1);
            assert($this->subscriptionId <= 268435455);
            $pkt_props .= chr(0x0b) . DataEncoder::varint($this->subscriptionId);
        }

        /* 3.3.2.3.9  Content Type */
        if ($this->contentType !== null) {
            // FIXME: is it legit to send empty string?
            $pkt_props .= chr(0x03) . DataEncoder::utf8string($this->contentType);
        }


        return $header . $payload
    }

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
                $this->assertNullProperty($this->formatIndicator, 'Payload Format Indicator');
                $this->formatIndicator =
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
                $this->correlationData = $packet->binary();
                break;

            // 3.3.2.3.7  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            // 3.3.2.3.8  Subscription Identifier
            case 0x0b:
                $this->assertNullProperty($this->subscriptionIdentifier, 'Subscription Identifier');
                $this->subscriptionIdentifier =
                    $packet->varint();
                break;

            // 3.3.2.3.9  Content Type
            case 0x03:
                $this->assertNullProperty($this->contentType, 'Content Type');
                $this->contentType =
                    $packet->utf8string();
                break;

            default: // FIXME ?
        }
        $packet->chunkRelease();

        $this->content = $packet->raw(null);
    }
}
