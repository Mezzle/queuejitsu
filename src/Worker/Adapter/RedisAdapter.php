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

namespace QueueJitsu\Worker\Adapter;

use Predis\Client;
use QueueJitsu\Job\Job;
use QueueJitsu\Worker\Worker;

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
     * getAllWorkerIds
     *
     * @return array
     */
    public function getAllWorkerIds(): array
    {
        $workers = $this->client->smembers('workers');

        if (!is_array($workers)) {
            $workers = [];
        }

        return $workers;
    }

    /**
     * reestablishConnection
     *
     */
    public function reestablishConnection(): void
    {
        $this->client->connect();
    }

    /**
     * registerWorker
     *
     * @param $worker
     */
    public function registerWorker($worker): void
    {
        $this->client->sadd('workers', $worker);
        $this->client->set(
            sprintf('worker:%s:started', $worker),
            strftime('%a %b %d %H:%M:%S %Z %Y')
        );
    }

    /**
     * setWorkerWorkingOn
     *
     * @param \QueueJitsu\Worker\Worker $worker
     * @param \QueueJitsu\Job\Job $job
     */
    public function setWorkerWorkingOn(Worker $worker, Job $job)
    {
        $data = [
            'queue' => $job->getQueue(),
            'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
            'payload' => $job->getPayload(),
        ];

        $key = sprintf('worker:%s', $worker->getId());

        $this->client->set($key, json_encode($data));
    }

    /**
     * unregisterWorker
     *
     * @param $id
     */
    public function unregisterWorker($id): void
    {
        $this->client->srem('workers', $id);
        $worker_key = sprintf('worker:%s', $id);
        $worker_started = sprintf('%s:started', $worker_key);
        $this->client->del([$worker_key, $worker_started]);
        $this->deleteStats($id);

    }

    /**
     * deleteStats
     *
     * @param $worker
     */
    private function deleteStats($worker)
    {
        $processed = sprintf('processed:%s', $worker);
        $failed = sprintf('failed:%s', $worker);
        $this->client->del([$processed, $failed]);
    }

    /**
     * increaseProcessedCount
     *
     */
    public function increaseProcessedCount(): void
    {
        $this->client->incr('stat:processed');
    }

    /**
     * increaseWorkerProcessedCount
     *
     * @param \QueueJitsu\Worker\Worker $worker
     */
    public function increaseWorkerProcessedCount(Worker $worker): void
    {
        $this->client->incr(sprintf('stat:processed:%s', $worker->getId()));
    }

    /**
     * clearJob
     *
     * @param \QueueJitsu\Worker\Worker $worker
     */
    public function clearJob(Worker $worker): void
    {
        $this->client->del([sprintf('worker:%s', $worker->getId())]);
    }
}
