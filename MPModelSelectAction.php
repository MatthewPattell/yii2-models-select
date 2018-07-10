<?php
/**
 * Created by PhpStorm.
 * User: Yarmaliuk Mikhail
 * Date: 29.09.2017
 * Time: 13:04
 */

namespace MP\SelectModel;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\data\Pagination;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class    MPModelSelectAction
 * @package MP\SelectModel
 * @author  Yarmaliuk Mikhail
 * @version 1.0
 */
class MPModelSelectAction extends Action
{
    /**
     * @var int
     */
    public $minQueryLength = 1;

    /**
     * @var int
     */
    public $pageSize = 30;

    /**
     * @var string
     */
    public $tablePrefix = 'mps';

    /**
     * @var array
     */
    protected $data;

    /**
     * Search models
     *
     * @return array
     */
    public function run(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $encryptionKey = NULL;

        if (!empty(Yii::$app->params['MPModelSelect']['encryptionKey'])) {
            $encryptionKey = Yii::$app->params['MPModelSelect']['encryptionKey'];
        } else {
            throw new InvalidConfigException('Required `encryptionKey` param isn\'t set.');
        }

        $data  = Yii::$app->getSecurity()->decryptByKey(Yii::$app->request->get('mpDataMS'), $encryptionKey);
        $term  = Yii::$app->request->post('q');
        $page  = Yii::$app->request->post('page', 0);
        $items = [];
        $count = 0;

        if (empty($data) || empty($this->data = json_decode($data, true))) {
            throw new NotFoundHttpException();
        }

        if (mb_strlen($term) >= $this->minQueryLength) {
            list($items, $count) = $this->searchModels($term, $page);
        }

        return [
            'total_count' => $count,
            'items'       => $items,
        ];
    }

    /**
     * Find models
     *
     * @param mixed $term
     * @param int   $page
     *
     * @return array
     */
    protected function searchModels($term, int $page): array
    {
        $modelQuery = $this->getQuery($term);
        $count      = $modelQuery->count();
        $items      = $this->getItems($modelQuery, $count, $page);

        return [$items, $count];
    }

    /**
     * Get model items
     *
     * @param ActiveQuery $modelQuery
     * @param int         $count
     * @param int         $page
     *
     * @return array
     */
    protected function getItems($modelQuery, int $count, int $page): array
    {
        $paginationModels = new Pagination([
            'totalCount'      => $count,
            'defaultPageSize' => $this->pageSize,
            'page'            => $page,
        ]);;

        $models = $modelQuery
            ->limit($paginationModels->limit)
            ->offset($paginationModels->offset)
            ->all();

        $models = $this->handleModels($models);

        $items = [];

        foreach ($models as $model) {
            $items[] = [
                'id'    => $model->{$this->data['valueField']},
                'title' => $model->{$this->data['titleField']},
            ];
        }

        return $items;
    }

    /**
     * Handle result models hook
     *
     * @param array $models
     *
     * @return array
     */
    protected function handleModels($models): array
    {
        return $models;
    }

    /**
     * Get model search query
     *
     * @param mixed $term
     *
     * @return mixed
     */
    protected function getQuery($term)
    {
        /** @var ActiveRecord $modelClassName */
        $modelClassName = $this->data['model'];
        $modelQuery     = $modelClassName::find();
        $termQuery      = new Query();

        $modelQuery->from([$this->tablePrefix => $modelClassName::tableName()]);

        foreach ($this->data['searchFields'] as $key => $searchField) {
            if ($key === $searchField) {
                $modelQuery->andFilterWhere(["{$this->tablePrefix}.$key" => Yii::$app->request->post($key, NULL)]);
            } elseif ($key !== $searchField) {
                $termQuery->orFilterWhere(['LIKE', "{$this->tablePrefix}.$searchField", $term]);
            }
        }

        if ($termQuery->where !== NULL) {
            $modelQuery->andWhere($termQuery->where);
        }

        return $modelQuery;
    }
}