<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RetryPolicy
{
    /**
     * @param int[] $delaysSeconds
     */
    public function __construct(
        private int $maxAttempts,
        private array $delaysSeconds,
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 10) {
            throw new InvalidArgumentException('Invalid retry max attempts.');
        }

        if ($delaysSeconds === []) {
            throw new InvalidArgumentException('Retry delays must not be empty.');
        }

        foreach ($delaysSeconds as $delaySeconds) {
            if (!is_int($delaySeconds) || $delaySeconds < 0 || $delaySeconds > 86400) {
                throw new InvalidArgumentException('Invalid retry delay.');
            }
        }
    }

    public static function fromConfig(int $maxAttempts, string $delays): self
    {
        $parts = array_filter(array_map('trim', explode(',', $delays)), static fn (string $part): bool => $part !== '');
        $delaysSeconds = [];

        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                throw new InvalidArgumentException('Invalid retry delay.');
            }

            $delaysSeconds[] = (int) $part;
        }

        return new self($maxAttempts, $delaysSeconds);
    }

    public function shouldRetry(int $attempts): bool
    {
        return $attempts > 0 && $attempts < $this->maxAttempts;
    }

    public function delaySeconds(int $attempts): int
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Invalid retry attempt.');
        }

        $index = min($attempts - 1, count($this->delaysSeconds) - 1);

        return $this->delaysSeconds[$index];
    }

    public function nextAvailableAt(int $attempts, DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->add(new DateInterval('PT' . $this->delaySeconds($attempts) . 'S'));
    }
}
