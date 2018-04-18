<?php

// 此控制器用来提供一些公用的接口 
namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use yii\web\Response;

class PublicController extends Controller
    
{

    public function beforeAction($action)
    {
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    public function actionSchoolType()
    {
        $array = [
            ['id' => 0, 'name' => '其它'],
            ['id' => 1, 'name' => '幼儿园'],
            ['id' => 2, 'name' => '小学'],
            ['id' => 3, 'name' => '初中'],
            ['id' => 4, 'name' => '高中'],
            ['id' => 5, 'name' => '大学'],
        ];

        return $array;
    }
}