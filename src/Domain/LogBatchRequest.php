<?php namespace App\Domain;

class LogBatchRequest
{
    public readonly array $entries;

    public function __construct(
        public readonly array $logs = []
    )
    {
    }
}
