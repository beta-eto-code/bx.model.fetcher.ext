<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Iblock\PropertyTable;
use BX\Data\Provider\DataManagerDataProvider;
use Bx\Model\AbsOptimizedModel;
use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;
use Bx\Model\Fetcher\Ext\Model\PropertyModel;

class IblockPropsDefinitionFetcher extends BaseFetcher
{
    private ?QueryCriteriaInterface $query;
    private DataProviderInterface $propsDataProvider;
    private ?int $defaultIblockId;

    public static function init(
        string $foreignKey,
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $propsDataProvider = null,
        ?int $defaultIblockId = null
    ): IblockPropsDefinitionFetcher {
        $keyInfo = new KeyInfo();
        $keyInfo->foreignKey = $foreignKey ?: 'IBLOCK_ID';
        $keyInfo->classForCast = PropertyModel::class;
        return new IblockPropsDefinitionFetcher(
            $keyInfo,
            $keyInfo->foreignKey,
            $query,
            $propsDataProvider,
            $defaultIblockId
        );
    }

    public function __construct(
        KeyInfo $keyInfo,
        string $linkedModelKey,
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $propsDataProvider = null,
        ?int $defaultIblockId = null
    ) {
        $keyInfo->isMultiple = true;
        $keyInfo->foreignKey = $keyInfo->foreignKey ?: 'IBLOCK_ID';
        parent::__construct($keyInfo, $linkedModelKey);
        $this->query = $query;
        $this->propsDataProvider = $propsDataProvider ?? new DataManagerDataProvider(PropertyTable::class);
        $this->defaultIblockId = $defaultIblockId;
    }

    public function fillAndGetPropertyList(ModelCollection $collection): iterable
    {
        $linkedDataList = $this->getLinkedDataList($collection);
        if (is_callable($this->modifyCallback)) {
            $linkedDataList = $this->getModifyLinkedDataList($linkedDataList, $this->modifyCallback);
        }

        $this->keyInfo->loadValues($collection, $linkedDataList, $this->linkedModelKey);
        if (is_a($this->keyInfo->classForCast, AbsOptimizedModel::class, true)) {
            $linkedDataList = new ModelCollection($linkedDataList, $this->keyInfo->classForCast);
        }

        return $linkedDataList;
    }

    protected function getLinkedDataList(ModelCollection $collection): iterable
    {
        $iblockIdList = $this->keyInfo->getKeyValuesFromCollection($collection);
        if (empty($iblockIdList) && !empty($this->defaultIblockId)) {
            $iblockIdList = [$this->defaultIblockId];
        }

        if (empty($iblockIdList)) {
            return [];
        }

        $query = $this->query ?? new QueryCriteria();
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $iblockIdList);
        return $this->propsDataProvider->getData($query);
    }
}
