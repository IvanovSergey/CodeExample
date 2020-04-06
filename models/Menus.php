<?php

namespace app\modules\designer\models;

use Yii;

/**
 * This is the model class for table "designer_menu".
 *
 * @property integer $id
 * @property integer $metadata_id
 * @property integer $parent_id
 * @property string $url
 * @property string $text
 * @property string $icon
 * @property integer $new_tab
 *
 * @property Metadata $metadata
 * @property Menu $parent
 * @property Menu[] $children
 */
class Menus extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'designer_menus';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'        => Yii::t('app', 'ID'),
            'name'      => Yii::t('app', 'Name'),
            'icon'      => Yii::t('app', 'Icon'),
        ];
    }
}
