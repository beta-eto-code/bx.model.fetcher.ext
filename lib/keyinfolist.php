<?php

namespace Bx\Model\Fetcher\Ext;

use ArrayIterator;
use Bx\Model\AbsOptimizedModel;
use Bx\Model\ModelCollection;
use Exception;
use Iterator;
use IteratorAggregate;

class KeyInfoList implements IteratorAggregate
{
    /**
     * @var KeyInfo[]
     */
    private array $keyList = [];

    public function __construct(KeyInfo ...$keyList)
    {
        foreach ($keyList as $keyInfo) {
            $this->addKey($keyInfo);
        }
    }

    public function addKey(KeyInfo $keyInfo): void
    {
        $this->keyList[$keyInfo->foreignKey] = $keyInfo;
    }

    /**
     * @return Iterator|KeyInfo[]
     * @psalm-suppress ImplementedReturnTypeMismatch,MismatchingDocblockReturnType
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->keyList);
    }

    public function getKeyValuesFromCollection(ModelCollection $collection): array
    {
        $resultList = [];
        foreach ($this->keyList as $keyInfo) {
            $resultList = array_merge($resultList, $keyInfo->getKeyValuesFromCollection($collection));
        }
        return array_unique(array_filter($resultList));
    }

    public function loadValues(
        ModelCollection $targetCollection,
        iterable $linkedDataList,
        string $linkedKey
    ): void {
        foreach ($this->keyList as $keyInfo) {
            $keyInfo->loadValues($targetCollection, $linkedDataList, $linkedKey);
        }
    }

    /**
     * @param ModelCollection $targetCollection
     * @param iterable $linkedDataList
     * @param string $linkedKey
     * @param AbsOptimizedModel|class-string $modelClass
     * @return void
     * @throws Exception
     * @psalm-suppress MismatchingDocblockParamType
     */
    public function loadValuesAsModel(
        ModelCollection $targetCollection,
        iterable $linkedDataList,
        string $linkedKey,
        string $modelClass
    ): void {
        if (!is_a($modelClass, AbsOptimizedModel::class, true)) {
            throw new Exception('invalid model class');
        }

        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        $linkedDataList = new ModelCollection($linkedDataList, $modelClass);
        foreach ($this->keyList as $keyInfo) {
            $newKeyInfo = clone $keyInfo;
            $newKeyInfo->classForCast = null;
            $newKeyInfo->loadValues($targetCollection, $linkedDataList, $linkedKey);
        }
    }
}
