<?php

namespace App\Libraries;

use App\Models\JobQueueModel;

class JobDispatcher
{
    public function dispatch(string $jobType, array $payload, ?string $runAfter = null, int $priority = 0): int
    {
        $model = new JobQueueModel();

        return $model->insert([
            'job_type'    => $jobType,
            'payload'     => json_encode($payload),
            'status'      => 'pending',
            'priority'    => max(0, min(10, $priority)),
            'run_after'   => $runAfter,
            'attempts'    => 0,
            'max_retries' => 3,
        ]);
    }
}
