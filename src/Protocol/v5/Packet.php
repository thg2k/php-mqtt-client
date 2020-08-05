<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;
use PhpMqtt\Protocol\Packet as BasePacket;

/**
 * MQTT v5.0 - ...
 */
abstract class Packet
        extends BasePacket
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

    public static function decode(string &$buffer): Packet
    {
        if (strlen($buffer) < 2) {
            throw new InsufficientDataException(2);
        }

        // Peak at the first byte to determine the packet type
        $fixedHeader = ord(substr($buffer, 0, 1));

        // Decode the packet size
        $length     = 0;
        $multiplier = 1;
        $step       = 1;
        do {
            if ($step > 3) {
                throw new MalformedPacketException("Bad length??");
            }
            if ($step > strlen($buffer)) {
                throw new InsufficentDataException($step + 1);
            }
            $byte = ord(substr($buffer, $step, 1));
            $length += ($byte & 0x7f) * $multiplier;
            $multiplier *= 128;
            $step++;
        } while ($byte & 0x80);

        if ($length > strlen($buffer)) {
            throw new InsufficientDataException($length + $step);
        }

        // Decode the packet type and flags.
        $packetType = ($fixedHeader >> 4);
        $packetFlags = ($fixedHeader & 0x0f);

        switch ($packetType) {
        case 1:
            $packet = new Packet_CONNECT();
            break;

        case 2:
            $packet = new Packet_CONNACK();
            break;

        case 3:
            $packet = new Packet_PUBLISH();
            break;

        case 4:
            $packet = new Packet_PUBACK();
            break;

        case 12:
            $packet = new Packet_PINGREQ();
            break;

        case 13:
            $packet = new Packet_PINGRESP();
            break;

        default:
            die("Bad packet type $packetType");
        }

        $decoder = new DataDecoder($buffer);
        $decoder->raw(2);
        $packet->packetFlags = $packetFlags;

        $packet->decodeInternal($decoder);

        return $packet;
    }

    protected function assertIntegerValue(int $value, int $min = null, int $max = null, string $description = null): int
    {
        return $value;
    }

    protected function assertNullProperty($value, string $description = null): void
    {
        if ($value !== null) {
            throw new \Exception("Dupicated property '$description'");
        }
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
        $this->optionalPropertiesOffsets[] = $this->encodedProperties->size();
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
        // $this->encodedHeader = null;
        // $this->encodedProperties = null;
        // $this->encodedPayload = null;
        try {
            $this->encodeInternal();
        }
        catch (FIXME $e) { }

        // Calculate the packet size, shrink if necessary and possible.
        do {
            // Calculate the encoded properties length.
            $propsEncodedLength = new DataEncoder();
            if ($this->encodedProperties !== null) {
                $propsEncodedLength->varint($this->encodedProperties->size());
            }

            // Calculate the packet size minus the fixed header.
            $remainingSize =
                ($this->encodedHeader ? $this->encodedHeader->size() : 0) +
                $propsEncodedLength->size() +
                ($this->encodedProperties ? $this->encodedProperties->size() : 0) +
                ($this->encodedPayload ? $this->encodedPayload->size() : 0);

            $packetEncodedLength = new DataEncoder();
            $packetEncodedLength->varint($remainingSize);

            // Finally, we can calculate the full packet size.
            $packetSize = 1 + $packetEncodedLength->size() + $remainingSize;

            // If the packet is oversized, chop off something discardable and retry.
            if ($maxPacketSize && ($packetSize + 2) > $maxPacketSize) {
                if (!$this->optionalPropertiesOffsets) {
                    // We ran out of stuff we can trash, we have to give up.
                    throw new \Exception("Packet too large");
                }
                $this->encodedProperties = substr($this->encodedProperties, 0, array_pop($this->optionalPropertiesOffsets));
            } else {
                break;
            }
        } while (true);

        // Generate the first byte of the fixed header.
        assert(0 <= $this->packetFlags && $this->packetFlags <= 15);
        $fixedHeader = (static::TYPE << 4) | ($this->packetFlags & 0xf);


        $packet = chr($fixedHeader) .
            $packetEncodedLength->getBuffer() .
            ($this->encodedHeader ? $this->encodedHeader->getBuffer() : "") .
            $propsEncodedLength->getBuffer() .
            ($this->encodedProperties ? $this->encodedProperties->getBuffer() : "") .
            ($this->encodedPayload ? $this->encodedPayload->getBuffer() : "");

        /* free up the internal encoding buffers */
        $this->encodedHeader = null;
        $this->encodedProperties = null;
        $this->encodedPayload = null;

        return $packet;
    }
}
