<?php

namespace Bx\Model\Fetcher\Ext;

use Bx\Model\Interfaces\AggregateModelInterface;
use Bx\Model\Interfaces\DerivativeModelInterface;
use Bx\Model\Interfaces\FetcherModelInterface;
use Bx\Model\ModelCollection;
use Exception;

abstract class BaseMultiFetcher implements FetcherModelInterface
{
    protected KeyInfoList $keyInfoList;
    protected string $linkedModelKey;
    /**
     * @var ?callable
     */
    protected $modifyCallback = null;

    public function __construct(KeyInfoList $keyInfoList, string $linkedModelKey)
    {
        $this->keyInfoList = $keyInfoList;
        $this->linkedModelKey = $linkedModelKey;
    }

    abstract protected function getLinkedDataList(ModelCollection $collection): iterable;

    public function fill(ModelCollection $collection)
    {
        $linkedDataList = $this->getLinkedDataList($collection);
        if (is_callable($this->modifyCallback)) {
            $linkedDataList = $this->getModifyLinkedDataList($linkedDataList, $this->modifyCallback);
        }

        $this->keyInfoList->loadValues($collection, $linkedDataList, $this->linkedModelKey);
    }

    private function getModifyLinkedDataList(iterable $linkedDataList, callable $modifyCallback): array
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
        throw new Exception('castTo is not implemented in BaseMultiFetcher class');
    }

    /**
     * @param DerivativeModelInterface|class-string $derivativeModelClass
     * @return FetcherModelInterface
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType,MoreSpecificImplementedParamType
     */
    public function loadAs(string $derivativeModelClass): FetcherModelInterface
    {
        throw new Exception('loadAs is not implemented in BaseMultiFetcher class');
    }

    /**
     * @throws Exception
     */
    public function setCompareCallback(callable $fn): FetcherModelInterface
    {
        throw new Exception('setCompareCallback is not implemented in BaseMultiFetcher class');
    }

    /**
     * @throws Exception
     */
    public function setModifyCallback(callable $fn): FetcherModelInterface
    {
        $this->modifyCallback = $fn;
        return $this;
    }

    protected function getForeignKeyValues(ModelCollection $collection): array
    {
        return $this->keyInfoList->getKeyValuesFromCollection($collection);
    }
}
