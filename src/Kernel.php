<?php

namespace App;

use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use Endertech\EcommerceMiddleware\Driver\Shopify\Data\Product\ShopifySource as ProductShopifySource;
use Endertech\EcommerceMiddleware\Driver\Shopify\Data\Variant\ShopifySource as VariantShopifySource;
use Endertech\EcommerceMiddleware\ShopifyStoris\Data\Order\ConfiguredMiddleware as OrderConfiguredMiddleware;
use Endertech\EcommerceMiddleware\ShopifyStoris\Data\Product\ConfiguredMiddleware as ProductConfiguredMiddleware;
use Endertech\EcommerceMiddleware\ShopifyStoris\Data\Variant\ConfiguredMiddleware as VariantConfiguredMiddleware;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function process(ContainerBuilder $container): void
    {
        $container->findDefinition(OrderConfiguredMiddleware::class)->setClass(AppOrderMiddleware::class);
        $container->findDefinition(ProductConfiguredMiddleware::class)->setClass(AppProductMiddleware::class);
        $container->findDefinition(VariantConfiguredMiddleware::class)->setClass(AppVariantMiddleware::class);
        $container->findDefinition(ProductShopifySource::class)->setClass(AppProductShopifySource::class);
        $container->findDefinition(VariantShopifySource::class)->setClass(AppVariantShopifySource::class);
    }
}
