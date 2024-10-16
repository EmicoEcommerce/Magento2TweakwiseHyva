<?php

namespace Tweakwise\TweakwiseHyva\Plugin\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Helper\Data as SearchHelper;

class SearchForm implements ArgumentInterface
{
    public function __construct(private readonly SearchHelper $searchHelper)
    {

    }

    public function getSearchHelper()
    {
        return $this->searchHelper;
    }
}
