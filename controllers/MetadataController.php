<?php

namespace app\modules\designer\controllers;

use app\core\app\base\Html;
use app\core\app\base\Url;
use app\core\app\components\controls\ControlFactory;
use app\core\app\components\controls\PrimaryButton;
use app\core\app\components\controls\PrimarySubmitButtonExternal;
use app\core\app\components\controls\SecondaryButton;
use app\core\app\components\renderer\AppRenderer;
use app\core\app\controllers\AdminController;
use app\core\helpers\ForbiddenHttpException;
use app\core\helpers\Loader;
use app\core\helpers\RoleHelper;
use app\modules\designer\assets\IconsSet;
use app\modules\designer\generator\Generator;
use app\modules\designer\helpers\ObjectTypes;
use app\modules\designer\helpers\TableManager;
use app\modules\designer\models\EmbedFormSearch;
use app\modules\designer\models\EnumerationValue;
use app\modules\designer\models\EnumerationValueSearch;
use app\modules\designer\models\ListForm;
use app\modules\designer\models\ListFormSearch;
use app\modules\designer\models\MetadataColumnsSearch;
use app\modules\designer\models\MetadataSubsystems;
use app\modules\designer\models\MetadataSubsystemsSearch;
use app\modules\designer\models\ModalForm;
use app\modules\designer\models\ModalFormSearch;
use app\modules\designer\models\ObjectForm;
use app\modules\designer\models\ObjectFormSearch;
use app\modules\designer\models\PrintFormSearch;
use app\modules\designer\models\Role;
use app\modules\designer\Module;
use Exception;
use kartik\grid\EditableColumnAction;
use Yii;
use app\modules\designer\models\Metadata;
use app\modules\designer\models\MetadataSearch;
use yii\base\DynamicModel;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * MetadataController implements the CRUD actions for Metadata model.
 */
class MetadataController extends AdminController
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
                    'delete' => ['POST'],
                    'ajax-delete-form' => ['POST'],
                    'ajax-set-main-form' => ['POST'],
                    'ajax-unset-main-form' => ['POST'],
                    'ajax-create-tabular-section' => ['POST'],
                    'ajax-update-tabular-section' => ['POST'],
                    'ajax-delete-tabular-section' => ['POST'],
                    'ajax-deploy' => ['POST'],
                    'ajax-restructure-tables' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * @param null $type
     * @return string
     * @throws Exception
     */
    public function actionIndex($type = null)
    {
        Loader::saveIndexURL();
        $searchModel  = new MetadataSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $type);

        $content = $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);

        $contentActions = [];

        $contentActions[] = ControlFactory::create(PrimaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_CREATE,
            'label'         => Yii::t('app', 'Create'),
            'visible_xs'    => false,
            'options'       => [
                'href'  => Url::to(['create', 'type' => Yii::$app->request->get('type')]),
            ],

        ]);

        $contentActions[] = ControlFactory::create(PrimaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_CREATE,
            'visible_xs'    => true,
            'options'       => [
                'href'  => Url::to(['create', 'type' => Yii::$app->request->get('type')]),
            ],

        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_REFRESH,
            'options'       => [
                'href'  => Loader::getIndexURL(),
            ],

        ]);

        return AppRenderer::widget([
            'title'             => Yii::t('app', 'Metadata'),
            'content'           => $content,
            'contentActions'    => $contentActions,
            'sidebarItems'      => Module::getDesignerMenuItems(),
        ]);
    }

    /**
     * @param $id
     * @return string
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        if (in_array($model->type, [ObjectTypes::TABLE, ObjectTypes::SYSTEM_TABLE])) {
            throw new ForbiddenHttpException();
        }

        $columnsSearchModel = new MetadataColumnsSearch();
        $columnsDataProvider = $columnsSearchModel->search(Yii::$app->request->queryParams, $id);
        $columnsDataProvider->pagination = false;

        $tabularSectionSearchModel = new MetadataSearch();
        $tabularSectionDataProvider = $tabularSectionSearchModel->search(Yii::$app->request->queryParams, ObjectTypes::TABLE, $id);
        $tabularSectionDataProvider->sort = false;
        $tabularSectionDataProvider->pagination = false;

        $tabularSectionModel = new Metadata();

        $subsystemModel = new MetadataSubsystems();
        $subsystemModel->subsystem_id = $model->id;

        if ($subsystemModel->load(Yii::$app->request->post()) and $subsystemModel->validate()) {
            $query = MetadataSubsystems::find()->andWhere([
                'subsystem_id' => $subsystemModel->subsystem_id,
                'metadata_id' => $subsystemModel->metadata_id,
            ]);
            if ($query->count() == 0) $subsystemModel->save();
        }

        $subsystemModel = new MetadataSubsystems(); //reset model
        $subsystemModel->subsystem_id = $model->id;

        $subsystemsSearchModel = new MetadataSubsystemsSearch();
        $contentDataProvider = $subsystemsSearchModel->search(Yii::$app->request->queryParams, $id);
        $subsystemsDataProvider = $subsystemsSearchModel->search(Yii::$app->request->queryParams, null, $model->id);
        $subsystemsDataProvider->pagination = false;

        $objectFormSearchModel = new ObjectFormSearch();
        $objectFormDataProvider = $objectFormSearchModel->search(Yii::$app->request->queryParams, $id);
        $objectFormDataProvider->sort = false;
        $objectFormDataProvider->pagination = false;

        $listFormSearchModel = new ListFormSearch();
        $listFormDataProvider = $listFormSearchModel->search(Yii::$app->request->queryParams, $id);
        $listFormDataProvider->sort = false;
        $listFormDataProvider->pagination = false;

        $modalFormSearchModel = new ModalFormSearch();
        $modalFormDataProvider = $modalFormSearchModel->search(Yii::$app->request->queryParams, $id);
        $modalFormDataProvider->sort = false;
        $modalFormDataProvider->pagination = false;

        $formModel = $this->getFormModel();

        $enumerationValueModel = new EnumerationValue();
        $enumerationValueModel->metadata_id = $model->id;

        $enumerationValueSearchModel = new EnumerationValueSearch();
        $enumerationValueDataProvider = $enumerationValueSearchModel->search(Yii::$app->request->queryParams, $model->id);
        $enumerationValueDataProvider->pagination = false;

        $printFormSearchModel = new PrintFormSearch();
        $printFormDataProvider = $printFormSearchModel->search(Yii::$app->request->queryParams, $model->id);
        $printFormDataProvider->pagination = false;

        $embedFormSearch = new EmbedFormSearch();
        $embedFormDataProvider = $embedFormSearch->search(Yii::$app->request->queryParams, $model->id);
        $embedFormDataProvider->pagination = false;

        $actionSearchModel = new MetadataSearch();
        $actionDataProvider = $actionSearchModel->search(Yii::$app->request->queryParams, ObjectTypes::ACTION, $id, true);
        $actionDataProvider->sort       = false;
        $actionDataProvider->pagination = false;

        $content = $this->render('view', [
            'model'                         => $model,
            'columnsDataProvider'           => $columnsDataProvider,
            'subsystemModel'                => $subsystemModel,
            'contentDataProvider'           => $contentDataProvider,
            'subsystemsDataProvider'        => $subsystemsDataProvider,
            'objectFormDataProvider'        => $objectFormDataProvider,
            'listFormDataProvider'          => $listFormDataProvider,
            'modalFormDataProvider'         => $modalFormDataProvider,
            'formModel'                     => $formModel,
            'tabularSectionDataProvider'    => $tabularSectionDataProvider,
            'tabularSectionModel'           => $tabularSectionModel,
            'enumerationValueModel'         => $enumerationValueModel,
            'enumerationValueDataProvider'  => $enumerationValueDataProvider,
            'printFormDataProvider'         => $printFormDataProvider,
            'embedFormDataProvider'         => $embedFormDataProvider,
            'actionDataProvider'            => $actionDataProvider,
            'roles'                         => $this->getRoles($id),
        ]);

        $contentActions = [];

        $contentActions[] = ControlFactory::create(PrimaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_EDIT,
            'label'         => Yii::t('app', 'Edit'),
            'visible_xs'    => false,
            'options'       => [
                'href'  => Url::to(['update', 'id' => $model->id])
            ],
        ]);

        $contentActions[] = ControlFactory::create(PrimaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_EDIT,
            'visible_xs'    => true,
            'options'       => [
                'href'  => Url::to(['update', 'id' => $model->id])
            ],
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_DELETE,
            'label'         => Yii::t('app', 'Delete'),
            'visible_xs'    => false,
            'options'       => [
                'href'  => Url::to(['delete', 'id' => $model->id]),
                'data'  => [
                    'confirm'   => Yii::t('app', 'Are you sure to delete this item?'),
                    'method'    => 'post',
                ],
            ],
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'asLink'        => true,
            'icon'          => IconsSet::ACTION_DELETE,
            'visible_xs'    => true,
            'options'       => [
                'href'  => Url::to(['delete', 'id' => $model->id]),
                'data'  => [
                    'confirm'   => Yii::t('app', 'Are you sure to delete this item?'),
                    'method'    => 'post',
                ],
            ],
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'asLink'    => true,
            'icon'      => IconsSet::ACTION_CLOSE,
            'options'   => [
                'href'  => Loader::getIndexURL()
            ],
        ]);

        return AppRenderer::widget([
            'title'                     => $model->name,
            'content'                   => $content,
            'contentActions'            => $contentActions,
            'contentRenderContainer'    => false,
            'sidebarItems'              => Module::getDesignerMenuItems(),
        ]);
    }


    public function actionAjaxTabularSection($id)
    {
        if(Yii::$app->request->isAjax){
            if($model = $this->findModel($id)){
                Yii::$app->response->format = Response::FORMAT_JSON;
                $searchModel = new MetadataColumnsSearch();
                $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $model->id);
                $dataProvider->sort = false;
                return $this->renderAjax('/metadata/object_tabs/_expand-tabular-section', [
                    'model' => $model,
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
                ]);
            }
        }
        return $this->redirect(['/designer/metadata/view','id'=>$id]);
    }

    public function actionAjaxListEnumeration($id)
    {
        if(Yii::$app->request->isAjax){
            if($model = $this->findModel($id)){
                Yii::$app->response->format = Response::FORMAT_JSON;
                $searchModel = new EnumerationValueSearch();
                return $this->renderAjax('_enumeration_list', [
                    'model'                        => $model,
                    'dataProvider' => $searchModel->search(Yii::$app->request->queryParams, $model->id),
                ]);
            }
        }
        return $this->redirect(['/designer/metadata/view','id'=>$id]);
    }

    protected function getRoles($id)
    {
        $roles = [];
        foreach (RoleHelper::getAvailableRoles() as $key => $value) {

            /** @var Role $role */
            $role = Role::find()->andWhere([
                'metadata_id' => $id,
                'role'        => $key,
            ])->one();

            if ($role !== null) {
                $roles[] = [
                    'name'        => $key,
                    'description' => $value,
                    'can_insert'  => (boolean) $role->can_insert,
                    'can_update'  => (boolean) $role->can_update,
                    'can_view'    => (string)  $role->can_view,
                    'can_delete'  => (string)  $role->can_delete,
                    'can_export'  => (string)  $role->can_export,
                    'id'          => (string)  $role->id,
                    'query_rls_view'       =>   (string)    $role->query_rls_view,
                    'query_rls_insert'       => (string)  $role->query_rls_insert,
                    'query_rls_update'       => (string)  $role->query_rls_update,
                    'query_rls_delete'       => (string)  $role->query_rls_delete,
                ];
            } else {
                $roles[] = [
                    'name'        => $key,
                    'description' => $value,
                    'can_insert'  => false,
                    'can_update'  => false,
                    'can_view'    => false,
                    'can_delete'  => false,
                    'can_export'  => false,
                ];
            }

        }
        return $roles;
    }

    /**
     * @param null $type
     * @return string|Response
     * @throws Exception
     * @throws \yii\base\ErrorException
     * @throws \yii\db\Exception
     */
    public function actionCreate($type = null)
    {
        $model = new Metadata();


        if ($model->load(Yii::$app->request->post()) and $model->validate() and (new TableManager())->createMetadata($model)) {

            (new Generator($model))->createFiles();

            return $this->redirect($model->local_action ? ['view', 'id' => $model->owner_id, '#' => 'designer-actions'] : ['view', 'id' => $model->id]);
        } else {

            if (empty($model->type) and array_key_exists($type, ObjectTypes::getList())) {
                $model->type = $type;
            }

            return $this->renderFormContent($model);
        }
    }

    /**
     * @throws \yii\base\ErrorException
     * @throws \yii\db\Exception
     */
    public function actionAjaxCreateTabularSection()
    {
        $post = Yii::$app->request->post();
        if(isset($post['Metadata']) and isset($post['Metadata']['id'])){
            $model = $this->findModel($post['Metadata']['id']);
            unset($post['Metadata']['id']);
        }else{
            $model = new Metadata();
        }
        $old_alias = preg_replace('/\PL/u', '', $metadata_model->name) . $model->script_alias;
        if ($model->load($post)) {
            $model->type = ObjectTypes::TABLE;  
            Yii::$app->response->format = Response::FORMAT_JSON;
            if ($model->validate()) {
                if($model->isNewRecord){
                    (new TableManager())->createMetadata($model);
                }else{
                    if($old_alias != $model->script_alias){
                        (new TableManager())->recreateView($model->script_alias, $model->table_name, $old_alias);
                        $model->save(false);
                    } else {
                        $model->save(false);
                    }
                }
            }
            return \app\core\app\base\ActiveForm::validate($model);
        }
    }

    /**
     * @param $id
     * @throws NotFoundHttpException
     */
    public function actionAjaxUpdateTabularSection($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post())) {
            $model->save();
        }
    }

    /**
     * @param $id
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function actionAjaxDeleteTabularSection($id)
    {
        /** @var $model Metadata */
        $model = Metadata::findOne($id);
        (new TableManager())->deleteMetadata($model);
    }

    /**
     * @param $id
     * @return string|Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $model       = $this->findModel($id);
        $sourceModel = clone $model;

        if ($model->type == ObjectTypes::SYSTEM_TABLE) {
            throw new ForbiddenHttpException();
        }

        if ($model->load(Yii::$app->request->post()) and $model->validate() and $model->save()) {
            (new Generator($sourceModel))->deleteFiles();
            (new Generator($model))->createFiles();
            return $this->redirect($model->local_action ? ['view', 'id' => $model->owner_id, '#' => 'designer-actions'] : ['view', 'id' => $model->id]);
        } else {
            return $this->renderFormContent($model);
        }
    }

    /**
     * @param Metadata $model
     * @return string
     * @throws Exception
     */
    protected function renderFormContent($model)
    {
        $formId = 'designer-frm-metadata';
        $metadataId = Yii::$app->request->get('metadata_id', false);
        if($metadataId)
            $model->owner_id = $metadataId;

        $content = $this->render('form', [
            'model'     =>  $model,
            'formId'    =>  $formId,
        ]);

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
            'asLink'    => true,
            'icon'      => IconsSet::ACTION_CLOSE,
            'options'   => [
                'href'  => $model->isNewRecord
                    ? Loader::getIndexURL()
                    : ($model->local_action
                            ? Url::to(['view', 'id' => $model->owner_id, '#' => 'designer-actions'])
                            : Url::to(['view', 'id' => $model->id])),
            ],
        ]);

        $helpNote = '';

        if ($model->isNewRecord || $model->type === ObjectTypes::CATALOG) {
            $helpNote = $this->renderPartial('@app/modules/designer/help/metadata/form');
        }

        return AppRenderer::widget([
            'title'             => Yii::t('app', 'Objects') . ': ' . ($model->isNewRecord ? Yii::t('app', 'new') : Html::encode($model->name)),
            'content'           => $content,
            'contentActions'    => $contentActions,
            'sidebarItems'      => Module::getDesignerMenuItems(),
            'helpNote'          => $helpNote,
        ]);
    }

    /**
     * @param $id
     * @return Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $no_redirect = $model->local_action ? true : false;

        if ($model->type == ObjectTypes::SYSTEM_TABLE) {
            throw new ForbiddenHttpException();
        }

        $tableManager = new TableManager();
        if ($tableManager->deleteMetadata($model)) {
            (new Generator($model))->deleteFiles();
            if(!$no_redirect){
                return $this->redirect(['index']);
            }
        } else {
            return $this->redirect(['view', 'id' => $model->id]);
        }
    }

    /**
     * Finds the Metadata model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Metadata the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Metadata::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionGetReferentialMetadata($q = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $query = Metadata::find()
            ->select([
                'id',
                'name AS text'
            ])
            ->andWhere([
                'or',
                ['type' => ObjectTypes::getReferentialTypes()],
                ['type' => ObjectTypes::ACTION],
            ])
            ->andFilterWhere(['like', 'name', $q])
            ->orderBy('name')
            ->asArray()
            ->limit(20);

        $out = [];
        $out['results'] = array_values($query->all());

        return $out;
    }

    public function actionMaintenance()
    {
        $content = $this->render('maintenance');

        return AppRenderer::widget([
            'title'         => Module::t('@designer_tools_maintenance'),
            'content'       => $content,
            'useComponents' => true,
            'sidebarItems'  => Module::getDesignerMenuItems(),
        ]);
    }

    public function actionDeploy()
    {
        /** @var Metadata[] $models */
        $models = Metadata::find()->with('metadataColumns')->all();

        foreach ($models as $model) {
            (new Generator($model))->createFiles();
        }

        Yii::$app->session->setFlash(Module::FLASH_MESSAGE_SUCCESS, 'Deployment successfully completed');

        return $this->redirect(['index']);
    }

    public function actionAjaxDeploy()
    {
        Yii::$app->response->format = \app\core\app\base\Response::FORMAT_JSON;

        $transaction = Yii::$app->db->beginTransaction();

        try {

            /** @var Metadata[] $models */
            $models = Metadata::find()->with('metadataColumns')->all();

            foreach ($models as $model) {
                (new Generator($model))->createFiles();
            }

            $transaction->commit();

        } catch (\Exception $e) {

            $transaction->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];

        } catch (\Throwable $e) {

            $transaction->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];

        }

        return [
            'success' => true,
            'message' => Yii::t('app', '@core_message_operation_successfully_completed'),
        ];
    }

    public function actionAjaxRestructureTables()
    {
        Yii::$app->response->format = \app\core\app\base\Response::FORMAT_JSON;

        $transaction = Yii::$app->db->beginTransaction();

        try {

            Generator::deleteAllDBViews();
            Generator::generateAllDBViews();

            $transaction->commit();

        } catch (\Exception $e) {

            $transaction->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];

        } catch (\Throwable $e) {

            $transaction->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];

        }

        return [
            'success' => true,
            'message' => Yii::t('app', '@core_message_operation_successfully_completed'),
        ];
    }

    /**
     * @param string $type
     * @param integer $id
     * @throws \Exception
     */
    public function actionAjaxDeleteForm($type, $id)
    {
        $this->findFormModel($type, $id)->delete();
    }

    /**
     * @param string $type
     * @param integer $id
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionAjaxSetMainForm($type, $id)
    {
        $model = $this->findFormModel($type, $id);
        Yii::$app->db->createCommand()
            ->update($model->tableName(), ['main' => 0], ['main' => 1, 'metadata_id' => $model->metadata_id])
            ->execute();
        $model->main = 1;
        $model->save(false);
    }

    /**
     * @param string $type
     * @param integer $id
     * @throws NotFoundHttpException
     */
    public function actionAjaxUnsetMainForm($type, $id)
    {
        $model = $this->findFormModel($type, $id);
        $model->main = 0;
        $model->save(false);
    }

    public function actionGetListForms($id = null)
    {
        if ($id) {
            $result = ListForm::find()
                ->select('id, name AS text')
                ->andWhere(['metadata_id' => $id])
                ->asArray()
                ->orderBy('name')
                ->all();
        } else {
            $result = [];
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['results' => $result];
    }

    public function actionGetObjectForms($id = null)
    {
        if ($id) {
            $result = ObjectForm::find()
                ->select('id, name AS text')
                ->andWhere(['metadata_id' => $id])
                ->asArray()
                ->orderBy('name')
                ->all();
        } else {
            $result = [];
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['results' => $result];
    }

    /**
     * @return DynamicModel
     */
    protected function getFormModel()
    {
        $model = new DynamicModel(['id', 'type', 'name', 'content', 'no_taskbar']);
        $model->addRule(['type', 'name', 'content'], 'string');
        $model->addRule(['name'], 'required');
        $model->addRule(['id', 'no_taskbar'], 'integer');
        return $model;
    }

    /**
     * @param $type
     * @param $id
     * @return ObjectForm|ListForm|ModalForm
     * @throws NotFoundHttpException
     */
    protected function findFormModel($type, $id)
    {
        $model = null;
        switch ($type) {
            case Generator::FORM_OBJECT:
                $model = ObjectForm::findOne($id);
                break;
            case Generator::FORM_LIST:
                $model = ListForm::findOne($id);
                break;
            case Generator::FORM_MODAL:
                $model = ModalForm::findOne($id);
                break;
        }
        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        return $model;
    }
}
