<?php

namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use yii\web\Response;
use api\models\RbacApp;
class RbacappController extends Controller
    
{

    public function beforeAction($action)
    {
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    //  3:    家校圈
    //  4:    即时通讯(屏蔽)
    //  6:    通知
    //  7:    作业
    //  11:   请假
    //  12:   投票
    //  13:   调研
    //  14:   定向消息
    public function actionList(){
      
        $where['status'] = 1;
        $where['id']     = [3,6,7,8,10,12,13,14];
        $apps = RbacApp::find()->select('id,title')->where($where)->all();

        return $apps;
    }

}