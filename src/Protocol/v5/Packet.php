<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;
// use PhpMqtt\Protocol\Packet;

/**
 * MQTT v5.0 - ...
 */
abstract class Packet
{
    /**
     * ...
     *
     * @var int
     */
    protected $packetFlags = 0;

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
    private $optionalPropertiesOffsets = array();

    public static function decode(string $packet): Packet
    {
        $packet = new DataDecoder($packet);

        /* extract the first byte of the fixed header */

        $fixedHeader = DataDecoder::byte($packet);

        /* 
        $packet = new static();

    }

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
        $this->optionalPropertiesOffsets[] = strlen($this->encodedProperties);
    }

    /**
     * ...
     *
     * ...
     */
    public function encode(int $maxPacketSize = null): string
    {
        // Run the internal packet encoding, will prepare the following chunks:
        //  - encodedHeader: This represents the Variable Header, depends on the packet type
        //  - encodedProperties: If the packet has properties, this will be encoded
        $this->encodedHeader = "";
        $this->encodedProperties = null;
        $this->encodedPayload = "";
        $this->encodeInternal();

        // Calculate the packet size, shrink if necessary and possible.
        do {
            // Generate the current props length (0-4 bytes).
            $propsEncodedLength = ($this->encodedProperties !== null ?
                                   DataEncoder::varint(strlen($this->encodedProperties)) : "");

            // Calculate packet size minus the fixed header.
            $remainingSize =
                strlen($this->encodedHeader) +
                strlen($propsEncodedLength) +
                strlen($this->encodedProperties) +
                strlen($this->encodedPayload);

            // Encode now the packet size length, as it accounts itself in the packet size,
            // and it is variable in length which might affect the shrink process.
            $packetEncodedLength = DataEncoder::varint($remainingSize);

            // Finally, we can calculate the full packet size
            $packetSize = 1 + strlen($packetEncodedLength) + $remainingSize;

            // If the packet is oversized, chop off something discardable and retry.
            if (($packetSize + 2) > $maxPacketSize) {
                if (!$this->optionalPropertiesOffsets) {
                    // We ran out of stuff we can trash, we have to give up.
                    throw new \Exception("Packet too large");
                }
                $this->encodedProperties = substr($this->encodedProperties, 0, array_pop($this->optionalPropertiesOffsets));
            }
        } while (true);

        // Generate the first byte of the fixed header.
        assert(0 <= $this->packetFlags && $this->packetFlags <= 15);
        $fixedHeader = chr((static::TYPE << 4) | ($this->packetFlags & 0xf));

        $packet = $fixedHeader .
            $packetEncodedLength .
            $this->encodedHeader .
            $propsEncodedLength .
            $this->encodedProperties .
            $this->encodedPayload;

        /* free up the internal encoding buffers */
        $this->encodedHeader = null;
        $this->encodedProperties = null;
        $this->encodedPayload = null;

        return $packet;
    }
}
