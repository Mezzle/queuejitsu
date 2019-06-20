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

namespace QueueJitsu\Worker;

use Psr\Log\LoggerInterface;
use QueueJitsu\Exception\DirtyExitException;
use QueueJitsu\Job\Job;
use QueueJitsu\Job\JobManager;
use QueueJitsu\Queue\QueueManager;

/**
 * Class Worker
 *
 * @package QueueJitsu\Worker
 */
class Worker extends AbstractWorker
{
    /**
     * @var \QueueJitsu\Job\JobManager $job_manager
     */
    protected $job_manager;

    /**
     * @var \QueueJitsu\Queue\QueueManager $queue_manager
     */
    protected $queue_manager;

    /**
     * @var Job|null $current_job
     */
    private $current_job;

    /**
     * Worker constructor.
     *
     * @param \Psr\Log\LoggerInterface $log
     * @param \QueueJitsu\Queue\QueueManager $queue_manager
     * @param \QueueJitsu\Worker\WorkerManager $manager
     * @param \QueueJitsu\Job\JobManager $job_manager
     */
    public function __construct(
        LoggerInterface $log,
        WorkerManager $manager,
        QueueManager $queue_manager,
        JobManager $job_manager
    ) {
        $this->queue_manager = $queue_manager;
        $this->job_manager = $job_manager;

        parent::__construct($log, $manager);
    }

    /**
     * workingOn
     *
     * @return string
     */
    protected function getWorkerIdentifier(): string
    {
        return implode(',', $this->queue_manager->getQueues());
    }

    /**
     * finishedWorking
     */
    protected function finishedWorking(): void
    {
        $this->current_job = null;

        parent::finishedWorking();
    }

    /**
     * loop
     *
     * @throws \QueueJitsu\Exception\ForkFailureException
     */
    protected function loop(): void
    {
        $job = $this->getJob();

        if (!$job) {
            $this->sleep();

            return;
        }

        $this->log->info(sprintf('got Job %s', $job->getId()));

        $this->getEventManager()->trigger('beforeFork', $job);
        $this->setWorkingOn($job);

        $this->child = $this->fork();

        // We are the Child Process
        if ($this->child === 0 || $this->child === false) {
            $this->runAsChild($job);
        }

        // We are the parent Process
        if ($this->child > 0) {
            $this->runAsParent($job);
        }

        $this->child = null;
        $this->finishedWorking();
    }

    /**
     * getJob
     *
     * @return null|\QueueJitsu\Job\Job
     */
    private function getJob(): ?Job
    {
        return $this->queue_manager->reserve();
    }

    /**
     * setWorkingOn
     *
     * @param \QueueJitsu\Job\Job $job
     */
    private function setWorkingOn(Job $job)
    {
        $this->setTask(
            [
                'queue' => $job->getQueue(),
                'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                'payload' => $job->getPayload(),
            ]
        );

        $this->current_job = $job;
        $job->setWorker($this->getId());
        $this->job_manager->updateStatus($job, JobManager::STATUS_RUNNING);
    }

    /**
     * runAsChild
     *
     * @param \QueueJitsu\Job\Job $job
     */
    private function runAsChild(Job $job): void
    {
        $status = sprintf('Processing ID: %s in %s', $job->getId(), $job->getQueue());
        $this->updateProcLine($status);

        $this->log->info($status);

        $this->job_manager->run($job);

        if ($this->child === 0) {
            exit(0);
        }
    }

    /**
     * runAsParent
     *
     * @param \QueueJitsu\Job\Job $job
     */
    private function runAsParent(Job $job): void
    {
        $status = sprintf('Forked %s for ID: %s', $this->child, $job->getId());
        $this->updateProcLine($status);

        $this->log->debug(
            $status,
            [
                'type' => 'fork',
                'worker' => $this->worker_name,
                'job_id' => $job->getId(),
            ]
        );

        pcntl_wait($wait_status);
        $exit_status = pcntl_wexitstatus($wait_status);

        if ($exit_status !== 0) {
            $this->job_manager->failJob(
                $job,
                new DirtyExitException(
                    sprintf('Job Exited with exit code %d', $exit_status)
                )
            );
        }
    }
}
