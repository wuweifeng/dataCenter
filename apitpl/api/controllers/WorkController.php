<?php

namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use api\models\Work;
use yii\web\Response;

class WorkController extends Controller

{

    public function beforeAction($action)
    {
        // $cache = Yii::$app->cache; 
        // $cache->flush();
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    public function actionWorkList(){
        
    }

}