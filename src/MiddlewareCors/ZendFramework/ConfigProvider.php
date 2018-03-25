<?php
declare(strict_types=1);

namespace Bairwell\MiddlewareCors\ZendFramework;

use Bairwell\MiddlewareCors;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies()
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                MiddlewareCors::class => MiddlewareCorsFactory::class
            ]
        ];
    }
}
