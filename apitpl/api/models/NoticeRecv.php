<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class NoticeRecv extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'notice_recv';
    }

     /**
     * 根据条件统计收消息数
     * @param string $map
     * by sherlock
     */
    public static function notice_count($where='',$stime='',$etime='',$qid_arr)
    {

        $qid_string = implode(',',$qid_arr);
        $sql = "select count(id) as count,FROM_UNIXTIME(create_time, '%Y-%m-%d') as date,qid FROM notice_recv where create_time between ".$stime." AND ".$etime." AND qid IN (".$qid_string.") group by date,qid";
        $connection  = Yii::$app->db;
        $res = $connection->createCommand($sql)->queryAll();
        return $res;
    }

    //查询每一条
    public static function notice_count_date($where='',$stime='',$etime='')
    {

        $query = new Query();
        $res = $query
            ->select("id,qid,create_time")  
            ->from(static::tableName())  
            ->where($where)
            ->andFilterWhere(['between','create_time',$stime, $etime])
            ->all();
            
        return $res;
    }
    

}

