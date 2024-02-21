<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use BX\Data\Provider\HlBlockDataProvider;
use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;

class HlBlockDataProviderFetcher extends BaseMultiFetcher
{
    private ?QueryCriteriaInterface $query;
    private DataProviderInterface $hlDataProvider;

    /**
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function initByTableName(
        string $hlTableName,
        KeyInfoList $keyInfoList,
        string $linkedModelKey = 'UF_XML_ID',
        ?QueryCriteriaInterface $query = null
    ): HlBlockDataProviderFetcher {
        $hlDataProvider = HlBlockDataProvider::initByTableName($hlTableName);
        return new HlBlockDataProviderFetcher($hlDataProvider, $keyInfoList, $linkedModelKey, $query);
    }

    public function __construct(
        DataProviderInterface $hlDataProvider,
        KeyInfoList $keyInfoList,
        string $linkedModelKey = 'UF_XML_ID',
        ?QueryCriteriaInterface $query = null
    ) {
        parent::__construct($keyInfoList, $linkedModelKey);
        $this->query = $query;
        $this->hlDataProvider = $hlDataProvider;
    }

    protected function getLinkedDataList(ModelCollection $collection): iterable
    {
        $query = $this->query ?? new QueryCriteria();
        $keyValueList = $this->keyInfoList->getKeyValuesFromCollection($collection);
        if (empty($keyValueList)) {
            return [];
        }

        $query->addCriteria('UF_XML_ID', CompareRuleInterface::EQUAL, $keyValueList);
        return $this->hlDataProvider->getData($query);
    }
}
