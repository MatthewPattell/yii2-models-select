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
        $page  = Yii::$app->request->post('page');
        $items = [];
        $count = 0;

        if (empty($data) || empty($data = json_decode($data, true))) {
            throw new NotFoundHttpException();
        }

        if (mb_strlen($term) >= $this->minQueryLength) {
            /** @var ActiveRecord $modelClassName */
            $modelClassName = $data['model'];
            $modelQuery     = $modelClassName::find();
            $termQuery      = new Query();

            foreach ($data['searchFields'] as $key => $searchField) {
                if ($key === $searchField) {
                    $modelQuery->andFilterWhere([$key => Yii::$app->request->post($key, NULL)]);
                } elseif ($key !== $searchField) {
                    $termQuery->orFilterWhere(['LIKE', $searchField, $term]);
                }
            }

            if ($termQuery->where !== NULL) {
                $modelQuery->andWhere($termQuery->where);
            }

            $count = $modelQuery->count();

            $paginationModels = new Pagination([
                'totalCount'      => $count,
                'defaultPageSize' => 30,
                'page'            => $page,
            ]);

            $models = $modelQuery
                ->limit($paginationModels->limit)
                ->offset($paginationModels->offset)
                ->all();

            foreach ($models as $model) {
                $items[] = [
                    'id'    => $model->{$data['valueField']},
                    'title' => $model->{$data['titleField']},
                ];
            }
        }

        return [
            'total_count' => $count,
            'items'       => $items,
        ];
    }
}