<?php

namespace Bx\Model\Fetcher\Ext;

use Bx\Model\AbsOptimizedModel;
use Bx\Model\Interfaces\AggregateModelInterface;
use Bx\Model\Interfaces\DerivativeModelInterface;
use Bx\Model\ModelCollection;
use Exception;

class KeyInfo
{
    public string $foreignKey = '';
    public string $keyForSave = '';
    public bool $isMultiple = false;
    /**
     * @var ?callable
     */
    public $valueGetterCallback = null;
    /**
     * @var ?callable
     */
    public $valueSetterCallback = null;
    /**
     * @var AbsOptimizedModel|class-string|null
     */
    public $classForCast = null;
    /**
     * @var callable|null
     */
    public $compareCallback = null;
    /**
     * @var DerivativeModelInterface|class-string|null
     */
    private $derivativeModelClass = null;
    /**
     * @var AggregateModelInterface|class-string|null
     */
    private $aggregateModelClass = null;

    /**
     * @param AbsOptimizedModel|class-string $baseModelClass
     * @param DerivativeModelInterface|class-string $derivativeModelClass
     * @return void
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType
     */
    public function setDerivativeModelClass(string $baseModelClass, string $derivativeModelClass): void
    {
        if (!is_a($baseModelClass, AbsOptimizedModel::class, true)) {
            throw new Exception("invalid model class");
        }

        if (!is_a($derivativeModelClass, DerivativeModelInterface::class, true)) {
            throw new Exception("invalid derivative class, instance of DerivativeModelInterface excepted");
        }

        $this->classForCast = $baseModelClass;
        $this->derivativeModelClass = $derivativeModelClass;
    }

    /**
     * @param AbsOptimizedModel|class-string $baseModelClass
     * @param AggregateModelInterface|class-string $aggregateModelClass
     * @return void
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType
     */
    public function setAggregateModelClass(string $baseModelClass, string $aggregateModelClass): void
    {
        if (!is_a($baseModelClass, AbsOptimizedModel::class, true)) {
            throw new Exception("invalid model class");
        }

        if (!is_a($aggregateModelClass, AggregateModelInterface::class, true)) {
            throw new Exception("invalid derivative class, instance of AggregateModelInterface excepted");
        }

        $this->classForCast = $baseModelClass;
        $this->aggregateModelClass = $aggregateModelClass;
        $this->isMultiple = true;
    }

    public function getKeyValuesFromCollection(ModelCollection $collection): array
    {
        $hasValueGetter = !is_null($this->valueGetterCallback) && is_callable($this->valueGetterCallback);
        $resultList = [];
        foreach ($collection as $item) {
            $value = $hasValueGetter ? ($this->valueGetterCallback)($item) : $item->getValueByKey($this->foreignKey);
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $resultList = array_merge($resultList, $value);
            } else {
                $resultList[] = $value;
            }
        }
        return array_unique(array_filter($resultList));
    }

    public function loadValues(
        ModelCollection $targetCollection,
        iterable $linkedDataList,
        string $linkedKey
    ): void {
        foreach ($targetCollection as $model) {
            if ($this->isMultiple) {
                $this->loadMultipleValueToModel($model, $linkedDataList, $linkedKey);
            } else {
                $this->loadSingleValueToModel($model, $linkedDataList, $linkedKey);
            }
        }
    }

    private function loadMultipleValueToModel(
        AbsOptimizedModel $model,
        iterable $linkedDataList,
        string $linkedKey
    ): void {
        $hasCompareCallback = !is_null($this->compareCallback) && is_callable($this->compareCallback);
        $originalKeyValue = (array) ($model[$this->foreignKey] ?? []);
        if (empty($originalKeyValue) && !$hasCompareCallback) {
            return;
        }

        $filteredDataList = [];
        foreach ($linkedDataList as $linkedItem) {
            $linkedKeyValue = $linkedItem[$linkedKey] ?? null;
            if (empty($linkedKeyValue) && !$hasCompareCallback) {
                continue;
            }

            $isValidItem = $hasCompareCallback ?
                ($this->compareCallback)($model, $linkedItem) :
                in_array($linkedKeyValue, $originalKeyValue);

            if ($isValidItem) {
                $filteredDataList[] = $linkedItem;
            }
        }

        $filteredDataList = $this->getUpdatedLinkedList($filteredDataList);
        if (is_callable($this->valueSetterCallback)) {
            ($this->valueSetterCallback)($model, $filteredDataList);
            return;
        }

        $model[$this->keyForSave] = $filteredDataList;
    }

    private function getUpdatedLinkedList(iterable $linkedList): iterable
    {
        if (is_a($this->classForCast, AbsOptimizedModel::class, true)) {
            $linkedList = new ModelCollection($linkedList, $this->classForCast);
            if (is_a($this->derivativeModelClass, DerivativeModelInterface::class, true)) {
                $newCollection = new ModelCollection([], $this->derivativeModelClass);
                foreach ($linkedList as $item) {
                    /**
                     * @psalm-suppress InvalidStringClass
                     */
                    $newCollection->append($this->derivativeModelClass::init($item));
                }
                $linkedList = $newCollection;
            }

            if (is_a($this->aggregateModelClass, AggregateModelInterface::class, true)) {
                $linkedList = $this->aggregateModelClass::init($linkedList);
            }
        }

        return $linkedList;
    }

    private function loadSingleValueToModel(
        AbsOptimizedModel $model,
        iterable $linkedDataList,
        string $linkedKey
    ): void {
        $hasValueSetterCallback = !is_null($this->valueSetterCallback) && is_callable($this->valueSetterCallback);
        $hasCompareCallback = !is_null($this->compareCallback) && is_callable($this->compareCallback);
        $originalKeyValue = $model[$this->foreignKey] ?? null;
        if (empty($originalKeyValue) && !$hasCompareCallback) {
            return;
        }

        foreach ($linkedDataList as $linkedItem) {
            $linkedKeyValue = $linkedItem[$linkedKey] ?? null;
            if (empty($linkedKeyValue) && !$hasCompareCallback) {
                continue;
            }

            $isValidItem = $hasCompareCallback ?
                ($this->compareCallback)($model, $linkedItem) :
                $linkedKeyValue == $originalKeyValue;

            if ($isValidItem) {
                $updatedItem = $this->getUpdatedLinkedItem($linkedItem);
                if ($hasValueSetterCallback) {
                    ($this->valueSetterCallback)($model, $updatedItem);
                    break;
                }

                $model[$this->keyForSave] = $this->getUpdatedLinkedItem($linkedItem);
                break;
            }
        }
    }

    /**
     * @param mixed $linkedItem
     * @return DerivativeModelInterface|mixed
     */
    private function getUpdatedLinkedItem($linkedItem)
    {
        if (is_a($this->classForCast, AbsOptimizedModel::class, true) && is_array($linkedItem)) {
            $linkedItem = new $this->classForCast($linkedItem);
            if (is_a($this->derivativeModelClass, DerivativeModelInterface::class, true)) {
                $linkedItem = $this->derivativeModelClass::init($linkedItem);
            }
        }

        return $linkedItem;
    }
}
