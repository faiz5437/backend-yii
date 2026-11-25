<?php

namespace app\models;

class Outbox extends BaseModel
{
    public static function tableName(): string
    {
        return 'outbox';
    }

    public static function publish(string $eventType, string $aggregateType, string $aggregateId, array $payload): void
    {
        $event = new self([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
        ]);
        $event->save(false);
    }
}
