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
use QueueJitsu\Exception\ForkFailureException;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;

/**
 * Class AbstractWorker
 *
 * @package QueueJitsu\Worker
 */
abstract class AbstractWorker implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * @var bool|null|int $child
     */
    protected $child = null;

    /**
     * @var bool $finish
     */
    protected $finish = false;

    /**
     * @var string $hostname
     */
    protected $hostname;

    /**
     * @var string $id
     */
    protected $id;

    /**
     * @var int $interval
     */
    protected $interval = 5;

    /**
     * @var \Psr\Log\LoggerInterface $log
     */
    protected $log;

    /**
     * @var \QueueJitsu\Worker\WorkerManager $manager
     */
    protected $manager;

    /**
     * @var bool $paused
     */
    protected $paused = false;

    /**
     * @var string $worker_name
     */
    protected $worker_name;

    /**
     * AbstractWorker constructor.
     *
     * @param \Psr\Log\LoggerInterface $log
     * @param \QueueJitsu\Worker\WorkerManager $manager
     */
    public function __construct(LoggerInterface $log, WorkerManager $manager)
    {
        $this->log = $log;
        $this->hostname = gethostname();
        $this->worker_name = sprintf('%s:%d', $this->hostname, getmypid());
        $this->id = sprintf('%s:%s', $this->worker_name, $this->getWorkerIdentifier());
        $this->manager = $manager;
    }

    /**
     * workingOn
     *
     * @return string
     */
    abstract protected function getWorkerIdentifier(): string;

    /**
     * continueProcessing
     */
    public function continueProcessing(): void
    {
        $this->log->info('CONT received; resuming job processing');
        $this->paused = false;
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
     * __invoke
     *
     * @param int $interval
     *
     * @throws \QueueJitsu\Exception\ForkFailureException
     */
    public function __invoke($interval = 5)
    {
        $this->interval = $interval;

        $this->updateProcLine('Starting');
        $this->startup();

        while (true) {
            if ($this->finish) {
                break;
            }

            if ($this->paused) {
                $this->sleep();

                continue;
            }

            $this->loop();
        }

        $this->manager->unregisterWorker($this->getId());
    }

    /**
     * updateProcLine
     *
     * @param string $status
     */
    protected function updateProcLine(string $status): void
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title(sprintf('qjitsu-%s: %s', $this->getWorkerType(), $status));
        }
    }

    /**
     * getWorkerType
     *
     * @return string
     */
    protected function getWorkerType(): string
    {
        return 'worker';
    }

    /**
     * startup
     */
    protected function startup()
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
    protected function registerSignalHandlers(): bool
    {
        if (!function_exists('pcntl_signal')) {
            $this->log->warning('Signal Handling is not supported on this system');

            return false;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [$this, 'shutdownNow']);
        pcntl_signal(SIGINT, [$this, 'shutdownNow']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        pcntl_signal(SIGUSR1, [$this, 'killChild']);
        pcntl_signal(SIGUSR2, [$this, 'pauseProcessing']);
        pcntl_signal(SIGCONT, [$this, 'continueProcessing']);

        $this->log->debug('Registered Signals');

        return true;
    }

    /**
     * pruneDeadWorkers
     */
    protected function pruneDeadWorkers(): void
    {
        $this->manager->pruneDeadWorkers();
    }

    /**
     * sleep
     *
     */
    protected function sleep(): void
    {
        $this->log->debug(sprintf('Sleeping for %d', $this->interval));

        $waitString = sprintf('Waiting for %s', $this->getWorkerIdentifier());
        $procline = $this->paused ? 'Paused' : $waitString;
        $this->updateProcLine($procline);

        usleep($this->interval * 1000000);
    }

    /**
     * loop
     *
     */
    abstract protected function loop(): void;

    /**
     * getId
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * finishedWorking
     */
    protected function finishedWorking(): void
    {
        $this->manager->finishedWorking($this);
    }

    /**
     * fork
     *
     * @return bool|int
     *
     * @throws \QueueJitsu\Exception\ForkFailureException
     */
    protected function fork()
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
     * setTask
     *
     * @param $data
     */
    protected function setTask($data)
    {
        $this->manager->setTask($this, $data);
    }
}
