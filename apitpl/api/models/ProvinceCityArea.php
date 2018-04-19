<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class ProvinceCityArea extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'province_city_area';
    }

    //获取所有区
    public static function areaList($province_id='',$city_id='',$area_id=''){

        if((empty($province_id) && empty($city_id) && $area_id) || ($province_id && $city_id && $area_id) ||(empty($province_id) && $city_id && $area_id)){
            //区
            $where['id'] = $area_id;
            $areas = static::find()->select('id')->from(static::tableName())->where($where)->asArray()->all();
        }elseif((empty($province_id) && $city_id && empty($area_id)) || ($province_id && $city_id && empty($area_id))){
            //城市
            $where['pid'] = $city_id;
            $areas = static::find()->select('id')->from(static::tableName())->where($where)->asArray()->all();
        }elseif($province_id && empty($city_id) && empty($area_id)){
            //省份
            $where['pid'] = $province_id;
            $citys = static::find()->select('id')->from(static::tableName())->where($where)->asArray()->all();
            $city_arr = [];
            foreach ($citys as $key => $value) {
                $city_arr[] = $value['id'];
            }
            $map['pid'] = $city_arr; 
            $areas =  static::find()->select('id')->from(static::tableName())->where($map)->asArray()->all();
        }else{
            $where['type'] = 3;
            $areas = static::find()->select('id')->from(static::tableName())->where($where)->asArray()->all();
        }
            
        return $areas;
    }


    //通过区获取省和市
    public static function area_name($area_id){

        $sql = "select a.name as area, c.name as city, p.name as province from ".static::tableName().' as a,'.static::tableName().' as c,'.static::tableName().' as p where c.`id`=a.`pid` AND p.`id`=c.`pid` AND a.id='.$area_id;

        $res = Yii::$app->db->createCommand($sql)->queryOne();
        $area_name = $res['province'].'-'.$res['city'].'-'.$res['area'];
        return $area_name;
    }
}

