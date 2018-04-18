<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class Vote extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'vote';
    }

    //查询所有
    public static function list($where='',$stime='',$etime='')
    {
        $res = static::find()->select('qid,create_time')->where($where)->andFilterWhere(['between','create_time',$stime, $etime])->all();
        return $res;
    }


}

