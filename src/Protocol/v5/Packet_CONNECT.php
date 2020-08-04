<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;
use PhpMqtt\Protocol\Message;

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
     * Client ID
     *
     * @var string
     */
    public $clientId = "";

    /**
     * Session expiration
     *
     * @var int
     */
    public $sessionExpiration = null;

    /**
     * Receive Maximum
     *
     * @var int
     */
    public $receiveMaximum = null;

    /**
     * Maximum packet size (1-??)
     *
     * Default protocol value: null (unlimited)
     *
     * @var ?int
     */
    public $maximumPacketSize = null;

    /**
     * Topic alias maximum (0-65535)
     *
     * Default protocol value: 0
     *
     * @var ?int
     */
    public $topicAliasMaximum = null;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestResponseInformation = null;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestProblemInformation = null;

    /**
     * User properties
     *
     * @var array<string, string>
     */
    public $userProperties = array();

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): string
    {
        // 3.1.1  CONNECT Fixed Header
        // Nothing to do.

        // 3.1.2  CONNECT Variable Header
        $this->encodedHeader = new DataEncoder();

        // 3.1.2.1  Protocol Name
        $this->encodedHeader->utf8string("MQTT");

        // 3.1.2.2  Protocol Version
        $this->encodedHeader->byte(5);

        // 3.1.2.3  Connect Flags
        $connectionFlags = 0;
        if ($this->username !== null)
            $connectionFlags |= 0x80;
        if ($this->password !== null)
            $connectionFlags |= 0x40;
        if ($this->will) {
            $connectionFlags |= 0x04 |
                ($this->will->hasRetain() << 5) |
                ($this->will->getQoS() << 3);
        if ($this->cleanSession)
            $connectionFlags |= 0x02;
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
            $this->encodedProperties->byte(0x26)->utf8pair($name, $value);
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
        $this->encodedPayload->utf8string($this->clientId());

        // 3.1.3.2  Will Properties
        if ($this->will) {
            $encodedWillProperties = "";

            // 3.1.3.2.2  Will Delay Interval
            if ($this->willDelay !== null) {
                $encodedWIllProperties .= chr(0x18) . DataEncoder::uint32($this->willDelay);
            }

            // 3.1.3.2.3  Payload Format Indicator
            if ($this->will->getFormat() != 0) {
                // The format defaults to zero (binary), and the Message
                // implementation does not distinguish between zero and
                // undefined (by choice).
                // Differently from the rest of the implementation, this voids
                // the 1:1 relation between the binary representation and the logical one, but I consider
                // it reasonable and acceptable.
                // Also, even thought it would be nice to have 1:1 to self-test the protocol, the order of the
                // properties is free, so it wouldn't work anyway
                // FIXME: move this long essay to a more appropriate spot (Protocol class?)
                $encodedWillProperties .= chr(0x01) . DataEncoder::byte($this->will->getFormat());
            }

            // 3.1.3.2.4  Message Expiry Interval
            if ($this->willExpiration !== null) {
                $encodedWillProperties .= chr(0x02) . DataEncoder::uint32($this->will->expiration);
            }

            // 3.1.3.2.5  Content Type
            if ($this->will->contentType !== null) {
                $encodedWillProperties .= chr(0x03) . DataEncoder::utf8string($this->will->contentType);
            }

            // 3.1.3.2.6  Response Topic
            if ($this->will->responseTopic !== null) {
                $encodedWillProperties .= chr(0x08) . DataEncoder::utf8string($this->will->responseTopic);
            }

            // 3.1.3.2.7  Correlation Data
            if ($this->will->correlationData !== null) {
                $encodedWillProperties .= chr(0x09) . DataEncoder::binary($this->will->correlationData);
            }

            // 3.1.3.2.8  User Property
            foreach ($this->will->userProperties as $userProperty) {
                $encodedWillProperties .= chr(0x26) . DataEncoder::utf8pair(
                    (string) ($userProperty[0] ?? null),
                    (string) ($userProperty[1] ?? null));
            }
        }
        $this->encodedPayload .= DataEncoder::varint(strlen($encodedWillProperties)) . $encodedWillProperties;

        // 3.1.3.3  Will Topic
        if ($this->will) {
            $this->encodedPayload->utf8string($this->will->topic);
        }

        // 3.1.3.4  Will Payload
        if ($this->will) {
            $this->encodedPayload->binary($this->will->payload);
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
    protected function decodeInternal(string $data): void
    {
        /* FIXME to-be-implemented */
    }
}
