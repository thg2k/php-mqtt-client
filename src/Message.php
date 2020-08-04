<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

/**
 * Logical representation of a message
 *
 * This class implements features available as of MQTT 5.0, but that might not
 * be able to be represented in lower versions of the protocol.
 *
 * Topic is not part of this class as it is not consider part of the
 * application, but rather the address where to send to.
 */
class Message
{
    /**
     * Message format - Indicates that the content is binary
     */
    const MESSAGE_FORMAT_BINARY = 0;

    /**
     * Message format - Indicates that the content is UTF-8
     */
    const MESSAGE_FORMAT_UTF8 = 1;

    /**
     * Content format indicator
     *
     * @var int
     */
    protected $contentFormat;

    /**
     * Message content type
     *
     * @var ?string
     */
    protected $contentType;

    /**
     * Message content
     *
     * @var string
     */
    protected $content;

    /**
     * Requester's response topic
     *
     * @var ?string
     */
    protected $responseTopic;

    /**
     * Requester's correlation data
     *
     * @var ?string
     */
    protected $correlationData;

    /**
     * User properties (available since MQTT v5.0)
     *
     * @var array<array{
     *    name: string,
     *    value: string}>
     */
    protected $userProperties = array();

    /**
     * ...
     *
     * ...
     */
    public function __construct(string $content)
    {
        $this->setContent($content);
    }

    public function getQoS(): int
    {
        return $this->qos;
    }

    /**
     * Returns the message format
     *
     * @return int ...
     */
    public function getFormat(): int
    {
    }

    /**
     * Sets the message format
     *
     * @param int $format ...
     * @return self Message instance
     */
    public function setFormat(int $format): self
    {
        if (($format < 0) || ($format > 1))
            throw new \InvalidArgumentException("Invalid format value '$format'");
        $this->format = $format;
        return $this;
    }

    /**
     * Gets the user properties
     *
     * @return array<array{
     *    name: string,
     *    value: string}> User properties
     */
    public function getUserProperties(): array
    {
        return $this->userProperties;
    }

    /**
     * Returns the user properties
     *
     * @return self Message instance
     */
    public function clearUserProperties(): self
    {
        $this->userProperties = null;
        return $this;
    }

    /**
     * Retrieves user properties by name
     *
     * @param string $name User property name
     * @return array<string> Matching user properties
     */
    public function getUserProperty(string $name): array
    {
        $retval = array();
        foreach ($this->userProperties as $userProperty) {
            if ($userProperty['name'] == $name) {
                $retval[] = $userProperty['value'];
            }
        }
        return $retval;
    }

    /**
     * Adds a user property
     *
     * @param string $name User property name
     * @param string $value User property value
     * @return self Message instance
     */
    public function addUserProperty(string $name, string $value): self
    {
      $this->userProperties[$name] = $value;
      return $this;
    }
}
