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

namespace QueueJitsu\Queue\Strategy;

use Psr\Log\LoggerInterface;
use QueueJitsu\Job\Job;
use QueueJitsu\Queue\Adapter\AdapterInterface;

class Simple implements StrategyInterface
{
    /**
     * @var \Psr\Log\LoggerInterface $log
     */
    private $log;

    /**
     * Simple constructor.
     *
     * @param \Psr\Log\LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }

    /**
     * reserve
     *
     * @param string[] $queues
     * @param \QueueJitsu\Queue\Adapter\AdapterInterface $adapter
     *
     * @return null|\QueueJitsu\Job\Job
     */
    public function reserve(array $queues, AdapterInterface $adapter): ?Job
    {
        if (in_array('*', $queues)) {
            $queues = $adapter->getAllQueueNames();
        }

        foreach ($queues as $queue) {
            $this->log->debug(sprintf('Checking %s', $queue));

            $job = $adapter->reserve($queue);

            if (!is_null($job)) {
                $this->log->debug(sprintf('Found job on %s', $queue));

                return $job;
            }
        }

        return null;
    }
}
