<?php

namespace Bx\Model\Fetcher\Ext;

use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;

class DataProviderMultiFetcher extends BaseMultiFetcher
{
    private DataProviderInterface $dataProvider;
    private string $destKey;
    private ?QueryCriteriaInterface $query;

    /**
     * @param DataProviderInterface $dataProvider
     * @param KeyInfoList $keyInfoList
     * @param string $destKey
     * @param QueryCriteriaInterface|null $query
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        KeyInfoList $keyInfoList,
        string $destKey,
        ?QueryCriteriaInterface $query = null
    ) {
        parent::__construct($keyInfoList, $destKey);
        $this->dataProvider = $dataProvider;
        $this->keyInfoList = $keyInfoList;
        $this->linkedModelKey = $this->destKey = $destKey;
        $this->query = $query;
    }

    protected function getLinkedDataList(ModelCollection $collection): iterable
    {
        $query = $this->query ?? new QueryCriteria();
        $query->addCriteria($this->destKey, CompareRuleInterface::IN, $this->getForeignKeyValues($collection));
        return $this->dataProvider->getData($query);
    }
}
