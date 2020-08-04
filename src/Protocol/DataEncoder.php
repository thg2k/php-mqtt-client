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

    public function byte(int $value): self
    {
        if (($value < 0) || ($value > 255)) {
            throw new DataEncoderException("Cannot encode value");
        }

        $this->buffer .= chr($value);
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
    }

    public function uint32(int $value, int $min = null, int $max = null): string
    {
        if (($value < 0) || ($value < ($min ?? 0)) || ($value > ($max ?? 65535))) {
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

    public function binary(string $value): self
    {
        $length = strlen($value);
        if ($length > 65535)
            throw new ProtocolException("String too long ($length, max 65535)");

        $this->buffer .= pack("n", $length) . $value;
        return $this;
    }
 
    public function varint($value): string
    {
      $retval = "";
      do {
        $byte = $value % 128;
        $value >>= 7;
        if ($value > 0)
          $byte |= 0x80;

        $retval .= chr($byte);
      } while ($value > 0);

      return $retval;
    }
}
