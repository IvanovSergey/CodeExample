<?php

namespace app\modules\designer\controllers;

use app\core\app\base\Url;
use app\core\app\components\controls\ControlFactory;
use app\core\app\components\controls\PrimarySubmitButtonExternal;
use app\core\app\components\controls\SecondaryButton;
use app\core\app\components\renderer\AppRenderer;
use app\core\app\controllers\AdminController;
use app\core\helpers\ForbiddenHttpException;
use app\modules\designer\assets\IconsSet;
use app\modules\designer\generator\Generator;
use app\modules\designer\helpers\ModelHelper;
use app\modules\designer\models\FormAccess;
use app\modules\designer\models\FormResource;
use app\modules\designer\models\FormResourceSearch;
use app\modules\designer\models\ListForm;
use app\modules\designer\models\Metadata;
use app\modules\designer\models\ModalForm;
use app\modules\designer\models\EmbedForm;
use app\modules\designer\models\ObjectForm;
use app\modules\designer\models\Resource;
use app\modules\designer\Module;
use Exception;
use Throwable;
use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FormController extends AdminController
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
                    'create'            => ['GET'],
                    'save'              => ['POST'],
                    'get-roles-form'    => ['GET'],
                    'save-roles-form'   => ['POST'],
                    'delete'            => ['POST'],
                    'assign-resource'   => ['POST'],
                    'unassign-resource' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * @param $type
     * @param $owner
     * @param $useComponents
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionCreate($type, $owner, $useComponents = false)
    {
        $model = $this->getNewModel($type, $owner, $useComponents);

        $query = (new Query())->from($model->tableName())->andWhere([
            'main'        => true,
            'metadata_id' => $model->metadata_id,
        ]);

        if ($query->count() == 0) {
            $model->main = true;
        }

        $model->save(false);

        return $this->redirect(Url::to(['/designer/form/update', 'type' => $type, 'id' => $model->id]));
    }

    /**
     * @param $type
     * @param $id
     * @return string|Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionUpdate($type, $id)
    {
        $model    = $this->findModel($type, $id);
        $metadata = Metadata::findOne($model->metadata_id);
        return $this->renderFormContent($metadata, $type, $model);
    }

    public function actionSave($type, $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $result  = null;
        $success = false;

        $transaction = Yii::$app->db->beginTransaction();

        try {

            $object = Yii::$app->request->post('object');

            if (empty($object)) {
                throw new BadRequestHttpException();
            }

            $object = Json::decode($object);

            if (empty($object)) {
                throw new BadRequestHttpException();
            }

            $model = $this->findModel($type, $id);
            $model->setAttribute('name',           ArrayHelper::getValue($object, 'name'));
            $model->setAttribute('content',        ArrayHelper::getValue($object, 'content'));
            $model->setAttribute('script',         ArrayHelper::getValue($object, 'script'));
            $model->setAttribute('css',            ArrayHelper::getValue($object, 'css'));
            $model->setAttribute('title',          ArrayHelper::getValue($object, 'title'));
            $model->setAttribute('no_taskbar',     filter_var(ArrayHelper::getValue($object, 'no_taskbar'), FILTER_VALIDATE_BOOLEAN));
            $model->setAttribute('use_components', filter_var(ArrayHelper::getValue($object, 'use_components'), FILTER_VALIDATE_BOOLEAN));

            if ($model->save()) {

                $model->saveHelpNotes(ArrayHelper::getValue($object, 'helpNotes'));

                $transaction->commit();

                $result  = $model->serialize();
                $success = true;
                $message = Yii::t('app', 'Your changes have been saved successfully.');

            } else {
                throw new BadRequestHttpException(ModelHelper::getModelError($model));
            }

        } catch (Exception $e) {
            $transaction->rollBack();
            $message = $e->getMessage();
        } catch (Throwable $e) {
            $transaction->rollBack();
            $message = $e->getMessage();
        }

        return [
            'result'  => $result,
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * @param Metadata $metadata
     * @param string $type
     * @param ObjectForm|ListForm|ModalForm $model
     * @return string
     * @throws \Exception
     */
    protected function renderFormContent($metadata, $type, $model)
    {
        $searchModel = new FormResourceSearch();
        $resourcesDataProvider = $searchModel->search($model);

        $urlSave  = Url::to(['/designer/form/save', 'id' => $model->id, 'type' => $type]);
        $urlIndex = Url::to(['/designer/metadata/view', 'id' => $metadata->id, '#' => 'forms']);
        $content = $this->render('form', [
            'type'                  => $type,
            'model'                 => $model,
            'metadata'              => $metadata,
            'fields'                => $this->renderFields($type, $metadata, $model->use_components),
            'resourcesDataProvider' => $resourcesDataProvider,
        ]);

        $contentActions = [];

        $contentActions[] = ControlFactory::create(PrimarySubmitButtonExternal::className(), [
            'tagName'       => 'e8-button',
            'icon'          => IconsSet::ACTION_SAVE,
            'label'         => Yii::t('app', 'Save and Close'),
            'visible_xs'    => false,
            'options'       => [
                'class'         => 'js-object-form-action-save-and-close',
                '@click.native' => '$root.saveCurrentObjectAndClose()',
            ],
        ]);

        $contentActions[] = ControlFactory::create(PrimarySubmitButtonExternal::className(), [
            'tagName'       => 'e8-button',
            'icon'          => IconsSet::ACTION_SAVE,
            'visible_xs'    => true,
            'options'       => [
                'class'         => 'js-object-form-action-save-and-close',
                '@click.native' => '$root.saveCurrentObjectAndClose()',
            ],
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'tagName'       => 'e8-button',
            'icon'          => IconsSet::ACTION_SAVE,
            'label'         => Yii::t('app', 'Save'),
            'visible_xs'    => false,
            'options'       => [
                'class'         => 'js-object-form-action-save',
                '@click.native' => '$root.saveCurrentObject()',
            ],
        ]);

        $contentActions[] = ControlFactory::create(SecondaryButton::className(), [
            'asLink'    => true,
            'icon'      => IconsSet::ACTION_CLOSE,
            'options'   => [
                'href'  => $urlIndex,
            ],
        ]);

        $serializedModel = Json::encode($model->serialize());
        $this->view->registerJs("E8App.setCurrentObject({$serializedModel});");

        $objectAttributes = Json::encode($model->getClientAttributes());
        $this->view->registerJs("E8App.setAttributes({$objectAttributes});");
        $this->view->registerJs("E8App.setUrlSave('{$urlSave}');");
        $this->view->registerJs("E8App.setUrlIndex('{$urlIndex}');");

        return AppRenderer::widget([
            'title'             => "{$metadata->name}: $model->name",
            'content'           => $content,
            'contentActions'    => $contentActions,
            'sidebarItems'      => Module::getDesignerMenuItems(),
            'useComponents'     => true,
        ]);
    }

    /**
     * @param $type
     * @param $id
     * @throws NotFoundHttpException
     */
    public function actionAssignResource($type, $id)
    {
        $model    = $this->findModel($type, $id);
        $resource = Resource::findOne(Yii::$app->request->post('resource'));

        if ($model && $resource) {

            $formClass  = $model::className();
            $formId     = $model->id;
            $resourceId = $resource->id;

            $count = FormResource::find()
                ->andWhere([
                    'form_class'  => $formClass,
                    'form_id'     => $formId,
                    'resource_id' => $resourceId,
                ])
                ->count();

            if (!$count) {
                $formResource = new FormResource();
                $formResource->form_class  = $formClass;
                $formResource->form_id     = $formId;
                $formResource->resource_id = $resourceId;
                $formResource->save();
            }
        }
    }

    /**
     * @param $type
     * @param $id
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     */
    public function actionUnassignResource($type, $id)
    {
        $model    = $this->findModel($type, $id);
        $resource = Resource::findOne(Yii::$app->request->post('resource'));

        if ($model && $resource) {

            $formClass  = $model::className();
            $formId     = $model->id;
            $resourceId = $resource->id;

            Yii::$app->db->createCommand()->delete(FormResource::tableName(), [
                'form_class'  => $formClass,
                'form_id'     => $formId,
                'resource_id' => $resourceId,
            ])->execute();
        }
    }

    public function actionGetRolesForm($type,$id){

        if(!Yii::$app->request->isAjax){
            throw new MethodNotAllowedHttpException();
        }

        $tables = [
            Generator::FORM_MODAL => ModalForm::tableName(),
            Generator::FORM_OBJECT => ObjectForm::tableName(),
            Generator::FORM_LIST => ListForm::tableName(),
        ];

        if(!isset($tables[$type])){
            throw new ForbiddenHttpException('Unknown or forbidden type');
        }

        $query = new \yii\db\Query();
        $query->select(['name','type','description'])
            ->from('auth_item')
            ->where(['type'=>1]);

        return $this->renderAjax('_roles_form',[
            'appliedRolesList'=> ArrayHelper::getColumn(FormAccess::find()->andWhere([
                'form_table' => $tables[$type],
                'form_id' => $id
            ])->asArray()->all(),'role_name'),
            'allRolesList' => ArrayHelper::map($query->all(),'name','description')
        ]);
    }

    public function actionSaveRolesForm($type,$id)
    {
        if(!Yii::$app->request->isAjax){
            throw new MethodNotAllowedHttpException();
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        $tables = [
            Generator::FORM_MODAL => ModalForm::tableName(),
            Generator::FORM_OBJECT => ObjectForm::tableName(),
            Generator::FORM_LIST => ListForm::tableName(),
        ];

        if(!isset($tables[$type])){
            throw new ForbiddenHttpException('Unknown or forbidden type');
        }

        $post_role_list = Yii::$app->request->post('FormRoles');

        if(is_array($post_role_list)){
            $ap_role_list = ArrayHelper::getColumn(FormAccess::find()->andWhere([
                'form_table' => $tables[$type],
                'form_id' => $id
            ])->asArray()->all(),'role_name');
            $to_dell = array_diff($ap_role_list,$post_role_list);
            $to_add = array_diff($post_role_list,$ap_role_list);
            if(!empty($to_dell)){
                FormAccess::deleteAll([
                    'form_table' => $tables[$type],
                    'form_id' => $id,
                    'role_name' => $to_dell
                ]);
            }
            if(!empty($to_add)){
                foreach ($to_add as $new){
                    $model = new FormAccess([
                        'form_table' => $tables[$type],
                        'form_id' => $id,
                        'role_name' => $new
                    ]);
                    $model->save();
                }
            }
        }else{
            FormAccess::deleteAll([
                'form_table' => $tables[$type],
                'form_id' => $id
            ]);
        }

        return true;
    }

    /**
     * @param $type
     * @param $owner
     * @param $useComponents
     * @return ListForm|ModalForm|ObjectForm|null
     * @throws NotFoundHttpException
     */
    protected function getNewModel($type, $owner, $useComponents = false)
    {
        /** @var Metadata $metadata */
        $metadata  = Metadata::findOne($owner);
        $generator = Generator::getObject($metadata->table_name);
        switch ($type) {
            case Generator::FORM_OBJECT:
                $model = new ObjectForm();
                $model->content         = $generator->getObjectFormContent($useComponents);
                $model->name            = Yii::t('app', 'Object Form');
                $model->use_components  = $useComponents;
                break;
            case Generator::FORM_LIST:
                $model = new ListForm();
                $model->content         = $generator->getListFormContent($useComponents);
                $model->name            = Yii::t('app', 'List Form');
                $model->use_components  = $useComponents;
                break;
            case Generator::FORM_MODAL:
                $model = new ModalForm();
                $model->content         = $generator->getModalFormContent($useComponents);
                $model->name            = Yii::t('app', 'Modal Form');
                $model->use_components  = $useComponents;
                break;
            default:
                $model = null;
        }

        $model->metadata_id = $metadata->id;

        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        return $model;
    }

    /**
     * @param string $type
     * @param integer $id
     * @return ObjectForm|ListForm|ModalForm
     * @throws NotFoundHttpException
     */
    protected function findModel($type, $id)
    {
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
            case Generator::FORM_EMBED:
                $model = EmbedForm::findOne($id);
                break;
            default:
                $model = null;
        }
        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        return $model;
    }

    /**
     * @param $type
     * @param $metadata
     * @param $useComponents
     * @return string
     * @throws \Exception
     */
    protected function renderFields($type, $metadata, $useComponents)
    {
        return Generator::getObject($metadata->table_name)->renderFields($type, $useComponents);
    }
}
