<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin\ViewModel;

use Emico\CodeCept\Test\Unit;
use Hyva\Theme\ViewModel\ProductListItem as Subject;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use ReflectionException;
use ReflectionMethod;
use Tweakwise\Magento2Tweakwise\Helper\Cache;
use Tweakwise\Magento2Tweakwise\Model\Analytics\GroupedProductIdResolver;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\TweakwiseHyva\Plugin\ViewModel\ProductListItem;

class ProductListItemTest extends Unit
{
    use MockeryPHPUnitIntegration;

    private ProductListItem $subject;
    private Config&MockInterface $config;
    private GroupedProductIdResolver&MockInterface $groupedProductIdResolver;

    protected function _before(): void
    {
        $cacheHelper = Mockery::mock(Cache::class);
        $layout = Mockery::mock(LayoutInterface::class);
        $storeManager = Mockery::mock(StoreManagerInterface::class);
        $customerSession = Mockery::mock(Session::class);
        $this->config = Mockery::mock(Config::class);
        $this->groupedProductIdResolver = Mockery::mock(GroupedProductIdResolver::class);

        $cacheHelper->shouldReceive('personalMerchandisingCanBeApplied')->andReturn(false);
        $cacheHelper->shouldReceive('isTweakwiseAjaxRequest')->andReturn(false);

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(1);
        $storeManager->shouldReceive('getStore')->andReturn($store);

        $this->subject = new ProductListItem(
            $cacheHelper,
            $layout,
            $storeManager,
            $customerSession,
            $this->config,
            $this->groupedProductIdResolver
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testAroundGetItemHtmlWithRendererAddsParentProductIdWhenGroupedProductsAreDisabled(): void
    {
        $this->config->shouldReceive('isGroupedProductsEnabled')->once()->andReturn(false);
        $this->groupedProductIdResolver->shouldNotReceive('resolve');

        $product = Mockery::mock(Product::class);
        $product->shouldReceive('getId')->atLeast()->once()->andReturn(1220);

        $itemRendererBlock = Mockery::mock(AbstractBlock::class);
        $parentBlock = Mockery::mock(AbstractBlock::class);
        $subject = Mockery::mock(Subject::class);

        $result = $this->subject->aroundGetItemHtmlWithRenderer(
            $subject,
            static fn () => '<form class="item product product-item"></form>',
            $itemRendererBlock,
            $product,
            $parentBlock,
            'grid',
            'default',
            'category_page_grid',
            false
        );

        $this->assertStringContainsString('data-product-id="1220"', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testAddProductIdAttributeReplacesExistingAttributeWithChildParentIdWhenGroupedProductsAreEnabled(): void
    {
        $this->config->shouldReceive('isGroupedProductsEnabled')->once()->andReturn(true);
        $this->groupedProductIdResolver->shouldNotReceive('resolve');

        $product = Mockery::mock(Product::class);
        $product->shouldReceive('getId')->atLeast()->once()->andReturn(1220);
        $product->shouldReceive('getData')->with('tw_id')->once()->andReturn('1211');

        $method = new ReflectionMethod(ProductListItem::class, 'addProductIdAttribute');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->subject,
            '<div class="item product product-item" data-product-id="old-value"></div>',
            $product
        );

        $this->assertStringContainsString('data-product-id="1211-1220"', (string)$result);
        $this->assertStringNotContainsString('data-product-id="old-value"', (string)$result);
    }

    /**
     * @throws ReflectionException
     */
    public function testAddProductIdAttributeUsesGroupedResolverWhenChildIdIsUnavailable(): void
    {
        $this->config->shouldReceive('isGroupedProductsEnabled')->once()->andReturn(true);

        $product = Mockery::mock(Product::class);
        $product->shouldReceive('getId')->atLeast()->once()->andReturn(1220);
        $product->shouldReceive('getData')->with('tw_id')->once()->andReturn(null);

        $this->groupedProductIdResolver->shouldReceive('resolve')->once()->with($product)->andReturn('1210-1220');

        $method = new ReflectionMethod(ProductListItem::class, 'addProductIdAttribute');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->subject,
            "<div class='item product product-item'></div>",
            $product
        );

        $this->assertStringContainsString('data-product-id="1210-1220"', (string)$result);
    }
}
