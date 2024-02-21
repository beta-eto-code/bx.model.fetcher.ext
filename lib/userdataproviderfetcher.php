<?php

namespace Bx\Model\Fetcher\Ext;

use Bitrix\Main\UserTable;
use BX\Data\Provider\DataManagerDataProvider;
use Bx\Model\ModelCollection;
use Bx\Model\Models\User;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\QueryCriteria;
use Exception;

class UserDataProviderFetcher extends BaseMultiFetcher
{
    private ?QueryCriteriaInterface $query;
    private DataProviderInterface $userDataProvider;

    public function __construct(
        KeyInfoList $keyInfoList,
        ?QueryCriteriaInterface $query = null,
        ?DataProviderInterface $userDataProvider = null
    ) {
        parent::__construct($keyInfoList, 'ID');
        $this->query = $query;
        $this->userDataProvider = $userDataProvider ?? new DataManagerDataProvider(UserTable::class);
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
            User::class
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
        return $this->userDataProvider->getData($query);
    }
}
