<?php

namespace Tweakwise\TweakwiseHyva\ViewModel\ProductList;

use Closure;
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
}
