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
abstract class Packet
{
    /**
     * Encoded variable header
     *
     * @var ?string
     */
    protected $encodedHeader;

    /**
     * Encoded properties
     *
     * @var ?string
     */
    protected $encodedProperties;

    /**
     * Encoded payload
     *
     * @var ?string
     */
    protected $encodedPayload;

    /**
     * Stored optional property offsets
     *
     * @var array<int>
     */
    private $optionalPropertyOffsets = array();

    /**
     * Marks the upcoming property in the encoded packet as optional
     *
     * The MQTT 5.0 specification states that if the packet would exceed the
     * Maximum Packet Size, it shall not transmit certain properties, which we
     * thus deem as "discardable".
     *
     * We store only the current offset of the encoded properties to keep the
     * memory requirements low, and starts chopping it at these recorded
     * offsets in case it's necessary.
     */
    protected function markDiscardableProperty(): void
    {
        $this->optionalPropertyOffsets[] = strlen($this->encodedProperties);
    }

    /**
     * ...
     *
     * ...
     */
    protected function packetAssembly(int $maxPacketSize): string
    {
        do {
            // Generate the current props length (1-4 bytes).
            $propsEncodedLength = DataEncoder::varint(strlen($this->encodedProperties));

            // Calculate total packet size.
            $packetSize = 2 +
                strlen($this->encodedHeader) +
                strlen($propsEncodedLength) +
                strlen($this->encodedProperties) +
                strlen($this->encodedPayload);

            // If the packet is oversized, chop off something discardable.
            if ($packetSize > $maxPacketSize) {
                if (!$this->optionalPropertyOffsets) {
                    // We ran out of stuff to trash, so we have to give up.
                    throw new \Exception("Packet too large");
                }

                $this->encodedProperties = substr($this->encodedProperties, 0, array_pop($this->optionalPropertyOffsets));
            }
        } while (true);

        return $this->encodedHeader .
               $propsEncodedLength .
               $this->encodedProperties .
               $this->encodedPayload;
    }
}
