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

    /**
     * Cache key must differ for two products that share the same parent id but have
     * a different `tw_id`, otherwise ESI/personal-merchandising would serve the same
     * cached `data-product-id` for both grouped variants.
     */
    public function testEsiCacheKeyDiffersPerGroupedVariantOfSameParent(): void
    {
        $cacheHelper = Mockery::mock(Cache::class);
        $layout = Mockery::mock(LayoutInterface::class);
        $storeManager = Mockery::mock(StoreManagerInterface::class);
        $customerSession = Mockery::mock(Session::class);
        $config = Mockery::mock(Config::class);
        $groupedProductIdResolver = Mockery::mock(GroupedProductIdResolver::class);

        $cacheHelper->shouldReceive('personalMerchandisingCanBeApplied')->andReturn(true);
        $cacheHelper->shouldReceive('isTweakwiseAjaxRequest')->andReturn(false);
        $cacheHelper->shouldReceive('getImage')->andReturn('image.jpg');
        $cacheHelper->shouldReceive('load')->andReturn('cached-html');

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(1);
        $storeManager->shouldReceive('getStore')->andReturn($store);
        $customerSession->shouldReceive('getCustomerGroupId')->andReturn(0);

        $config->shouldReceive('isGroupedProductsEnabled')->andReturn(true);
        $groupedProductIdResolver->shouldNotReceive('resolve');

        $capturedKeys = new \stdClass();
        $capturedKeys->list = [];
        $cacheHelper->shouldReceive('hashCacheKeyInfo')
            ->andReturnUsing(static function (...$args) use ($capturedKeys): string {
                $key = implode('|', $args);
                $capturedKeys->list[] = $key;
                return $key;
            });

        $subject = new ProductListItem(
            $cacheHelper,
            $layout,
            $storeManager,
            $customerSession,
            $config,
            $groupedProductIdResolver
        );

        $itemRendererBlock = Mockery::mock(AbstractBlock::class);
        $itemRendererBlock->shouldReceive('getNameInLayout')->andReturn('product.card');
        $parentBlock = Mockery::mock(AbstractBlock::class);
        $subjectMock = Mockery::mock(Subject::class);

        $variantA = Mockery::mock(Product::class);
        $variantA->shouldReceive('getId')->andReturn(1220);
        $variantA->shouldReceive('getData')->with('tw_id')->andReturn('1211');

        $variantB = Mockery::mock(Product::class);
        $variantB->shouldReceive('getId')->andReturn(1220);
        $variantB->shouldReceive('getData')->with('tw_id')->andReturn('1212');

        $noop = static fn () => '';

        $subject->aroundGetItemHtmlWithRenderer(
            $subjectMock,
            $noop,
            $itemRendererBlock,
            $variantA,
            $parentBlock,
            'grid',
            'default',
            'category_page_grid',
            false
        );
        $subject->aroundGetItemHtmlWithRenderer(
            $subjectMock,
            $noop,
            $itemRendererBlock,
            $variantB,
            $parentBlock,
            'grid',
            'default',
            'category_page_grid',
            false
        );

        $keys = $capturedKeys->list;
        $this->assertCount(2, $keys);
        $this->assertNotSame(
            $keys[0],
            $keys[1],
            'ESI cache key must include child (tw_id) so grouped variants of the same parent are cached separately'
        );
    }
}
