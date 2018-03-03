Select models widget for Yii2
===========================
Find and select models in select2 input. 

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist matthew-p/yii2-models-select "*"
```

or add

```
"matthew-p/yii2-models-select": "*"
```

to the require section of your `composer.json` file.

Usage
-----

Once the extension is installed, simply use it in your code by:

```php
$form->field($model, 'attribute')->widget(MPModelSelect::class, [
    'searchModel'     => YouActiveRecordModel::class,
    'valueField'      => 'id',
    'titleField'      => 'title',
    'searchFields'    => [
        // convert to orWhere 'id' => query-string and etc.
        'id', 'title', 
        // add related input (will be added to data request and conver to ->andWhere 'category_id' => request value)
        'category_id' => new JsExpression('$("#category-id").val()'),
    ],
    'dropdownOptions' => [
        'options'       => [
            'placeholder' => Yii::t('app', 'Select models ...'),
            'multiple'    => true,
        ],
        'pluginOptions' => [
            'minimumInputLength' => 1,
        ],
    ],
])
```

Add action in controller:
```php
class SampleController extends Controller
{
...
    public function actions(): array
    {
        return array_merge(parent::actions(), [
            'model-search' => [
                'class' => MPModelSelectAction::class,
            ],
        ]);
    }
...
}
```

Define encryption key in params.php:
```
'MPModelSelect'   => [
    'encryptionKey' => 'RandomKey',
],
```

That's all. Check it.