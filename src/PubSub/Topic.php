<?php

namespace Rabbit\Dapr\PubSub;

use Rabbit\Dapr\Client\DaprClient;
use Rabbit\Dapr\exceptions\DaprException;
use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerInterface;

/**
 * Class Topic
 * @package Rabbit\Dapr\PubSub
 */
class Topic
{
    public function __construct(
        private string $pubsub,
        private string $topic,
        private DaprClient $client,
        #[Deprecated(since: '1.2.0')] private LoggerInterface|null $logger = null
    ) {
    }

    /**
     * Publish an event to the topic
     *
     * @param CloudEvent|mixed $event The event to publish
     * @param array|null $metadata Additional metadata to pass to the component
     * @param string $content_type The header to include in the publish request. Ignored when $event is a CloudEvent
     *
     * @return bool Whether the event was successfully dispatched
     */
    public function publish(mixed $event, ?array $metadata = null, string $content_type = 'application/json'): bool
    {
        if ($this->logger !== null) {
            $this->logger->debug('Sending {event} to {topic}', ['event' => $event, 'topic' => $this->topic]);
        } elseif ($this->client instanceof DaprClient) {
            $this->client->logger->debug('Sending {event} to {topic}', ['event' => $event, 'topic' => $this->topic]);
        }
        if ($event instanceof CloudEvent) {
            $content_type = 'application/cloudevents+json';
            $this->client->extra_headers = [
                'Content-Type: application/cloudevents+json',
            ];

            $event = $event->to_array();
        }

        try {
            $this->client->publishEvent($this->pubsub, $this->topic, $event, $metadata ?? [], $content_type);
            return true;
        } catch (DaprException) {
            return false;
        }

        return false;
    }
}
