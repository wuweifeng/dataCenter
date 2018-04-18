<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;
use api\models\ProvinceCityArea;

class Account extends \yii\db\ActiveRecord{

    public static function tableName(){
        return 'account';
    }

    public function getprovince_city_area(){
         return $this->hasOne(provinceCityArea::className(), ['id' => 'area_id']);
    }

    //获取学校列表
    public static function list($where){

        $result = ProvinceCityArea::find()->select('name,id')->all();

        $account = static::find()
                ->select('a.id,a.province_id,a.city_id,a.area_id,a.title,a.school_type')
                ->from(static::tableName().' as a')
                ->joinWith(ProvinceCityArea::tableName().' as p' , false, 'LEFT JOIN')
                ->where($where)
                ->distinct('a.id')
                ->asArray()
                ->all();

        $schoolType = [ 0=>'其它',1=>'幼儿园',2=>'小学',3=>'初中',4=>'高中',5=>'大学' ];  

        $province_city_area= [];
        foreach ($result as $k => $v) {
            $province_city_area[$v['id']] = $v['name']; 
        }

        $new_account = [];
        foreach ($account as $k => $v) {
            $v['type_info'] = $schoolType[$v['school_type']];
            $v['area_name'] = $province_city_area[$v['province_id']].'-'.$province_city_area[$v['city_id']].'-'.$province_city_area[$v['area_id']];
            $new_account[$v['area_id']][]   = $v;

        }

        return $new_account;

    }

}

