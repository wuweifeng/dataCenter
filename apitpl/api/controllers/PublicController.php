<?php

// 此控制器用来提供一些公用的接口 
namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use yii\web\Response;
use api\models\Test;

class PublicController extends Controller
{

    public function beforeAction($action)
    {
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    // 获取学校类型
    public function actionSchoolType()
    {
        $array = $this->actionSchool_type();

        return $array;
    }

    // 获取省市区(格式：{id:2,name:'北京',child:{id:16,name:'南京',child:{...}}})
    public function actionProviceCityArea()
    {
        $request = Yii::$app->request;
        $user_id = $request->get('user_id','');
        $type    = $request->get('type','');
        $Test = new Test();
        $data  = $Test->user_zone($user_id,2,$type);

        $province = $data['province'];
        $city_area = $data['city_area'];
        $province_city = $data['province_city'];
        $_province = $_province_city = $_city_area = [];
        // 重新排列省
        foreach ($province[1] as $key => $value) {
            $tmp['id'] = $key;
            $tmp['name'] = $value;
            $_province[] = $tmp; 
        }
        // 重新排列市
        foreach ($province_city as $key => $value) {
            foreach ($value as $k => $val) {
                $tmp['pid'] = $key;
                $tmp['id'] = $k;
                $tmp['name'] = $val;
                $_province_city[] = $tmp;
            }
        }
        // 重新排列区
        foreach ($city_area as $key => $value) {
            foreach ($value as $k => $val) {
                $tmp['pid'] = $key;
                $tmp['id'] = $k;
                $tmp['name'] = $val;
                $_city_area[] = $tmp;
            }
        }

        // 循环嵌套算法 (格式：{id:2,name:'北京',child:{id:16,name:'南京',child:{...}}})
        $response = [];
        foreach ($_province as $key => $value) {
            $ptmp['id'] = $value['id'];
            $ptmp['name'] = $value['name'];
            $_city = [];
            foreach ($_province_city as $ke => $val) {
                if ($value['id'] == $val['pid']) {
                    $ctmp['id'] = $val['id'];
                    $ctmp['name'] = $val['name'];
                    $_area = [];
                    foreach ($_city_area as $k => $v) {
                        if ($val['id'] == $v['pid']) {
                            $atmp['id'] = $v['id'];
                            $atmp['name'] = $v['name'];
                            $_area[] = $atmp;
                        }
                    }
                    $ctmp['child'] = $_area;
                    $_city[] = $ctmp;
                }
            }
            $ptmp['child'] = $_city;
            $response[] = $ptmp;
        }
        return $response;
    }

    // 获取学校列表
    public function actionSchoolList()
    {

    }

    // 获取年级列表
    public function actionGradeList()
    {
        $schoolType = $this->actionSchool_type();

        $data = [];
        foreach ($schoolType as $key => $value) {
            $tmp['school_type'] = $value;
            $tmp['child'] = $this->actionGrade_list();
            $data[] = $tmp;
        }

        return $data;
    }

    // 学校类型
    private function actionSchool_type()
    {
        return [
            ['id' => 0, 'name' => '其它'],
            ['id' => 1, 'name' => '幼儿园'],
            ['id' => 2, 'name' => '小学'],
            ['id' => 3, 'name' => '初中'],
            ['id' => 4, 'name' => '高中'],
            ['id' => 5, 'name' => '大学'],
        ];
    }

    // 年级列表
    private function actionGrade_list()
    {
        return [
            2011,2012,2013,2014,2015,2016,2017
        ];
    }
}