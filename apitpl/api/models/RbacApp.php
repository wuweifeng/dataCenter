<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class RbacApp extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'rbac_app';
    }

}

