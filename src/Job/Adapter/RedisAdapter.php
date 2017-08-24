<?php
/**
 * Copyright (c) 2017 Martin Meredith
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

namespace QueueJitsu\Job\Adapter;

use Predis\Client;
use QueueJitsu\Job\Job;
use QueueJitsu\Job\JobManager;
use Throwable;

/**
 * Class RedisAdapter
 *
 * @package QueueJitsu\Job\Adapter
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
     * createFailure
     *
     * @param array $payload
     * @param \Throwable $exception
     * @param string $worker
     * @param string $queue
     */
    public function createFailure(array $payload, Throwable $exception, string $worker, string $queue): void
    {
        $data = [];
        $data['failed_at'] = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data['payload'] = $payload;
        $data['exception'] = get_class($exception);
        $data['error'] = $exception->getMessage();
        $data['backtrace'] = explode("\n", $exception->getTraceAsString());
        $data['worker'] = $worker;
        $data['queue'] = $queue;

        $this->client->setex(
            sprintf('failed:%s', $payload['id']),
            3600 * 14,
            json_encode($data)
        );
    }

    /**
     * updateStatus
     *
     * @param \QueueJitsu\Job\Job $job
     * @param int $status
     */
    public function updateStatus(Job $job, int $status): void
    {
        $id = sprintf('job:%s:status', $job->getId());
        $packet = [
            'status' => $status,
            'updated' => time(),
        ];

        $this->client->set($id, json_encode($packet));

        if (in_array($status, JobManager::COMPLETED_STATUSES)) {
            $this->client->expire($id, 86400);
        }
    }

    /**
     * enqueue
     *
     * @param \QueueJitsu\Job\Job $job
     */
    public function enqueue(Job $job): void
    {
        $queue = $job->getQueue();

        $this->client->sadd('queue', [$queue]);

        $this->client->rpush(
            sprintf('queue:%s', $queue),
            [
                json_encode($job->getPayload()),
            ]
        );
    }
}
