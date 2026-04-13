<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Base Symfony Kernel orchestrating routing, bundles, and container configuration natively.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
