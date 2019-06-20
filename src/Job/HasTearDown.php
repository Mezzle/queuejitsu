<?php
/**
 * Copyright (c) 2019 Martin Meredith <martin@sourceguru.net>
 */

declare(strict_types=1);

namespace QueueJitsu\Job;

/**
 * Interface HasSetup
 *
 * @package QueueJitsu\Job
 */
interface HasTearDown
{
    /**
     * setUp
     */
    public function tearDown(): void;
}
