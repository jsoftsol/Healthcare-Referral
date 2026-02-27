<?php
namespace App\Contracts;

interface AiTriageContract
{
    public function assess(array $payload): array;
}
