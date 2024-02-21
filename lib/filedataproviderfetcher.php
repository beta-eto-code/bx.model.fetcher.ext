<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Main\FileTable;
use BX\Data\Provider\DataManagerDataProvider;
use Bx\Model\ModelCollection;
use Bx\Model\Models\File;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;
use Exception;

class FileDataProviderFetcher extends BaseMultiFetcher
{
    private ?QueryCriteriaInterface $query;
    private DataProviderInterface $fileDataProvider;

    public function __construct(
        KeyInfoList $keyInfoList,
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $fileDataProvider = null
    ) {
        parent::__construct($keyInfoList, 'ID');
        $this->query = $query;
        $this->fileDataProvider = $fileDataProvider ?? new DataManagerDataProvider(FileTable::class);
    }

    public static function initForSingleKey(
        KeyInfo $keyInfo,
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $fileDataProvider = null
    ): FileDataProviderFetcher {
        $keyInfoList = new KeyInfoList($keyInfo);
        return new FileDataProviderFetcher($keyInfoList, $query, $fileDataProvider);
    }

    /**
     * @throws Exception
     */
    public function fill(ModelCollection $collection)
    {
        $linkedDataList = $this->getLinkedDataList($collection);
        $this->keyInfoList->loadValuesAsModel(
            $collection,
            $linkedDataList,
            $this->linkedModelKey,
            File::class
        );
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
        return $this->fileDataProvider->getData($query);
    }
}
