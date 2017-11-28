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

namespace QueueJitsu\Job;

use Psr\Log\LoggerInterface;
use QueueJitsu\Exception\DontPerform;
use QueueJitsu\Exception\NotImplemented;
use QueueJitsu\Job\Adapter\AdapterInterface;
use QueueJitsu\Job\Adapter\StatusQueryInterface;
use QueueJitsu\Job\Strategy\StrategyInterface;
use Throwable;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;

/**
 * Class Runner
 *
 * @package QueueJitsu\Job
 */
class JobManager implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    public const COMPLETED_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_COMPLETE,
    ];

    public const STATUS_COMPLETE = 4;

    public const STATUS_FAILED = 3;

    public const STATUS_RUNNING = 2;

    public const STATUS_WAITING = 1;

    /**
     * @var \QueueJitsu\Job\Adapter\AdapterInterface $adapter
     */
    private $adapter;

    /**
     * @var \Psr\Log\LoggerInterface $log
     */
    private $log;

    /**
     * @var \QueueJitsu\Job\Strategy\StrategyInterface $strategy
     */
    private $strategy;

    /**
     * JobManager constructor.
     *
     * @param \Psr\Log\LoggerInterface $log
     * @param \QueueJitsu\Job\Adapter\AdapterInterface $adapter
     * @param \QueueJitsu\Job\Strategy\StrategyInterface $strategy
     */
    public function __construct(LoggerInterface $log, AdapterInterface $adapter, StrategyInterface $strategy)
    {
        $this->log = $log;
        $this->adapter = $adapter;
        $this->strategy = $strategy;
    }

    /**
     * run
     *
     * @param \QueueJitsu\Job\Job $job
     */
    public function run(Job $job)
    {
        $this->getEventManager()->trigger('afterFork', $job);

        try {
            $jobInstance = $this->strategy->getJobInstance($job->getClass());

            $this->getEventManager()->trigger('beforePerform', $job);

            if (\method_exists($jobInstance, 'setUp')) {
                $jobInstance->setUp();
            }

            $args = $job->getArgs();

            $jobInstance(...$args);

            if (\method_exists($jobInstance, 'tearDown')) {
                $jobInstance->tearDown();
            }

            $this->getEventManager()->trigger('afterPerform', $job);
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (DontPerform $e) {
            $this->log->debug(\sprintf('Job %s triggered a DontPerform', $job->getId()));
            // Don't Perform this job triggered
        } catch (Throwable $e) {
            $this->log->error(
                \sprintf(
                    '%s failed %s',
                    $job->getId(),
                    $e->getMessage()
                ),
                ['exception' => $e, 'job' => $job]
            );

            $this->failJob($job, $e);

            return;
        }

        $this->updateStatus($job, self::STATUS_COMPLETE);
    }

    /**
     * failJob
     *
     * @param \QueueJitsu\Job\Job $job
     * @param \Throwable $e
     */
    public function failJob(Job $job, Throwable $e)
    {
        $this->getEventManager()->trigger('onFailure', $job, [$e]);

        $this->updateStatus($job, self::STATUS_FAILED);

        $this->createFailure($job, $e);
    }

    /**
     * updateStatus
     *
     * @param \QueueJitsu\Job\Job $job
     * @param int $status
     */
    public function updateStatus(Job $job, int $status)
    {
        $this->adapter->updateStatus($job, $status);
    }

    /**
     * createFailure
     *
     * @param \QueueJitsu\Job\Job $job
     * @param \Throwable $exception
     */
    private function createFailure(Job $job, Throwable $exception)
    {
        $this->adapter->createFailure($job, $exception);
    }

    /**
     * enqueue
     *
     * @param \QueueJitsu\Job\Job $job
     */
    public function enqueue(Job $job): void
    {
        $this->adapter->enqueue($job);
        $this->updateStatus($job, self::STATUS_WAITING);
    }

    /**
     * getStatus
     *
     * @param string $guid
     *
     * @throws \QueueJitsu\Exception\NotImplemented
     *
     * @return array
     */
    public function getStatus(string $guid): array
    {
        if (!$this->adapter instanceof StatusQueryInterface) {
            throw new NotImplemented('Querying of Statuses not available in this implementation');
        }

        return $this->adapter->getStatus($guid);
    }
}
