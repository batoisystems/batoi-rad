<?php
namespace Core\Sys;

class CronJob {
    private $cronJobs;

    public function addJob($expression, $function) {
        $this->cronJobs[] = ['expression' => $expression, 'function' => $function];
    }

    public function run() {
        foreach ($this->cronJobs as $job) {
            $expression = \Cron\CronExpression::factory($job['expression']);

            if ($expression->isDue()) {
                call_user_func($job['function']);
            }
        }
    }
}
