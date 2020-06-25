<?php
/*
 * Copyright (c) 2017 - 2020 Martin Meredith
 * Copyright (c) 2017 Stickee Technology Limited
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace QueueJitsu\Queue\Adapter;

use Predis\Client;
use QueueJitsu\Job\Job;

/**
 * Class RedisAdapter
 *
 * @package QueueJitsu\Queue\Adapter
 */
class RedisAdapter implements AdapterInterface
{
    /**
     * @var \Predis\Client $client
     */
    private $client;

    /**
     * RedisAdapter constructor.
     *
     * @param \Predis\Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * getAllQueueNames
     *
     * @return array
     */
    public function getAllQueueNames(): array
    {
        $queues = $this->client->smembers('queue');

        if (!is_array($queues)) {
            $queues = [];
        }

        return $queues;
    }

    /**
     * reserve
     *
     * @param string $queue
     *
     * @return null|\QueueJitsu\Job\Job
     */
    public function reserve(string $queue): ?Job
    {
        $item = $this->client->lpop(sprintf('queue:%s', $queue));

        if (!$item) {
            return null;
        }

        $payload = json_decode($item, true);

        $id = $payload['id'] ?? null;

        return new Job($payload['class'], $queue, $payload['args'], $id);
    }
}
