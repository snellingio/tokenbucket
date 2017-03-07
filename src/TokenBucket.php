<?php
declare (strict_types=1);

namespace Snelling;

class TokenBucket
{

    private $bucket = [];

    private $bucketKey;

    private $capacity;

    private $fillRate;

    private $redis;

    private $ttl;

    /**
     * TokenBucket constructor.
     * @param Redis  $redis
     * @param string $key
     * @param int    $capacity
     * @param int    $fillRate
     * @param float  $ttl
     */
    public function __construct(Redis $redis, string $key = '', int $capacity = 10, int $fillRate = 1, float $ttl = 1.0)
    {
        $this->bucketKey = 'tokenbucket' . $key;
        $this->capacity  = $capacity;
        $this->fillRate  = $fillRate;
        $this->ttl       = $ttl;

        $this->redis = $redis->instance();
    }

    /**
     * @param int $amount
     * @return bool
     */
    public function consume($amount = 1): bool
    {
        $this->fill();
        if ($amount <= $this->bucket['count']) {
            $this->bucket['count'] -= $amount;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    public function fill(): int
    {
        $this->bucket = $this->redis->get($this->bucketKey);

        if ($this->bucket) {
            $this->bucket = json_decode($this->bucket, true);
        }

        $now = time();

        if (is_array($this->bucket) === false || count($this->bucket) === 0 || $now >= $this->bucket['reset']) {
            $this->bucket = [
                'count' => $this->capacity,
                'time'  => $now,
                'reset' => $now + $this->ttl,
            ];
        } else {
            if ($this->bucket['count'] < $this->capacity) {
                $delta                 = $this->fillRate * ($now - $this->bucket['time']);
                $this->bucket['count'] = min($this->capacity, $this->bucket['count'] + $delta);
            }
            $this->bucket['time'] = $now;
        }

        $this->save();

        return $this->bucket['count'];
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        if (is_array($this->bucket) === false || count($this->bucket) === 0) {
            $this->fill();
        }

        return [
            'X-RateLimit-Limit'     => $this->capacity,
            'X-RateLimit-Remaining' => $this->bucket['count'],
            'X-RateLimit-Reset'     => $this->bucket['reset'],
        ];
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        $this->redis->setex($this->bucketKey, $this->ttl, json_encode($this->bucket));

        return true;
    }
}
