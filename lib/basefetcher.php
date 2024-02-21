<?php

namespace Bx\Model\Fetcher\Ext;

use Bx\Model\Interfaces\AggregateModelInterface;
use Bx\Model\Interfaces\DerivativeModelInterface;
use Bx\Model\Interfaces\FetcherModelInterface;
use Bx\Model\ModelCollection;
use Exception;

abstract class BaseFetcher implements FetcherModelInterface
{
    protected KeyInfo $keyInfo;
    /**
     * @var callable|null
     */
    protected $modifyCallback = null;
    protected string $linkedModelKey;

    public function __construct(KeyInfo $keyInfo, string $linkedModelKey)
    {
        $this->keyInfo = $keyInfo;
        $this->linkedModelKey = $linkedModelKey;
    }

    abstract protected function getLinkedDataList(ModelCollection $collection): iterable;

    public function fill(ModelCollection $collection)
    {
        $linkedDataList = $this->getLinkedDataList($collection);
        if (is_callable($this->modifyCallback)) {
            $linkedDataList = $this->getModifyLinkedDataList($linkedDataList, $this->modifyCallback);
        }

        $this->keyInfo->loadValues($collection, $linkedDataList, $this->linkedModelKey);
    }

    protected function getModifyLinkedDataList(iterable $linkedDataList, callable $modifyCallback): array
    {
        $resultList = [];
        foreach ($linkedDataList as $item) {
            $resultList[] = $modifyCallback($item);
        }
        return $resultList;
    }

    /**
     * @param AggregateModelInterface|class-string $aggregateModelClass
     * @return FetcherModelInterface
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType,MoreSpecificImplementedParamType
     */
    public function castTo(string $aggregateModelClass): FetcherModelInterface
    {
        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        if (!class_exists($aggregateModelClass)) {
            /**
             * @psalm-suppress InvalidCast
             */
            throw new Exception("$aggregateModelClass is not found!");
        }

        if (!is_a($aggregateModelClass, AggregateModelInterface::class, true)) {
            throw new Exception("invalid aggregate model class, instance of AggregateModelInterface excepted");
        }

        if (!empty($this->keyInfo->classForCast)) {
            $this->keyInfo->setAggregateModelClass($this->keyInfo->classForCast, $aggregateModelClass);
        }

        return $this;
    }

    /**
     * @param DerivativeModelInterface|class-string $derivativeModelClass
     * @return FetcherModelInterface
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType,MoreSpecificImplementedParamType
     */
    public function loadAs(string $derivativeModelClass): FetcherModelInterface
    {
        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        if (!class_exists($derivativeModelClass)) {
            /**
             * @psalm-suppress InvalidCast
             */
            throw new Exception("$derivativeModelClass is not found!");
        }

        if (!is_a($derivativeModelClass, DerivativeModelInterface::class, true)) {
            throw new Exception("invalid derivative class, instance of DerivativeModelInterface excepted");
        }

        if (!empty($this->keyInfo->classForCast)) {
            $this->keyInfo->setDerivativeModelClass($this->keyInfo->classForCast, $derivativeModelClass);
        }

        return $this;
    }

    public function setCompareCallback(callable $fn): FetcherModelInterface
    {
        $this->keyInfo->compareCallback = $fn;
        return $this;
    }

    public function setModifyCallback(callable $fn): FetcherModelInterface
    {
        $this->modifyCallback = $fn;
        return $this;
    }

    protected function getForeignKeyValues(ModelCollection $collection): array
    {
        return $this->keyInfo->getKeyValuesFromCollection($collection);
    }
}
