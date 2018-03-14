<?php
/**
 * Created by PhpStorm.
 * User: Yarmaliuk Mikhail
 * Date: 29.09.2017
 * Time: 12:28
 */

namespace MP\SelectModel;

use Yii;
use kartik\select2\Select2;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;
use yii\helpers\Json;
use yii\widgets\InputWidget;

/**
 * Class    MPModelSelect
 * @package MP\SelectModel
 * @author  Yarmaliuk Mikhail
 * @version 1.0
 *
 * Define widget action in controller!
 * @see     MPModelSelectAction
 */
class MPModelSelect extends InputWidget
{
    /**
     * @var string|ActiveRecord
     */
    public $searchModel;

    /**
     * @var string
     */
    public $titleField;

    /**
     * @var string
     */
    public $valueField;

    /**
     * Example:
     *
     * - search by id or title
     *  [
     *      'id', 'title',
     *  ]
     *
     * - search by related input
     *  [
     *      'category_id' => new JsExpression('$("#category-id").val()'),
     *  ]
     *  or
     *  [
     *      'category_id' => [
     *          new JsExpression('$("#category-id").val()'),
     *          'select' => '#category-id', // Required if previous key is JsExpression. jQuery select related input by this field and add change trigger
     *          'reset' => true, // reset field after related input change
     *      ],
     *  ]
     *  or
     *  [
     *      'category_id' => [
     *          '#category-id',
     *          'reset' => true, // reset field after related input change
     *      ],
     *  ]
     *
     * @var array
     */
    public $searchFields;

    /**
     * @var array
     */
    public $dropdownOptions = [];

    /**
     * @var string|array
     */
    public $actionUrl;

    /**
     * @var bool
     */
    public $autoFillValue = true;

    /**
     * @var string
     */
    private $encryptionKey;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!empty(Yii::$app->params['MPModelSelect']['encryptionKey'])) {
            $this->encryptionKey = Yii::$app->params['MPModelSelect']['encryptionKey'];
        } else {
            throw new InvalidConfigException('Required `encryptionKey` param isn\'t set.');
        }

        if (empty($this->actionUrl)) {
            $this->actionUrl = [Yii::$app->controller->id . '/model-search'];
        }

        if (empty($this->searchModel)) {
            throw new InvalidConfigException(Yii::t('app', 'Widget param "searchModel" cannot be empty'));
        }

        if (empty($this->searchFields)) {
            throw new InvalidConfigException(Yii::t('app', 'Widget param "searchFields" cannot be empty'));
        }

        if (empty($this->valueField)) {
            throw new InvalidConfigException(Yii::t('app', 'Widget param "valueField" cannot be empty'));
        }

        if (empty($this->value) && $this->hasModel()) {
            $this->value = $this->model->{$this->attribute};
        }
    }

    /**
     * @inheritdoc
     */
    public function run(): string
    {
        parent::run();

        $this->registerAsset();

        $valueDesc = NULL;

        if (!empty($this->value) && $this->autoFillValue) {
            $queryValueDesc = ($this->searchModel)::find()->where([$this->valueField => $this->value]);

            if (!empty($this->value) && $this->value !== 0) {
                if (is_array($this->value)) {
                    $valueDesc = $queryValueDesc->all();

                    if (!empty($valueDesc)) {
                        $valueDesc = ArrayHelper::map($valueDesc, $this->valueField, $this->titleField);
                    }
                } else {
                    $valueDesc = $queryValueDesc->one()->{$this->titleField} ?? NULL;
                }
            }
        }

        $requestData = [
            'q'    => new JsExpression('params.term'),
            'page' => new JsExpression('params.page'),
        ];

        foreach ($this->searchFields as $searchField => $relatedInputSelect) {
            $relatedInputSelect = $this->handleSeachField($relatedInputSelect);

            if (is_string($searchField) && $relatedInputSelect instanceof JsExpression) {
                $requestData[$searchField]        = $relatedInputSelect;
                $this->searchFields[$searchField] = $searchField;
            }
        }

        $options = [
            'attribute'     => $this->attribute,
            'name'          => $this->name,
            'model'         => $this->model,
            'value'         => $this->value,
            'initValueText' => $valueDesc,
            'pluginOptions' => [
                'allowClear'         => true,
                'minimumInputLength' => 2,
                'maximumInputLength' => 100,
                'ajax'               => [
                    'url'            => Url::to(array_merge((array) $this->actionUrl, ['mpDataMS' => $this->encodeData()])),
                    'dataType'       => 'json',
                    'delay'          => 250,
                    'method'         => 'POST',
                    'data'           => new JsExpression('function(params) { return ' . Json::encode($requestData) . '; }'),
                    'processResults' => new JsExpression('function (data, params) {
                                        params.page = params.page || 1;
                                        return {
                                            results: data.items,
                                            pagination: {
                                                more: (params.page * 30) < data.total_count
                                            }
                                        };
                                    }'),
                    'cache'          => true,
                ],
                'escapeMarkup'       => new JsExpression('function (markup) { return markup; }'),
                'templateResult'     => new JsExpression('formatModel'),
                'templateSelection'  => new JsExpression('formatModelSelection'),
            ],
        ];

        return Select2::widget(ArrayHelper::merge($options, $this->dropdownOptions));
    }

    /**
     * Register widget asset
     *
     * @return void
     */
    private function registerAsset()
    {
        $formatJs = <<<JS
let formatModel = function (model) {
    if (model.loading) {
        return model.text;
    }
    let markup =
'<div class="row">' + 
    '<div class="col-sm-12">' +
        '<b style="margin-left:5px">' + model.title + '</b>' + 
    '</div>' +
'</div>';

    return '<div style="overflow:hidden;">' + markup + '</div>';
};
let formatModelSelection = function (model) {
    return model.id ? (model.title ? model.title : model.text) : model.text;
}
JS;

        $this->view->registerJs($formatJs, View::POS_HEAD);
    }

    /**
     * Encode widget data for transfer in action
     *
     * @return string
     */
    private function encodeData(): string
    {
        return Yii::$app->getSecurity()->encryptByKey(json_encode([
            'model'        => $this->searchModel,
            'titleField'   => $this->titleField,
            'valueField'   => $this->valueField,
            'searchFields' => $this->searchFields,
        ]), $this->encryptionKey);
    }

    /**
     * Handle search field value
     *
     * @param $relatedInputSelect
     *
     * @return NULL|JsExpression
     */
    private function handleSeachField($relatedInputSelect)
    {
        if ($relatedInputSelect instanceof JsExpression) {
            return $relatedInputSelect;
        } elseif (is_array($relatedInputSelect)) {
            $expressionValue = $relatedInputSelect[0];
            $selectId        = $relatedInputSelect['select'] ?? NULL;

            if (!$relatedInputSelect[0] instanceof JsExpression) {
                $expressionValue = new JsExpression("$('$relatedInputSelect[0]').val()");
                $selectId        = $relatedInputSelect[0];

            }

            if (!empty($relatedInputSelect['reset']) && $relatedInputSelect['reset'] === true) {
                $this->view->registerJs("$('$selectId').change(function () { $('#{$this->options['id']}').val('').trigger('change'); });");
            }

            return $expressionValue;
        }

        return NULL;
    }
}