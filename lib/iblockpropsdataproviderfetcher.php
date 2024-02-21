<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\FileTable;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use BX\Data\Provider\DataManagerDataProvider;
use BX\Data\Provider\IblockDataProvider;
use BX\Data\Provider\UserDataProvider;
use Bx\Model\AbsOptimizedModel;
use Bx\Model\Interfaces\CollectionItemInterface;
use Bx\Model\Interfaces\FetcherModelInterface;
use Bx\Model\ModelCollection;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\QueryCriteria;
use Exception;

class IblockPropsDataProviderFetcher extends BaseFetcher
{
    private DataProviderInterface $dataProvider;
    /**
     * @var string[]
     */
    private array $propNames;
    /**
     * @var string[]
     */
    private array $multiPropNames = [];
    private string $keyForFill = '';
    private string $destKey;

    private ?IblockPropsDefinitionFetcher $propDefinitionFetcher = null;
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

    public function __construct(
        DataProviderInterface $dataProvider,
        array $propNames,
        string $foreignKey = 'ID'
    ) {
        $keyInfo = new KeyInfo();
        $keyInfo->foreignKey = $foreignKey;

        parent::__construct($keyInfo, $foreignKey);
        $this->destKey = 'ID';
        $this->propNames = $propNames;
        $this->dataProvider = $dataProvider;
    }

    /**
     * @throws Exception
     */
    public static function initWithAllProperties(
        DataProviderInterface $dataProvider,
        string $foreignKey = 'ID',
        string $iblockIdKey = 'IBLOCK_ID',
        array $propSelectFields = [],
        string $propKeyForFill = '',
        ?string $propModelClass = null,
        ?DataProviderInterface $iblockPropDataProvider = null
    ): IblockPropsDataProviderFetcher {
        $iblockId = static::getIblockId($dataProvider);
        if (empty($iblockId)) {
            throw new Exception('Iblock is not found');
        }

        $propsFetcher = new IblockPropsDataProviderFetcher($dataProvider, [], $foreignKey);
        /**
         * @psalm-suppress ArgumentTypeCoercion
         */
        $propsFetcher->useIblockPropsFetcher(
            $propSelectFields,
            $propKeyForFill,
            $propModelClass,
            $iblockPropDataProvider,
            $iblockIdKey
        );

        return $propsFetcher;
    }

    /**
     * Определяем нужно ли загружать информацию о пользовательских свойствах
     * @param array $selectFields
     * @param string $keyForFill
     * @param AbsOptimizedModel|class-string|null $propModelClass
     * @param DataProviderInterface|null $iblockPropDataProvider
     * @param string $foreignKeyKey
     * @return $this
     * @psalm-suppress MismatchingDocblockParamType
     */
    public function useIblockPropsFetcher(
        array $selectFields,
        string $keyForFill = '',
        ?string $propModelClass = null,
        ?DataProviderInterface $iblockPropDataProvider = null,
        string $foreignKeyKey = 'IBLOCK_ID'
    ): IblockPropsDataProviderFetcher {
        $keyInfo = new KeyInfo();
        $keyInfo->keyForSave = $keyForFill ?: 'properties_info';
        $keyInfo->classForCast = $propModelClass;
        $keyInfo->foreignKey = $foreignKeyKey;
        $keyInfo->compareCallback = function (): bool {
            return true;
        };

        $query = new QueryCriteria();
        $query->setOrderBy('SORT');
        $query->addCriteria('ACTIVE', CompareRuleInterface::EQUAL, 'Y');
        if (!empty($selectFields)) {
            $query->setSelect($selectFields);
        }

        $this->propDefinitionFetcher = new IblockPropsDefinitionFetcher(
            $keyInfo,
            'IBLOCK_ID',
            $query,
            $iblockPropDataProvider,
            static::getIblockId($this->dataProvider)
        );

        return $this;
    }

    public function useFileFetcher(?DataProviderInterface $fileDataProvider = null): void
    {
        $this->fileDataProvider = $fileDataProvider ?? new DataManagerDataProvider(FileTable::class);
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
     * Указываем множественные свойства
     * @param string ...$multiPropNames
     * @return $this
     */
    public function setMultiplePropNames(string ...$multiPropNames): IblockPropsDataProviderFetcher
    {
        $this->multiPropNames = $multiPropNames;
        return $this;
    }

    /**
     * Определяем общий ключ для сохранения значений свойств
     * @param string $keyForFill
     * @return $this
     */
    public function setKeyForFill(string $keyForFill): IblockPropsDataProviderFetcher
    {
        $this->keyForFill = $keyForFill;
        return $this;
    }

    /**
     * @param ModelCollection|CollectionItemInterface[] $collection
     * @return void
     * @psalm-suppress MismatchingDocblockParamType
     * @throws Exception
     */
    public function fill(ModelCollection $collection): void
    {
        $propertyList = [];
        if ($this->propDefinitionFetcher instanceof FetcherModelInterface) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $propertyList = $this->propDefinitionFetcher->fillAndGetPropertyList($collection);
            if (empty($this->propNames)) {
                $this->propNames = $this->makePropNamesFromPropDefinitionList($propertyList);
            }
        }

        $hasKeyForFill = !empty($this->keyForFill);
        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        $linkedCollection = $this->getLinkedDataList($collection);
        foreach ($collection as $item) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $propValues = $this->getPropValuesFromPropCollection($linkedCollection, $item);
            if (empty($propValues)) {
                continue;
            }

            $propsTarget = $hasKeyForFill ? [] : $item;
            foreach ($propValues as $key => $value) {
                if ($key === $this->destKey) {
                    continue;
                }

                /**
                 * @psalm-suppress UndefinedInterfaceMethod
                 */
                $propsTarget[$key] = $value;
            }

            if ($hasKeyForFill) {
                /**
                 * @psalm-suppress UndefinedInterfaceMethod
                 */
                $item[$this->keyForFill] = $propsTarget;
            }
        }

        $needLoadFiles = $this->fileDataProvider instanceof DataProviderInterface;
        if ($needLoadFiles) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillFiles($propertyList, $collection);
        }

        $needLoadElements = $this->iblockElementDataProvider instanceof DataProviderInterface;
        if ($needLoadElements) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillIblockElements($propertyList, $collection);
        }

        $needLoadUsers = $this->userDataProvider instanceof DataProviderInterface;
        if ($needLoadUsers) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillUsers($propertyList, $collection);
        }

        $needLoadEnum = $this->enumDataProvider instanceof DataProviderInterface;
        if ($needLoadEnum) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillEnumList($propertyList, $collection);
        }

        $needLoadSections = $this->sectionDataProvider instanceof DataProviderInterface;
        if ($needLoadSections) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillSections($propertyList, $collection);
        }

        $needLoadHl = !empty($this->hlTables);
        if ($needLoadHl) {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $this->fillHlBlockElements($propertyList, $collection);
        }
    }

    /**
     * @throws Exception
     */
    private function fillFiles(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        $keyInfoList = new KeyInfoList();
        foreach ($propertyList as $property) {
            $propertyType = $property['PROPERTY_TYPE'] ?? null;
            if ($propertyType !== 'F') {
                continue;
            }

            $keyInfo = $this->buildKeyInfo($property);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }
        }

        $fileFetcher = new FileDataProviderFetcher($keyInfoList, null, $this->fileDataProvider);
        $fileFetcher->fill($collection);
    }

    private function fillIblockElements(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        if (empty($this->iblockElementDataProvider)) {
            return;
        }

        $keyInfoList = new KeyInfoList();
        foreach ($propertyList as $property) {
            $propertyType = $property['PROPERTY_TYPE'] ?? null;
            if ($propertyType !== 'E') {
                continue;
            }

            $keyInfo = $this->buildKeyInfo($property);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }
        }

        $query = new QueryCriteria();
        if (!empty($this->iblockElementSelectFields)) {
            $query->setSelect($this->iblockElementSelectFields);
        }

        $elementFetcher = new DataProviderMultiFetcher(
            $this->iblockElementDataProvider,
            $keyInfoList,
            'ID',
            $query
        );
        $elementFetcher->fill($collection);
    }

    /**
     * @throws Exception
     */
    private function fillUsers(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        $keyInfoList = new KeyInfoList();
        foreach ($propertyList as $property) {
            $propertyType = $property['USER_TYPE'] ?? null;
            if (!in_array($propertyType, ['UserID', 'employee'])) {
                continue;
            }

            $keyInfo = $this->buildKeyInfo($property);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }
        }

        $query = new QueryCriteria();
        if (!empty($this->userSelectFields)) {
            $query->setSelect($this->userSelectFields);
        }

        $userFetcher = new UserDataProviderFetcher(
            $keyInfoList,
            $query,
            $this->userDataProvider
        );
        $userFetcher->fill($collection);
    }

    private function fillEnumList(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        $keyInfoList = new KeyInfoList();
        foreach ($propertyList as $property) {
            $propertyType = $property['PROPERTY_TYPE'] ?? null;
            if ($propertyType !== 'L') {
                continue;
            }

            $keyInfo = $this->buildKeyInfo($property);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }
        }

        $query = new QueryCriteria();
        if (!empty($this->enumSelectFields)) {
            $query->setSelect($this->enumSelectFields);
        }

        $enumFetcher = new EnumDataProviderFetcher(
            $keyInfoList,
            'ID',
            $query,
            $this->enumDataProvider
        );
        $enumFetcher->fill($collection);
    }

    private function fillSections(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        $keyInfoList = new KeyInfoList();
        foreach ($propertyList as $property) {
            $propertyType = $property['PROPERTY_TYPE'] ?? null;
            if ($propertyType !== 'G') {
                continue;
            }

            $keyInfo = $this->buildKeyInfo($property);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }
        }

        $query = new QueryCriteria();
        if (!empty($this->sectionSelectFields)) {
            $query->setSelect($this->sectionSelectFields);
        }

        $enumFetcher = new SectionDataProviderFetcher(
            $keyInfoList,
            'ID',
            $query,
            $this->sectionDataProvider
        );
        $enumFetcher->fill($collection);
    }

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function fillHlBlockElements(array $propertyList, ModelCollection $collection): void
    {
        if (empty($propertyList) || $collection->count() === 0) {
            return;
        }

        $fetcherList = [];
        foreach ($propertyList as $property) {
            $propertyType = $property['USER_TYPE'] ?? null;
            $typeSettingsSource = (string) ($property['USER_TYPE_SETTINGS'] ?? '');
            if ($propertyType !== 'directory' || empty($typeSettingsSource)) {
                continue;
            }

            $typeSettings = unserialize($typeSettingsSource);
            if ($typeSettings === false) {
                continue;
            }

            $hlTableName = $typeSettings['TABLE_NAME'] ?? '';
            if (empty($hlTableName) || empty($this->hlTables[$hlTableName])) {
                continue;
            }

            $keyInfoList = $fetcherList[$hlTableName] ?? new KeyInfoList();
            $keyInfo = $this->buildKeyInfo($property, 'UF_XML_ID', false);
            if (!empty($keyInfo)) {
                $keyInfoList->addKey($keyInfo);
            }

            $fetcherList[$hlTableName] = $keyInfoList;
        }

        foreach ($fetcherList as $hlTableName => $keyInfoList) {
            $hlInfo = $this->hlTables[$hlTableName] ?? [];
            $select = $hlInfo['select'] ?? [];
            $query = new QueryCriteria();
            if (!empty($select)) {
                $query->setSelect($select);
            }

            $hlDataProvider = $hlInfo['dataProvider'] ?? null;
            $hlFetcher = $hlDataProvider instanceof DataProviderInterface ?
                new HlBlockDataProviderFetcher($hlDataProvider, $keyInfoList, 'UF_XML_ID', $query) :
                HlBlockDataProviderFetcher::initByTableName(
                    $hlTableName,
                    $keyInfoList,
                    'UF_XML_ID',
                    $query
                );
            $hlFetcher->fill($collection);
        }
    }

    private function buildKeyInfo(array $property, string $destKey = 'ID', bool $castToInt = true): ?KeyInfo
    {
        $hasKeyForFill = !empty($this->keyForFill);
        $propertyCode = $property['CODE'] ?? null;
        if (empty($propertyCode)) {
            return null;
        }

        $propertyIsMultiple = ($property['MULTIPLE'] ?? 'N') === 'Y';
        $valueKey = "{$propertyCode}_VALUE";

        $keyInfo = new KeyInfo();
        $keyInfo->foreignKey = $valueKey;
        if ($hasKeyForFill) {
            /**
             * @param AbsOptimizedModel $model
             * @return mixed
             * @psalm-suppress MissingClosureReturnType
             */
            $keyInfo->valueGetterCallback = function (AbsOptimizedModel $model) use ($valueKey, $castToInt) {
                $properties = $model[$this->keyForFill] ?? [];
                $resultList = (array) ($properties[$valueKey] ?? []);
                if (!$castToInt) {
                    return $resultList;
                }

                return array_map(function ($value): int {
                    return (int) $value;
                }, $resultList);
            };

            /**
             * @param AbsOptimizedModel $model
             * @param mixed $value
             * @return void
             * @psalm-suppress MissingClosureParamType
             */
            $keyInfo->valueSetterCallback = function (AbsOptimizedModel $model, $value) use ($valueKey): void {
                $properties = $model[$this->keyForFill] ?? [];
                $properties[$valueKey] = $value;
                $model[$this->keyForFill] = $properties;
            };
            /**
             * @param AbsOptimizedModel $model
             * @param mixed $data
             * @return bool
             * @psalm-suppress MissingClosureParamType
             */
            $keyInfo->compareCallback = function (
                AbsOptimizedModel $model,
                $data
            ) use (
                $valueKey,
                $destKey,
                $castToInt
            ): bool {
                $properties = $model[$this->keyForFill] ?? [];
                $itemValue = (array) ($properties[$valueKey] ?? []);
                if ($castToInt) {
                    $itemValue = array_map(function ($value): int {
                        return (int)$value;
                    }, $itemValue);
                }

                return in_array($data[$destKey] ?? null, $itemValue);
            };
        }

        $keyInfo->keyForSave = $keyInfo->foreignKey;
        $keyInfo->isMultiple = $propertyIsMultiple;
        return $keyInfo;
    }

    private function makePropNamesFromPropDefinitionList(iterable $propDefinitionList): array
    {
        $propNames = [];
        $isNewIblockApi = $this->dataProvider instanceof IblockDataProvider;
        foreach ($propDefinitionList as $propDefinition) {
            $propCode = $propDefinition['CODE'] ?? null;
            if (empty($propCode)) {
                continue;
            }

            if ($isNewIblockApi) {
                $propNames["{$propCode}_VALUE"] = "{$propCode}.VALUE";
            } else {
                $propNames[] = "PROPERTY_{$propCode}_VALUE";
            }
        }

        return $propNames;
    }

    private function getPropValuesFromPropCollection(array $linkedList, CollectionItemInterface $itemModel): array
    {
        $resultList = [];
        $filteredPropCollection = $this->filterLinkedListByItem($linkedList, $itemModel);
        $hasModifyCallback = !is_null($this->modifyCallback) && is_callable($this->modifyCallback);
        foreach ($filteredPropCollection as $modelProps) {
            if ($hasModifyCallback) {
                /**
                 * @psalm-suppress PossiblyNullFunctionCall
                 */
                $modelProps = ($this->modifyCallback)($modelProps);
            }

            foreach ($modelProps as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if (is_resource($value)) {
                    $resultList[$key][] = $value;
                    continue;
                }

                $valueKey = is_scalar($value) ? $value : md5(serialize($value));
                /**
                 * @psalm-suppress InvalidArrayOffset
                 */
                $resultList[$key][$valueKey] = $value;
            }
        }

        foreach ($resultList as $key => $values) {
            $isMultiProp = in_array($key, $this->multiPropNames) || count($values) > 1;
            $values = array_values($values);
            $resultList[$key] = $isMultiProp ? $values : current($values);
        }

        return $resultList;
    }

    private function filterLinkedListByItem(array $linkedList, CollectionItemInterface $itemModel): array
    {
        $resultList = [];
        $compareCallback = $this->keyInfo->compareCallback;
        if (is_callable($compareCallback)) {
            foreach ($linkedList as $propsItem) {
                if (($compareCallback)($itemModel, $propsItem)) {
                    $resultList[] = $propsItem;
                }
            }

            return $resultList;
        }

        foreach ($linkedList as $propsItem) {
            if ($itemModel->getValueByKey($this->linkedModelKey) == $propsItem[$this->destKey]) {
                $resultList[] = $propsItem;
            }
        }

        return $resultList;
    }

    protected function getLinkedDataList(ModelCollection $collection): array
    {
        $linkedDataList = [];
        $query = $this->query ?? new QueryCriteria();
        $totalSelect = array_merge([$this->destKey], $this->propNames);
        $iterations = (count($totalSelect) / 45);
        $i = 0;
        do {
            $offset = 45;
            $select = array_slice($totalSelect, $offset * $i, $offset);
            $query->setSelect($select);
            $query->addCriteria(
                $this->destKey,
                CompareRuleInterface::IN,
                $this->getForeignKeyValues($collection)
            );
            $data = $this->dataProvider->getData($query);
            $linkedDataList = array_merge_recursive($linkedDataList, $data);
            $i++;
        } while ($i < $iterations);

        return $linkedDataList;
    }

    private static function getIblockId(DataProviderInterface $dataProvider): int
    {
        if (!method_exists($dataProvider, 'getIblockId')) {
            return 0;
        }
        return (int) $dataProvider->getIblockId();
    }
}
