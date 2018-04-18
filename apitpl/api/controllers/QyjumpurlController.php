<?php

namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use api\models\QyJumpurl;
use yii\web\Response;

class QyjumpurlController extends Controller

{

    public function beforeAction($action)
    {
        // $cache = Yii::$app->cache; 
        // $cache->flush();
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    public function actionList(){
      	
      	$QyJumpurl = new QyJumpurl();

      	$res = $QyJumpurl->list();

      	return $res;
    
    }

}