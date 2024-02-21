# Расширение Fetcher для библиотеки bx.model

* DataProviderFetcher - для работы с провайдером данных
* DataProviderMultiFetcher - для работы с провайдером данных с привязкой к нескольким свойствам исходной модели
* EnumDataProviderFetcher - для работы со списками свойств инфоблока
* FileDataProviderFetcher - для работы c записями из b_file
* HlBlockDataProviderFetcher - для работы с элементами hl блока
* IblockPropsDataProviderFetcher - для работы со значениями пользовательских свойств инфоблока
* IblockPropsDefinitionFetcher - для работы с описанием пользовательских свойств инфоблока
* MultiIblockPropsDataProviderFetcher - для работы со значениями пользовательских свойств инфоблока с привязкой к нескольким свойствам исходной модели
* SectionDataProviderFetcher - для работы с разделами инфоблока
* UserDataProviderFetcher - для работы с записями пользователей


*Пример работы DataProviderFetcher (как декоратор коллекции):*

```php
use Bx\Model\Fetcher\Ext\DataProviderFetcher;
use BX\Data\Provider\DataManagerDataProvider;
use Bitrix\Main\UserTable;
use Bx\Model\Fetcher\Ext\KeyInfo;
use Data\Provider\QueryCriteria;
use Data\Provider\Interfaces\CompareRuleInterface;

$userDataProvider = new DataManagerDataProvider(UserTable::class);
$keyInfo = new KeyInfo();
$keyInfo->foreignKey = 'userId'; // ключ модели для связи с данными из провайдера данных
$keyInfo->keyForSave = 'user'; // ключ для сохранения связываемых данных
$query = new QueryCriteria();
$query->addCompareRule('ACTIVE', CompareRuleInterface::EQUAL, 'Y'); // выбираем только активных пользователей
$userFetcher = new DataProviderFetcher(
    $userDataProvider,
    $keyInfo,
    'ID',
    $query
);

$customService = new CustomService();
$someCollection = $customService->getList(['select' => ['id', 'name', 'userId']]);

$firstElementCollection = $someCollection->first();
$firstElementCollection->hasValueKey('user'); // false

$userFetcher->fill($someCollection); // загружаем данные о пользователях

$firstElementCollection = $someCollection->first();
$firstElementCollection->hasValueKey('user'); // true
$userData = $firstElementCollection->getValueByKey('user');
echo $firstElementCollection->getValueByKey('userId'); // 1
echo $userData['ID']; // 1
echo $userData['NAME'];
echo $userData['LAST_NAME'];
```

*Пример работы DataProviderFetcher (как часть сервиса):*

```php
use Bx\Model\BaseLinkedModelService;
use Bx\Model\Fetcher\Ext\DataProviderFetcher;
use BX\Data\Provider\DataManagerDataProvider;
use Bitrix\Main\UserTable;
use Bx\Model\Fetcher\Ext\KeyInfo;
use Data\Provider\QueryCriteria;
use Data\Provider\Interfaces\CompareRuleInterface;
use Data\Provider\Interfaces\DataProviderInterface;

class CustomService extends BaseLinkedModelService 
{
    private DataProviderInterface $userDataProvider;
    
    public function __construct(DataProviderInterface $userDataProvider)  
    {
        $this->userDataProvider = $userDataProvider;
    }

    protected function getLinkedFields(): array
    {
        $keyInfo = new KeyInfo();
        $keyInfo->foreignKey = 'userId'; // ключ модели для связи с данными из провайдера данных
        $keyInfo->keyForSave = 'user'; // ключ для сохранения связываемых данных
        $query = new QueryCriteria();
        $query->addCompareRule('ACTIVE', CompareRuleInterface::EQUAL, 'Y'); // выбираем только активных пользователей
        $userFetcher = new DataProviderFetcher(
            $this->userDataProvider,
            $keyInfo,
            'ID',
            $query
        );
        
        $userFetcher->setCompareCallback(function (CusomModel $model, array $linkedData) {
            return $model->isImportant() && $model->getUserId() === $linkedData['ID'];
        }); // можем установить произвольное сопоставление данных с моделью
        
        $userFetcher->setModifyCallback(function (array $linkedData): array {
            return [
                'id' => $linkedData['ID'],
                'fullName' => trim(implode(
                    ' ', 
                    [
                        $linkedData['LAST_NAME'], 
                        $linkedData['NAME'], 
                        $linkedData['SECOND_NAME']
                    ]
                )),
            ];
        }); // модификация данных перед привязкой к модели
        
        return [
            'user' => $userFetcher
        ];
    }

    protected function getInternalList(array $params, UserContextInterface $userContext = null): ModelCollection
    {
        // Выборка коллекции
    }
}
```