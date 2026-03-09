<?php

declare(strict_types = 1);

namespace PCP;

enum Env: string
{
    case Dev = 'dev';
    case Prod = 'prod';
    case Test = 'test';

    public function isDev(): bool
    {
        return $this === self::Dev;
    }
    public function isProd(): bool
    {
        return $this === self::Prod;
    }
    public function isTest(): bool
    {
        return $this === self::Test;
    }
}
