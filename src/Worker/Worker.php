<?php

declare(ticks=1);
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
use QueueJitsu\Exception\ForkFailureException;
use QueueJitsu\Job\Job;
use QueueJitsu\Job\JobManager;
use QueueJitsu\Queue\QueueManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;

/**
 * Class Worker
 *
 * @package QueueJitsu\Worker
 */
class Worker implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * @var bool|null|int $child
     */
    private $child = null;

    /**
     * @var Job|null $current_job
     */
    private $current_job;

    /**
     * @var bool $finish
     */
    private $finish = false;

    /**
     * @var string $hostname
     */
    private $hostname;

    /**
     * @var string $id
     */
    private $id;

    /**
     * @var \QueueJitsu\Job\JobManager $job_manager
     */
    private $job_manager;

    /**
     * @var \Psr\Log\LoggerInterface $log
     */
    private $log;

    /**
     * @var \QueueJitsu\Worker\WorkerManager $manager
     */
    private $manager;

    /**
     * @var bool $paused
     */
    private $paused = false;

    /**
     * @var \QueueJitsu\Queue\QueueManager $queue_manager
     */
    private $queue_manager;

    /**
     * @var string $worker_name
     */
    private $worker_name;

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
        QueueManager $queue_manager,
        WorkerManager $manager,
        JobManager $job_manager
    ) {
        $this->queue_manager = $queue_manager;
        $this->log = $log;
        $this->manager = $manager;
        $this->job_manager = $job_manager;

        $this->hostname = gethostname();
        $this->worker_name = sprintf('%s:%d', $this->hostname, getmypid());
        $this->id = sprintf('%s:%s', $this->worker_name, $this->getQueueString());
    }

    /**
     * getQueueString
     *
     * @return string
     */
    private function getQueueString(): string
    {
        return implode(',', $this->queue_manager->getQueues());
    }

    /**
     * __invoke
     *
     * @param int $interval
     *
     * @throws \QueueJitsu\Exception\ForkFailureException
     */
    public function __invoke($interval = 5)
    {
        $this->updateProcLine('Starting');
        $this->startup();

        while (true) {
            if ($this->finish) {
                break;
            }

            $job = $this->getJob();

            if (!$job) {
                $this->log->debug(sprintf('Sleeping for %d', $interval));

                $waitString = sprintf('Waiting for %s', $this->getQueueString());
                $procline = $this->paused ? 'Paused' : $waitString;
                $this->updateProcLine($procline);

                usleep($interval * 1000000);
                continue;
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
    }

    /**
     * updateProcLine
     *
     * @param string $status
     */
    protected function updateProcLine(string $status): void
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title('qjitsu: ' . $status);
        }
    }

    /**
     * startup
     */
    private function startup()
    {
        $this->log->info(sprintf('Starting worker %s', $this->id));

        $this->registerSignalHandlers();
        $this->pruneDeadWorkers();

        $this->getEventManager()->trigger('beforeFirstFork', $this);
        $this->manager->registerWorker($this->id);
    }

    /**
     * registerSignalHandlers
     *
     * @return bool
     */
    private function registerSignalHandlers(): bool
    {
        if (!function_exists('pcntl_signal')) {
            $this->log->warning('Signal Handling is not supported on this system');

            return false;
        }

        pcntl_signal(SIGTERM, [$this, 'shutdownNow']);
        pcntl_signal(SIGINT, [$this, 'shutdownNow']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        pcntl_signal(SIGUSR1, [$this, 'killChild']);
        pcntl_signal(SIGUSR2, [$this, 'pauseProcessing']);
        pcntl_signal(SIGCONT, [$this, 'continueProcessing']);
        pcntl_signal(SIGPIPE, [$this, 'reestablishConnection']);

        $this->log->debug('Registered Signals');

        return true;
    }

    /**
     * pruneDeadWorkers
     */
    private function pruneDeadWorkers(): void
    {
        $this->manager->pruneDeadWorkers();
    }

    /**
     * getJob
     *
     * @return null|\QueueJitsu\Job\Job
     */
    private function getJob(): ? Job
    {
        $job = null;

        if (!$this->paused) {
            $job = $this->queue_manager->reserve();
        }

        return $job;
    }

    /**
     * setWorkingOn
     *
     * @param \QueueJitsu\Job\Job $job
     */
    private function setWorkingOn(Job $job)
    {
        $this->manager->setWorkerWorkingOn($this, $job);
        $this->current_job = $job;
        $job->setWorker($this->getId());
        $this->job_manager->updateStatus($job, JobManager::STATUS_RUNNING);
    }

    /**
     * getId
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * fork
     *
     * @return bool|int
     *
     * @throws \QueueJitsu\Exception\ForkFailureException
     */
    private function fork()
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new ForkFailureException('Unable to for a child worker');
        }

        return $pid;
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
        $exit_status = (pcntl_wexitstatus($wait_status));

        if ($exit_status !== 0) {
            $this->job_manager->failJob(
                $job,
                new DirtyExitException(
                    sprintf('Job Exited with exit code %d', $exit_status)
                )
            );
        }
    }

    /**
     * finishedWorking
     */
    private function finishedWorking(): void
    {
        $this->current_job = null;
        $this->manager->finishedWorking($this);
    }

    /**
     * shutdownNow
     */
    public function shutdownNow(): void
    {
        $this->log->warning('Forced Shutdown Started');
        $this->shutdown();
        $this->killChild();
    }

    /**
     * shutdown
     */
    public function shutdown(): void
    {
        $this->finish = true;
        $this->log->info('Exiting...');
    }

    /**
     * killChild
     */
    public function killChild(): void
    {
        if (is_null($this->child)) {
            $this->log->debug('No child to kill');

            return;
        }

        $this->log->debug(sprintf('Finding child at %d', $this->child));

        // Check if pid is running
        $executed = exec(sprintf('ps -o pid,state -p %d', $this->child), $output, $return_code);

        if ($executed && $return_code != 1) {
            $this->log->debug(sprintf('Killing child at %d', $this->child));
            posix_kill($this->child, SIGKILL);
            $this->child = null;

            return;
        }

        $this->log->error(sprintf('Child %d not found, restarting', $this->child));
        $this->shutdown();
    }

    /**
     * pauseProcessing
     */
    public function pauseProcessing(): void
    {
        $this->log->info('USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * continueProcessing
     */
    public function continueProcessing(): void
    {
        $this->log->info('CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * reestablishConnection
     */
    public function reestablishConnection(): void
    {
        $this->log->info('SIGPIPE received - attempting to reconnect');
        $this->queue_manager->reestablishConnection();
        $this->manager->reestablishConnection();
    }
}
