<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;
use PhpMqtt\Protocol\MalformedPacketException;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_CONNECT
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 1;

    /**
     * Clean session flag
     *
     * @var bool
     */
    public $cleanSession = false;

    /**
     * Keep alive interval
     *
     * @var ?int
     */
    public $keepAlive = null;

    /**
     * Client ID
     *
     * @var string
     */
    public $clientId = "";

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

    public $authenticationMethod;
    public $authenticationData;

    /**
     * Session expiration
     *
     * @var ?int
     */
    public $sessionExpiration;

    /**
     * Receive Maximum
     *
     * @var ?int
     */
    public $receiveMaximum;

    /**
     * Maximum packet size (1-??)
     *
     * Default protocol value: null (unlimited)
     *
     * @var ?int
     */
    public $maximumPacketSize;

    /**
     * Topic alias maximum (0-65535)
     *
     * Default protocol value: 0
     *
     * @var ?int
     */
    public $topicAliasMaximum;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestResponseInformation;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestProblemInformation;

    /**
     * Last will message topic
     *
     * @var ?string
     */
    public $willTopic;

    /**
     * Last will message content
     *
     * @var ?string
     */
    public $willContent;

    /**
     * Last will retain flag
     *
     * @var bool
     */
    public $willRetain = false;

    /**
     * Last will QoS
     *
     * @var int
     */
    public $willQoS = 0;

    /**
     * Last will delay interval in seconds
     *
     * @var ?int
     */
    public $willDelay;

    /**
     * Last will format
     *
     * @var ?int
     */
    public $willFormat;

    /**
     * Last will expiration
     *
     * @var ?int
     */
    public $willExpiration;

    /**
     * Last will content type
     *
     * @var ?string
     */
    public $willContentType;

    /**
     * Last will response topic
     *
     * @var ?string
     */
    public $willResponseTopic;

    /**
     * Last will correlation data
     *
     * @var ?string
     */
    public $willCorrelationData;

    public $willUserProperties = array();

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
        // 3.1.1  CONNECT Fixed Header
        // Empty.

        // 3.1.2  CONNECT Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.1.2.1  Protocol Name
        $this->encodedHeader->utf8string("MQTT");

        // 3.1.2.2  Protocol Version
        $this->encodedHeader->byte(5);

        // 3.1.2.3  Connect Flags
        $connectionFlags = 0;

        // 3.1.2.4  Clean Start
        if ($this->cleanSession) {
            $connectionFlags |= 0x02;
        }

        // 3.1.2.5  Will Flag
        if ($this->willTopic !== null) {
            $connectionFlags |= 0x04;

            // 3.1.2.6  Will QoS
            $connectionFlags |= $this->willQoS << 3;

            // 3.1.2.7  Will Retain
            $connectionFlags |= ($this->willRetain ? 0x20 : 0);
        }

        // 3.1.2.8  User Name Flag
        if ($this->username !== null) {
            $connectionFlags |= 0x80;
        }

        // 3.1.2.9  Password Flag
        if ($this->password !== null) {
            $connectionFlags |= 0x40;
        }

        // Pack the connection flags.
        $this->encodedHeader->byte($connectionFlags);

        // 3.1.2.10  Keep Alive
        $this->encodedHeader->uint16($this->keepAlive);

        // 3.1.2.11  CONNECT Properties
        $this->encodedProperties = new DataEncoder();

        // 3.1.2.11.2  Session Expiry Interval
        if ($this->sessionExpiration !== null) {
            $this->encodedProperties->byte(0x11)->uint32($this->sessionExpiration);
        }

        // 3.1.2.11.3  Receive Maximum
        if ($this->receiveMaximum !== null) {
            $this->encodedProperties->byte(0x21)->uint16($this->receiveMaximum);
        }

        // 3.1.2.11.4  Maximum Packet Size
        if ($this->maximumPacketSize !== null) {
            $this->encodedProperties->byte(0x27)->uint32($this->maximumPacketSize);
        }

        // 3.1.2.11.5  Topic Alias Maximum
        if ($this->topicAliasMaximum !== null) {
            $this->encodedProperties->byte(0x22)->uint16($this->topicAliasMaximum);
        }

        // 3.1.2.11.6  Request Response Information
        if ($this->requestResponseInformation !== null) {
            $this->encodedProperties->byte(0x19)->byte($this->requestResponseInformation ? 1 : 0);
        }

        // 3.1.2.11.7  Request Problem Information
        if ($this->requestProblemInformation !== null) {
            $this->encodedProperties->byte(0x17)->byte($this->requestProblemInformation ? 1 : 0);
        }

        // 3.1.2.11.8  User Property
        foreach ($this->userProperties as $userProperty) {
            $this->encodedProperties->byte(0x26)->utf8pair(
                (string) ($userProperty[0] ?? null),
                (string) ($userProperty[1] ?? null));
        }

        // 3.1.2.11.9  Authentication Method
        if ($this->authenticationMethod !== null) {
            $this->encodedProperties->byte(0x15)->utf8string($this->authenticationMethod);
        }

        // 3.1.2.11.10  Authentication Data
        if ($this->authenticationData !== null) {
            $this->encodedProperties->byte(0x16)->binary($this->authenticationData);
        }

        // 3.1.3  CONNECT Payload
        $this->encodedPayload = new DataEncoder();

        // 3.1.3.1  Client Identifier (ClientID)
        $this->encodedPayload->utf8string($this->clientId);

        // 3.1.3.2  Will Properties
        if ($this->willTopic !== null) {
            $encodedWillProperties = new DataEncoder();

            // 3.1.3.2.2  Will Delay Interval
            if ($this->willDelay !== null) {
                $encodedWillProperties->byte(0x18)->uint32($this->willDelay);
            }

            // 3.1.3.2.3  Payload Format Indicator
            if ($this->willFormat !== null) {
                // The format defaults to zero (binary), and the Message
                // implementation does not distinguish between zero and
                // undefined (by choice).
                // Differently from the rest of the implementation, this voids
                // the 1:1 relation between the binary representation and the logical one, but I consider
                // it reasonable and acceptable.
                // Also, even thought it would be nice to have 1:1 to self-test the protocol, the order of the
                // properties is free, so it wouldn't work anyway
                // FIXME: move this long essay to a more appropriate spot (Protocol class?)
                $encodedWillProperties->byte(0x01)->byte($this->willFormat);
            }

            // 3.1.3.2.4  Message Expiry Interval
            if ($this->willExpiration !== null) {
                $encodedWillProperties->byte(0x02)->uint32($this->willExpiration);
            }

            // 3.1.3.2.5  Content Type
            if ($this->willContentType !== null) {
                $encodedWillProperties->byte(0x03)->utf8string($this->willContentType);
            }

            // 3.1.3.2.6  Response Topic
            if ($this->willResponseTopic !== null) {
                $encodedWillProperties->byte(0x08)->utf8string($this->willResponseTopic);
            }

            // 3.1.3.2.7  Correlation Data
            if ($this->willCorrelationData !== null) {
                $encodedWillProperties->byte(0x09)->binary($this->willCorrelationData);
            }

            // 3.1.3.2.8  User Property
            foreach ($this->willUserProperties as $userProperty) {
                $encodedWillProperties->byte(0x26)->utf8pair(
                    (string) ($userProperty[0] ?? null),
                    (string) ($userProperty[1] ?? null));
            }

            $this->encodedPayload->attach($encodedWillProperties);
            unset($encodedWillProperties);
        }

        // 3.1.3.3  Will Topic
        if ($this->willTopic !== null) {
            $this->encodedPayload->utf8string($this->willTopic);
        }

        // 3.1.3.4  Will Payload
        if ($this->willTopic !== null) {
            $this->encodedPayload->binary($this->willContent);
        }

        // 3.1.3.5  User Name
        if ($this->username !== null) {
            $this->encodedPayload->utf8string($this->username);
        }

        // 3.1.3.6  Password
        if ($this->password !== null) {
            $this->encodedPayload->binary($this->password);
        }
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(DataDecoder $packet): void
    {
        // 3.1.1  CONNECT Fixed Header
        // FIXME ?

        // 3.1.2  CONNECT Variable Header

        // 3.1.2.1  Protocol Name
        $protocolName = $packet->utf8string();
        if ($protocolName !== "MQTT") {
            throw new MalformedPacketException("Invalid protocol banner");
        }

        // 3.1.2.2  Protocol Version
        $protocolVersion = $packet->byte();
        if ($protocolVersion != 5) {
            throw new MalformedPacketException("Invalid protocol version");
        }

        // 3.1.2.3  Connect Flags
        $connectFlags = $packet->byte();

        // 3.1.2.4  Clean Start
        $this->cleanSession = (bool) ($connectFlags & 0x02);

        // 3.1.2.5  Will Flag
        $hasWill = (bool) ($connectFlags & 0x04);

        // 3.1.2.6  Will QoS
        $this->willQoS = ($connectFlags >> 3) & 0x03;
        if ($this->willQoS && !$hasWill) {
            throw new MalformedPacketException("willQoS greater than 0 but hasWill not set");
        }
        if ($this->willQoS > 2) {
            throw new MalformedPacketException("Invalid willQoS value '" . $this->willQoS . "'");
        }

        // 3.1.2.8  User Name Flag
        $hasUsername = (bool) ($connectFlags & 0x80);

        // 3.1.2.9  Password Flag
        $hasPassword = (bool) ($connectFlags & 0x40);

        // 3.1.2.10  Keep Alive
        $this->keepAlive = $packet->uint16();

        // 3.1.2.11  CONNECT Properties
        $propsLength = $packet->varint();
        $packet->chunkSet($propsLength);

        while ($packet->remaining()) {
            $propId = $packet->byte();
            switch ($propId) {

            // 3.1.2.11.2  Session Expiry Interval
            case 0x11:
                $this->assertNullProperty($this->sessionExpiration, 'Session Expiry Interval');
                $this->sessionExpiration =
                    $packet->uint32();
                break;

            // 3.1.2.11.3  Receive Maximum
            case 0x21:
                $this->assertNullProperty($this->receiveMaximum, 'Receive Maximum');
                $this->receiveMaximum =
                    $packet->uint16();
                break;

            // 3.1.2.11.4  Maximum Packet Size
            case 0x27:
                $this->assertNullProperty($this->maximumPacketSize, 'Maximum Packet Size');
                $this->maximumPacketSize =
                    $packet->uint32();
                break;

            // 3.1.2.11.5  Topic Alias Maximum
            case 0x22:
                $this->assertNullProperty($this->topicAliasMaximum, 'Topic Alias Maximum');
                $this->topicAliasMaximum =
                    $packet->uint16();
                break;

            // 3.1.2.11.6  Request Response Information
            case 0x19:
                $this->assertNullProperty($this->requestResponseInformation, 'Request Response Information');
                $this->requestResponseInformation =
                    (bool) $this->assertIntegerValue($packet->byte(), 0, 1, 'Request Response Information');
                break;

            // 3.1.2.11.7  Request Problem Information
            case 0x17:
                $this->assertNullProperty($this->requestProblemInformation, 'Request Problem Information');
                $this->requestProblemInformation =
                    (bool) $this->assertIntegerValue($packet->byte(), 0, 1, 'Request Problem Information');
                break;

            // 3.1.2.11.8  User Property
            case 0x26:
                $this->userProperties[] = $packet->utf8pair();
                break;

            // 3.1.2.11.9  Authentication Method
            case 0x15:
                $this->assertNullProperty($this->authenticationMethod, 'Authentication Method');
                $this->authenticationMethod = $packet->utf8string();
                break;

            // 3.1.2.11.10  Authentication Data
            case 0x16:
                $this->assertNullProperty($this->authenticationData, 'Authentication Data');
                $this->authenticationData = $packet->binary();
                break;

            default:
                throw new MalformedPacketException(sprintf("Invalid property id '0x%02x'", $propId));
            }
        }
        $packet->chunkRelease();

        // 3.1.3  CONNECT Payload

        // 3.1.3.1  Client Identifier (ClientID)
        $this->clientId = $packet->utf8string();

        // 3.1.3.2  Will Properties
        if ($hasWill) {

            // 3.1.3.2.1  Property Length
            $propsLength = $packet->varint();
            $packet->chunkSet($propsLength);

            while ($packet->remaining()) {
                $propId = $packet->byte();
                switch ($propId) {

                // 3.1.3.2.2  Will Delay Interval
                case 0x18:
                    $this->assertNullProperty($this->willDelay, 'Will Delay Interval');
                    $this->willDelay = $packet->uint32();
                    break;

                // 3.1.3.2.3  Payload Format Indicator
                case 0x01:
                    $this->assertNullProperty($this->willFormat, 'Will Payload Format Indicator');
                    $this->willFormat = $packet->byte();
                    break;

                // 3.1.3.2.4  Message Expiry Interval
                case 0x02:
                    $this->assertNullProperty($this->willExpiration, 'Will Message Expiry Interval');
                    $this->willExpiration = $packet->uint32();
                    break;

                // 3.1.3.2.5  Content Type
                case 0x03:
                    $this->assertNullProperty($this->willContentType, 'Will Content Type');
                    $this->willContentType = $packet->utf8string();
                    break;

                // 3.1.3.2.6  Response Topic
                case 0x08:
                    $this->assertNullProperty($this->willResponseTopic, 'Will Response Topic');
                    $this->willResponseTopic = $packet->utf8string();
                    break;

                // 3.1.3.2.7  Correlation Data
                case 0x09:
                    $this->assertNullProperty($this->willCorrelationData, 'Will Correlation Data');
                    $this->willCorrelationData = $packet->utf8string();
                    break;

                // 3.1.3.2.8  User Property
                case 0x26:
                    $this->willUserProperties[] = $packet->utf8pair();
                    break;

                default:
                    throw new MalformedPacketException("Invalid will property id '$propId'");
                }
            }
            $packet->chunkRelease();

            // 3.1.3.3  Will Topic
            $this->willTopic = $packet->utf8string();

            // 3.1.3.4  Will Payload
            $this->willContent = $packet->binary();
        }

        // 3.1.3.5  User Name
        if ($hasUsername) {
            $this->username = $packet->utf8string();
        }

        // 3.1.3.6  Password
        if ($hasPassword) {
            $this->password = $packet->utf8string();
        }

        // FIXME: assert finished?
    }
}
