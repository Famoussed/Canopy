<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\InvalidStatusTransitionException;

/**
 * Stateful model'lerin kullandığı state machine trait'i.
 *
 * Model'in use ettiği Enum'da allowedTransitions() metodu olmalıdır.
 * Model'in 'status' attribute'ü bir backed enum'a cast edilmiş olmalıdır.
 */
trait HasStateMachine
{
    /**
     * Hedef duruma geçiş yapılabilir mi?
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $statusEnum = $this->status;
        $targetEnum = $statusEnum::from($newStatus);

        return $statusEnum->canTransitionTo($targetEnum);
    }

    /**
     * Durum geçişini gerçekleştirir.
     * Geçiş yasak ise InvalidStatusTransitionException fırlatır.
     *
     * NOT: Bu metot sadece durum değişikliğini yapar.
     * Event dispatch Service katmanında yapılır.
     */
    public function transitionTo(string $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException(
                currentStatus: $this->status->value,
                targetStatus: $newStatus,
                entity: static::class,
            );
        }

        $this->status = $this->status::from($newStatus);
        $this->save();
    }

    /**
     * Mevcut durumdan yapılabilecek geçişlerin listesini döner.
     *
     * @return string[]
     */
    public function availableTransitions(): array
    {
        $transitions = $this->status::allowedTransitions();

        return $transitions[$this->status->value] ?? [];
    }
}
