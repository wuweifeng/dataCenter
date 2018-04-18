<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class QyJumpurl extends \yii\db\ActiveRecord{

    //选择数据库
    public static function getDb()
    {
        return Yii::$app->get('db1');
    }

    public static function tableName()
    {
        return 't_qy_jumpurl';
    }


    public static function list()
    {
        
        $jump_res = static::find()->select("id,title")->where(['status'=>1])->andFilterWhere(['>','pid',0])->all();

        return $jump_res;
        
    }

}

