<?php

namespace app\modules\designer\models;

use app\core\app\base\Url;
use app\core\app\behaviors\AttributeTypecastBehavior;
use app\modules\designer\helpers\ModelHelper;
use app\modules\designer\helpers\ObjectTypes;
use app\modules\designer\helpers\Types;
use app\modules\designer\Module;
use app\modules\files\models\File;
use Yii;
use yii\base\DynamicModel;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use Exception;
use yii\web\UploadedFile;

/**
 * This is the model class for table "designer_metadata_columns".
 *
 * @property integer $id
 * @property integer $metadata_id
 * @property string $name
 * @property string $index
 * @property integer $predefined
 * @property string $label
 * @property string $type
 * @property integer $length
 * @property integer $number_precision
 * @property integer $ref_id
 * @property string $mask
 * @property integer $default_sort
 * @property string $dropdown_variant
 * @property string $dropdown_value
 * @property string $filtering_mode
 * @property string $default_value
 * @property integer $show_hyperlink
 * @property string $search_method
 * @property string $is_non_negative
 * @property bool $isTabularSectionColumn
 * @property string $starts_from
 * @property string $comments
 * @property string $config
 * @property string $script_alias
 * @property string $validation_rules
 *
 * @property integer $required
 * @property integer $is_unique
 * @property float $min_value
 * @property float $max_value
 * @property string $mimes
 * @property string $max_size
 *
 * @property Metadata $metadata
 * @property Metadata $ref
 * @property string $refName
 * @property ChoiceParameter[] $choiceParameters
 * @property ExtendedChoiceParameter[] $extendedChoiceParameters
 * @property bool $choiceParametersAvailable
 * @property bool $isEnumeration
 * @property bool $isSystemTable
 * @property bool $clientDefaultValue
 * @property int $defaultSortDirection
 * @property array $reservedAliases
 * @property bool $isAllowedForEmbeddedForms
 */
class MetadataColumns extends \yii\db\ActiveRecord
{
    const DROPDOWN_VARIANT_DEFAULT = 'default';
    const DROPDOWN_VARIANT_ALL     = 'all';
    const DROPDOWN_VARIANT_VALUE   = 'value';

    const MAX_DROPDOWN_ROWS = 500;
    const DEFAULT_DROPDOWN_ROWS = 20;

    const FILTERING_MODE_INPUT    = 'asInput';
    const FILTERING_MODE_DROPDOWN = 'asDropdown';

    const SEARCH_METHOD_ANYWHERE  = 'anywhere';
    const SEARCH_METHOD_BEGINNING = 'beginning';

    const ID_COLUMN_NAME            = 'id';
    const VERSION_COLUMN_NAME       = 'version';
    const PRESENTATION_COLUMN_NAME  = 'presentation';
    const OWNER_COLUMN_NAME         = 'owner_id';
    const DATE_COLUMN_NAME          = 'date';
    const NUMBER_COLUMN_NAME        = 'number';
    const NAME_COLUMN_NAME          = 'name';

    private $_validationRules = [];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'designer_metadata_columns';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [
            [['metadata_id', 'label', 'type'], 'required'],
            [['min_value', 'max_value', 'max_size'], 'number'],
            [['comments','config'], 'string'],
            [['ref_id'], 'required', 'when' => function(MetadataColumns $model) {
                return Types::isReferentialType($model->type);
            }, 'whenClient' => "function(attribute, value) {
                var type = $('[name=\"MetadataColumns[type]\"]').val();
                return ($.inArray(type, ['Reference']) >= 0);
            }"],
            [['metadata_id', 'ref_id', 'length', 'number_precision'], 'integer'],
            [['predefined', 'required', 'show_hyperlink', 'is_non_negative'], 'boolean'],
            [['type', 'dropdown_variant', 'filtering_mode', 'default_value', 'search_method'], 'string'],
            [['name', 'index', 'label', 'mask', 'mimes'], 'string', 'max' => 255],
            [['metadata_id'], 'exist', 'skipOnError' => true, 'targetClass' => Metadata::className(), 'targetAttribute' => ['metadata_id' => 'id']],
            [['ref_id'], 'exist', 'skipOnError' => true, 'skipOnEmpty' => true, 'targetClass' => Metadata::className(), 'targetAttribute' => ['ref_id' => 'id']],
            ['ref_id', 'default', 'value' => null],
            [['dropdown_value'], 'required', 'when' => function(MetadataColumns $model) {
                return (Types::isReferentialType($model->type) and $model->dropdown_variant == static::DROPDOWN_VARIANT_VALUE);
            }, 'whenClient' => "function(attribute, value) {
                var type = $('select[name=\"MetadataColumns[type]\"]').val();
                var variant = $('input[name=\"MetadataColumns[dropdown_variant]\"]:checked').val();
                return ($.inArray(type, ['Reference']) >= 0 && $.inArray(variant, ['value']) >= 0);
            }"],
            ['dropdown_value', 'number', 'integerOnly' => true, 'max' => static::MAX_DROPDOWN_ROWS],
            ['dropdown_value', 'default', 'value' => static::DEFAULT_DROPDOWN_ROWS],
            ['dropdown_variant', 'default', 'value' => static::DROPDOWN_VARIANT_DEFAULT],
            ['filtering_mode', 'default', 'value' => static::FILTERING_MODE_INPUT],
            ['starts_from', 'string', 'max' => 11],
            [['is_unique'], 'boolean'],
        ];

        if (!$this->predefined) {
            $rules[] = ['script_alias', 'required'];
            $rules[] = ['script_alias', 'string', 'min' => 2, 'max' => 150];
            $rules[] = ['script_alias', 'validateScriptAliasUnique'];
            $rules[] = ['script_alias', 'match', 'pattern' => '/^[0-9a-zA-Z_]+$/', 'message' => '{attribute} must contain only letters, numbers, underscores and must begin with a letter'];
            $rules[] = ['script_alias', 'match', 'pattern' => '/^[a-zA-Z]/', 'message' => '{attribute} string must begin with a letter'];
            $rules[] = ['script_alias', 'in', 'range' => $this->reservedAliases, 'not' => true, 'message' => Module::t('@designer_script_alias_in_use')];
        }

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                => Yii::t('app', 'ID'),
            'metadata_id'       => Yii::t('app', 'Object'),
            'name'              => Yii::t('app', 'Internal ID'),
            'index'             => Yii::t('app', 'Index'),
            'predefined'        => Yii::t('app', 'Predefined'),
            'label'             => Yii::t('app', 'Label'),
            'type'              => Yii::t('app', 'Type'),
            'length'            => Yii::t('app', 'Length'),
            'number_precision'  => Yii::t('app', 'Precision'),
            'required'          => Yii::t('app', 'Required'),
            'ref_id'            => Yii::t('app', 'Relation'),
            'refName'           => Yii::t('app', 'Relation'),
            'mask'              => Yii::t('app', 'Mask'),
            'dropdown_variant'  => Yii::t('app', 'Number of rows in dropdown'),
            'dropdown_value'    => Yii::t('app', 'Number of rows in dropdown'),
            'filtering_mode'    => Yii::t('app', 'Filtering Mode'),
            'default_value'     => Yii::t('app', 'Default Value'),
            'min_value'         => Yii::t('app', 'Minimum Value'),
            'max_value'         => Yii::t('app', 'Maximum Value'),
            'show_hyperlink'    => Yii::t('app', 'Show Hyperlink'),
            'search_method'     => Yii::t('app', '@designer_search_method'),
            'is_non_negative'   => Module::t('@designer_is_non_negative'),
            'starts_from'       => Module::t('@designer_starts_from'),
            'is_unique'         => Module::t('@designer_is_unique'),
            'script_alias'      => Module::t('@designer_script_alias'),
            'mimes'             => Module::t('@designer_validation_mimes'),
            'max_size'          => Module::t('@designer_validation_max_size'),
        ];
    }

    public function validateScriptAliasUnique()
    {
        $query = self::find()
            ->andWhere(['script_alias' => $this->script_alias])
            ->andWhere(['metadata_id'  => $this->metadata_id]);
        if (!empty($this->id)) $query->andWhere(['!=', 'id', $this->id]);
        if ($query->exists()) $this->addError('script_alias', 'The script alias is not unique');
    }

    /**
     * @param string $metadataType
     * @return array
     */
    public static function getAttributeReservedAliases($metadataType)
    {
        switch ($metadataType) {
            case ObjectTypes::CATALOG:
                return ['id', 'name', 'version', 'presentation', 'files'];
            case ObjectTypes::DOCUMENT:
                return ['id', 'date', 'number', 'version', 'presentation', 'files'];
            case ObjectTypes::TABLE:
                return ['id', 'owner_id'];
            default:
                return [];
        }
    }

    public function getReservedAliases()
    {
        $columns = MetadataColumns::find()
            ->select('script_alias')
            ->andFilterWhere(['!=', 'designer_metadata_columns.id', $this->id])
            ->andWhere(['metadata_id' => $this->metadata->id])
            ->asArray()
            ->all();

        $reservedAliases = ArrayHelper::getColumn($columns, 'script_alias');

        $metadataType = $this->metadata ? $this->metadata->type : null;
        return ArrayHelper::merge($reservedAliases, static::getAttributeReservedAliases($metadataType));
    }

    public function canChangeScriptAlias()
    {
        return !in_array(mb_strtolower($this->name), $this->reservedAliases);
    }

    /**
     * @param string $name
     * @return mixed
     */
    protected function getRuleValue($name)
    {
        return ArrayHelper::getValue($this->_validationRules, $name);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    protected function setRuleValue($name, $value)
    {
        $this->_validationRules[$name] = $value;
    }

    /**
     * @param string $name
     * @param bool $defaultValue
     * @return bool
     */
    protected function getBoolRuleValue($name, $defaultValue = false)
    {
        $value = ArrayHelper::getValue($this->_validationRules, $name);
        return is_bool($value) ? $value : $defaultValue;
    }

    /**
     * @param string $name
     * @return float
     */
    protected function getFloatRuleValue($name)
    {
        $value = ArrayHelper::getValue($this->_validationRules, $name);
        return (float) $value;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getStringRuleValue($name)
    {
        $value = ArrayHelper::getValue($this->_validationRules, $name);
        return (string) $value;
    }

    /**
     * @return bool
     */
    public function getRequired()
    {
        return $this->getBoolRuleValue('required');
    }

    /**
     * @param bool $value
     */
    public function setRequired($value)
    {
        $this->setRuleValue('required', (bool) $value);
    }

    /**
     * @return bool
     */
    public function getIs_unique()
    {
        return $this->getBoolRuleValue('is_unique');
    }

    /**
     * @param bool $value
     */
    public function setIs_unique($value)
    {
        $this->setRuleValue('is_unique', (bool) $value);
    }

    /**
     * @return float
     */
    public function getMax_value()
    {
        return $this->getFloatRuleValue('max_value');
    }

    /**
     * @param float|int $value
     */
    public function setMax_value($value)
    {
        $this->setRuleValue('max_value', (float) $value);
    }

    /**
     * @return float
     */
    public function getMin_value()
    {
        return $this->getFloatRuleValue('min_value');
    }

    /**
     * @param float|int $value
     */
    public function setMin_value($value)
    {
        $this->setRuleValue('min_value', (float) $value);
    }

    /**
     * @return string
     */
    public function getMimes()
    {
        return $this->getStringRuleValue('mimes');
    }

    /**
     * @param string $value
     */
    public function setMimes($value)
    {
        $this->setRuleValue('mimes', (string) $value);
    }

    /**
     * @return float
     */
    public function getMax_size()
    {
        return $this->getFloatRuleValue('max_size');
    }

    /**
     * @param float|int $value
     */
    public function setMax_size($value)
    {
        $this->setRuleValue('max_size', (float) $value);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMetadata()
    {
        return $this->hasOne(Metadata::className(), ['id' => 'metadata_id']);
    }

    public function getIsEnumeration()
    {
        return Types::isReferentialType($this->type)
            && $this->ref
            && $this->ref->type === ObjectTypes::ENUMERATION;
    }

    public function getIsSystemTable()
    {
        return Types::isReferentialType($this->type)
            && $this->ref
            && $this->ref->type === ObjectTypes::SYSTEM_TABLE;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRef()
    {
        return $this->hasOne(Metadata::className(), ['id' => 'ref_id']);
    }

    public function getChoiceParamsConfig(){
        try {
            $cp = Json::decode($this->config);
            return ArrayHelper::getValue($cp, 'choice_param');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @return \yii\db\Query
     */
    public function getChoiceParamsQuery()
    {
        if ($this->config !== null and ($config = $this->getChoiceParamsConfig())) {
            if ($ref = $this->ref){
                return (new Query())->from($ref->table_name)->where($config);
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function getChoiceParamsArray()
    {
        return ($q = $this->getChoiceParamsQuery()) ? $q->all() : null;
    }

    /**
     * @return string
     */
    public function getRefName()
    {
        $ref = $this->ref;
        return $ref ? $ref->name : null;
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        try {
            $this->_validationRules = Json::decode($this->validation_rules);
        } catch (\Exception $e) {
            $this->_validationRules = [];
        } catch (\Throwable $e) {
            $this->_validationRules = [];
        }

        parent::afterFind();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($this->type === Types::Image) {
            $this->show_hyperlink = false;
        } elseif ($this->type === Types::Image) {
            $this->required = false;
        } elseif ($insert && $this->predefined) {
            $this->show_hyperlink = true;
        }

        $this->validation_rules = Json::encode($this->_validationRules);

        return parent::beforeSave($insert);
    }

    /**
     * @return string
     */
    public function getTypePresentation()
    {
        if (Types::isReferentialType($this->type)) {
            return $this->type . '.' . $this->refName;
        } else {
            switch ($this->type) {

                case Types::String:
                    return Types::String . ($this->length ? ' (' . $this->length . ')' : '');

                case Types::Number:
                    return $this->type . ' ('
                        . $this->length
                        . ($this->number_precision ? ', ' . $this->number_precision : '')
                        . ')';

                default:
                    return $this->type;
            }
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChoiceParameters()
    {
        $query = $this->hasMany(ChoiceParameter::className(), ['metadata_column_id' => 'id']);
        if (!$this->choiceParametersAvailable) {
            $query->where('0=1');
        }
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getExtendedChoiceParameters()
    {
        $query = $this->hasMany(ExtendedChoiceParameter::className(), ['metadata_column_id' => 'id'])->with('metadataColumn', 'refColumn');
        if (!$this->choiceParametersAvailable) {
            $query->where('0=1');
        }
        return $query;
    }

    /**
     * @return bool
     */
    public function getChoiceParametersAvailable()
    {
        return Types::isReferentialType($this->type)
            and $this->ref
            and ObjectTypes::hasColumns($this->ref->type);
    }

    public function saveChoiceParameters()
    {
        ChoiceParameter::deleteAll(['metadata_column_id' => $this->id]);

        if ($this->getChoiceParametersAvailable()) {

            $postParams = Yii::$app->request->post('ChoiceParameter');

            if (is_array($postParams)) {

                /** @var ChoiceParameter[] $params */
                $params = [];

                for ($i = 0; $i <= count($postParams); $i++) {
                    $params[] = new ChoiceParameter();
                }

                ChoiceParameter::loadMultiple($params, Yii::$app->request->post());

                foreach ($params as $model) {
                    $model->metadata_column_id = $this->id;
                    switch ($model->refColumn->type) {
                        case Types::String:
                        case Types::Text:
                            $model->value_string = is_string($model->value) ? $model->value: '';
                            $model->save();
                            break;
                        case Types::Boolean:
                            $model->value_boolean = $model->value ? 1 : 0;
                            $model->save();
                            break;
                        case Types::Number:
                        case Types::Hidden:
                            $model->value_number = is_numeric($model->value) ? $model->value : 0;
                            $model->save();
                            break;
                        case Types::Reference:
                            $model->value_enum_id = is_numeric($model->value) ? $model->value : 0;
                            $model->save();
                            break;
                    }
                }
            }
        }
    }

    public function saveExtendedChoiceParameters()
    {
        ExtendedChoiceParameter::deleteAll(['metadata_column_id' => $this->id]);

        if ($this->getChoiceParametersAvailable()) {

            $postParams = Yii::$app->request->post('ExtendedChoiceParameter');

            if (is_array($postParams)) {

                /** @var ExtendedChoiceParameter[] $params */
                $params = [];

                for ($i = 0; $i <= count($postParams); $i++) {
                    $params[] = new ExtendedChoiceParameter();
                }

                ExtendedChoiceParameter::loadMultiple($params, Yii::$app->request->post());

                foreach ($params as $model) {
                    $model->metadata_column_id = $this->id;
                    $model->save();
                }
            }
        }
    }

    /**
     * @return array
     */
    public static function getListOfDropdownVariants()
    {
        return [
            static::DROPDOWN_VARIANT_DEFAULT => Yii::t('app', 'Default'),
            static::DROPDOWN_VARIANT_ALL     => Yii::t('app', 'All'),
            static::DROPDOWN_VARIANT_VALUE   => Yii::t('app', 'Value'),
        ];
    }

    /**
     * @return array
     */
    public static function getListOfFilteringModes()
    {
        return [
            static::FILTERING_MODE_INPUT    => Yii::t('app', 'Input'),
            static::FILTERING_MODE_DROPDOWN => Yii::t('app', 'Dropdown'),
        ];
    }

    /**
     * @return array
     */
    public static function getListOfSearchMethods()
    {
        return [
            static::SEARCH_METHOD_ANYWHERE  => Yii::t('app', '@designer_search_method_anywhere'),
            static::SEARCH_METHOD_BEGINNING => Yii::t('app', '@designer_search_method_beginning'),
        ];
    }

    /**
     * @return bool
     */
    public function getIsTabularSectionColumn()
    {
        return $this->metadata && $this->metadata->type === ObjectTypes::TABLE;
    }

    /**
     * @return bool
     */
    public function isUniqueRequired()
    {
        return $this->metadata && ($this->metadata->type === ObjectTypes::CATALOG or $this->metadata->type === ObjectTypes::DOCUMENT);
    }

    /**
     * @return string
     */
    public function getDbType()
    {
        switch ($this->type) {
            case Types::Reference:
            case Types::File:
            case Types::Image:
                return AttributeTypecastBehavior::TYPE_INTEGER_OR_NULL;
            case Types::Date:
            case Types::Time:
            return AttributeTypecastBehavior::TYPE_STRING_OR_NULL;
            case Types::Number:
                return $this->number_precision ? AttributeTypecastBehavior::TYPE_FLOAT : AttributeTypecastBehavior::TYPE_INTEGER;
            case Types::Boolean:
                return AttributeTypecastBehavior::TYPE_BOOLEAN;
            case Types::Hidden:
                switch ($this->name) {
                    case static::VERSION_COLUMN_NAME;
                        return AttributeTypecastBehavior::TYPE_INTEGER;
                    case static::OWNER_COLUMN_NAME;
                        return AttributeTypecastBehavior::TYPE_INTEGER_OR_NULL;
                    default:
                        return AttributeTypecastBehavior::TYPE_STRING;
                }
            default:
                return AttributeTypecastBehavior::TYPE_STRING;
        }
    }

    public function typecastValue($value)
    {
        if ($this->isEnumeration) {
            return (string) $value;
        }

        switch ($this->type) {
            case Types::Reference:
            case Types::File:
            case Types::Image:
                return $value ? (int) $value : null;
            case Types::Date:
            case Types::Time:
                return $value ? (string) $value : null;
            case Types::Number:
                return $this->number_precision ? (float) $value : (int) $value;
            case Types::Boolean:
                return (bool) $value;
            case Types::Hidden:
                switch ($this->name) {
                    case static::VERSION_COLUMN_NAME;
                        return (int) $value;
                    default:
                        return (string) $value;
                }
            default:
                return (string) $value;
        }
    }

    /**
     * @return string
     */
    public function getClientType()
    {
        switch ($this->type) {
            case Types::Email:
                return Types::String;
                break;
            case Types::Reference:
                return $this->isEnumeration ? 'Enumeration' : $this->type;
                break;
            default:
                return $this->type;
        }
    }

    /**
     * @param bool $embedded
     * @return array
     */
    public function getClientConfig($embedded = false)
    {
        $config = [];

        switch ($this->type) {
            case Types::String:
                if ($this->mask) {
                    $config['mask'] = $this->mask;
                }
                break;
            case Types::Number:
                $config['length']       = (int) $this->length;
                $config['precision']    = (int) $this->number_precision;
                $config['nonNegative']  = (bool) $this->is_non_negative;
                break;
            case Types::File:
            case Types::Image:
                if ($embedded) {
                    $config['urlUpload'] = $this->getEmbeddedUploadUrl();
                } else {
                    $config['urlUpload'] = $this->getInternalUploadUrl();
                }
                $config['canDownload'] = !$embedded;
                break;
            case Types::Reference:
                if ($this->isEnumeration) {
                    $config['options'] = $this->ref->enumerationValuesWithScriptAliases;
                } elseif ($this->isSystemTable) {
                    $config['internalId']   = $this->ref->table_name;
                    $config['urlList']      = Url::to(['/designer/system/list', 'id' => $this->id]);
                } else {
                    if ($this->extendedChoiceParameters) {
                        $extChoiceParams = [];
                        foreach ($this->extendedChoiceParameters as $param) {
                            if ($param->ref_column_id && $param->depends_column_id) {
                                $paramKey = $param->dependsColumn->script_alias;
                                if ($param->dependsColumn->isTabularSectionColumn) {
                                    $paramKey = $param->dependsColumn->metadata->full_script_alias . '.' . $paramKey;
                                }
                                $extChoiceParams[$paramKey] = $param->dependsColumn->name;
                            }
                        }
                    } else {
                        $extChoiceParams = null;
                    }

                    $config['internalId']       = $this->ref->full_script_alias;
                    $config['urlList']          = Url::to(["/{$this->ref->table_name}/list", 'id' => $this->id]);
                    $config['urlCreate']        = Url::to(["/{$this->ref->table_name}/ajax-create"]);
                    $config['urlObject']        = Url::to(["/{$this->ref->table_name}/get-object?id={{id}}"]);
                    $config['extChoiceParams']  = $extChoiceParams;
                }
        }

        return $config;
    }

    /**
     * @return array
     */
    public function getClientValidation()
    {
        if ($this->metadata->type === ObjectTypes::DOCUMENT && $this->name === self::NUMBER_COLUMN_NAME) {
            $required = false;
        } else {
            $required = (bool) $this->required;
        }

        $validation = [
            'required' => $required
        ];

        switch ($this->type) {
            case Types::Email:
                $validation['email'] = true;
                break;
            case Types::Number:
                $minValue = (int) $this->number_precision ? (float) $this->min_value : (int) $this->min_value;
                if ($minValue) {
                    $validation['min_value'] = $minValue;
                }
                $maxValue = (int) $this->number_precision ? (float) $this->max_value : (int) $this->max_value;
                if ($maxValue) {
                    $validation['max_value'] = $maxValue;
                }
        }

        return $validation;
    }

    /**
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    public function getModelRules()
    {
        return $this->generateModelRules();
    }

    public function getDbViewModelRules()
    {
        return $this->generateModelRules($this->script_alias);
    }

    protected function generateModelRules($columnName = null)
    {
        if (is_null($columnName)) {
            $columnName = $this->name;
        }

        if ($this->type === Types::Autoincrement) {
            return [];
        }

        if ($this->name === MetadataColumns::PRESENTATION_COLUMN_NAME) {
            return [];
        }

        $rules = [];

        if ($this->name === MetadataColumns::VERSION_COLUMN_NAME) {
            $rules[] = "['{$columnName}', 'integer']";
            return $rules;
        }

        if ($this->required && $this->name !== MetadataColumns::NUMBER_COLUMN_NAME) {
            $rules[] = "['{$columnName}', 'required']";
        }

        if ($this->is_unique) {
            $rules[] = "['{$columnName}', 'unique']";
        }

        switch ($this->type) {
            case Types::String:
                $rules[] = "['{$columnName}', 'string', 'max' => {$this->length}]";
                break;
            case Types::Email:
                $rules[] = "['{$columnName}', 'string', 'max' => {$this->length}]";
                $rules[] = "['{$columnName}', 'email']";
                break;
            case Types::Text:
                $rules[] = "['{$columnName}', 'string']";
                break;
            case Types::Number:

                $rules[] = "['{$columnName}', 'trim']";
                $rules[] = "['{$columnName}', 'default', 'value' => 0]";

                $validator = $this->number_precision ? 'number' : 'integer';
                $rules[] = "['{$columnName}', '{$validator}']";

                $minValue = $this->number_precision ? (float) $this->min_value : (int) $this->min_value;

                if ($minValue) {
                    $rules[] = "['{$columnName}', '{$validator}', 'min' => {$minValue}]";
                }

                $maxValue = $this->number_precision ? (float) $this->max_value : (int) $this->max_value;

                if ($maxValue) {
                    $rules[] = "['{$columnName}', '{$validator}', 'max' => {$maxValue}]";
                }

                $isNonNegative = (bool) $this->is_non_negative;

                if ($isNonNegative) {
                    $rules[] = "['{$columnName}', '{$validator}', 'min' => 0]";
                }

                if (!$this->number_precision && !$isNonNegative) {
                    $rules[] = "['{$columnName}', 'filter', 'filter' => 'intval', 'skipOnEmpty' => false]";
                }

                break;
            case Types::Date:
            case Types::Time:
                $rules[] = "['{$columnName}', 'string']";
                $rules[] = "['{$columnName}', 'default', 'value' => null]";
                break;

            case Types::Boolean:
                $rules[] = "['{$columnName}', 'boolean']";
                $rules[] = "['{$columnName}', 'trim']";
                $rules[] = "['{$columnName}', 'default', 'value' => false]";
                $rules[] = "['{$columnName}', 'filter', 'filter' => 'boolval', 'skipOnEmpty' => true]";
                break;

            case Types::Reference:
                $ref = $this->ref;
                switch ($ref->type) {
                    case ObjectTypes::ENUMERATION:
                        $className = EnumerationValue::className();
                        break;
                    case ObjectTypes::SYSTEM_TABLE:
                        $className = $ref->system_class;
                        break;
                    default:
                        $className = ModelHelper::getFullClassName($ref->table_name);
                }
                $rules[] = "['{$columnName}', 'exist', 'skipOnEmpty' => true, 'targetClass' => '{$className}', 'targetAttribute' => ['{$columnName}' => 'id']]";
                $rules[] = "['{$columnName}', 'integer']";
                $rules[] = "['{$columnName}', 'filter', 'filter' => 'intval', 'skipOnEmpty' => true]";
                $rules[] = "['{$columnName}', 'default', 'value' => null]";
                break;

            case Types::File:
            case Types::Image:
                $className = File::className();
                $rules[] = "['{$columnName}', 'exist', 'skipOnEmpty' => true, 'targetClass' => '{$className}', 'targetAttribute' => ['{$columnName}' => 'id']]";
                $rules[] = "['{$columnName}', 'integer']";
                $rules[] = "['{$columnName}', 'filter', 'filter' => 'intval', 'skipOnEmpty' => true]";
                $rules[] = "['{$columnName}', 'default', 'value' => null]";
                break;
        }

        return $rules;
    }

    /**
     * @return int|null
     */
    public function getClientDefaultValue()
    {
        if ($this->isEnumeration && ($value = (int) $this->default_value)) {
            return $value;
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getDefaultSortDirection()
    {
        if ($this->default_sort === SORT_DESC) {
            return SORT_DESC;
        } elseif ($this->name === static::NUMBER_COLUMN_NAME) {
            return SORT_DESC;
        }

        return SORT_ASC;
    }

    /**
     * @return bool
     */
    public function getIsAllowedForEmbeddedForms()
    {
        return $this->isEnumeration || in_array($this->type, [
            Types::String,
            Types::Text,
            Types::Number,
            Types::Date,
            Types::Time,
            Types::Boolean,
            Types::Email,
            Types::File,
            Types::Hidden,
        ]);
    }

    /**
     * @param UploadedFile $file
     * @param string $message
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function validateUploadedFile($file, &$message = '')
    {
        $type = $this->type;

        $model = DynamicModel::validateData(compact('file', 'type'), [
            ['type', 'in', 'range' => [Types::File, Types::Image]],
            [
                'file',
                $type === Types::Image ? 'image' : 'file',
                'mimeTypes' => $this->mimes ? $this->mimes : null,
                'maxSize' => $this->max_size ? $this->max_size * 1000 : null,
            ],
        ]);

        $message = ModelHelper::getModelError($model);

        return !$model->hasErrors();
    }

    public function getInternalUploadUrl()
    {
        return $config['urlUpload'] = Url::to(["/{$this->metadata->table_name}/upload", 'id' => $this->id]);
    }

    public function getEmbeddedUploadUrl()
    {
        return Url::to(["/{$this->metadata->table_name}/embed-upload", 'id' => $this->id]);
    }
}
