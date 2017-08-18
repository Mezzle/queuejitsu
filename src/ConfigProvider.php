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

namespace QueueJitsu;

use Psr\Log\NullLogger;
use QueueJitsu\Job\JobManager;
use QueueJitsu\Job\JobManagerFactory;
use QueueJitsu\Job\Strategy\ContainerStrategy;
use QueueJitsu\Job\Strategy\ContainerStrategyFactory;
use QueueJitsu\Queue\QueueManager;
use QueueJitsu\Queue\QueueManagerFactory;
use QueueJitsu\Worker\Worker;
use QueueJitsu\Worker\WorkerFactory;
use QueueJitsu\Worker\WorkerManager;
use QueueJitsu\Worker\WorkerManagerFactory;

/**
 * Class ConfigProvider
 *
 * @package QueueJitsu
 */
class ConfigProvider
{
    /**
     * __invoke
     *
     * @return array
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'queuejitsu' => $this->getDefaultConfig(),
        ];
    }

    /**
     * getDependencies
     *
     * @return array
     */
    private function getDependencies()
    {
        return [
            'invokables' => [
                NullLogger::class => NullLogger::class,
            ],
            'factories' => [
                ContainerStrategy::class => ContainerStrategyFactory::class,
                JobManager::class => JobManagerFactory::class,
                QueueManager::class => QueueManagerFactory::class,
                Worker::class => WorkerFactory::class,
                WorkerManager::class => WorkerManagerFactory::class,
            ],
        ];
    }

    /**
     * getDefaultConfig
     *
     * @return string
     */
    private function getDefaultConfig()
    {
        return __DIR__ . '/../config/config.php';
    }
}
