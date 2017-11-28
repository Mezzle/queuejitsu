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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use QueueJitsu\Job\Adapter\AdapterInterface;
use QueueJitsu\Job\Strategy\ContainerStrategy;
use QueueJitsu\Job\Strategy\StrategyInterface;

/**
 * Class JobManagerFactory
 *
 * @package QueueJitsu\Job
 */
class JobManagerFactory
{
    /**
     * __invoke
     *
     * @param \Psr\Container\ContainerInterface $container
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return \QueueJitsu\Job\JobManager
     */
    public function __invoke(ContainerInterface $container)
    {
        $logger_class = $container->has(LoggerInterface::class) ? LoggerInterface::class : NullLogger::class;

        $logger = $container->get($logger_class);

        $adapter = $container->get(AdapterInterface::class);

        $strategy_class = $container->has(
            StrategyInterface::class
        ) ? StrategyInterface::class : ContainerStrategy::class;

        $strategy = $container->get($strategy_class);

        return new JobManager($logger, $adapter, $strategy);
    }
}
