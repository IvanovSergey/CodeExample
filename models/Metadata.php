<?php

namespace app\modules\designer\models;

use app\modules\designer\components\DesignerHelper;
use app\modules\designer\generator\Generator;
use app\modules\designer\helpers\ObjectTypes;
use app\modules\designer\helpers\Types;
use app\modules\designer\Module;
use Yii;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;

/**
 * This is the model class for table "designer_metadata".
 *
 * @property integer $id
 * @property string $name
 * @property string $type
 * @property string $table_name
 * @property string $icon
 * @property boolean $attach_files
 * @property integer $owner_id
 * @property boolean $show_in_menu
 * @property boolean $limited_access
 * @property boolean $enable_autosave
 * @property string $system_class
 * @property string $system_field
 * @property string $url
 * @property string $number_length
 * @property integer $list_form_id
 * @property integer $object_form_id
 * @property boolean $enable_notes
 * @property boolean $enable_events
 * @property boolean $enable_tasks
 * @property boolean $enable_activity_stream
 * @property boolean $enable_mail
 * @property string $aliases
 * @property string $comments
 * @property string $script_alias
 * @property string $action_type
 *
 * @property Metadata[] $tabularSections
 * @property MetadataColumns[] $metadataColumns
 * @property MetadataColumns[] $metadataColumnsExceptHidden
 * @property Role[] $roles
 * @property Metadata $owner
 * @property ListForm $listForm
 * @property string $listFormPresentation
 * @property ObjectForm $objectForm
 * @property string $objectFormPresentation
 * @property array $enumerationValues
 * @property array $enumerationValuesWithScriptAliases
 * @property MetadataColumns|null $defaultSortColumn
 * @property bool $disable_event_log
 * @property array $triggerAttributes
 */
class Metadata extends \yii\db\ActiveRecord
{
    public $full_script_alias, $short_script_alias;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'designer_metadata';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'type','script_alias'], 'required'],
            [['type', 'icon', 'url','comments', 'action_type'], 'string'],
            [['number_length'], 'number', 'max' => 11, 'min' => 0],
            [['attach_files', 'show_in_menu', 'limited_access', 'enable_autosave', 'enable_notes', 'enable_events', 'enable_tasks','enable_activity_stream', 'enable_mail', 'local_action', 'disable_event_log'], 'boolean'],
            [['name', 'table_name','aliases'], 'string', 'max' => 100],
            [['script_alias'], 'string', 'min' => 5, 'max' => 150],
            [['script_alias'], 'validateScriptAliasUnique'],
            ['script_alias', 'match', 'pattern' => '/^[0-9a-zA-Z_]+$/', 'message' => '{attribute} must contain only letters, numbers, underscores and must begin with a letter'],
            [['name'], 'unique', 'targetAttribute' => ['name', 'type'], 'when' => function ($model) {
                /** @var Metadata $model */
                return $model->type !== ObjectTypes::TABLE;
            }],
            [['owner_id'], 'exist', 'skipOnError' => true, 'skipOnEmpty' => true, 'targetClass' => Metadata::className(), 'targetAttribute' => ['owner_id' => 'id']],
            [['list_form_id', 'object_form_id'], 'integer'],
            [['list_form_id'], 'exist', 'skipOnError' => true, 'targetClass' => ListForm::className(), 'targetAttribute' => ['list_form_id' => 'id']],
            [['object_form_id'], 'exist', 'skipOnError' => true, 'targetClass' => ObjectForm::className(), 'targetAttribute' => ['object_form_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                        => Yii::t('app', 'ID'),
            'name'                      => Module::t('@designer_metadata_name'),
            'type'                      => Yii::t('app', 'Type'),
            'table_name'                => Yii::t('app', 'Internal ID'),
            'icon'                      => Module::t('@designer_metadata_icon'),
            'attach_files'              => Module::t('@designer_metadata_attach_files'),
            'owner_id'                  => Yii::t('app', 'Owner'),
            'show_in_menu'              => Module::t('@designer_metadata_show_in_menu'),
            'limited_access'            => Yii::t('app', 'Limited Access'),
            'tabularSections'           => Yii::t('app', 'Tabular Sections'),
            'url'                       => Yii::t('app', 'Url'),
            'number_length'             => Yii::t('app', 'Number Length'),
            'list_form_id'              => Yii::t('app', 'List Form'),
            'object_form_id'            => Yii::t('app', 'Object Form'),
            'aliases'                   => Module::t('@designer_menu_alias'),
            'comments'                  => Yii::t('app', 'Comment'),
            'script_alias'              => Module::t('@designer_script_alias'),
            'full_script_alias'         => Module::t('@designer_script_alias'),
            'action_type'               => Module::t('@designer_actions'),
            'enable_autosave'           => Yii::t('app', 'Enable Autosave'),
            'enable_notes'              => Module::t('@designer_notes_enable'),
            'enable_mail'               => Module::t('@designer_enable_mail'),
            'enable_events'             => Module::t('@designer_events_enable'),
            'enable_tasks'              => Module::t('@designer_tasks_enable'),
            'enable_activity_stream'    => Module::t('@designer_activity_stream_enable'),
            'disable_event_log'         => Module::t('@designer_metadata_disable_event_log'),
            'default_sort'              => Module::t('@designer_default_sort'),
        ];
    }

    public function validateScriptAliasUnique()
    {
        $query = self::find()
            ->andWhere(['script_alias'=>$this->script_alias]);
        if(!empty($this->id))
            $query->andWhere(['!=','id',$this->id]);
        if($query->exists())
            $this->addError('script_alias','The script alias is not unique');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTabularSections()
    {
        $query = $this
            ->hasMany(Metadata::className(), ['owner_id' => 'id'])
            ->with('metadataColumns')
            ->andWhere(['type' => ObjectTypes::TABLE]);

        if (!ObjectTypes::hasTabularSections($this->type)) {
            $query->andWhere('1=0');
        }

        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMetadataColumns()
    {
        return $this->hasMany(MetadataColumns::className(), ['metadata_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMetadataColumnsExceptHidden()
    {
        return $this->getMetadataColumns()->andWhere(['!=', 'type', Types::Hidden]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRoles()
    {
        return $this->hasMany(Role::className(), ['metadata_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwner()
    {
        return $this->hasOne(Metadata::className(), ['id' => 'owner_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getListForm()
    {
        return $this->hasOne(ListForm::className(), ['id' => 'list_form_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getListForms()
    {
        return $this->hasMany(ListForm::className(), ['metadata_id' => 'id']);
    }

    /**
     * @return string
     */
    public function getListFormPresentation()
    {
        $form = $this->listForm;
        return $form ? $form->name : '';
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObjectForm()
    {
        return $this->hasOne(ObjectForm::className(), ['id' => 'object_form_id']);
    }

    /**
     * @return string
     */
    public function getObjectFormPresentation()
    {
        $form = $this->objectForm;
        return $form ? $form->name : '';
    }

    /**
     * @return null|string
     */
    public function getClientType()
    {
        switch ($this->type) {
            case ObjectTypes::TABLE:
                return 'Table';
            case ObjectTypes::CATALOG:
                return 'List';
            case ObjectTypes::DOCUMENT:
                return 'Transaction';
            case ObjectTypes::ENUMERATION:
                return 'Enumeration';
            default:
                return null;
        }
    }

    /**
     * @return array
     */
    public function getClientConfig()
    {
        $config = [];

        if ($this->type === ObjectTypes::TABLE) {

            $columns = [];

            foreach ($this->metadataColumns as $metadataColumn) {
                if ($metadataColumn->name !== MetadataColumns::OWNER_COLUMN_NAME) {
                    $columns[] = $metadataColumn->script_alias;
                }
            }

            $config['columns'] = $columns;
        }

        return $config;
    }

    /**
     * @return array
     */
    public function getEnumerationValues()
    {
        if ($this->type === ObjectTypes::ENUMERATION) {
            $values = EnumerationValue::find()
                ->where(['metadata_id' => $this->id])
                ->orderBy('sort DESC')
                ->asArray()
                ->all();
            return ArrayHelper::map($values, 'id', 'name');
        } else {
            return [];
        }
    }

    /**
     * @return array
     */
    public function getEnumerationValuesWithScriptAliases()
    {
        if ($this->type === ObjectTypes::ENUMERATION) {
            $values = EnumerationValue::find()
                ->where(['metadata_id' => $this->id])
                ->orderBy('sort DESC')
                ->asArray()
                ->all();
            return ArrayHelper::map($values, 'script_alias', 'name');
        } else {
            return [];
        }
    }

    public function normalizeScriptAlias()
    {
        if (substr($this->script_alias, 0, 3) != ObjectTypes::scriptPrefixByType($this->type)) {
            return ObjectTypes::scriptPrefixByType($this->type) . $this->script_alias;
        }
        return Inflector::slug($this->script_alias, '-', false);
    }

    public function getShortScriptAlias()
    {
        if (substr($this->script_alias, 0, 3) == ObjectTypes::scriptPrefixByType($this->type)) {
            return substr($this->script_alias, 3);
        }
        return $this->script_alias;
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->full_script_alias  = $this->script_alias;
        $this->short_script_alias = $this->getShortScriptAlias();
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {

            (new Generator($this))->deleteDBView();

            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate() {
        if(empty($this->script_alias)){
            if($this->type == ObjectTypes::TABLE){
                $ownerName = ($this->owner) ? substr($this->owner->normalizeScriptAlias(),3) : '';
                $this->script_alias = ObjectTypes::scriptPrefixByType($this->type) . $ownerName .$this->name;
            }else{
                $this->script_alias = Inflector::camelize($this->name);
            }
        }
        $this->script_alias = $this->normalizeScriptAlias();
        if (parent::beforeValidate()) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (($this->type == ObjectTypes::ACTION && $this->owner && $this->owner->table_name)) {
            $this->url = "/{$this->owner->table_name}-" . Inflector::slug($this->name);
        }elseif($this->type == ObjectTypes::PAGE && $this->isNewRecord){
            $this->table_name = DesignerHelper::getNewTableName();
        }
        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            if($this->type == ObjectTypes::PAGE) {
                $model = new ListForm();
                $model->content = '';
                $model->metadata_id = $this->id;
                $model->main = 1;
                $model->name = $this->name;
                $model->title = $this->name;
                if (!$model->save()) {
                    $this->delete();
                    throw new ErrorException('Error while saving form');
                }
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return MetadataColumns|null
     */
    public function getDefaultSortColumn()
    {
        $predefinedMetadataColumn = null;

        $isCatalog  = ($this->type === ObjectTypes::CATALOG);
        $isDocument = ($this->type === ObjectTypes::DOCUMENT);

        foreach ($this->metadataColumns as $metadataColumn) {

            if ($metadataColumn->default_sort) {
                return $metadataColumn;
            } elseif ($isCatalog && $metadataColumn->name === MetadataColumns::NAME_COLUMN_NAME) {
                $predefinedMetadataColumn = $metadataColumn;
            } elseif ($isDocument && $metadataColumn->name === MetadataColumns::NUMBER_COLUMN_NAME) {
                $predefinedMetadataColumn = $metadataColumn;
            }

        }

        return $predefinedMetadataColumn;
    }

    /**
     * @return array
     */
    public function getTriggerAttributes()
    {
        $columns = [];

        foreach ($this->metadataColumnsExceptHidden as $column) {
            $columns[$column->script_alias] = $column->label;
        }

        return $columns;
    }

    /**
     * @param string $name
     * @return MetadataColumns|null
     */
    public function getMetadataColumnByName($name)
    {
        foreach ($this->metadataColumns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }
        return null;
    }

    /**
     * @param string $alias
     * @return MetadataColumns|null
     */
    public function getMetadataColumnByAlias($alias)
    {
        foreach ($this->metadataColumns as $column) {
            if ($column->script_alias === $alias) {
                return $column;
            }
        }
        return null;
    }
}
