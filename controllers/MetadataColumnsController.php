<?php

namespace app\modules\designer\controllers;

use app\core\app\base\Url;
use app\core\app\components\controls\ControlFactory;
use app\core\app\components\controls\PrimarySubmitButtonExternal;
use app\core\app\components\controls\SecondaryButton;
use app\core\app\components\renderer\AppRenderer;
use app\core\app\controllers\AdminController;
use app\modules\designer\assets\IconsSet;
use app\modules\designer\helpers\ObjectTypes;
use app\modules\designer\helpers\TableManager;
use app\modules\designer\helpers\Types;
use app\modules\designer\models\ChoiceParameter;
use app\modules\designer\models\EnumerationValue;
use app\modules\designer\models\ExtendedChoiceParameter;
use app\modules\designer\models\Metadata;
use app\modules\designer\Module;
use Yii;
use app\modules\designer\models\MetadataColumns;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * MetadataColumnsController implements the CRUD actions for MetadataColumns model.
 */
class MetadataColumnsController extends AdminController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete'    => ['POST'],
                    'ajax-sort' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * @param $metadata_id
     * @return string|Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionCreate($metadata_id)
    {
        $model = new MetadataColumns();

        $metadata = $this->findMetadataModel($metadata_id);
        $model->metadata_id = $metadata->id;

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ((new TableManager())->addColumn($model)) {
                $model->saveChoiceParameters();
                $model->saveExtendedChoiceParameters();
                return $this->renderAjax('form-close');
            }
        }

        return $this->renderFormContent($model);
    }

    /**
     * @param $id
     * @return string
     * @throws NotFoundHttpException
     * @throws \yii\base\ErrorException
     * @throws \yii\db\Exception
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ((new TableManager())->changeColumn($model)) {
                $model->saveChoiceParameters();
                $model->saveExtendedChoiceParameters();
                return $this->renderAjax('form-close');
            }
        }

        return $this->renderFormContent($model);
    }

    /**
     * @param MetadataColumns $model
     * @return string
     * @throws \Exception
     */
    protected function renderFormContent($model)
    {
        $formId = 'frm-edit-metadata-column';

        $contentActions = [];

        $contentActions[] = ControlFactory::create(PrimarySubmitButtonExternal::className(), [
            'target'        => '#' . $formId,
            'icon'          => IconsSet::ACTION_SAVE,
            'label'         => Yii::t('app', 'Save and Close'),
            'visible_xs'    => false,
        ]);

        $contentActions[] = ControlFactory::create(PrimarySubmitButtonExternal::className(), [
            'target'        => '#' . $formId,
            'icon'          => IconsSet::ACTION_SAVE,
            'visible_xs'    => true,
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'icon'          => IconsSet::ACTION_CLOSE,
            'options'       => ['onclick' => 'window.parent.postMessage({ action: "closeMetadataColumnEditor"}, "*")'],
        ]);

        $content = $this->render('form', [
            'model'  => $model,
            'formId' => $formId,
        ]);

        return AppRenderer::widget([
            'layout'                    => AppRenderer::LAYOUT_IFRAME,
            'content'                   => $content,
            'contentActions'            => $contentActions,
            'contentRenderContainer'    => false,
            'title'                     => $model->isNewRecord ? Yii::t('app', 'Create Column')  : $model->label,
        ]);
    }

    /**
     * @param $id
     * @return Response
     * @throws NotFoundHttpException
     * @throws \yii\base\ErrorException
     * @throws \yii\db\Exception
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if (!$model->predefined) {
            (new TableManager())->deleteColumn($model);
        }
        return $this->redirect(['/designer/metadata/view', 'id' => $model->metadata_id]);
    }

    /**
     * @param $id
     * @throws NotFoundHttpException
     * @throws \yii\base\ErrorException
     * @throws \yii\db\Exception
     */
    public function actionAjaxDelete($id)
    {
        $model = $this->findModel($id);
        if (!$model->predefined) {
            (new TableManager())->deleteColumn($model);
        }
    }

    /**
     * @param $id
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionAjaxSort($id)
    {
        $model = $this->findModel($id);

        switch (Yii::$app->request->post('dir')) {
            case SORT_ASC:
                $defaultSort = SORT_ASC;
                break;
            case SORT_DESC:
                $defaultSort = SORT_DESC;
                break;
            default:
                $defaultSort = null;
        }

        Yii::$app->db
            ->createCommand()
            ->update(MetadataColumns::tableName(), ['default_sort' => null], ['metadata_id' => $model->metadata_id])
            ->execute();

        if (!is_null($defaultSort)) {
            Yii::$app->db
                ->createCommand()
                ->update(MetadataColumns::tableName(), ['default_sort' => $defaultSort], ['id' => $model->id])
                ->execute();
        }
    }

    /**
     * @param null $id
     * @param null $q
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionGetChoiceParameterColumns($id = null, $q = null, $limit=50)
    {
        $result = ['results' => []];
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!empty($id)) {

            $query = $this->findMetadataModel($id)
                 ->getMetadataColumns()
                 ->select('id, label AS text, type, script_alias AS name, ref_id')
                 ->andWhere([
                     'or',
                     [
                         'and',
                         ['type' => ChoiceParameter::getAllowedTypes()],
                         ['not', ['type' => Types::Reference]]
                     ],
                     [
                         'and',
                         ['type' => Types::Reference],
                         ['ref_id' => Metadata::find()->where(['type' => ObjectTypes::ENUMERATION])->select('id')]
                     ],
                 ])
                 ->andFilterWhere(['like', 'label', $q])
                 ->asArray();
                if($limit > 0)
                    $query->limit($limit);

            $values = array_values($query->all());

            foreach ($values as $value) {

                $refId = $value['ref_id'];
                unset($value['ref_id']);

                if ($value['type'] === Types::Reference) {

                    $choiceList = EnumerationValue::find()
                        ->select('id, name')
                        ->andWhere(['metadata_id' => $refId])
                        ->asArray()
                        ->all();

                    if (count($choiceList) == 0) {
                        continue;
                    }

                    $value['choiceList'] = ArrayHelper::map($choiceList, 'id', 'name');

                }

                $result['results'][] = $value;
            }
        } 
        return $result;
    }

    /**
     * @param null $id
     * @param null $q
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionGetExtendedChoiceParameterColumns($id = null, $q = null)
    {
        $result = [];
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!empty($id)) {
            $query = $this->findMetadataModel($id)
                ->getMetadataColumns()
                ->select('id, label AS text, type')
                ->andWhere([
                    'type'   => ExtendedChoiceParameter::getAllowedTypes(),
                    'ref_id' => Metadata::find()
                        ->andWhere(['type' => ExtendedChoiceParameter::getAllowedMetadataTypes()])
                        ->select('id')
                ])
                ->andFilterWhere(['like', 'label', $q])
                ->asArray()
                ->limit(50);
            $result['results'] = array_values($query->all());
        }
        return $result;
    }

    /**
     * @param null $id
     * @param null $q
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionGetExtendedChoiceParameterDependsColumns($id = null, $q = null)
    {
        $result = [];
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!empty($id)) {

            $metadata = $this->findMetadataModel($id);

            if ($metadata->type === ObjectTypes::TABLE) {

                $ownerColumns = $metadata->owner
                    ->getMetadataColumns()
                    ->select('id, label AS text, type')
                    ->andWhere([
                        'type'   => ExtendedChoiceParameter::getAllowedTypes(),
                        'ref_id' => Metadata::find()
                            ->andWhere(['type' => ExtendedChoiceParameter::getAllowedMetadataTypes()])
                            ->select('id')
                    ])
                    ->andFilterWhere(['like', 'label', $q]);

                $query = $metadata
                    ->getMetadataColumns()
                    ->select([
                        'id'   => 'id',
                        'text' => new Expression("CONCAT_WS('.', '{$metadata->name}', label)"),
                        'type' => 'type',
                    ])
                    ->union($ownerColumns, true)
                    ->andWhere([
                        'type'   => ExtendedChoiceParameter::getAllowedTypes(),
                        'ref_id' => Metadata::find()
                            ->andWhere(['type' => ExtendedChoiceParameter::getAllowedMetadataTypes()])
                            ->select('id')
                    ])
                    ->andWhere(['not', ['name' => MetadataColumns::OWNER_COLUMN_NAME]])
                    ->andFilterWhere(['like', 'label', $q])
                    ->asArray();

            } else {

                $query = $metadata
                    ->getMetadataColumns()
                    ->select('id, label AS text, type')
                    ->andWhere([
                        'type'   => ExtendedChoiceParameter::getAllowedTypes(),
                        'ref_id' => Metadata::find()
                            ->andWhere(['type' => ExtendedChoiceParameter::getAllowedMetadataTypes()])
                            ->select('id')
                    ])
                    ->andFilterWhere(['like', 'label', $q])
                    ->asArray();

            }

            $result['results'] = array_values($query->all());
        }
        return $result;
    }

    /**
     * Finds the MetadataColumns model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return MetadataColumns the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MetadataColumns::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * Finds the Metadata model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Metadata the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findMetadataModel($id)
    {
        if (($model = Metadata::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionDefaultValue()
    {
        $metadataColumn = new MetadataColumns();
        $metadataColumn->load(Yii::$app->request->get());
        Yii::$app->response->format = Response::FORMAT_HTML;
        return $this->renderAjax('_default-value', [
            'model' => $metadataColumn,
        ]);
    }

    public function actionGetColumnsByMetadataId($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $values = ['results' => []];

        $metadata = $this->findMetadataModel($id);

        foreach ($metadata->metadataColumnsExceptHidden as $column) {
            $values['results'][] = [
                'id'    => $column->id,
                'text'   => $column->label,
                'type'   => $column->type,
                'name'   => $column->script_alias,
                'ref_id' => $column->ref_id,
            ];
        }

        return $values;
    }
}