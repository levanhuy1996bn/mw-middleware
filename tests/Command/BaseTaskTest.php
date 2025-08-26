<?php

namespace App\Tests\Command;

use Endertech\EcommerceMiddleware\ShopifyStoris\Tests\Task\ShopifyStorisTaskTesterTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BaseTaskTest extends KernelTestCase
{
    use ShopifyStorisTaskTesterTrait;
}
