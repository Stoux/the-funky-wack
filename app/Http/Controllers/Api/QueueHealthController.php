<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

class QueueHealthController extends Controller
{
    public function __construct(
        protected JobRepository $jobs,
        protected MasterSupervisorRepository $masters,
        protected MetricsRepository $metrics,
        protected WorkloadRepository $workload
    ) {}

    /**
     * Get queue health metrics.
     */
    public function __invoke(): JsonResponse
    {
        $masters = $this->masters->all();
        $isRunning = ! empty($masters);

        // Get workload (pending jobs per queue)
        $workload = collect($this->workload->get())->map(function ($queue) {
            return [
                'name' => $queue['name'],
                'pending' => $queue['length'],
                'processes' => $queue['processes'],
                'wait' => $queue['wait'],
            ];
        });

        // Get recent job stats
        $recentJobs = $this->jobs->getRecent();
        $failedCount = $this->jobs->countFailed();
        $recentFailedCount = $this->jobs->countRecentlyFailed();

        // Calculate health status
        $totalPending = $workload->sum('pending');
        $maxWait = $workload->max('wait') ?? 0;

        $status = 'healthy';
        if (! $isRunning) {
            $status = 'critical';
        } elseif ($totalPending > 1000 || $maxWait > 60) {
            $status = 'degraded';
        } elseif ($recentFailedCount > 10) {
            $status = 'warning';
        }

        return response()->json([
            'status' => $status,
            'horizon_running' => $isRunning,
            'queues' => $workload->values(),
            'total_pending' => $totalPending,
            'max_wait_seconds' => $maxWait,
            'failed_jobs' => $failedCount,
            'recent_failed' => $recentFailedCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
