<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Iblock\PropertyEnumerationTable;
use BX\Data\Provider\DataManagerDataProvider;
use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;

class EnumDataProviderFetcher extends BaseMultiFetcher
{
    private ?QueryCriteriaInterface $query;
    private DataProviderInterface $enumerationDataProvider;

    public function __construct(
        KeyInfoList $keyInfoList,
        string $linkedModelKey = 'ID',
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $enumerationDataProvider = null
    ) {
        parent::__construct($keyInfoList, $linkedModelKey);
        $this->query = $query;
        $this->enumerationDataProvider = $enumerationDataProvider ??
            new DataManagerDataProvider(PropertyEnumerationTable::class);
    }

    protected function getLinkedDataList(ModelCollection $collection): iterable
    {
        $query = $this->query ?? new QueryCriteria();
        $keyValueList = $this->keyInfoList->getKeyValuesFromCollection($collection);
        if (empty($keyValueList)) {
            return [];
        }

        $keyValueList = array_map(function ($value): int {
            return (int) $value;
        }, $keyValueList);

        $query->addCriteria('ID', CompareRuleInterface::EQUAL, $keyValueList);
        return $this->enumerationDataProvider->getData($query);
    }
}
