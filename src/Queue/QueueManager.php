<?php

declare(strict_types=1);
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

namespace QueueJitsu\Queue;

use QueueJitsu\Job\Job;
use QueueJitsu\Queue\Adapter\AdapterInterface;
use QueueJitsu\Queue\Strategy\StrategyInterface;

/**
 * Class QueueManager
 *
 * @package QueueJitsu\Queue
 */
class QueueManager
{
    /**
     * @var \QueueJitsu\Queue\Adapter\AdapterInterface $adapter
     */
    private $adapter;

    /**
     * @var string[] $queues
     */
    private $queues;

    /**
     * @var \QueueJitsu\Queue\Strategy\StrategyInterface $strategy
     */
    private $strategy;

    /**
     * QueueManager constructor.
     *
     * @param \QueueJitsu\Queue\Adapter\AdapterInterface $adapter
     * @param \QueueJitsu\Queue\Strategy\StrategyInterface $strategy
     * @param array $queues
     */
    public function __construct(
        AdapterInterface $adapter,
        StrategyInterface $strategy,
        array $queues = ['*']
    ) {
        $this->strategy = $strategy;
        $this->queues = $queues;
        $this->adapter = $adapter;
    }

    /**
     * Queues
     *
     * @return string[]
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * reserve
     *
     * @return null|\QueueJitsu\Job\Job
     */
    public function reserve(): ?Job
    {
        return $this->strategy->reserve($this->queues, $this->adapter);
    }
}
