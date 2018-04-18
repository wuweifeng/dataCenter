<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;
    
//作业统计表
class statisScore extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'statis_score';
    }

    public static function counts($where,$stime,$etime){
    	$res = static::find()->select('ctime,qid,scounts')->where($where)->andFilterWhere(['between','ctime',$stime,$etime])->all();
    	return $res;
    }
}

