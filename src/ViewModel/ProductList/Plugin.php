<?php

namespace Tweakwise\TweakwiseHyva\ViewModel\ProductList;

use Closure;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Tweakwise\Magento2Tweakwise\Block\Catalog\Product\ProductList\AbstractRecommendationPlugin;
use Tweakwise\Magento2Tweakwise\Exception\ApiException;
use Tweakwise\Magento2Tweakwise\Exception\InvalidArgumentException;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Product\Recommendation\Collection;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Product\Recommendation\Context;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\Recommendations\FeaturedRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\Recommendations\ProductRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Config\TemplateFinder;
use Hyva\Theme\ViewModel\ProductList;
use Magento\Catalog\Block\Product\ProductList\Related;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Tweakwise\Magento2Tweakwise\Model\Cart\Crosssell as TweakwiseCrosssell;

class Plugin extends AbstractRecommendationPlugin
{
    /**
     * @var String $type
     */
    protected $type;

    /**
     * @var Integer $templateId
     */
    protected $templateId;

    /**
     * @param Config $config
     * @param Registry $registry
     * @param Context $context
     * @param TemplateFinder $templateFinder
     * @param ObjectManagerInterface $objectManager
     */

    public function __construct(Config $config, Registry $registry, Context $context, TemplateFinder $templateFinder, ObjectManagerInterface $objectManager)
    {
        parent::__construct($config, $registry, $context, $templateFinder);
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     */

    protected function getType()
    {
        return $this->type;
    }

    public function aroundGetCrosssellItems(ProductList $subject, Closure $proceed, QuoteItem ...$cartItems): array
    {
        if (empty($cartItems)) {
            return [];
        }

        $this->type = Config::RECCOMENDATION_TYPE_SHOPPINGCART;

        if (!$this->config->isRecommendationsEnabled($this->getType())) {
            return $proceed(...$cartItems);
        }

        // return most recently added product crosssell items first
        usort($cartItems, function (QuoteItem $itemA, QuoteItem $itemB) {
            return ($itemA->getCreatedAt() <=> $itemB->getCreatedAt()) * -1;
        });

        $items = [];

        foreach ($cartItems as $item) {
            $items = $this->getShoppingcartTweakwiseItems($item->getProduct(), [], $cartItems);

            if (!empty($items)) {
                break;
            }
        }

        return $items;
    }

    /**
     * Overwrite Hyva function to get items for upsell/crossell
     * @param ProductList $subject
     * @param Closure $proceed
     * @param string $linkType
     * @param Product|ProductInterface|QuoteItem ...$items
     * @return ProductInterface[]
     */
    public function aroundGetLinkedItems(ProductList $subject, Closure $proceed, string $linkType, $items): array
    {
        return $this->loadLinkedTweakwiseItems($proceed, $linkType, $items);
    }

    /**
     * Overwrite Hyva function to get items for upsell/crossell
     *
     * @param ProductList $subject
     * @param Closure $proceed
     * @param string $linkType
     * @param ...$items
     * @return array|Collection
     */
    private function loadLinkedTweakwiseItems(Closure $proceed, string $linkType, ...$items): array
    {
        $this->type = Config::RECOMMENDATION_TYPE_UPSELL;
        if ($linkType === 'crosssell' || $linkType === 'related') {
            $this->type = Config::RECOMMENDATION_TYPE_CROSSSELL;
        }

        if (!$this->config->isRecommendationsEnabled($this->type)) {
            return $proceed($linkType, ...$items);
        }

        if (!$this->templateFinder->forProduct($items[0], $this->getType())) {
            return $proceed($linkType, ...$items);
        }

        $this->templateId = $this->templateFinder->forProduct($items[0], $this->getType());

        try {
            return $this->getCollection();
        } catch (ApiException $e) {
            return $proceed($linkType, ...$items);
        }
    }

    /**
     * @return Collection
     */
    protected function getCollection()
    {
        $requestFactory = new RequestFactory($this->objectManager, ProductRequest::class);
        $request = $requestFactory->create();

        $featureRequestFactory = new RequestFactory($this->objectManager, FeaturedRequest::class);
        $featureRequest = $featureRequestFactory->create();
        $path = $featureRequest->getPath();

        $request->setPath($path);
        $request->setTemplate($this->templateId);
        $this->context->setRequest($request);

        if (!$request instanceof ProductRequest) {
            throw new InvalidArgumentException('Set context should contain ProductRequest');
        }

        $this->configureRequest($request);
        $this->collection = $this->context->getCollection();

        $this->collection->load();

        return $this->collection->getItems();
    }

    private function getShoppingcartTweakwiseItems (ProductInterface $product, array $result, array $cartItems) {
        $items = [];

        //show featured products
        if ($this->getType() === Config::RECCOMENDATION_TYPE_SHOPPINGCART_FEATURED) {
            return $this->getFeaturedItems();
        }

        //show crosssell products
        $requestFactory = new RequestFactory(ObjectManager::getInstance(), ProductRequest::class);
        $request = $requestFactory->create();
        $request->setProduct($product);

        if (!$this->templateFinder->forProduct($product, $this->getType())) {
            return $result;
        }

        $request->setTemplate($this->templateFinder->forProduct($product, $this->getType()));
        $this->context->setRequest($request);

        try {
            $collection = Parent::getCollection();
        } catch (ApiException $e) {
            return $result;
        }

        if (!empty($ninProductIds)) {
            $collection = $this->removeCartItems($collection, $cartItems);
        }

        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param $collection
     * @param $filteredProducts
     * @return void
     */
    protected function removeCartItems($collection, $cartItems)
    {
        $items = $collection->getItems();

        if(!empty($cartItems)) {
            foreach ($cartItems as $cartItem) {
                unset($items[$cartItem]);
            }
        }
        return $items;
    }

    private function getFeaturedItems()
    {
        $requestFactory = new RequestFactory(ObjectManager::getInstance(), FeaturedRequest::class);
        $request = $requestFactory->create();

        $templateId = $this->config->getRecommendationsTemplate(Config::RECCOMENDATION_TYPE_SHOPPINGCART_FEATURED);
        $request->setTemplate($templateId);

        $this->recommendationsContext->setRequest($request);

        try {
            $collection = $this->recommendationsContext->getCollection();
        } catch (ApiException $e) {
            return [];
        }

        if (!empty($cartItems)) {
            $collection = $this->removeCartItems($collection, $cartItems);
        }

        foreach ($collection as $item) {
            $items[] = $item;
        }

        return $items;
    }
}
