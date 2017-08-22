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

namespace QueueJitsu\Worker;

use Psr\Log\LoggerInterface;
use QueueJitsu\Job\Job;
use QueueJitsu\Worker\Adapter\AdapterInterface;

/**
 * Class WorkerManager
 *
 * @package QueueJitsu\Worker
 */
class WorkerManager
{
    /**
     * @var \QueueJitsu\Worker\Adapter\AdapterInterface $adapter
     */
    private $adapter;

    /**
     * @var string $hostname
     */
    private $hostname;

    /**
     * @var \Psr\Log\LoggerInterface $log
     */
    private $log;

    /**
     * WorkerManager constructor.
     *
     * @param \QueueJitsu\Worker\Adapter\AdapterInterface $adapter
     * @param \Psr\Log\LoggerInterface $log
     */
    public function __construct(AdapterInterface $adapter, LoggerInterface $log)
    {
        $this->hostname = gethostname();
        $this->adapter = $adapter;
        $this->log = $log;
    }

    /**
     * pruneDeadWorkers
     */
    public function pruneDeadWorkers(): void
    {
        $local_pids = $this->getLocalWorkerPids();
        $workers = $this->getAllWorkerIds();

        foreach ($workers as $worker) {
            [$host, $pid] = explode(':', $worker, 3);

            if ($host != $this->hostname || in_array($pid, $local_pids) || $pid == getmypid()) {
                continue;
            }
            $this->log->debug(sprintf('Pruning dead worker: %s', $worker));

            $this->unregisterWorker($worker);
        }
    }

    /**
     * getLocalWorkerPids
     *
     * @return array
     */
    public function getLocalWorkerPids(): array
    {
        $pids = [];
        exec('ps -A -o pid,args | grep "[q]jitsu"', $output);

        foreach ($output as $line) {
            [$pid] = explode(' ', trim($line), 2);
            $pids[] = $pid;
        }

        return $pids;
    }

    /**
     * getAllWorkerIds
     *
     * @return array
     */
    public function getAllWorkerIds(): array
    {
        return $this->adapter->getAllWorkerIds();
    }

    /**
     * unregisterWorker
     *
     * @param string $worker
     */
    private function unregisterWorker(string $worker): void
    {
        $this->adapter->unregisterWorker($worker);
    }

    /**
     * reestablishConnection
     */
    public function reestablishConnection()
    {
        $this->adapter->reestablishConnection();
    }

    /**
     * registerWorker
     *
     * @param string $worker
     */
    public function registerWorker(string $worker)
    {
        $this->adapter->registerWorker($worker);
    }

    public function setWorkerWorkingOn(Worker $id, Job $job)
    {
        $this->adapter->setWorkerWorkingOn($id, $job);
    }

    public function finishedWorking(Worker $worker)
    {
        $this->adapter->increaseProcessedCount();
        $this->adapter->increaseWorkerProcessedCount($worker);
        $this->adapter->clearJob($worker);
    }
}
