<?php declare(strict_types=1);

namespace PhpMqtt\Protocol;

class ProtocolException
        extends \Exception
{

}

final class DataEncoder
{
    public $buffer;

    public function __construct()
    {
        $this->buffer = "";
    }

    public function size(): int
    {
        return strlen($this->buffer);
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function byte(int $value): self
    {
        if (($value < 0) || ($value > 255)) {
            throw new DataEncoderException("Cannot encode value");
        }

        $this->buffer .= chr($value);
        return $this;
    }

    public function raw(string $data): self
    {
        $this->buffer .= $data;
        return $this;
    }

    public function uint8(int $value, int $min = null, int $max = null): self
    {
        if (($value < 0) || ($value < ($min ?? 0)) || ($value > ($max ?? 255))) {
            throw new DataEncoderException("Value out of range?");
        }

        $this->buffer .= chr($value);
        return $this;
    }

    public function uint16(int $value, int $min = null, int $max = null): self
    {
        if (($value < 0) || ($value < ($min ?? 0)) || ($value > ($max ?? 65535))) {
            throw new DataEncoderException("Value out of range?");
        }

        $this->buffer .= pack("n", $value);
        return $this;
    }

    public function uint32(int $value, int $min = null, int $max = null): self
    {
        if (($value < 0) || ($value < ($min ?? 0))) {
            throw new DataEncoderException("Value out of range?");
        }

        $this->buffer .= pack("N", $value);
        return $this;
    }

    public function utf8string(string $value): self
    {
      $length = strlen($value);
      if ($length > 65535)
        throw new ProtocolException("String too long ($length, max 65535)");

      $this->buffer .= pack("n", $length) . $value;
      return $this;
    }

    public function utf8pair(string $value1, string $value2): self
    {
        $this->utf8string($value1);
        $this->utf8string($value2);
        return $this;
    }

    public function binary(string $value): self
    {
        $length = strlen($value);
        if ($length > 65535)
            throw new ProtocolException("String too long ($length, max 65535)");

        $this->buffer .= pack("n", $length) . $value;
        return $this;
    }
 
    public function varint(int $value): self
    {
        $retval = "";
        do {
          $byte = $value % 128;
          $value >>= 7;
          if ($value > 0)
            $byte |= 0x80;

          $retval .= chr($byte);
        } while ($value > 0);

        $this->buffer .= $retval;

        return $this;
    }

    public function attach(DataEncoder $data = null): self
    {
        if ($data) {
            $this->varint(strlen($data->buffer));
            $this->buffer .= $data->buffer;
        }
        return $this;
    }

    // public static function varintsize(int $value): int
    // {
        // $size = 0;
        // do {
            // $value >>= 7;
            // $size++;
        // }
        // while ($value > 0);
    // }
}
