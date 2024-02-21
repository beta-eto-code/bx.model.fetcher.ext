<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ORM\ElementEntity;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Objectify\EntityObject;
use BX\Data\Provider\DataManagerDataProvider;
use BX\Data\Provider\IblockDataProvider;
use BX\Data\Provider\UserDataProvider;
use Bx\Model\Interfaces\AggregateModelInterface;
use Bx\Model\Interfaces\CollectionItemInterface;
use Bx\Model\Interfaces\DerivativeModelInterface;
use Bx\Model\Interfaces\FetcherModelInterface;
use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\QueryCriteria;
use Exception;

class MultiIblockPropsDataProviderFetcher implements FetcherModelInterface
{
    private string $foreignKey;
    private string $iblockIdKey;
    private string $keyForFill = '';
    private array $iblockProviderList = [];
    /**
     * @var string[]
     */
    private array $multiPropNames = [];

    private DataProviderInterface $iblockPropertyProvider;
    private ?DataProviderInterface $fileDataProvider = null;

    /**
     * @var string[]
     */
    private array $enumSelectFields = [];
    private ?DataProviderInterface $enumDataProvider = null;

    /**
     * @var string[]
     */
    private array $iblockElementSelectFields = [];
    private ?DataProviderInterface $iblockElementDataProvider = null;

    /**
     * @var string[]
     */
    private array $userSelectFields = [];
    private ?DataProviderInterface $userDataProvider = null;

    /**
     * @var string[]
     */
    private array $sectionSelectFields = [];
    private ?DataProviderInterface $sectionDataProvider = null;

    private array $hlTables = [];
    /**
     * @var callable|null
     */
    private $compareCallback = null;
    /**
     * @var callable|null
     */
    private $modifyCallback = null;

    public function __construct(
        string $iblockIdKey,
        string $foreignKey = 'ID',
        ?DataProviderInterface $iblockPropertyProvider = null
    ) {
        $this->foreignKey = $foreignKey;
        $this->iblockIdKey = $iblockIdKey;
        $this->iblockPropertyProvider = $iblockPropertyProvider ??
            new DataManagerDataProvider(PropertyTable::class);
    }

    public function setMultiplePropNames(string ...$multiPropNames): MultiIblockPropsDataProviderFetcher
    {
        $this->multiPropNames = $multiPropNames;
        return $this;
    }

    public function setKeyForFill(string $keyForFill): MultiIblockPropsDataProviderFetcher
    {
        $this->keyForFill = $keyForFill;
        return $this;
    }

    public function setIblockDataProviderById(
        int $iblockId,
        DataProviderInterface $dataProvider
    ): MultiIblockPropsDataProviderFetcher {
        $this->iblockProviderList[$iblockId] = $dataProvider;
        return $this;
    }

    public function useFileFetcher(?DataProviderInterface $fileDataProvider = null): void
    {
        $this->fileDataProvider = $fileDataProvider ?? new DataManagerDataProvider(FileTable::class);
    }

    public function useIblockElementFetcher(
        array $select,
        ?DataProviderInterface $iblockElementDataProvider = null
    ): void {
        $this->iblockElementSelectFields = $select;
        $this->iblockElementDataProvider = $iblockElementDataProvider ??
            new DataManagerDataProvider(ElementTable::class);
    }

    public function useUserService(array $select, ?DataProviderInterface $userDataProvider = null): void
    {
        $this->userSelectFields = $select;
        $this->userDataProvider = $userDataProvider ?? new UserDataProvider();
    }

    public function useEnumFetcher(array $select, ?DataProviderInterface $enumDataProvider = null): void
    {
        $this->enumSelectFields = $select;
        $this->enumDataProvider = $enumDataProvider ??
            new DataManagerDataProvider(PropertyEnumerationTable::class);
    }

    public function useSectionFetcher(array $select, ?DataProviderInterface $sectionDataProvider = null): void
    {
        $this->sectionSelectFields = $select;
        $this->sectionDataProvider = $sectionDataProvider ??
            new DataManagerDataProvider(SectionTable::class);
    }

    public function useHlBlockFetcher(
        array $select,
        string $hlTableName,
        ?DataProviderInterface $hlDataProvider = null
    ): void {
        $this->hlTables[$hlTableName] = [
            'select' => $select,
            'dataProvider' => $hlDataProvider
        ];
    }


    /**
     * @param ModelCollection|CollectionItemInterface[] $collection
     * @return void
     * @psalm-suppress MismatchingDocblockParamType
     * @throws Exception
     */
    public function fill(ModelCollection $collection)
    {
        /**
         * @psalm-suppress PossiblyInvalidMethodCall
         */
        $groupCollection = $collection->groupByKey($this->iblockIdKey);
        $hasMultipleProps = !empty($this->multiPropNames);
        $hasKeyForFill = !empty($this->keyForFill);
        foreach ($groupCollection as $group) {
            $iblockId = (int) ($group->getValue() ?: 0);
            $dataProvider = $this->createIblockDataProviderByIblockId($iblockId);
            $fetcher = IblockPropsDataProviderFetcher::initWithAllProperties(
                $dataProvider,
                $this->foreignKey,
                $this->iblockIdKey,
                [
                    'ID',
                    'CODE',
                    'NAME',
                    'PROPERTY_TYPE',
                    'LINK_IBLOCK_ID',
                    'MULTIPLE',
                    'USER_TYPE',
                    'SORT',
                    'USER_TYPE_SETTINGS'
                ],
                'properties_info',
                null,
                $this->iblockPropertyProvider
            );

            if (is_callable($this->modifyCallback)) {
                $fetcher->setModifyCallback($this->modifyCallback);
            }

            if (is_callable($this->compareCallback)) {
                $fetcher->setCompareCallback($this->compareCallback);
            }

            if ($hasMultipleProps) {
                $fetcher->setMultiplePropNames(...$this->multiPropNames);
            }

            if ($hasKeyForFill) {
                $fetcher->setKeyForFill($this->keyForFill);
            }

            if ($this->fileDataProvider instanceof DataProviderInterface) {
                $fetcher->useFileFetcher($this->fileDataProvider);
            }

            if ($this->iblockElementDataProvider instanceof DataProviderInterface) {
                $fetcher->useIblockElementFetcher($this->iblockElementSelectFields, $this->iblockElementDataProvider);
            }

            if ($this->userDataProvider instanceof DataProviderInterface) {
                $fetcher->useUserService($this->userSelectFields, $this->userDataProvider);
            }

            if ($this->enumDataProvider instanceof DataProviderInterface) {
                $fetcher->useEnumFetcher($this->enumSelectFields, $this->enumDataProvider);
            }

            if ($this->sectionDataProvider instanceof DataProviderInterface) {
                $fetcher->useSectionFetcher($this->sectionSelectFields, $this->sectionDataProvider);
            }

            if (!empty($this->hlTables)) {
                foreach ($this->hlTables as $hlTable) {
                    $selectFields = $hlTable['select'] ?? [];
                    $dataProvider = $hlTable['dataProvider'] ?? null;
                    $fetcher->useHlBlockFetcher($selectFields, $hlTable, $dataProvider);
                }
            }

            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $fetcher->fill($collection);
        }
    }

    private function getPropertyForSelectByIblockId(int $iblockId): array
    {
        $query = new QueryCriteria();
        $query->addCriteria('IBLOCK_ID', CompareRuleInterface::EQUAL, $iblockId);
        $query->addCriteria('ACTIVE', CompareRuleInterface::EQUAL, 'Y');
        $query->setSelect(['ID', 'CODE', 'NAME', 'PROPERTY_TYPE', 'LINK_IBLOCK_ID', 'USER_TYPE', 'USER_TYPE_SETTINGS']);
        $dataList = $this->iblockPropertyProvider->getData($query);

        $resultList = [];
        foreach ($dataList as $propertyData) {
            $code = (string) $propertyData['CODE'] ?: '';
            if (empty($code)) {
                continue;
            }

            $resultList["{$code}_VALUE"] = "{$code}.VALUE";
        }

        return $resultList;
    }

    private function createIblockDataProviderByIblockId(int $iblockId): DataProviderInterface
    {
        if ($this->iblockProviderList[$iblockId] instanceof DataProviderInterface) {
            return $this->iblockProviderList[$iblockId];
        }

        return $this->iblockProviderList[$iblockId] = new class ($iblockId) extends IblockDataProvider
        {
            private ?EntityObject $iblock;
            /**
             * @var ElementEntity|false
             */
            private $elementEntity;
            private int $iblockId;

            public function __construct(int $iblockId)
            {
                Loader::includeModule('iblock');
                $this->iblockId = $iblockId;
                $this->iblock = IblockTable::getList([
                    'filter' => [
                        '=ID' => $iblockId,
                    ],
                    'limit' => 1,
                ])->fetchObject();

                if (empty($this->iblock)) {
                    throw new Exception('iblock is not found');
                }
                if ($this->iblock instanceof \Bitrix\Iblock\Iblock && empty($this->iblock->getApiCode())) {
                    throw new Exception('api code is required for iblock ' . $this->iblockId);
                }

                $this->elementEntity = IblockTable::compileEntity($this->iblock);
                $this->dataManagerClass = $this->elementEntity->getDataClass();
                $this->pkName = 'ID';
            }

            /**
             * @return int
             */
            public function getIblockId(): int
            {
                return $this->iblockId;
            }
        };
    }

    /**
     * @param AggregateModelInterface|class-string $aggregateModelClass
     * @return FetcherModelInterface
     * @throws Exception
     * @psalm-suppress MoreSpecificImplementedParamType,MismatchingDocblockParamType
     */
    public function castTo(string $aggregateModelClass): FetcherModelInterface
    {
        throw new Exception('not implemented');
    }

    /**
     * @param DerivativeModelInterface|class-string $derivativeModelClass
     * @return FetcherModelInterface
     * @throws Exception
     * @psalm-suppress MoreSpecificImplementedParamType,MismatchingDocblockParamType
     */
    public function loadAs(string $derivativeModelClass): FetcherModelInterface
    {
        throw new Exception('not implemented');
    }

    public function setCompareCallback(callable $fn): FetcherModelInterface
    {
        $this->compareCallback = $fn;
        return $this;
    }

    public function setModifyCallback(callable $fn): FetcherModelInterface
    {
        $this->modifyCallback = $fn;
        return $this;
    }
}
