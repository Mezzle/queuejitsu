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

namespace QueueJitsu\Job;

use Ramsey\Uuid\Uuid;

/**
 * Class Job
 *
 * @package QueueJitsu\Job
 */
class Job
{
    /**
     * @var array $args
     */
    private $args;

    /**
     * @var string $class
     */
    private $class;

    /**
     * @var string $id
     */
    private $id;

    /**
     * @var string $queue
     */
    private $queue;

    /**
     * @var string $worker
     */
    private $worker;

    /**
     * Job constructor.
     *
     * @param string $class
     * @param string $queue
     * @param array $args
     * @param string|null $id
     */
    public function __construct(
        string $class,
        string $queue,
        array $args = [],
        $id = null
    ) {
        $this->class = $class;
        $this->args = $args;

        if (is_null($id)) {
            $id = Uuid::uuid4()->toString();
        }

        $this->id = $id;
        $this->queue = $queue;
    }

    /**
     * Worker
     *
     * @return string
     */
    public function getWorker(): string
    {
        return $this->worker;
    }

    /**
     * @param string $worker
     */
    public function setWorker(string $worker): void
    {
        $this->worker = $worker;
    }

    /**
     * Args
     *
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Class
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * getQueue
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * getPayload
     *
     * @return array
     */
    public function getPayload(): array
    {
        return [
            'queue' => $this->queue,
            'id' => $this->id,
            'class' => $this->class,
            'args' => $this->args,
        ];
    }
}
