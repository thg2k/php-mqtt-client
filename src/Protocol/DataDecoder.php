<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

/**
 * ...
 */
class DataDecoder
{
    /**
     * Buffer data
     *
     * @var string
     */
    private $buffer;

    /**
     * Buffer size
     *
     * @var int
     */
    private $size;

    /**
     * Buffer pointer
     *
     * @var int
     */
    private $ptr;

    /**
     * ...
     *
     * @param string $buffer
     */
    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;
        $this->size = strlen($buffer);
        $this->ptr = 0;
    }

    /**
     * Creates a boundary in the current buffer of the given size
     *
     * @param int $limit ...
     */
    public function chunkSet(int $limit): void
    {
        if ((strlen($this->buffer) - $this->ptr) < $limit) {
            throw new BufferOverrunException("chunk:$limit");
        }

        // Set as new buffer size the chunk limit plus the current position.
        $this->size = $this->ptr + $limit;
    }

    /**
     * Removes the boundary created in the current buffer
     *
     * @return int ...
     */
    public function chunkRelease(): int
    {
        // Skip any unconsumed data, as per chunk definition
        $discardedBytes = $this->size - $this->ptr;
        $this->ptr = $this->size;

        // Restore the size to the full buffer
        $this->size = strlen($this->buffer);

        return $discardedBytes;
    }

    /**
     * ...
     *
     * ...
     */
    public function remaining(): int
    {
        return ($this->size - $this->ptr);
    }

    /**
     * ...
     *
     * ...
     */
    public function raw(int $bytes = null): string
    {
        if ($bytes === null) {
            return substr($this->buffer, $this->ptr, ($this->size - $this->ptr));
            $this->ptr = $this->size;
        }

        if (($this->size - $this->ptr) < $bytes) {
            throw new BufferOverrunException("raw:$bytes");
        }

        $value = substr($this->buffer, $this->ptr, $bytes);
        $this->ptr += $bytes;

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function byte(): int
    {
        if (($this->size - $this->ptr) < 1) {
            throw new BufferOverrunException("byte");
        }

        $value = ord(substr($this->buffer, $this->ptr++, 1));

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function varint(): int
    {
        $value      = 0;
        $multiplier = 1;
        $step       = 1;
        do {
            if ($step > 3) {
                throw new InvalidDataException("Malformed varint encoding");
            }
            if (($this->size - $this->ptr) < 1) {
                throw new BufferOverrunException("varint:$step");
            }
            $byte = $this->byte();
            $value += ($byte & 0x7f) * $multiplier;
            $multiplier *= 128;
            $step++;
        } while ($byte & 0x80);

        // if (($min !== null) && ($value < $min)) {
            // throw new DataAssertionException("Invalid value '$value', min is $min");
        // }

        // if (($max !== null) && ($value > $max)) {
            // throw new DataAssertionException("Invalid value '$value', max is $max");
        // }

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function uint8(): int
    {
        if (($this->size - $this->ptr) < 1) {
            throw new BufferOverrunException("uint8");
        }

        $value = ord(substr($this->buffer, $this->ptr++, 1));

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function uint16(): int
    {
        if (($this->size - $this->ptr) < 2) {
            throw new BufferOverrunException("uint16");
        }

        list(, $value) = unpack("n", $this->buffer, $this->ptr);
        $this->ptr += 2;

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function uint32(): int
    {
        if (($this->size - $this->ptr) < 4) {
            throw new BufferOverrunException("uint32");
        }

        list(, $value) = unpack("N", $this->buffer, $this->ptr);
        $this->ptr += 4;

        return $value;
    }

    /**
     * Extracts a binary encoded value from the buffer
     *
     * @return string ...
     */
    public function binary(): string
    {
        if (($this->size - $this->ptr) < 2) {
            throw new BufferOverrunException("utf8string:prefix");
        }

        list(, $length) = unpack("n", $this->buffer, $this->ptr);
        $this->ptr += 2;

        if (($this->size - $this->ptr) < $length) {
            throw new BufferOverrunException("utf8string:$length");
        }

        $value = substr($this->buffer, $this->ptr, $length);
        $this->ptr += $length;

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function utf8string(): string
    {
        $value = $this->binary();

        // FIXME: profiling tests required: should we mb_check_encoding or not?

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function utf8pair(): array
    {
        $value = array($this->utf8string(), $this->utf8string());

        return $value;
    }

    /**
     * ...
     *
     * ...
     */
    public function __toString()
    {
        return "DataDecoder [buffer " .
            strlen($buffer) . ", position " .
            $this->ptr . ", remaining " .
            (strlen($this->_buffer) - $this->ptr) . "]";
    }
}
