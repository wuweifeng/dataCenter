<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class QyJumpcontact extends \yii\db\ActiveRecord{

    //选择数据库
    public static function getDb()
    {
        return Yii::$app->get('db1');
    }

    public static function tableName()
    {
        return 't_qy_jumpcontact';
    }


    public static function list($where='',$stime='',$etime='')
    {
        //通过学校名称来寻找api_db数据库中的学校ID

        $res = static::find()
            ->select("id,qid,create_time")  
            ->where($where)
            ->andFilterWhere(['between','create_time',$stime, $etime])
            ->all();

        return $res;
        
    }

}

