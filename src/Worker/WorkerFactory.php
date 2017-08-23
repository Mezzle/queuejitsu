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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;
use QueueJitsu\Job\JobManager;
use QueueJitsu\Queue\QueueManager;

/**
 * Class WorkerFactory
 *
 * @package QueueJitsu\Worker
 */
class WorkerFactory
{
    /**
     * __invoke
     *
     * @param \Psr\Container\ContainerInterface $container
     *
     * @return \Closure
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container)
    {
        $logger_class = $container->has(Logger::class) ? Logger::class : NullLogger::class;

        $logger = $container->get($logger_class);
        $job_manager = $container->get(JobManager::class);
        $queue_manager_factory = $container->get(QueueManager::class);
        $worker_manager = $container->get(WorkerManager::class);

        return function ($queues) use ($logger, $job_manager, $queue_manager_factory, $worker_manager) {
            $queue_manager = $queue_manager_factory($queues);

            return new Worker($logger, $worker_manager, $queue_manager, $job_manager);
        };
    }
}
