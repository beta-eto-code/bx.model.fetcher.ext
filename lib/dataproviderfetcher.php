<?php

namespace Bx\Model\Fetcher\Ext;

use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;

class DataProviderFetcher extends BaseFetcher
{
    private DataProviderInterface $dataProvider;
    private string $destKey;
    private ?QueryCriteriaInterface $query;

    /**
     * @param DataProviderInterface $dataProvider
     * @param KeyInfo $keyInfo
     * @param string $destKey
     * @param QueryCriteriaInterface|null $query
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        KeyInfo $keyInfo,
        string $destKey,
        ?QueryCriteriaInterface $query = null
    ) {
        parent::__construct($keyInfo, $destKey);
        $this->dataProvider = $dataProvider;
        $this->destKey = $destKey;
        $this->query = $query;
    }

    protected function getLinkedDataList(ModelCollection $collection): iterable
    {
        $query = $this->query ?? new QueryCriteria();
        $query->addCriteria($this->destKey, CompareRuleInterface::IN, $this->getForeignKeyValues($collection));
        return $this->dataProvider->getData($query);
    }
}
