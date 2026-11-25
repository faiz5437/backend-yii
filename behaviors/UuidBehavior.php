<?php

namespace app\behaviors;

use Ramsey\Uuid\Uuid;
use yii\base\Behavior;
use yii\db\ActiveRecord;

class UuidBehavior extends Behavior
{
    public string $attribute = 'id';

    public function events(): array
    {
        return [ActiveRecord::EVENT_BEFORE_INSERT => 'assignUuid'];
    }

    public function assignUuid(): void
    {
        if (empty($this->owner->{$this->attribute})) {
            $this->owner->{$this->attribute} = Uuid::uuid7()->toString();
        }
    }
}
