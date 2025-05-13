<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Optional: for logging

class AutoscalingTestController extends Controller
{
    /**
     * Intentionally stress CPU and Memory for autoscaling tests.
     *
     * Access this via a URI like: /stress-test
     * You can adjust load with query parameters:
     * /stress-test?cpu_iterations=10000000&memory_iterations=500000
     *
     * WARNING: This can make your server unresponsive. Use only in test environments.
     */
    public function stressTest(Request $request)
    {
        // Optional: Allow script to run for a longer time. Use with caution.
        set_time_limit(0); // 0 means no time limit
        ini_set('memory_limit', '-1');

        // --- Maximize CPU Usage ---
        $cpuIterations = (int) $request->input('cpu_iterations', 5000000); // Default iterations
        $cpuStartTime = microtime(true);
        $hashResult = '';

        Log::info('[AutoscalingTest] Starting CPU stress.', ['iterations' => $cpuIterations]);

        for ($i = 0; $i < $cpuIterations; $i++) {
            // password_hash is a CPU-intensive function.
            // The 'cost' parameter makes it more or less intensive.
            $hashResult = password_hash('laravel-autoscaling-test-' . $i, PASSWORD_BCRYPT, ['cost' => 12]);

            // If you want to make it even more CPU intensive, you could add more math operations here.
            // For example: for ($j = 0; $j < 100; $j++) { sqrt(rand()); }
        }
        $cpuEndTime = microtime(true);
        $cpuDuration = round($cpuEndTime - $cpuStartTime, 2);
        Log::info('[AutoscalingTest] CPU stress completed.', ['duration_seconds' => $cpuDuration]);

        // --- Maximize Memory Usage ---
        $memoryIterations = (int) $request->input('memory_iterations', 200000); // Default iterations
        $stringLength = 1024; // Allocate strings of 1KB
        $memoryHog = [];
        $initialMemory = memory_get_usage(true);

        Log::info('[AutoscalingTest] Starting Memory stress.', ['iterations' => $memoryIterations, 'string_length_bytes' => $stringLength]);

        for ($i = 0; $i < $memoryIterations; $i++) {
            $memoryHog[] = str_repeat('X', $stringLength); // Create a 1KB string and add it to an array

            // To prevent hitting PHP's max_execution_time too quickly if it's low,
            // and to allow CPU/memory monitoring tools to catch up,
            // you can uncomment the usleep. However, for pure maxing, you might remove it.
            // if ($i % 10000 === 0) {
            //     usleep(100); // Sleep for 0.1 milliseconds
            // }
        }

        $finalMemory = memory_get_peak_usage(true);
        $memoryUsedBytes = $finalMemory - $initialMemory;
        $memoryUsedMB = round($memoryUsedBytes / 1024 / 1024, 2);
        $peakMemoryMB = round($finalMemory / 1024 / 1024, 2);

        Log::info('[AutoscalingTest] Memory stress completed.', ['memory_added_mb' => $memoryUsedMB, 'peak_memory_mb' => $peakMemoryMB]);

        return response()->json([
            'status' => 'Stress test initiated. Monitor server resources.',
            'cpu_stress' => [
                'iterations_performed' => $cpuIterations,
                'duration_seconds' => $cpuDuration,
                'sample_hash_output' => substr($hashResult, 0, 30) . '...'
            ],
            'memory_stress' => [
                'iterations_performed' => $memoryIterations,
                'string_length_per_iteration_bytes' => $stringLength,
                'approx_memory_added_mb' => $memoryUsedMB,
                'peak_memory_usage_mb' => $peakMemoryMB,
            ],
            'warning' => 'This endpoint is for testing autoscaling and puts heavy load on the server. Do not use in production.',
            'note' => 'You can adjust load with cpu_iterations and memory_iterations query parameters.'
        ]);
    }
}
