<?php

namespace Tests\Libraries;

use App\Libraries\JobDispatcher;
use App\Models\JobQueueModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class JobQueueTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    // ── dispatch() ───────────────────────────────────────────────────────────

    public function testDispatchInsertsJobWithCorrectFields(): void
    {
        $dispatcher = new JobDispatcher();
        $jobId      = $dispatcher->dispatch('send_message', [
            'conversation_id' => 'conv-abc',
            'content_type'    => 'text',
            'content_text'    => 'Hello world',
        ], null, 5);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);

        $job = (new JobQueueModel())->find($jobId);
        $this->assertNotNull($job);
        $this->assertSame('send_message', $job['job_type']);
        $this->assertSame(5, (int) $job['priority']);
        $this->assertSame('pending', $job['status']);
        $this->assertSame(0, (int) $job['attempts']);
    }

    public function testDispatchClampsExcessivePriority(): void
    {
        $id  = (new JobDispatcher())->dispatch('test_job', [], null, 99);
        $job = (new JobQueueModel())->find($id);

        $this->assertLessThanOrEqual(10, (int) $job['priority']);
    }

    public function testDispatchClampsNegativePriority(): void
    {
        $id  = (new JobDispatcher())->dispatch('test_job', [], null, -5);
        $job = (new JobQueueModel())->find($id);

        $this->assertGreaterThanOrEqual(0, (int) $job['priority']);
    }

    // ── priority ordering ────────────────────────────────────────────────────

    public function testJobsReturnedInDescendingPriorityOrder(): void
    {
        $d = new JobDispatcher();
        $d->dispatch('low_prio',  [], null, 2);
        $d->dispatch('high_prio', [], null, 9);
        $d->dispatch('mid_prio',  [], null, 5);

        $jobs = (new JobQueueModel())
            ->where('status', 'pending')
            ->orderBy('priority', 'DESC')
            ->findAll();

        $priorities = array_column($jobs, 'priority');
        $sorted     = $priorities;
        rsort($sorted);

        $this->assertSame($sorted, array_values($priorities));
    }

    // ── payload serialisation ────────────────────────────────────────────────

    public function testPayloadStoredAsJson(): void
    {
        $payload = ['key' => 'value', 'nested' => ['a' => 1]];
        $id      = (new JobDispatcher())->dispatch('test', $payload);
        $job     = (new JobQueueModel())->find($id);

        $decoded = json_decode($job['payload'], true);
        $this->assertSame($payload, $decoded);
    }

    // ── status field ─────────────────────────────────────────────────────────

    public function testNewJobStatusIsPending(): void
    {
        $id  = (new JobDispatcher())->dispatch('status_test', []);
        $job = (new JobQueueModel())->find($id);

        $this->assertSame('pending', $job['status']);
    }
}
